import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { pathToFileURL } from 'node:url';
import { parse } from '@babel/parser';

/** Roadmap step 10: architecture rules for the typed admin app (assets/src/app) and its
 * published bundles (assets/dist/admin). See docs/ENFORCEMENT.md.
 *
 *   node tools/check-architecture.mjs                  run every check
 *   node tools/check-architecture.mjs --update-budgets rewrite tools/asset-budgets.json
 *                                                      at current size + headroom
 */

export const allowlistPath = 'tools/architecture-allowlist.json';
export const budgetsPath = 'tools/asset-budgets.json';

const SOURCE_EXTENSIONS = [ '.ts', '.tsx', '.js', '.jsx', '.mjs', '.cjs' ];
const RESOLVE_EXTENSIONS = [ ...SOURCE_EXTENSIONS, '.json' ];

// Recovered runtime code, by location (relative to assets/src).
const FORBIDDEN_DIRECTORIES = [
	'application/',
	'modules/',
	'runtimes/',
	'static/',
];
const RECOVERED_SDK = [
	'extensions/index.jsx',
	'extensions/lazyFeatures.js',
	'extensions/LazyFeature.jsx',
];
// Never forbidden by their own content; their imports are still followed.
const ALLOWED = [ 'ui/' ];

const toPosix = ( file ) => file.split( path.sep ).join( '/' );
const isSource = ( file ) => SOURCE_EXTENSIONS.includes( path.extname( file ) );
const isDeclaration = ( file ) => /\.d\.[cm]?ts$/.test( file );
const isTest = ( file ) =>
	/\.(?:test|spec)\.[cm]?[jt]sx?$/.test( file ) ||
	/(?:^|\/)(?:test|__tests__|__mocks__)\//.test( file );

/** Text with CRLF folded to LF, so results match on Windows and Linux checkouts. */
function readText( file ) {
	return fs.readFileSync( file, 'utf8' ).replace( /\r\n/g, '\n' );
}

function readBytes( file ) {
	return Buffer.from(
		fs.readFileSync( file ).toString( 'latin1' ).replace( /\r\n/g, '\n' ),
		'latin1'
	);
}

function walk( directory, skip = () => false ) {
	if ( ! fs.existsSync( directory ) ) return [];
	const files = [];
	for ( const entry of fs.readdirSync( directory, {
		withFileTypes: true,
	} ) ) {
		const full = path.join( directory, entry.name );
		if ( entry.name === 'node_modules' || skip( full ) ) continue;
		if ( entry.isDirectory() ) files.push( ...walk( full, skip ) );
		else files.push( full );
	}
	return files.sort();
}

function parserPlugins( file ) {
	const extension = path.extname( file );
	if ( extension === '.ts' || extension === '.mts' || extension === '.cts' )
		return [ 'typescript' ];
	if ( extension === '.tsx' ) return [ 'typescript', 'jsx' ];
	return [ 'jsx' ];
}

const SKIPPED_KEYS = new Set( [
	'loc',
	'start',
	'end',
	'extra',
	'comments',
	'leadingComments',
	'trailingComments',
	'innerComments',
] );

const literalValue = ( node ) => {
	if ( node?.type === 'StringLiteral' ) return node.value;
	if ( node?.type === 'TemplateLiteral' && ! node.expressions.length )
		return node.quasis[ 0 ].value.cooked;
	return null;
};

/** Imports and recovered-runtime markers of one module, from its AST (regex fallback). */
export function analyzeSource( file, text ) {
	const imports = [];
	const markers = new Set();
	let ast;
	try {
		ast = parse( text, {
			sourceType: 'unambiguous',
			plugins: parserPlugins( file ),
			errorRecovery: true,
			allowReturnOutsideFunction: true,
		} );
	} catch {
		return analyzeWithRegex( text );
	}
	let numericCalls = false;
	let webpackHelpers = false;
	const stack = [ ast.program ];
	while ( stack.length ) {
		const node = stack.pop();
		if ( ! node || typeof node !== 'object' ) continue;
		if ( Array.isArray( node ) ) {
			stack.push( ...node );
			continue;
		}
		switch ( node.type ) {
			case 'ImportDeclaration':
				imports.push( {
					specifier: node.source.value,
					kind: 'static',
					typeOnly:
						node.importKind === 'type' ||
						( node.specifiers.length > 0 &&
							node.specifiers.every(
								( specifier ) => specifier.importKind === 'type'
							) ),
				} );
				break;
			case 'ExportNamedDeclaration':
				if ( node.source )
					imports.push( {
						specifier: node.source.value,
						kind: 'static',
						typeOnly:
							node.exportKind === 'type' ||
							( node.specifiers.length > 0 &&
								node.specifiers.every(
									( specifier ) =>
										specifier.exportKind === 'type'
								) ),
					} );
				break;
			case 'ExportAllDeclaration':
				imports.push( {
					specifier: node.source.value,
					kind: 'static',
					typeOnly: node.exportKind === 'type',
				} );
				break;
			case 'ImportExpression': {
				const specifier = literalValue( node.source );
				if ( specifier !== null )
					imports.push( {
						specifier,
						kind: 'dynamic',
						typeOnly: false,
					} );
				break;
			}
			case 'CallExpression': {
				const specifier = literalValue( node.arguments[ 0 ] );
				if ( node.callee.type === 'Import' && specifier !== null )
					imports.push( {
						specifier,
						kind: 'dynamic',
						typeOnly: false,
					} );
				if (
					node.callee.type === 'Identifier' &&
					node.callee.name === 'require' &&
					specifier !== null
				)
					imports.push( {
						specifier,
						kind: 'require',
						typeOnly: false,
					} );
				if (
					node.callee.type === 'Identifier' &&
					node.callee.name === 'createComponent' &&
					node.arguments.some(
						( argument ) =>
							argument.type === 'Identifier' &&
							argument.name === 'readRuntime'
					)
				)
					markers.add( 'calls createComponent(readRuntime)' );
				const numeric =
					node.arguments.length === 1 &&
					node.arguments[ 0 ].type === 'NumericLiteral';
				if (
					numeric &&
					node.callee.type === 'Identifier' &&
					node.callee.name === '__webpack_require__'
				)
					markers.add( 'requires numeric webpack module ids' );
				if (
					numeric &&
					node.callee.type === 'Identifier' &&
					node.callee.name === 'n' &&
					node.arguments[ 0 ].value >= 10
				)
					numericCalls = true;
				if (
					node.callee.type === 'MemberExpression' &&
					node.callee.object.type === 'Identifier' &&
					node.callee.object.name === 'n' &&
					[ 'd', 'r', 'n' ].includes( node.callee.property.name )
				)
					webpackHelpers = true;
				break;
			}
			case 'Identifier':
				if ( node.name === 'readRuntime' )
					markers.add( 'reads readRuntime' );
				if ( node.name === 'webpackChunkohmylms' )
					markers.add( 'references window.webpackChunkohmylms' );
				break;
			case 'StringLiteral':
				if ( node.value.includes( 'webpackChunkohmylms' ) )
					markers.add( 'references window.webpackChunkohmylms' );
				break;
			case 'MemberExpression':
				if (
					node.object.type === 'Identifier' &&
					node.object.name === '__webpack_modules__' &&
					node.computed &&
					node.property.type === 'NumericLiteral'
				)
					markers.add( 'requires numeric webpack module ids' );
				break;
		}
		for ( const key of Object.keys( node ) ) {
			if (
				! SKIPPED_KEYS.has( key ) &&
				node[ key ] &&
				typeof node[ key ] === 'object'
			)
				stack.push( node[ key ] );
		}
	}
	// Recovered factories call n(<id>) next to the n.d/n.r/n.n webpack helpers.
	if ( numericCalls && webpackHelpers )
		markers.add( 'requires numeric webpack module ids' );
	return { imports, markers: [ ...markers ] };
}

function analyzeWithRegex( text ) {
	const code = text
		.replace( /\/\*[\s\S]*?\*\//g, '' )
		.replace( /(^|[^:\\])\/\/.*$/gm, '$1' );
	const imports = [];
	const patterns = [
		[
			/\bimport\s+(type\s+)?[^'"`;]*?\bfrom\s*['"]([^'"]+)['"]/g,
			'static',
		],
		[
			/\bexport\s+(type\s+)?[^'"`;]*?\bfrom\s*['"]([^'"]+)['"]/g,
			'static',
		],
		[ /\bimport\s*()['"]([^'"]+)['"]/g, 'static' ],
		[ /\bimport\s*\(\s*()['"`]([^'"`]+)['"`]\s*\)/g, 'dynamic' ],
		[ /\brequire\s*\(\s*()['"]([^'"]+)['"]\s*\)/g, 'require' ],
	];
	for ( const [ pattern, kind ] of patterns ) {
		for ( const match of code.matchAll( pattern ) )
			imports.push( {
				specifier: match[ 2 ],
				kind,
				typeOnly: Boolean( match[ 1 ] ),
			} );
	}
	const markers = [];
	if ( /\bcreateComponent\s*\(\s*readRuntime\b/.test( code ) )
		markers.push( 'calls createComponent(readRuntime)' );
	if ( /\breadRuntime\b/.test( code ) ) markers.push( 'reads readRuntime' );
	if ( /webpackChunkohmylms/.test( code ) )
		markers.push( 'references window.webpackChunkohmylms' );
	if (
		/__webpack_require__\(\s*\d+\s*\)/.test( code ) ||
		( /\bn\(\s*\d{2,}\s*\)/.test( code ) && /\bn\.[drn]\(/.test( code ) )
	)
		markers.push( 'requires numeric webpack module ids' );
	return { imports, markers };
}

/** Resolves a relative specifier like the bundler does; packages resolve to null. */
export function resolveImport( fromFile, specifier ) {
	if ( ! specifier.startsWith( '.' ) && ! specifier.startsWith( '/' ) )
		return null;
	const base = path.resolve(
		path.dirname( fromFile ),
		specifier.split( '?' )[ 0 ]
	);
	const candidates = [ base ];
	if ( /\.[cm]?js$/.test( base ) ) {
		const stem = base.replace( /\.[cm]?js$/, '' );
		candidates.push( stem + '.ts', stem + '.tsx' );
	}
	candidates.push(
		...RESOLVE_EXTENSIONS.map( ( extension ) => base + extension ),
		...RESOLVE_EXTENSIONS.map( ( extension ) =>
			path.join( base, 'index' + extension )
		)
	);
	return (
		candidates.find(
			( candidate ) =>
				fs.existsSync( candidate ) && fs.statSync( candidate ).isFile()
		) || null
	);
}

/** Import graph rooted at the app, followed through assets/src. */
export class Project {
	constructor( root ) {
		this.root = root;
		this.src = path.join( root, 'assets/src' );
		this.app = path.join( root, 'assets/src/app' );
		this.cache = new Map();
		this.componentFiles = this.collectComponentFiles();
	}

	relative( file ) {
		return toPosix( path.relative( this.root, file ) );
	}

	inSource( file ) {
		return ! path.relative( this.src, file ).startsWith( '..' );
	}

	inApp( file ) {
		return ! path.relative( this.app, file ).startsWith( '..' );
	}

	srcRelative( file ) {
		return toPosix( path.relative( this.src, file ) );
	}

	collectComponentFiles() {
		const files = new Map();
		const contracts = walk( this.src, ( full ) =>
			FORBIDDEN_DIRECTORIES.some(
				( directory ) => this.srcRelative( full ) + '/' === directory
			)
		).filter( ( file ) => path.basename( file ) === 'components.json' );
		for ( const contract of contracts ) {
			let entries;
			try {
				entries = JSON.parse( readText( contract ) );
			} catch {
				continue;
			}
			if ( ! Array.isArray( entries ) ) continue;
			for ( const entry of entries ) {
				if ( typeof entry?.file !== 'string' ) continue;
				files.set(
					path.resolve( path.dirname( contract ), entry.file ),
					this.relative( contract )
				);
			}
		}
		return files;
	}

	appFiles() {
		return walk( this.app ).filter(
			( file ) =>
				isSource( file ) &&
				! isDeclaration( file ) &&
				! isTest( this.relative( file ) )
		);
	}

	/** Why a file is recovered runtime code, or null. */
	forbiddenReason( file ) {
		if ( ! this.inSource( file ) ) return null;
		const relative = this.srcRelative( file );
		if (
			ALLOWED.some(
				( entry ) =>
					relative === entry ||
					( entry.endsWith( '/' ) && relative.startsWith( entry ) )
			)
		)
			return null;
		const directory = FORBIDDEN_DIRECTORIES.find( ( prefix ) =>
			relative.startsWith( prefix )
		);
		if ( directory ) return `recovered runtime (assets/src/${ directory })`;
		if ( RECOVERED_SDK.includes( relative ) )
			return 'recovered extension SDK';
		if ( this.componentFiles.has( file ) )
			return `listed in ${ this.componentFiles.get( file ) }`;
		const markers = this.module( file ).markers;
		return markers.length ? markers.join( ', ' ) : null;
	}

	module( file ) {
		if ( ! this.cache.has( file ) ) {
			const analysis =
				isSource( file ) && ! isDeclaration( file )
					? analyzeSource( file, readText( file ) )
					: { imports: [], markers: [] };
			this.cache.set( file, {
				...analysis,
				edges: analysis.imports
					.map( ( entry ) => ( {
						...entry,
						target: resolveImport( file, entry.specifier ),
					} ) )
					.filter( ( entry ) => entry.target ),
			} );
		}
		return this.cache.get( file );
	}

	runtimeEdges( file ) {
		return this.module( file ).edges.filter( ( edge ) => ! edge.typeOnly );
	}
}

function loadJson( file, fallback ) {
	return fs.existsSync( file ) ? JSON.parse( readText( file ) ) : fallback;
}

function requireReasons( list, section ) {
	return ( list || [] )
		.filter(
			( entry ) =>
				typeof entry.reason !== 'string' || ! entry.reason.trim()
		)
		.map(
			( entry ) =>
				`${ allowlistPath } ${ section }: entry ${ JSON.stringify( entry ) } has no reason`
		);
}

/** a. App code must not reach recovered runtime code, directly or through assets/src. */
export function checkRecoveredRuntime( project, allowlist = [] ) {
	const forbidden = new Map();
	const edges = new Map();
	const queue = project.appFiles();
	const seen = new Set( queue );
	while ( queue.length ) {
		const file = queue.shift();
		const reason = project.forbiddenReason( file );
		if ( reason ) {
			forbidden.set( file, reason );
			continue;
		}
		const targets = project
			.runtimeEdges( file )
			.map( ( edge ) => edge.target )
			.filter( ( target ) => project.inSource( target ) );
		edges.set( file, targets );
		for ( const target of targets ) {
			if ( ! seen.has( target ) ) {
				seen.add( target );
				queue.push( target );
			}
		}
	}
	// Shortest chain from a file to the nearest recovered module.
	const chainFrom = ( start ) => {
		const parents = new Map( [ [ start, null ] ] );
		const pending = [ start ];
		while ( pending.length ) {
			const file = pending.shift();
			if ( forbidden.has( file ) ) {
				const chain = [];
				for ( let node = file; node; node = parents.get( node ) )
					chain.unshift( node );
				return chain;
			}
			for ( const target of edges.get( file ) || [] ) {
				if ( ! parents.has( target ) ) {
					parents.set( target, file );
					pending.push( target );
				}
			}
		}
		return null;
	};
	const found = [];
	for ( const file of project.appFiles() ) {
		if ( forbidden.has( file ) ) {
			found.push( { from: file, to: file, chain: [ file ] } );
			continue;
		}
		for ( const target of edges.get( file ) || [] ) {
			if ( project.inApp( target ) && ! forbidden.has( target ) )
				continue;
			const chain = chainFrom( target );
			if (
				chain &&
				! found.some(
					( entry ) => entry.from === file && entry.to === target
				)
			)
				found.push( { from: file, to: target, chain } );
		}
	}
	const violations = found.map( ( entry ) => ( {
		from: project.relative( entry.from ),
		to: project.relative( entry.to ),
		chain: entry.chain.map( ( file ) => project.relative( file ) ),
		reason: forbidden.get( entry.chain.at( -1 ) ),
	} ) );
	return partition(
		violations,
		allowlist,
		( violation, entry ) =>
			violation.from === entry.from && violation.to === entry.to,
		( violation ) =>
			`${ violation.from } imports ${ violation.to }, which reaches recovered runtime code: ${ violation.chain.join( ' -> ' ) } (${ violation.reason })`,
		( entry ) => `${ entry.from } -> ${ entry.to }`
	);
}

/** b. Import cycles inside the app (runtime imports, static and dynamic). */
export function checkCycles( project, allowlist = [] ) {
	const files = new Set( project.appFiles() );
	const graph = new Map(
		[ ...files ].map( ( file ) => [
			file,
			[
				...new Set(
					project
						.runtimeEdges( file )
						.map( ( edge ) => edge.target )
						.filter( ( target ) => files.has( target ) )
				),
			],
		] )
	);
	const components = stronglyConnected( graph ).filter(
		( component ) =>
			component.length > 1 ||
			graph.get( component[ 0 ] ).includes( component[ 0 ] )
	);
	const violations = components.map( ( component ) => {
		const members = component
			.map( ( file ) => project.relative( file ) )
			.sort();
		return {
			files: members,
			cycle: exampleCycle( graph, component ).map( ( file ) =>
				project.relative( file )
			),
		};
	} );
	return partition(
		violations,
		allowlist,
		( violation, entry ) =>
			JSON.stringify( violation.files ) ===
			JSON.stringify( [ ...( entry.files || [] ) ].sort() ),
		( violation ) => `import cycle: ${ violation.cycle.join( ' -> ' ) }`,
		( entry ) => ( entry.files || [] ).join( ', ' )
	);
}

function stronglyConnected( graph ) {
	let index = 0;
	const indices = new Map();
	const lowlinks = new Map();
	const onStack = new Set();
	const stack = [];
	const result = [];
	const connect = ( node ) => {
		indices.set( node, index );
		lowlinks.set( node, index );
		index++;
		stack.push( node );
		onStack.add( node );
		for ( const next of graph.get( node ) ) {
			if ( ! indices.has( next ) ) {
				connect( next );
				lowlinks.set(
					node,
					Math.min( lowlinks.get( node ), lowlinks.get( next ) )
				);
			} else if ( onStack.has( next ) ) {
				lowlinks.set(
					node,
					Math.min( lowlinks.get( node ), indices.get( next ) )
				);
			}
		}
		if ( lowlinks.get( node ) === indices.get( node ) ) {
			const component = [];
			let member;
			do {
				member = stack.pop();
				onStack.delete( member );
				component.push( member );
			} while ( member !== node );
			result.push( component.sort() );
		}
	};
	for ( const node of [ ...graph.keys() ].sort() ) {
		if ( ! indices.has( node ) ) connect( node );
	}
	return result;
}

function exampleCycle( graph, component ) {
	const members = new Set( component );
	const start = component[ 0 ];
	const parents = new Map();
	const pending = [ start ];
	while ( pending.length ) {
		const node = pending.shift();
		for ( const next of graph.get( node ) ) {
			if ( ! members.has( next ) ) continue;
			if ( next === start ) {
				const cycle = [ start ];
				for (
					let current = node;
					current !== start;
					current = parents.get( current )
				)
					cycle.splice( 1, 0, current );
				return [ ...cycle, start ];
			}
			if ( ! parents.has( next ) ) {
				parents.set( next, node );
				pending.push( next );
			}
		}
	}
	return [ start, start ];
}

/** c. features/<a> may use features/<b> only through b's index or public.ts. */
export function checkFeatureBoundaries( project, allowlist = [] ) {
	const featuresRoot = path.join( project.app, 'features' );
	const featureOf = ( file ) => {
		const relative = toPosix( path.relative( featuresRoot, file ) );
		const parts = relative.split( '/' );
		return relative.startsWith( '..' ) || parts.length < 2
			? null
			: parts[ 0 ];
	};
	const isPublic = ( file ) => {
		const relative = toPosix( path.relative( featuresRoot, file ) ).split(
			'/'
		);
		const name = path.basename( file );
		return (
			( relative.length === 2 && /^index\.[cm]?[jt]sx?$/.test( name ) ) ||
			/^public\.tsx?$/.test( name )
		);
	};
	const violations = [];
	for ( const file of project.appFiles() ) {
		const source = featureOf( file );
		if ( ! source ) continue;
		for ( const edge of project.module( file ).edges ) {
			const target = featureOf( edge.target );
			if ( ! target || target === source || isPublic( edge.target ) )
				continue;
			const violation = {
				from: project.relative( file ),
				to: project.relative( edge.target ),
			};
			if (
				! violations.some(
					( entry ) =>
						entry.from === violation.from &&
						entry.to === violation.to
				)
			)
				violations.push( violation );
		}
	}
	return partition(
		violations,
		allowlist,
		( violation, entry ) =>
			violation.from === entry.from && violation.to === entry.to,
		( violation ) =>
			`${ violation.from } imports ${ violation.to }; use that feature's index.ts or a public.ts`,
		( entry ) => `${ entry.from } -> ${ entry.to }`
	);
}

function partition( violations, allowlist, matches, describe, describeEntry ) {
	const allowed = [];
	const failures = [];
	for ( const violation of violations ) {
		const entry = allowlist.find( ( candidate ) =>
			matches( violation, candidate )
		);
		if ( entry )
			allowed.push( {
				...violation,
				reason: entry.reason,
				message: describe( violation ),
			} );
		else failures.push( describe( violation ) );
	}
	const stale = allowlist
		.filter(
			( entry ) =>
				! violations.some( ( violation ) =>
					matches( violation, entry )
				)
		)
		.map(
			( entry ) =>
				`stale allow-list entry (no longer violated, remove it): ${ describeEntry( entry ) }`
		);
	return { failures: [ ...failures, ...stale ], allowed, violations };
}

/** Packages that @wordpress/dependency-extraction-webpack-plugin bundles instead of externalizing. */
export function bundledWordPressPackages( root ) {
	const util = path.join(
		root,
		'node_modules/@wordpress/dependency-extraction-webpack-plugin/lib/util.js'
	);
	if ( ! fs.existsSync( util ) ) return [ '@wordpress/icons' ];
	const list = readText( util ).match(
		/BUNDLED_PACKAGES\s*=\s*\[([\s\S]*?)\]/
	);
	return list
		? [ ...list[ 1 ].matchAll( /['"]([^'"]+)['"]/g ) ].map(
				( match ) => match[ 1 ]
			)
		: [ '@wordpress/icons' ];
}

const RUNTIME_SIGNATURES = [
	[
		/react(?:-dom)?\.production(?:\.min)?\b/,
		'React production build marker',
	],
	[ /scheduler\.production/, 'scheduler production build marker' ],
	[
		/__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED["']?\s*(?::|=(?!=))/,
		'defines React __SECRET_INTERNALS',
	],
	[
		/__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE["']?\s*(?::|=(?!=))/,
		'defines React 19 client internals',
	],
	[ /[{,]\s*["']?ReactCurrentOwner["']?\s*:/, 'defines ReactCurrentOwner' ],
];
const LICENSE_SIGNATURES =
	/@license React|react(?:-dom)?\.production|scheduler\.production/;
const REQUIRED_EXTERNALS = [ 'react', 'react-dom', 'wp-element' ];

function adminBundles( root, extensions ) {
	const dist = path.join( root, 'assets/dist/admin' );
	const pattern = new RegExp(
		`^admin-app(?:-[\\w-]+)?(?:\\.[0-9a-f]{8,})?\\.(?:${ extensions.join( '|' ) })$`
	);
	const chunkPattern = new RegExp(
		`^admin-app-[\\w-]+(?:\\.[0-9a-f]{8,})?\\.(?:${ extensions.join( '|' ) })$`
	);
	const list = ( directory, test ) =>
		fs.existsSync( directory )
			? fs
					.readdirSync( directory )
					.filter( ( name ) => test.test( name ) )
					.sort()
					.map( ( name ) => path.join( directory, name ) )
			: [];
	return [
		...list( dist, pattern ),
		...list( path.join( dist, 'chunks' ), chunkPattern ),
	];
}

/** d. Published admin bundles must use WordPress' React and @wordpress/* scripts. */
export function checkDuplicateRuntimes( root ) {
	const failures = [];
	const bundled = bundledWordPressPackages( root );
	const provided = ( name ) =>
		[ 'react', 'react-dom', 'scheduler' ].includes( name ) ||
		( name.startsWith( '@wordpress/' ) && ! bundled.includes( name ) );
	const bundles = adminBundles( root, [ 'js' ] );
	if ( ! bundles.length )
		failures.push(
			'no published admin-app bundles under assets/dist/admin'
		);
	for ( const bundle of bundles ) {
		const relative = toPosix( path.relative( root, bundle ) );
		const text = readText( bundle );
		const found = new Set();
		for ( const [ pattern, label ] of RUNTIME_SIGNATURES ) {
			if ( pattern.test( text ) ) found.add( label );
		}
		for ( const match of text.matchAll(
			/node_modules\/((?:@wordpress\/)?[a-z0-9-]+)\//g
		) ) {
			if ( provided( match[ 1 ] ) )
				found.add( `bundles module id of ${ match[ 1 ] }` );
		}
		const license = `${ bundle }.LICENSE.txt`;
		if (
			fs.existsSync( license ) &&
			LICENSE_SIGNATURES.test( readText( license ) )
		)
			found.add( `${ path.basename( license ) } lists React` );
		const map = `${ bundle }.map`;
		if ( fs.existsSync( map ) ) {
			let sources = [];
			try {
				sources = JSON.parse( readText( map ) ).sources || [];
			} catch {
				failures.push( `${ relative }.map is not valid JSON` );
			}
			for ( const source of sources ) {
				const match = source.match(
					/node_modules\/((?:@[^/]+\/)?[^/]+)\//
				);
				if ( match && provided( match[ 1 ] ) )
					found.add( `bundles ${ match[ 1 ] } (source map)` );
			}
		}
		for ( const label of [ ...found ].sort() )
			failures.push(
				`${ relative } carries its own runtime copy: ${ label }`
			);
	}
	const assetFile = path.join(
		root,
		'assets/dist/admin/admin-app.asset.php'
	);
	if ( ! fs.existsSync( assetFile ) ) {
		failures.push( 'assets/dist/admin/admin-app.asset.php is missing' );
	} else {
		const list = readText( assetFile ).match(
			/['"]dependencies['"]\s*=>\s*(?:array\s*\(|\[)([^)\]]*)/
		);
		const dependencies = list
			? [ ...list[ 1 ].matchAll( /['"]([^'"]+)['"]/g ) ].map(
					( match ) => match[ 1 ]
				)
			: [];
		for ( const handle of REQUIRED_EXTERNALS ) {
			if ( ! dependencies.includes( handle ) )
				failures.push(
					`admin-app.asset.php does not depend on the '${ handle }' external`
				);
		}
	}
	return { failures, checked: bundles.length };
}

const budgetKey = ( root, file ) =>
	toPosix(
		path.relative( path.join( root, 'assets/dist/admin' ), file )
	).replace( /\.[0-9a-f]{8,}(?=\.(?:js|css)$)/, '' );

/** Raw and gzip size of every admin-app entry and per-screen chunk. */
export function measureAssets( root ) {
	const sizes = new Map();
	const duplicates = [];
	for ( const file of adminBundles( root, [ 'js', 'css' ] ) ) {
		const key = budgetKey( root, file );
		const bytes = readBytes( file );
		if ( sizes.has( key ) ) duplicates.push( key );
		sizes.set( key, {
			file: toPosix( path.relative( root, file ) ),
			raw: bytes.length,
			gzip: zlib.gzipSync( bytes, { level: 9 } ).length,
		} );
	}
	return { sizes, duplicates };
}

const withHeadroom = ( size, headroom ) =>
	Math.ceil( ( size * ( 1 + headroom ) ) / 100 ) * 100;

export function budgetsFor( sizes, headroom = 0.1 ) {
	return Object.fromEntries(
		[ ...sizes.entries() ]
			.sort( ( [ a ], [ b ] ) => a.localeCompare( b ) )
			.map( ( [ key, size ] ) => [
				key,
				{
					raw: withHeadroom( size.raw, headroom ),
					gzip: withHeadroom( size.gzip, headroom ),
				},
			] )
	);
}

/** e. Per-screen size budgets. */
export function checkBudgets( root, budgets ) {
	const { sizes, duplicates } = measureAssets( root );
	const assets = budgets?.assets || {};
	const failures = duplicates.map(
		( key ) =>
			`${ key } is published more than once (stale hashed chunk); remove the old file`
	);
	const rows = [];
	for ( const [ key, size ] of [ ...sizes.entries() ].sort(
		( [ a ], [ b ] ) => a.localeCompare( b )
	) ) {
		const budget = assets[ key ];
		let status = 'ok';
		if ( ! budget ) {
			status = 'NO BUDGET';
			failures.push(
				`${ key } has no budget in ${ budgetsPath }; add one deliberately`
			);
		} else {
			const over = [ 'raw', 'gzip' ].filter(
				( kind ) => size[ kind ] > budget[ kind ]
			);
			if ( over.length ) {
				status = 'OVER';
				failures.push(
					`${ key } exceeds its budget: ${ over
						.map(
							( kind ) =>
								`${ kind } ${ size[ kind ] } B > ${ budget[ kind ] } B`
						)
						.join( ', ' ) }`
				);
			}
		}
		rows.push( [
			key,
			size.raw,
			budget?.raw ?? '-',
			size.gzip,
			budget?.gzip ?? '-',
			status,
		] );
	}
	for ( const key of Object.keys( assets ).sort() ) {
		if ( ! sizes.has( key ) )
			failures.push(
				`budget for ${ key } has no published asset; remove it from ${ budgetsPath }`
			);
	}
	return { failures, rows, sizes };
}

export function formatTable( rows ) {
	const head = [ 'Asset', 'Raw B', 'Budget', 'Gzip B', 'Budget', 'Status' ];
	const all = [ head, ...rows.map( ( row ) => row.map( String ) ) ];
	const widths = head.map( ( _, column ) =>
		Math.max( ...all.map( ( row ) => row[ column ].length ) )
	);
	const line = ( row ) =>
		row
			.map( ( cell, column ) =>
				column === 0 || column === 5
					? cell.padEnd( widths[ column ] )
					: cell.padStart( widths[ column ] )
			)
			.join( '  ' )
			.trimEnd();
	return [
		line( head ),
		widths.map( ( width ) => '-'.repeat( width ) ).join( '  ' ),
		...all.slice( 1 ).map( line ),
	].join( '\n' );
}

/** Runs every check; `allowlist` and `budgets` default to the checked-in files under root. */
export function runArchitectureChecks( root, options = {} ) {
	const allowlist =
		options.allowlist ?? loadJson( path.join( root, allowlistPath ), {} );
	const budgets =
		options.budgets ??
		loadJson( path.join( root, budgetsPath ), { assets: {} } );
	const project = new Project( root );
	const configErrors = [
		...requireReasons( allowlist.recoveredRuntime, 'recoveredRuntime' ),
		...requireReasons( allowlist.cycles, 'cycles' ),
		...requireReasons( allowlist.crossFeature, 'crossFeature' ),
	];
	const sections = [
		{
			name: 'allow-list entries are justified',
			failures: configErrors,
			allowed: [],
		},
		{
			name: 'app does not import recovered runtime code',
			...checkRecoveredRuntime( project, allowlist.recoveredRuntime ),
		},
		{
			name: 'no import cycles in assets/src/app',
			...checkCycles( project, allowlist.cycles ),
		},
		{
			name: 'features use each other only through index.ts / public.ts',
			...checkFeatureBoundaries( project, allowlist.crossFeature ),
		},
		{
			name: 'admin bundles use WordPress React and @wordpress/* externals',
			...checkDuplicateRuntimes( root ),
			allowed: [],
		},
	];
	const budgetResult = checkBudgets( root, budgets );
	sections.push( {
		name: 'per-screen asset budgets',
		failures: budgetResult.failures,
		allowed: [],
		table: formatTable( budgetResult.rows ),
	} );
	return {
		ok: sections.every( ( section ) => ! section.failures.length ),
		sections,
		sizes: budgetResult.sizes,
	};
}

export function formatReport( result ) {
	const lines = [];
	for ( const section of result.sections ) {
		lines.push(
			`${ section.failures.length ? '✖' : '✔' } ${ section.name }`
		);
		for ( const failure of section.failures )
			lines.push( `    ✖ ${ failure }` );
		for ( const entry of section.allowed || [] )
			lines.push(
				`    ~ allowed: ${ entry.message }\n      reason: ${ entry.reason }`
			);
		if ( section.table )
			lines.push( '', section.table.replace( /^/gm, '    ' ), '' );
	}
	lines.push(
		result.ok
			? 'Architecture checks passed.'
			: 'Architecture checks failed. See docs/ENFORCEMENT.md.'
	);
	return lines.join( '\n' );
}

if (
	process.argv[ 1 ] &&
	import.meta.url === pathToFileURL( process.argv[ 1 ] ).href
) {
	const root = path.resolve( import.meta.dirname, '..' );
	if ( process.argv.includes( '--update-budgets' ) ) {
		const file = path.join( root, budgetsPath );
		const current = loadJson( file, {} );
		const headroom = current.headroom ?? 0.1;
		const { sizes } = measureAssets( root );
		fs.writeFileSync(
			file,
			JSON.stringify(
				{
					description:
						current.description ??
						'Byte budgets for assets/dist/admin (LF-normalized raw size and gzip -9). Edit deliberately; see docs/ENFORCEMENT.md.',
					headroom,
					assets: budgetsFor( sizes, headroom ),
				},
				null,
				'\t'
			) + '\n'
		);
		console.log(
			`Wrote ${ budgetsPath } (${ sizes.size } assets, +${ Math.round( headroom * 100 ) }% headroom).`
		);
	}
	const result = runArchitectureChecks( root );
	console.log( formatReport( result ) );
	if ( ! result.ok ) process.exitCode = 1;
}
