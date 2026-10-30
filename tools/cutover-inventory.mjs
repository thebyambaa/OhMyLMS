/**
 * Inventory of everything that still depends on the recovered admin runtime (admin.php?page=ohmylms),
 * for the cutover to the new app (admin.php?page=ohmylms-app). Read-only and re-runnable; it changes no
 * file. The deletion plan that uses it is docs/CUTOVER.md.
 *
 * Usage: node tools/cutover-inventory.mjs [--details] [--json] [--check]
 *   --details  list every file per group instead of the first few
 *   --json     print the whole report as JSON
 *   --check    exit 1 when a cutover blocker is found: a browser spec opens the legacy page without
 *              `&ohmylms_legacy=1`, or code the new app or front end loads reaches recovered code
 *
 * How files are classified:
 * - assets/src/{application,modules}: recovered admin code (assets/src/manifest.json).
 * - assets/src/{runtimes,static}: by the manifest output each file is copied to. Outputs under
 *   assets/dist/{admin,chunks,vendors,css/admin,css/chunks} belong to the legacy admin; the rest
 *   (front end, blocks, theme support, images) is still shipped and must be kept or re-authored.
 * - Other source under assets/src: reachability over relative imports from the webpack entries in
 *   webpack.config.cjs. LEGACY_ENTRIES are the legacy SDK and the bridge entries the recovered bundles
 *   load; every other entry is kept. Legacy-only = reachable from legacy entries and not from kept ones.
 * - Tools: content markers (recovered paths, factories, adapters) or only imported by such tools;
 *   MIXED_TOOLS also do kept work and must be split before deletion.
 * - Tests: legacy-only when every local import is legacy-only or the file reads recovered bundles.
 * - npm packages: only used by recovery when every file that imports them is legacy-only.
 */
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve( import.meta.dirname, '..' );
const args = new Set( process.argv.slice( 2 ) );
const rel = ( file ) =>
	path.relative( root, file ).split( path.sep ).join( '/' );
const abs = ( file ) => path.join( root, file );
const exists = ( file ) => fs.existsSync( abs( file ) );
const read = ( file ) => fs.readFileSync( abs( file ), 'utf8' );
const size = ( file ) => fs.statSync( abs( file ) ).size;

const LEGACY_ENTRIES = [
	'extensions',
	'email-packages',
	'vendor-charts',
	'vendor-emotion',
	'editor-tiptap',
	'editor-lowlight',
	'course-structure',
];
const RECOVERED_DIRS = [ 'application', 'modules', 'runtimes', 'static' ];
const LEGACY_OUTPUT =
	/^assets\/dist\/(?:admin|chunks|vendors|css\/admin|css\/chunks)\//;
const CODE = /\.(?:m?jsx?|cjs|tsx?)$/;
const RESOLVE_EXT = [
	'',
	'.js',
	'.jsx',
	'.mjs',
	'.cjs',
	'.ts',
	'.tsx',
	'.json',
];
const RECOVERY_MARKER =
	/assets\/src\/(?:application|modules|runtimes|static)\b|src\/manifest\.json|readRuntime|recovered-modules|factory 1841|chunks\/355|dist\/vendors\/|dist\/admin\/ohmylms\.js|build\/parity/;
// Like RECOVERY_MARKER without `readRuntime`, which authored components on the runtime also use.
const RECOVERY_TEST_MARKER =
	/assets\/src\/(?:application|modules|runtimes|static)\b|src\/manifest\.json|recovered-modules|recovered-fragments|factory 1841|chunks\/355|dist\/vendors\/|dist\/admin\/ohmylms\.js|build\/parity|-adapters?\.mjs/;
// Tools that mention the runtime only to guard against it.
const KEEP_TOOLS = {
	'check-architecture.mjs':
		'guards the new app; drop its readRuntime rule once the runtime is gone',
	'cutover-inventory.mjs':
		'this inventory; delete with the last recovered file',
};
const MIXED_TOOLS = {
	'build-assets.mjs':
		'rebuilds the recovered bundles and also the admin and front-end styles; keep the style part',
	'sync-admin-assets.mjs':
		'publishes build/ to assets/dist, including the new app; drop the recovered copies only',
	'dev.mjs': 'watches the recovered build and the new app',
	'check-reproducible.mjs': 'compares rebuilt recovered and authored outputs',
};
// Native editors on post.php: these legacy specs reach them through a legacy redirect; at cutover
// they open post.php directly instead of being deleted.
const GUTENBERG_SPEC = /url\.pathname\.endsWith\(\s*['"]\/post\.php['"]\s*\)/;
const LEGACY_PAGE = /admin\.php\?page=ohmylms(?![-\w])/;
const LEGACY_PAGE_NO_OPT_OUT =
	/admin\.php\?page=ohmylms(?![-\w])(?!&ohmylms_legacy=1)/g;

/**
 * Files below a directory, as repository-relative paths.
 *
 * @param {string}   dir    Repository-relative directory.
 * @param {Function} filter Keep a relative path when it returns true.
 * @return {string[]} Paths.
 */
function walk( dir, filter = () => true ) {
	if ( ! exists( dir ) ) {
		return [];
	}
	const out = [];
	for ( const entry of fs.readdirSync( abs( dir ), {
		withFileTypes: true,
	} ) ) {
		const child = dir + '/' + entry.name;
		if ( entry.isDirectory() ) {
			if ( 'node_modules' !== entry.name ) {
				out.push( ...walk( child, filter ) );
			}
		} else if ( filter( child ) ) {
			out.push( child );
		}
	}
	return out;
}

/**
 * Count and total size of files.
 *
 * @param {string[]} files Paths.
 * @return {{files: number, bytes: number}} Totals.
 */
function totals( files ) {
	return {
		files: files.length,
		bytes: files.reduce( ( sum, file ) => sum + size( file ), 0 ),
	};
}

const importCache = new Map();
/**
 * Local (resolved) and package imports of a source file.
 *
 * @param {string} file Repository-relative path.
 * @return {{local: string[], packages: string[]}} Imports.
 */
function importsOf( file ) {
	if ( importCache.has( file ) ) {
		return importCache.get( file );
	}
	const source = read( file );
	const pattern =
		/(?:import|export)\s[^'"`;]*?from\s*['"]([^'"]+)['"]|import\s*['"]([^'"]+)['"]|import\(\s*(?:\/\*[\s\S]*?\*\/\s*)?['"]([^'"]+)['"]\s*\)|require\(\s*['"]([^'"]+)['"]\s*\)/g;
	const local = new Set();
	const packages = new Set();
	for ( const match of source.matchAll( pattern ) ) {
		const spec = match[ 1 ] || match[ 2 ] || match[ 3 ] || match[ 4 ];
		if ( spec.startsWith( '.' ) ) {
			const target = resolveLocal( file, spec );
			if ( target ) {
				local.add( target );
			}
		} else if ( ! spec.startsWith( 'node:' ) && ! spec.startsWith( '/' ) ) {
			const parts = spec.split( '/' );
			packages.add(
				spec.startsWith( '@' )
					? parts.slice( 0, 2 ).join( '/' )
					: parts[ 0 ]
			);
		}
	}
	const result = { local: [ ...local ], packages: [ ...packages ] };
	importCache.set( file, result );
	return result;
}

/**
 * Resolve a relative import like webpack does for this repository's extensions.
 *
 * @param {string} from Importing file.
 * @param {string} spec Relative specifier.
 * @return {string|null} Repository-relative path.
 */
function resolveLocal( from, spec ) {
	const base = path.posix.join( path.posix.dirname( from ), spec );
	for ( const candidate of [
		...RESOLVE_EXT.map( ( ext ) => base + ext ),
		...RESOLVE_EXT.slice( 1 ).map( ( ext ) => base + '/index' + ext ),
	] ) {
		if ( exists( candidate ) && fs.statSync( abs( candidate ) ).isFile() ) {
			return candidate;
		}
	}
	return null;
}

/**
 * Every source file reachable from some entries.
 *
 * @param {string[]} entries Entry paths.
 * @return {Set<string>} Reachable files.
 */
function reach( entries ) {
	const seen = new Set();
	const queue = [ ...entries ];
	while ( queue.length ) {
		const file = queue.pop();
		if ( seen.has( file ) || ! CODE.test( file ) ) {
			if ( ! CODE.test( file ) ) {
				seen.add( file );
			}
			continue;
		}
		seen.add( file );
		queue.push( ...importsOf( file ).local );
	}
	return seen;
}

/**
 * `file:line` for every line of the given files that matches.
 *
 * @param {string[]} files   Paths.
 * @param {RegExp}   pattern Line pattern.
 * @return {string[]} Locations.
 */
function grep( files, pattern ) {
	const found = [];
	for ( const file of files ) {
		read( file )
			.split( /\r?\n/ )
			.forEach( ( line, index ) => {
				if ( pattern.test( line ) ) {
					found.push( `${ file }:${ index + 1 }` );
				}
			} );
	}
	return found;
}

const format = ( bytes ) =>
	bytes >= 1048576
		? ( bytes / 1048576 ).toFixed( 1 ) + ' MB'
		: Math.round( bytes / 1024 ) + ' KB';

// 1. Recovered sources, classified by manifest output.
const manifest = JSON.parse( read( 'assets/src/manifest.json' ) );
const outputOf = new Map(
	manifest.assets.map( ( asset ) => [
		'assets/src/' + asset.source,
		asset.output,
	] )
);
const recovered = { legacy: [], keep: [], unlisted: [] };
const recoveredByDir = {};
for ( const dir of RECOVERED_DIRS ) {
	recoveredByDir[ dir ] = { legacy: [], keep: [] };
	for ( const file of walk( 'assets/src/' + dir ) ) {
		let group = 'legacy';
		if ( 'runtimes' === dir || 'static' === dir ) {
			const output = outputOf.get( file );
			group = ! output
				? 'unlisted'
				: LEGACY_OUTPUT.test( output )
					? 'legacy'
					: 'keep';
		}
		recovered[ group ].push( file );
		if ( 'unlisted' !== group ) {
			recoveredByDir[ dir ][ group ].push( file );
		}
	}
}
const recoveredSet = new Set( [
	...recovered.legacy,
	...recovered.keep,
	...recovered.unlisted,
] );

// 2. Authored source: reachability from the webpack entries.
const webpack = read( 'webpack.config.cjs' );
const entries = {};
for ( const match of webpack.matchAll(
	/['"]?([\w-]+)['"]?\s*:\s*path\.resolve\(\s*__dirname,\s*['"]([^'"]+)['"]\s*\)/g
) ) {
	if ( CODE.test( match[ 2 ] ) && exists( match[ 2 ] ) ) {
		entries[ match[ 1 ] ] = match[ 2 ];
	}
}
const keptEntries = Object.keys( entries ).filter(
	( name ) => ! LEGACY_ENTRIES.includes( name )
);
const phpFiles = walk( 'includes', ( file ) => file.endsWith( '.php' ) );
// Authored scripts PHP serves straight from assets/src (source mode), such as the front-end bundle.
const phpRoots = new Set();
for ( const file of phpFiles ) {
	for ( const match of read( file ).matchAll(
		/'(assets\/src\/[^']+\.(?:m?jsx?|tsx?))'/g
	) ) {
		if ( exists( match[ 1 ] ) ) {
			phpRoots.add( match[ 1 ] );
		}
	}
}
const kept = reach( [
	...keptEntries.map( ( name ) => entries[ name ] ),
	...phpRoots,
] );
const legacyReach = reach(
	LEGACY_ENTRIES.filter( ( name ) => entries[ name ] ).map(
		( name ) => entries[ name ]
	)
);
const isTest = ( file ) =>
	/\.test\.[cm]?[jt]sx?$|\/test\/|\/__tests__\/|fixtures?\.[jt]sx?$|\.d\.[cm]?ts$/i.test(
		file
	);
const authored = walk(
	'assets/src',
	( file ) =>
		CODE.test( file ) &&
		! RECOVERED_DIRS.some( ( dir ) =>
			file.startsWith( `assets/src/${ dir }/` )
		)
);
const legacyOnly = authored.filter(
	( file ) => legacyReach.has( file ) && ! kept.has( file )
);
const shared = authored.filter(
	( file ) => legacyReach.has( file ) && kept.has( file )
);
const unwired = authored.filter(
	( file ) =>
		! legacyReach.has( file ) && ! kept.has( file ) && ! isTest( file )
);
const runtimeBound = walk(
	'assets/src/features',
	( file ) =>
		CODE.test( file ) &&
		/createComponent\(\s*readRuntime|readRuntime\s*\(/.test( read( file ) )
);
const componentManifests = walk( 'assets/src/features', ( file ) =>
	file.endsWith( '/components.json' )
);
const keptReachingRecovered = [ ...kept ].filter(
	( file ) => recoveredSet.has( file ) || runtimeBound.includes( file )
);
const legacyOnlySet = new Set( legacyOnly );

// 3. Shipped assets.
const distGroup = ( files ) => files.filter( ( file ) => exists( file ) );
const bridgeNames = LEGACY_ENTRIES.filter( ( name ) => 'extensions' !== name );
const chunkNames = ( files ) => {
	const names = new Set();
	for ( const file of files ) {
		for ( const match of read( file ).matchAll(
			/webpackChunkName:\s*["']([^"']+)["']/g
		) ) {
			names.add( match[ 1 ] );
		}
	}
	return names;
};
const keptChunks = chunkNames( [ ...kept ].filter( ( f ) => CODE.test( f ) ) );
const sdkChunks = [ ...chunkNames( legacyOnly ) ].filter(
	( name ) => ! keptChunks.has( name )
);
const adminChunks = walk( 'assets/dist/admin/chunks' );
const distFiles = {
	runtime: distGroup( [
		...walk( 'assets/dist/admin', ( f ) =>
			/\/admin\/ohmylms(?:-rtl)?\.js(?:\.map)?$/.test( f )
		),
		...walk( 'assets/dist/vendors' ),
		...walk( 'assets/dist/chunks' ),
	] ),
	bridges: walk( 'assets/dist/admin', ( file ) =>
		bridgeNames.some( ( name ) =>
			new RegExp( `/admin/${ name }\\.` ).test( file )
		)
	),
	sdk: [
		...walk( 'assets/dist/admin', ( file ) =>
			/\/admin\/extensions\.(?:js|js\.map|asset\.php)$/.test( file )
		),
		...adminChunks.filter( ( file ) =>
			sdkChunks.some( ( name ) =>
				path.posix.basename( file ).startsWith( name + '.' )
			)
		),
	],
	css: [
		...walk( 'assets/dist/css/admin' ),
		...walk( 'assets/dist/css/chunks' ),
	],
};
const unknownChunks = adminChunks.filter(
	( file ) =>
		/\/\d+\.[0-9a-f]+\.js(?:\.map|\.LICENSE\.txt)?$/.test( file ) &&
		! distFiles.sdk.includes( file )
);
const cssModules = [
	...read( 'assets/dist/css/admin/modules.php' ).matchAll(
		/'path' => '([^']+)', 'layer' => '([^']+)', 'group' => '([^']+)'/g
	),
].map( ( match ) => ( {
	path: match[ 1 ],
	layer: match[ 2 ],
	group: match[ 3 ],
} ) );
const cssGroups = {};
for ( const module of cssModules ) {
	cssGroups[ module.group ] = ( cssGroups[ module.group ] || 0 ) + 1;
}
const adminAssets = read( 'includes/Assets/AdminAssets.php' );
// Stylesheets AdminAssets enqueues only on the legacy screen.
const toplevelStyles = [
	...adminAssets.matchAll(
		/'(ohmylms-[\w-]+)'\s*=>\s*array\(\s*'src'\s*=>\s*self::get_asset_url\(\s*'([^']+\.css)'\s*\)[\s\S]*?'screens'\s*=>\s*array\(\s*'toplevel_page_ohmylms'\s*\)/g
	),
].map( ( match ) => ( { handle: match[ 1 ], file: match[ 2 ] } ) );
for ( const style of toplevelStyles ) {
	style.otherUses = grep(
		phpFiles.filter(
			( file ) => 'includes/Assets/AdminAssets.php' !== file
		),
		new RegExp( style.file.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) )
	);
}

// 4. PHP that enqueues or links the legacy admin, and what the front end still takes from the SDK.
const phpChecks = {
	'toplevel_page_ohmylms screen checks':
		/toplevel_page_ohmylms|'toplevel_page_'\s*\.\s*OHMYLMS_SLUG/,
	'recovered bundles':
		/assets\/dist\/(?:vendors|chunks)\/|dist\/admin\/ohmylms|'ohmylms-(?:vendor|chartjs|editor|emotion)'/,
	'bridge entries':
		/email-packages|vendor-charts|vendor-emotion|editor-tiptap|editor-lowlight|course-structure\.js/,
	'legacy SDK (extensions.js)':
		/ohmylms-extension-sdk|extensions\.asset\.php|SourceAssets::/,
	'hard-coded legacy links (not via LegacyRoutes)':
		/admin\.php\?page=ohmylms(?![-\w])|'admin\.php\?page=ohmylms'\s*\)\s*\.\s*'#/,
};
const php = {};
for ( const [ label, pattern ] of Object.entries( phpChecks ) ) {
	php[ label ] = grep(
		phpFiles.filter(
			( file ) =>
				! /includes\/Admin\/(?:LegacyRoutes|AppPage|Menu)\.php$/.test(
					file
				)
		),
		pattern
	);
}
const sdkNeeds = {
	'front-end slots (Slots::render → SourceAssets::enqueue)': grep(
		[ 'includes/Extensions/Slots.php' ],
		/SourceAssets::enqueue|data-ohmylms-slot/
	),
	'checkout fields (checkout-field slots)': grep(
		[ 'includes/Extensions/Slots.php' ],
		/checkout\./
	),
	'registration fields (signup form)': [
		...grep(
			[ 'includes/Extensions/SourceAssets.php' ],
			/ohmylms_signup_form_start/
		),
		...grep( [ 'assets/src/extensions/index.jsx' ], /registration-fields/ ),
	],
	'MathLive config on the SDK handle': grep(
		phpFiles,
		/localize_script\(\s*'ohmylms-extension-sdk'/
	),
	'native editors and other kept code reading window.ohmylms.extensions':
		grep(
			[ ...kept ].filter(
				( file ) => CODE.test( file ) && ! isTest( file )
			),
			/ohmylms\??\.extensions\b|extensions-ready/
		),
};

// 5. Tools.
const toolFiles = walk( 'tools', ( file ) =>
	/^tools\/[^/]+\.(?:mjs|cjs|js)$/.test( file )
);
const classified = ( file ) =>
	MIXED_TOOLS[ path.posix.basename( file ) ] ||
	KEEP_TOOLS[ path.posix.basename( file ) ];
const recoveryTools = new Set(
	toolFiles.filter(
		( file ) =>
			! classified( file ) &&
			( RECOVERY_MARKER.test( read( file ) ) ||
				/adapters?\.mjs$/.test( file ) )
	)
);
for ( let changed = true; changed; ) {
	changed = false;
	for ( const file of toolFiles ) {
		if ( recoveryTools.has( file ) || classified( file ) ) {
			continue;
		}
		const importers = toolFiles.filter( ( other ) =>
			importsOf( other ).local.includes( file )
		);
		if (
			importers.length &&
			importers.every( ( other ) => recoveryTools.has( other ) )
		) {
			recoveryTools.add( file );
			changed = true;
		}
	}
}
const pkg = JSON.parse( read( 'package.json' ) );
const recoveryScripts = Object.entries( pkg.scripts || {} )
	.map( ( [ name, command ] ) => ( {
		name,
		tools: [
			...recoveryTools,
			...Object.keys( MIXED_TOOLS ).map( ( t ) => 'tools/' + t ),
		]
			.filter( ( tool ) => command.includes( tool ) )
			.concat( command.includes( '--parity' ) ? [ '--parity' ] : [] ),
	} ) )
	.filter( ( script ) => script.tools.length );

// 6. Tests.
const testFiles = walk( 'tests/js', ( file ) =>
	/\.(?:mjs|cjs|js)$/.test( file )
);
const isLegacyFile = ( file ) =>
	recoveredSet.has( file ) ||
	legacyOnlySet.has( file ) ||
	recoveryTools.has( file ) ||
	file.endsWith( '/components.json' );
const legacyTests = new Set();
for ( let changed = true; changed; ) {
	changed = false;
	for ( const file of testFiles ) {
		if ( legacyTests.has( file ) ) {
			continue;
		}
		const local = importsOf( file ).local.filter(
			( target ) => ! target.startsWith( 'tests/fixtures/' )
		);
		const legacyImports = local.filter(
			( target ) => isLegacyFile( target ) || legacyTests.has( target )
		);
		// A test that imports anything kept (such as the architecture checks) stays, whatever it mentions.
		const legacy =
			legacyImports.length === local.length &&
			( legacyImports.length > 0 ||
				RECOVERY_MARKER.test( read( file ) ) );
		if ( legacy ) {
			legacyTests.add( file );
			changed = true;
		}
	}
}
const recoveryTests = [ ...legacyTests ]
	.filter(
		( file ) =>
			RECOVERY_TEST_MARKER.test( read( file ) ) ||
			importsOf( file ).local.some(
				( target ) =>
					recoveredSet.has( target ) || recoveryTools.has( target )
			)
	)
	.sort();
const sdkFeatureTests = [ ...legacyTests ]
	.filter( ( file ) => ! recoveryTests.includes( file ) )
	.sort();
const specs = walk( 'tests/browser', ( file ) => file.endsWith( '.spec.cjs' ) );
const legacySpecs = specs.filter( ( file ) =>
	/ohmylms_legacy=1|recovered-runtime\.cjs|dist\/admin\/ohmylms\.js/.test(
		read( file )
	)
);
const retargetSpecs = legacySpecs.filter( ( file ) =>
	GUTENBERG_SPEC.test( read( file ) )
);
const specViolations = specs.flatMap( ( file ) =>
	read( file )
		.split( /\r?\n/ )
		.flatMap( ( line, index ) =>
			line.match( LEGACY_PAGE_NO_OPT_OUT )
				? [ `${ file }:${ index + 1 }` ]
				: []
		)
);

// 7. npm packages only imported by legacy files.
const isLegacy = ( file ) => isLegacyFile( file ) || legacyTests.has( file );
const scanned = [
	...walk(
		'assets',
		( file ) => CODE.test( file ) && ! file.startsWith( 'assets/dist/' )
	),
	...toolFiles,
	...walk( 'tests', ( file ) => CODE.test( file ) ),
	...fs
		.readdirSync( root )
		.filter( ( file ) => /\.(?:c?js|mjs|ts)$/.test( file ) ),
].filter( ( file ) => ! file.includes( '/node_modules/' ) );
const usage = {};
for ( const file of scanned ) {
	if ( recoveredSet.has( file ) && size( file ) > 512000 ) {
		continue;
	}
	for ( const name of importsOf( file ).packages ) {
		usage[ name ] = usage[ name ] || { legacy: [], kept: [] };
		usage[ name ][ isLegacy( file ) ? 'legacy' : 'kept' ].push( file );
	}
}
const dependencies = {
	...( pkg.dependencies || {} ),
	...( pkg.devDependencies || {} ),
};
// Packages whose command line an npm script runs stay, whoever imports them.
const scriptWords = new Set(
	Object.values( pkg.scripts || {} )
		.join( ' ' )
		.split( /[\s&|;]+/ )
);
const recoveryOnlyPackages = Object.keys( dependencies ).filter(
	( name ) =>
		usage[ name ]?.legacy.length &&
		! usage[ name ].kept.length &&
		! scriptWords.has( name )
);
const notImported = Object.keys( dependencies ).filter(
	( name ) =>
		! usage[ name ] &&
		! webpack.includes( name ) &&
		! scriptWords.has( name )
);

const report = {
	recoveredSources: {
		legacyAdmin: totals( recovered.legacy ),
		keep: totals( recovered.keep ),
		unlisted: totals( recovered.unlisted ),
		byDirectory: Object.fromEntries(
			Object.entries( recoveredByDir ).map( ( [ dir, groups ] ) => [
				dir,
				{
					legacyAdmin: totals( groups.legacy ),
					keep: totals( groups.keep ),
				},
			] )
		),
		keepFiles: recovered.keep,
	},
	authoredSource: {
		keptEntries,
		legacyEntries: LEGACY_ENTRIES,
		legacyOnly: totals( legacyOnly ),
		sharedWithKeptEntries: totals( shared ),
		unwired: totals( unwired ),
		runtimeBoundComponents: totals( runtimeBound ),
		componentManifests: componentManifests.length,
		legacyOnlyFiles: legacyOnly,
		sharedFiles: shared,
		unwiredFiles: unwired,
	},
	dist: {
		runtime: totals( distFiles.runtime ),
		bridges: totals( distFiles.bridges ),
		sdk: totals( distFiles.sdk ),
		sdkChunkNames: sdkChunks,
		numberedChunksToRecheck: totals( unknownChunks ),
		legacyCss: totals( distFiles.css ),
		cssModules: cssModules.length,
		cssModuleGroups: cssGroups,
		toplevelOnlyStyles: toplevelStyles,
		files: distFiles,
	},
	php,
	sdkNeeds,
	tools: {
		recoveryOnly: [ ...recoveryTools ].sort(),
		mixed: MIXED_TOOLS,
		keep: KEEP_TOOLS,
		npmScripts: recoveryScripts,
	},
	tests: {
		recoveryJs: recoveryTests,
		sdkFeatureJs: sdkFeatureTests,
		legacyOnlyBrowserSpecs: legacySpecs,
		retargetAtCutover: retargetSpecs,
		specsWithoutOptOut: specViolations,
	},
	packages: {
		recoveryOnly: recoveryOnlyPackages,
		notImported,
	},
	blockers: {
		specsWithoutOptOut: specViolations,
		keptCodeReachingRecovered: keptReachingRecovered,
	},
};

if ( args.has( '--json' ) ) {
	process.stdout.write( JSON.stringify( report, null, '\t' ) + '\n' );
} else {
	const limit = args.has( '--details' ) ? Infinity : 6;
	const line = ( label, value ) =>
		process.stdout.write( `${ label.padEnd( 50 ) } ${ value }\n` );
	const count = ( group ) =>
		`${ String( group.files ).padStart( 5 ) } files ${ format( group.bytes ).padStart( 9 ) }`;
	const list = ( items, indent = '    ' ) => {
		for ( const item of items.slice( 0, limit ) ) {
			process.stdout.write( indent + item + '\n' );
		}
		if ( items.length > limit ) {
			process.stdout.write(
				`${ indent }… ${ items.length - limit } more (--details)\n`
			);
		}
	};
	const heading = ( text ) => process.stdout.write( `\n${ text }\n` );
	process.stdout.write(
		'Cutover inventory: recovered admin (page=ohmylms) → new app (page=ohmylms-app)\n'
	);
	heading(
		'1. Recovered source (assets/src/{application,modules,runtimes,static})'
	);
	line(
		'  legacy admin only (delete at cutover)',
		count( report.recoveredSources.legacyAdmin )
	);
	for ( const [ dir, groups ] of Object.entries(
		report.recoveredSources.byDirectory
	) ) {
		line(
			`    ${ dir }`,
			`${ count( groups.legacyAdmin ) }   keep ${ count( groups.keep ) }`
		);
	}
	line(
		'  still shipped: front end, blocks, CSS (keep)',
		count( report.recoveredSources.keep )
	);
	line( '  not in manifest', count( report.recoveredSources.unlisted ) );
	heading( '2. Authored source on the legacy runtime' );
	line(
		'  legacy-only (SDK and bridge entries)',
		count( report.authoredSource.legacyOnly )
	);
	line(
		'  shared with kept entries (keep)',
		count( report.authoredSource.sharedWithKeptEntries )
	);
	line(
		'  unwired (no entry reaches them; review)',
		count( report.authoredSource.unwired )
	);
	list( unwired );
	line(
		'  createComponent(readRuntime) components',
		count( report.authoredSource.runtimeBoundComponents )
	);
	line(
		'  components.json manifests',
		String( report.authoredSource.componentManifests )
	);
	line( '  kept entries', keptEntries.join( ', ' ) );
	heading( '3. Shipped legacy assets (assets/dist)' );
	line(
		'  recovered bundles (ohmylms, vendors, 355)',
		count( report.dist.runtime )
	);
	line( '  bridge entries', count( report.dist.bridges ) );
	line( '  legacy SDK extensions.js + its chunks', count( report.dist.sdk ) );
	line(
		'  numbered chunks to re-check after rebuild',
		count( report.dist.numberedChunksToRecheck )
	);
	line(
		'  legacy admin CSS (css/admin, css/chunks)',
		count( report.dist.legacyCss )
	);
	line(
		'  CSS modules (toplevel_page_ohmylms only)',
		`${ cssModules.length } in ${ Object.keys( cssGroups ).length } groups`
	);
	for ( const style of toplevelStyles ) {
		line(
			`    ${ style.handle }`,
			`${ style.file }${ style.otherUses.length ? ' (also ' + style.otherUses.join( ', ' ) + ')' : ' (legacy only)' }`
		);
	}
	heading( '4. PHP still serving or linking the legacy admin' );
	for ( const [ label, found ] of Object.entries( php ) ) {
		line( `  ${ label }`, String( found.length ) );
		list( found );
	}
	heading( '5. Legacy SDK parts the front end and new app still need' );
	for ( const [ label, found ] of Object.entries( sdkNeeds ) ) {
		line( `  ${ label }`, String( found.length ) );
		list( found );
	}
	heading( '6. Tools and npm scripts' );
	line( '  recovery-only tools', String( report.tools.recoveryOnly.length ) );
	list( report.tools.recoveryOnly );
	line(
		'  mixed tools (split first)',
		String( Object.keys( MIXED_TOOLS ).length )
	);
	list(
		Object.entries( MIXED_TOOLS ).map(
			( [ tool, why ] ) => `${ tool }: ${ why }`
		)
	);
	line(
		'  kept tools that name the runtime',
		String( Object.keys( KEEP_TOOLS ).length )
	);
	list(
		Object.entries( KEEP_TOOLS ).map(
			( [ tool, why ] ) => `${ tool }: ${ why }`
		)
	);
	line(
		'  npm scripts using them',
		report.tools.npmScripts.map( ( s ) => s.name ).join( ', ' )
	);
	heading( '7. Tests' );
	line(
		'  recovery, parity and adapter tests/js',
		String( recoveryTests.length )
	);
	list( recoveryTests );
	line(
		'  tests/js of features only the SDK loads',
		String( sdkFeatureTests.length )
	);
	list( sdkFeatureTests );
	line( '  legacy-only browser specs', String( legacySpecs.length ) );
	list( legacySpecs );
	line( '  … of which retarget to post.php', String( retargetSpecs.length ) );
	list( retargetSpecs );
	heading( '8. npm packages' );
	line(
		'  only imported by legacy files',
		String( recoveryOnlyPackages.length )
	);
	list( recoveryOnlyPackages );
	line(
		'  not imported anywhere (CLI/config)',
		String( notImported.length )
	);
	list( notImported );
	heading( 'Blockers' );
	line(
		'  specs opening page=ohmylms without opt-out',
		String( specViolations.length )
	);
	list( specViolations );
	line(
		'  kept code reaching recovered/runtime code',
		String( keptReachingRecovered.length )
	);
	list( keptReachingRecovered );
}

if (
	args.has( '--check' ) &&
	( specViolations.length || keptReachingRecovered.length )
) {
	process.exitCode = 1;
}
