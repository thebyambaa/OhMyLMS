import fs from 'node:fs';
import path from 'node:path';
import { parse } from '@babel/parser';

/**
 * Architecture rules for the JavaScript outside the typed admin app (docs/ENFORCEMENT.md, sections
 * l to o): the native editor and block modules in assets/src/features and the learner script
 * modules in assets/interactivity.
 *
 * l. No import cycles between their modules (static, dynamic and `require()`).
 * m. Learner modules import the SDK by its import-map name `ohmylms/interactivity`, never by path;
 *    sdk.js imports nothing; nothing there uses jQuery or `$` (event bridges are allow-listed).
 * n. A folder of assets/src/features imports another folder's files only where the allow-list
 *    names that file for that pair of folders. Root modules (rest.mjs, requestFeedback.mjs) are
 *    shared by every folder.
 * o. A JSX module in assets/src/features binds `createElement`: webpack compiles its JSX with the
 *    classic `createElement` pragma (webpack.config.cjs).
 *
 * Exceptions go in tools/architecture-rules/frontend-allowlist.json, each with a reason:
 * `cycles` `{ files, reason }`, `jquery` `{ file, reason }` and `featureImports`
 * `{ from, to, files, reason }` (folder names; `files` relative to the `to` folder).
 */

export const FEATURES_ROOT = 'assets/src/features';
export const LEARNER_ROOT = 'assets/interactivity';
export const SDK_MODULE = 'ohmylms/interactivity';
export const SDK_FILE = `${ LEARNER_ROOT }/sdk.js`;
export const FRONTEND_ALLOWLIST =
	'tools/architecture-rules/frontend-allowlist.json';

const SOURCE = /\.(?:[cm]?js|jsx)$/;
const RESOLVE_EXTENSIONS = [ '.js', '.jsx', '.mjs', '.cjs', '.json' ];
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
const JQUERY_NAMES = new Set( [ 'jQuery', '$' ] );

const toPosix = ( file ) => file.split( path.sep ).join( '/' );

function readText( file ) {
	return fs.readFileSync( file, 'utf8' ).replace( /\r\n/g, '\n' );
}

function sourceFiles( directory ) {
	if ( ! fs.existsSync( directory ) ) return [];
	const files = [];
	for ( const entry of fs.readdirSync( directory, {
		withFileTypes: true,
	} ) ) {
		const full = path.join( directory, entry.name );
		if ( entry.isDirectory() ) {
			if ( entry.name !== 'node_modules' )
				files.push( ...sourceFiles( full ) );
		} else if ( SOURCE.test( entry.name ) ) files.push( full );
	}
	return files.sort();
}

const literal = ( node ) => {
	if ( node?.type === 'StringLiteral' ) return node.value;
	if ( node?.type === 'TemplateLiteral' && ! node.expressions.length )
		return node.quasis[ 0 ].value.cooked;
	return null;
};

/**
 * Imports, jQuery references, JSX use and a `createElement` binding of one module, from its AST.
 *
 * @param {string} text Module source.
 * @return {{imports: Array<{specifier: string, kind: string, line: number}>, jquery: number[], jsx: boolean, createElement: boolean}} Facts.
 */
export function analyzeModule( text ) {
	const facts = { imports: [], jquery: [], jsx: false, createElement: false };
	let ast;
	try {
		ast = parse( text, {
			sourceType: 'unambiguous',
			plugins: [ 'jsx' ],
			errorRecovery: true,
			allowReturnOutsideFunction: true,
		} );
	} catch {
		return facts;
	}
	const add = ( specifier, kind, node ) => {
		if ( specifier !== null )
			facts.imports.push( {
				specifier,
				kind,
				line: node.loc?.start.line ?? 0,
			} );
	};
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
				add( node.source.value, 'static', node );
				if (
					node.specifiers.some(
						( item ) => item.local?.name === 'createElement'
					)
				)
					facts.createElement = true;
				break;
			case 'ExportNamedDeclaration':
			case 'ExportAllDeclaration':
				if ( node.source ) add( node.source.value, 'static', node );
				break;
			case 'ImportExpression':
				add( literal( node.source ), 'dynamic', node );
				break;
			case 'CallExpression':
				if ( node.callee.type === 'Import' )
					add( literal( node.arguments[ 0 ] ), 'dynamic', node );
				if (
					node.callee.type === 'Identifier' &&
					node.callee.name === 'require'
				)
					add( literal( node.arguments[ 0 ] ), 'require', node );
				break;
			case 'VariableDeclarator':
			case 'FunctionDeclaration':
				if ( node.id?.name === 'createElement' )
					facts.createElement = true;
				break;
			case 'JSXElement':
			case 'JSXFragment':
				facts.jsx = true;
				break;
			case 'Identifier':
				if ( JQUERY_NAMES.has( node.name ) )
					facts.jquery.push( node.loc.start.line );
				break;
			case 'MemberExpression':
			case 'OptionalMemberExpression':
				if (
					node.computed &&
					JQUERY_NAMES.has( literal( node.property ) )
				)
					facts.jquery.push( node.loc.start.line );
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
	for ( const entry of facts.imports ) {
		if ( /^(?:jquery|\$)$/i.test( entry.specifier ) )
			facts.jquery.push( entry.line );
	}
	facts.jquery = [ ...new Set( facts.jquery ) ].sort( ( a, b ) => a - b );
	return facts;
}

/**
 * The file a specifier loads: relative paths like webpack resolves them, and the learner import-map
 * names that OhMyLMS\Extensions\Interactivity and Video register (`ohmylms/interactivity` is
 * sdk.js, `ohmylms/video-<provider>` is video/<provider>.js, `ohmylms/<id>` is <id>.js). Other
 * packages resolve to null.
 *
 * @param {string} root      Plugin root.
 * @param {string} from      Importing file.
 * @param {string} specifier Specifier.
 * @return {string|null} Absolute file.
 */
export function resolveFrontendImport( root, from, specifier ) {
	const exists = ( file ) =>
		fs.existsSync( file ) && fs.statSync( file ).isFile();
	if ( specifier.startsWith( 'ohmylms/' ) ) {
		const name = specifier.slice( 'ohmylms/'.length );
		const learner = path.join( root, LEARNER_ROOT );
		const file =
			specifier === SDK_MODULE
				? path.join( root, SDK_FILE )
				: path.join(
						learner,
						name.startsWith( 'video-' )
							? `video/${ name.slice( 6 ) }.js`
							: `${ name }.js`
					);
		return exists( file ) ? file : null;
	}
	if ( ! specifier.startsWith( '.' ) ) return null;
	const base = path.resolve(
		path.dirname( from ),
		specifier.split( '?' )[ 0 ]
	);
	return (
		[
			base,
			...RESOLVE_EXTENSIONS.map( ( extension ) => base + extension ),
			...RESOLVE_EXTENSIONS.map( ( extension ) =>
				path.join( base, 'index' + extension )
			),
		].find( exists ) || null
	);
}

/** Modules of assets/src/features and assets/interactivity with their resolved imports. */
export class FrontendGraph {
	constructor( root ) {
		this.root = root;
		this.modules = new Map();
		for ( const folder of [ FEATURES_ROOT, LEARNER_ROOT ] ) {
			for ( const file of sourceFiles( path.join( root, folder ) ) ) {
				const facts = analyzeModule( readText( file ) );
				this.modules.set( this.relative( file ), {
					...facts,
					edges: facts.imports
						.map( ( entry ) => {
							const target = resolveFrontendImport(
								root,
								file,
								entry.specifier
							);
							return target
								? { ...entry, target: this.relative( target ) }
								: null;
						} )
						.filter( Boolean ),
				} );
			}
		}
	}

	relative( file ) {
		return toPosix( path.relative( this.root, file ) );
	}
}

/** Allow-list of the front-end rules; an empty one when the file is missing. */
export function loadFrontendAllowlist( root ) {
	const file = path.join( root, FRONTEND_ALLOWLIST );
	return fs.existsSync( file ) ? JSON.parse( readText( file ) ) : {};
}

function partition(
	violations,
	allowlist,
	section,
	matches,
	describe,
	describeEntry
) {
	const failures = [];
	const allowed = [];
	const justified = ( allowlist || [] ).filter( ( entry ) => {
		if ( typeof entry?.reason === 'string' && entry.reason.trim() )
			return true;
		failures.push(
			`${ FRONTEND_ALLOWLIST } ${ section }: entry ${ JSON.stringify( entry ) } has no reason`
		);
		return false;
	} );
	for ( const violation of violations ) {
		const entry = justified.find( ( candidate ) =>
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
	for ( const entry of justified ) {
		if ( ! violations.some( ( violation ) => matches( violation, entry ) ) )
			failures.push(
				`stale allow-list entry (no longer violated, remove it): ${ section } ${ describeEntry( entry ) }`
			);
	}
	return { failures, allowed, violations };
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

/** l. Import cycles among the native editor and learner modules. */
export function checkFrontendCycles( graph, allowlist = [] ) {
	const edges = new Map(
		[ ...graph.modules ].map( ( [ file, module ] ) => [
			file,
			[
				...new Set(
					module.edges
						.map( ( edge ) => edge.target )
						.filter( ( target ) => graph.modules.has( target ) )
				),
			],
		] )
	);
	const violations = stronglyConnected( edges )
		.filter(
			( component ) =>
				component.length > 1 ||
				edges.get( component[ 0 ] ).includes( component[ 0 ] )
		)
		.map( ( component ) => ( {
			files: component,
			cycle: exampleCycle( edges, component ),
		} ) );
	return partition(
		violations,
		allowlist,
		'cycles',
		( violation, entry ) =>
			JSON.stringify( violation.files ) ===
			JSON.stringify( [ ...( entry.files || [] ) ].sort() ),
		( violation ) => `import cycle: ${ violation.cycle.join( ' -> ' ) }`,
		( entry ) => ( entry.files || [] ).join( ', ' )
	);
}

/** m. Learner modules use the SDK by name and do not use jQuery. */
export function checkLearnerModules( graph, allowlist = [] ) {
	const failures = [];
	const jquery = [];
	for ( const [ file, module ] of graph.modules ) {
		if ( ! file.startsWith( `${ LEARNER_ROOT }/` ) ) continue;
		if ( file === SDK_FILE && module.imports.length )
			failures.push(
				`${ file } imports ${ module.imports
					.map( ( entry ) => `'${ entry.specifier }'` )
					.join(
						', '
					) }; the SDK is the ${ SDK_MODULE } import-map entry and imports nothing`
			);
		for ( const edge of module.edges ) {
			if ( edge.target === SDK_FILE && edge.specifier !== SDK_MODULE )
				failures.push(
					`${ file }:${ edge.line } imports the SDK as '${ edge.specifier }'; import '${ SDK_MODULE }' (the import-map name), so the page loads one SDK instance`
				);
		}
		if ( module.jquery.length )
			jquery.push( { file, lines: module.jquery } );
	}
	const result = partition(
		jquery,
		allowlist,
		'jquery',
		( violation, entry ) => violation.file === entry.file,
		( violation ) =>
			`${ violation.file }:${ violation.lines.join( ',' ) } uses jQuery or $; learner modules use the DOM, @wordpress/interactivity and ${ SDK_MODULE } (emit() for add-on events)`,
		( entry ) => entry.file
	);
	return { ...result, failures: [ ...failures, ...result.failures ] };
}

/** Top-level folder of a features file, or null for root modules and files elsewhere. */
export function featureFolder( file ) {
	if ( ! file.startsWith( `${ FEATURES_ROOT }/` ) ) return null;
	const parts = file.slice( FEATURES_ROOT.length + 1 ).split( '/' );
	return parts.length > 1 ? parts[ 0 ] : null;
}

/** n. Imports between folders of assets/src/features, file by file. */
export function checkFeatureFolderImports( graph, allowlist = [] ) {
	const violations = [];
	for ( const [ file, module ] of graph.modules ) {
		const from = featureFolder( file );
		if ( ! from ) continue;
		for ( const edge of module.edges ) {
			const to = featureFolder( edge.target );
			if ( ! to || to === from ) continue;
			const target = edge.target.slice(
				FEATURES_ROOT.length + to.length + 2
			);
			let violation = violations.find(
				( entry ) =>
					entry.from === from &&
					entry.to === to &&
					entry.file === target
			);
			if ( ! violation ) {
				violation = { from, to, file: target, importers: [] };
				violations.push( violation );
			}
			if ( ! violation.importers.includes( file ) )
				violation.importers.push( file );
		}
	}
	const result = partition(
		violations,
		allowlist,
		'featureImports',
		( violation, entry ) =>
			violation.from === entry.from &&
			violation.to === entry.to &&
			( entry.files || [] ).includes( violation.file ),
		( violation ) =>
			`${ violation.importers.join( ', ' ) } imports ${ FEATURES_ROOT }/${ violation.to }/${ violation.file }; features/${ violation.from } may use features/${ violation.to } only through the files listed for that pair in ${ FRONTEND_ALLOWLIST } (featureImports)`,
		( entry ) => `${ entry.from } -> ${ entry.to }`
	);
	for ( const entry of allowlist || [] ) {
		for ( const listed of entry?.files || [] ) {
			if (
				violations.some(
					( violation ) =>
						violation.from === entry.from &&
						violation.to === entry.to
				) &&
				! violations.some(
					( violation ) =>
						violation.from === entry.from &&
						violation.to === entry.to &&
						violation.file === listed
				)
			)
				result.failures.push(
					`stale allow-list entry (no longer imported, remove it): featureImports ${ entry.from } -> ${ entry.to } ${ listed }`
				);
		}
	}
	return result;
}

/** o. JSX modules of assets/src/features bind the `createElement` their compiled JSX calls. */
export function checkJsxPragma( graph ) {
	const failures = [];
	for ( const [ file, module ] of graph.modules ) {
		if (
			file.startsWith( `${ FEATURES_ROOT }/` ) &&
			module.jsx &&
			! module.createElement
		)
			failures.push(
				`${ file } uses JSX without binding createElement; import { createElement } from '@wordpress/element' (webpack compiles JSX with the classic createElement pragma)`
			);
	}
	return { failures, allowed: [] };
}

/** The front-end sections of tools/check-architecture.mjs. */
export function frontendSections(
	root,
	allowlist = loadFrontendAllowlist( root )
) {
	const graph = new FrontendGraph( root );
	return [
		{
			name: 'no import cycles in assets/src/features and assets/interactivity',
			...checkFrontendCycles( graph, allowlist.cycles ),
		},
		{
			name: 'learner modules import the SDK as ohmylms/interactivity and do not use jQuery',
			...checkLearnerModules( graph, allowlist.jquery ),
		},
		{
			name: 'assets/src/features folders import each other only through allow-listed files',
			...checkFeatureFolderImports( graph, allowlist.featureImports ),
		},
		{
			name: 'assets/src/features JSX modules bind createElement',
			...checkJsxPragma( graph ),
		},
	];
}
