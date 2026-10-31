import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
	FRONTEND_ALLOWLIST,
	FrontendGraph,
	analyzeModule,
	checkFeatureFolderImports,
	checkFrontendCycles,
	checkJsxPragma,
	checkLearnerModules,
	featureFolder,
	frontendSections,
	loadFrontendAllowlist,
	resolveFrontendImport,
} from '../../tools/architecture-rules/frontend.mjs';
import { runArchitectureChecks } from '../../tools/check-architecture.mjs';

const pluginRoot = path.resolve( import.meta.dirname, '../..' );
const FEATURES = 'assets/src/features';
const LEARNER = 'assets/interactivity';

/** A throwaway plugin root holding the given files (path relative to the root => contents). */
function tree( files ) {
	const root = fs.mkdtempSync(
		path.join( os.tmpdir(), 'ohmylms-frontend-' )
	);
	for ( const [ file, contents ] of Object.entries( files ) ) {
		const full = path.join( root, file );
		fs.mkdirSync( path.dirname( full ), { recursive: true } );
		fs.writeFileSync( full, contents );
	}
	test.after( () => fs.rmSync( root, { recursive: true, force: true } ) );
	return root;
}

const graphOf = ( files ) => new FrontendGraph( tree( files ) );

test( 'analyzeModule reads static, re-export, dynamic and require imports from the AST', () => {
	const facts = analyzeModule( `
		// import './commented.js';
		import a from './a.js';
		export { b } from './b.mjs';
		export * from './c';
		const d = import( './d.js' );
		const e = import( \`./e.js\` );
		const f = require( './f.cjs' );
		const g = import( name );
		const text = "import './string.js'";
	` );
	assert.deepEqual(
		facts.imports
			.map( ( entry ) => [ entry.specifier, entry.kind ] )
			.sort(),
		[
			[ './a.js', 'static' ],
			[ './b.mjs', 'static' ],
			[ './c', 'static' ],
			[ './d.js', 'dynamic' ],
			[ './e.js', 'dynamic' ],
			[ './f.cjs', 'require' ],
		]
	);
	assert.equal(
		facts.imports.find( ( entry ) => entry.specifier === './a.js' ).line,
		3
	);
} );

test( 'analyzeModule finds jQuery and $ references, not words in comments or strings', () => {
	assert.deepEqual(
		analyzeModule( 'const $ = window.jQuery;' ).jquery,
		[ 1 ]
	);
	assert.deepEqual(
		analyzeModule(
			'win?.jQuery?.( document );\nwindow[ "jQuery" ];\n$( "a" );'
		).jquery,
		[ 1, 2, 3 ]
	);
	assert.deepEqual(
		analyzeModule( "import $ from 'jquery';" ).jquery,
		[ 1 ]
	);
	assert.deepEqual(
		analyzeModule(
			'// jQuery( document ).trigger()\nconst label = "jQuery event";\nexport function jqueryTrigger() {}\nconst price = `$${ 5 }`;'
		).jquery,
		[]
	);
} );

test( 'analyzeModule notes JSX and a createElement binding', () => {
	assert.deepEqual(
		[ 'jsx', 'createElement' ].map(
			( key ) =>
				analyzeModule(
					"import { createElement } from '@wordpress/element';\nexport const A = () => <p />;"
				)[ key ]
		),
		[ true, true ]
	);
	const fragment = analyzeModule( 'export const A = () => <></>;' );
	assert.equal( fragment.jsx, true );
	assert.equal( fragment.createElement, false );
	assert.equal(
		analyzeModule( 'const createElement = ( tag ) => tag;' ).createElement,
		true
	);
	assert.equal( analyzeModule( 'export const x = 1;' ).jsx, false );
} );

test( 'resolveFrontendImport follows relative paths and the learner import map', () => {
	const root = tree( {
		[ `${ LEARNER }/sdk.js` ]: '',
		[ `${ LEARNER }/checkout.js` ]: '',
		[ `${ LEARNER }/video/youtube.js` ]: '',
		[ `${ LEARNER }/quiz-player/index.js` ]: '',
		[ `${ FEATURES }/math/loader.mjs` ]: '',
		[ `${ FEATURES }/learning/Panel.jsx` ]: '',
	} );
	const from = path.join( root, LEARNER, 'stripe.js' );
	const resolve = ( file, specifier ) => {
		const target = resolveFrontendImport( root, file, specifier );
		return (
			target &&
			path.relative( root, target ).split( path.sep ).join( '/' )
		);
	};
	assert.equal(
		resolve( from, 'ohmylms/interactivity' ),
		`${ LEARNER }/sdk.js`
	);
	assert.equal(
		resolve( from, 'ohmylms/checkout' ),
		`${ LEARNER }/checkout.js`
	);
	assert.equal(
		resolve( from, 'ohmylms/video-youtube' ),
		`${ LEARNER }/video/youtube.js`
	);
	assert.equal( resolve( from, 'ohmylms/missing' ), null );
	assert.equal( resolve( from, '@wordpress/interactivity' ), null );
	assert.equal(
		resolve( from, './quiz-player' ),
		`${ LEARNER }/quiz-player/index.js`
	);
	assert.equal(
		resolve( from, '../src/features/math/loader.mjs' ),
		`${ FEATURES }/math/loader.mjs`
	);
	assert.equal(
		resolve(
			path.join( root, FEATURES, 'paper', 'X.jsx' ),
			'../learning/Panel'
		),
		`${ FEATURES }/learning/Panel.jsx`
	);
} );

test( 'cycles: a cycle across both trees fails, through dynamic imports and import-map names too', () => {
	const graph = graphOf( {
		[ `${ FEATURES }/math/loader.mjs` ]:
			"import '../../../interactivity/question-player.js';",
		[ `${ LEARNER }/question-player.js` ]:
			"const load = () => import( '../src/features/math/loader.mjs' );",
		[ `${ LEARNER }/stripe.js` ]:
			"import { registerGateway } from 'ohmylms/checkout';",
		[ `${ LEARNER }/checkout.js` ]: "import 'ohmylms/stripe';",
		[ `${ LEARNER }/sdk.js` ]: 'export const apiVersion = 1;',
	} );
	const result = checkFrontendCycles( graph );
	assert.equal( result.failures.length, 2 );
	assert.match(
		result.failures.join( '\n' ),
		/import cycle: assets\/interactivity\/checkout\.js -> assets\/interactivity\/stripe\.js -> assets\/interactivity\/checkout\.js/
	);
	assert.match( result.failures.join( '\n' ), /math\/loader\.mjs/ );
} );

test( 'cycles: allow-listed cycles pass with their reason; stale and unexplained entries fail', () => {
	const graph = graphOf( {
		[ `${ LEARNER }/a.js` ]: "import './b.js';",
		[ `${ LEARNER }/b.js` ]: "import './a.js';",
	} );
	const files = [ `${ LEARNER }/b.js`, `${ LEARNER }/a.js` ];
	const allowed = checkFrontendCycles( graph, [
		{ files, reason: 'Being split.' },
	] );
	assert.deepEqual( allowed.failures, [] );
	assert.equal( allowed.allowed[ 0 ].reason, 'Being split.' );
	const result = checkFrontendCycles( graph, [
		{ files },
		{ files: [ `${ LEARNER }/c.js`, `${ LEARNER }/d.js` ], reason: 'Old.' },
	] );
	assert.match(
		result.failures[ 0 ],
		/frontend-allowlist\.json cycles: entry .* has no reason/
	);
	assert.ok(
		result.failures.some( ( failure ) => /^import cycle:/.test( failure ) )
	);
	assert.ok(
		result.failures.some( ( failure ) =>
			failure.startsWith(
				'stale allow-list entry (no longer violated, remove it): cycles'
			)
		)
	);
} );

test( 'learner modules: the SDK is imported by name only, and imports nothing itself', () => {
	const graph = graphOf( {
		[ `${ LEARNER }/sdk.js` ]: "import { helper } from './helpers.js';",
		[ `${ LEARNER }/helpers.js` ]: '',
		[ `${ LEARNER }/tabs.js` ]:
			"import { emit } from 'ohmylms/interactivity';",
		[ `${ LEARNER }/cart.js` ]: "import { emit } from './sdk.js';",
		[ `${ LEARNER }/quiz-player/index.js` ]:
			"const sdk = import( '../sdk' );",
		[ `${ FEATURES }/learning/a.mjs` ]:
			"import '../../../interactivity/sdk.js';",
	} );
	const { failures } = checkLearnerModules( graph );
	assert.equal( failures.length, 3 );
	assert.match(
		failures.join( '\n' ),
		/^assets\/interactivity\/sdk\.js imports '\.\/helpers\.js'; the SDK is the ohmylms\/interactivity import-map entry and imports nothing$/m
	);
	assert.match(
		failures.join( '\n' ),
		/assets\/interactivity\/cart\.js:1 imports the SDK as '\.\/sdk\.js'; import 'ohmylms\/interactivity'/
	);
	assert.match(
		failures.join( '\n' ),
		/quiz-player\/index\.js:1 imports the SDK as '\.\.\/sdk'/
	);
} );

test( 'learner modules: jQuery fails unless the file is allow-listed with a reason', () => {
	const graph = graphOf( {
		[ `${ LEARNER }/sdk.js` ]:
			'export const jqueryTrigger = () => window.jQuery;',
		[ `${ LEARNER }/cart.js` ]:
			'const $ = window.jQuery;\n$( document ).trigger( "x" );',
		[ `${ LEARNER }/tabs.js` ]: 'export const ok = 1;',
		[ `${ FEATURES }/learning/a.mjs` ]: 'window.jQuery;',
	} );
	const failures = checkLearnerModules( graph ).failures;
	assert.deepEqual(
		failures.map( ( failure ) => failure.split( ' uses' )[ 0 ] ),
		[ `${ LEARNER }/cart.js:1,2`, `${ LEARNER }/sdk.js:1` ]
	);
	const result = checkLearnerModules( graph, [
		{ file: `${ LEARNER }/sdk.js`, reason: 'Bridge.' },
		{ file: `${ LEARNER }/cart.js`, reason: 'Legacy events.' },
		{ file: `${ LEARNER }/tabs.js`, reason: 'Gone.' },
	] );
	assert.deepEqual( result.failures, [
		`stale allow-list entry (no longer violated, remove it): jquery ${ LEARNER }/tabs.js`,
	] );
	assert.equal( result.allowed.length, 2 );
} );

test( 'featureFolder names top-level folders; root modules and other trees have none', () => {
	assert.equal(
		featureFolder( `${ FEATURES }/learning/a/b.js` ),
		'learning'
	);
	assert.equal( featureFolder( `${ FEATURES }/rest.mjs` ), null );
	assert.equal( featureFolder( `${ LEARNER }/sdk.js` ), null );
} );

test( 'feature folders: cross-folder imports need the target file listed for that pair', () => {
	const graph = graphOf( {
		[ `${ FEATURES }/rest.mjs` ]: '',
		[ `${ FEATURES }/learning/Panel.jsx` ]:
			"import { request } from '../rest.mjs';\nimport { a } from '../paper/PaperCanvas.jsx';\nimport { b } from '../paper/model.mjs';\nimport './local.mjs';",
		[ `${ FEATURES }/learning/Other.jsx` ]:
			"const c = import( '../paper/PaperCanvas.jsx' );",
		[ `${ FEATURES }/learning/local.mjs` ]: '',
		[ `${ FEATURES }/paper/PaperCanvas.jsx` ]:
			"import '../../../interactivity/puzzles.mjs';",
		[ `${ FEATURES }/paper/model.mjs` ]: '',
		[ `${ LEARNER }/puzzles.mjs` ]: '',
	} );
	const open = checkFeatureFolderImports( graph );
	assert.deepEqual(
		open.violations
			.map(
				( violation ) =>
					`${ violation.from } -> ${ violation.to } ${ violation.file }`
			)
			.sort(),
		[ 'learning -> paper PaperCanvas.jsx', 'learning -> paper model.mjs' ]
	);
	assert.match(
		open.failures.join( '\n' ),
		/assets\/src\/features\/learning\/Other\.jsx, assets\/src\/features\/learning\/Panel\.jsx imports assets\/src\/features\/paper\/PaperCanvas\.jsx/
	);
	const listed = checkFeatureFolderImports( graph, [
		{
			from: 'learning',
			to: 'paper',
			files: [ 'PaperCanvas.jsx' ],
			reason: 'Canvas.',
		},
	] );
	assert.equal( listed.failures.length, 1 );
	assert.match(
		listed.failures[ 0 ],
		/imports assets\/src\/features\/paper\/model\.mjs/
	);
	assert.equal( listed.allowed[ 0 ].reason, 'Canvas.' );
} );

test( 'feature folders: stale pairs, stale files and missing reasons fail', () => {
	const graph = graphOf( {
		[ `${ FEATURES }/learning/Panel.jsx` ]:
			"import '../paper/PaperCanvas.jsx';",
		[ `${ FEATURES }/paper/PaperCanvas.jsx` ]: '',
	} );
	const { failures } = checkFeatureFolderImports( graph, [
		{
			from: 'learning',
			to: 'paper',
			files: [ 'PaperCanvas.jsx', 'PaperInserter.jsx' ],
			reason: 'Canvas and inserter.',
		},
		{
			from: 'paper',
			to: 'learning',
			files: [ 'quizPreview.mjs' ],
			reason: 'Preview.',
		},
		{ from: 'math', to: 'paper', files: [ 'x.mjs' ] },
	] );
	assert.deepEqual( failures, [
		`${ FRONTEND_ALLOWLIST } featureImports: entry {"from":"math","to":"paper","files":["x.mjs"]} has no reason`,
		'stale allow-list entry (no longer violated, remove it): featureImports paper -> learning',
		'stale allow-list entry (no longer imported, remove it): featureImports learning -> paper PaperInserter.jsx',
	] );
} );

test( 'JSX modules in assets/src/features must bind createElement', () => {
	const graph = graphOf( {
		[ `${ FEATURES }/a/Bound.jsx` ]:
			"import { createElement } from '@wordpress/element';\nexport const A = () => <><p /></>;",
		[ `${ FEATURES }/a/Unbound.jsx` ]:
			"import { useState } from '@wordpress/element';\nexport const B = () => <p />;",
		[ `${ FEATURES }/a/model.mjs` ]: 'export const c = 1;',
		[ `${ LEARNER }/view.js` ]: 'export const d = () => <p />;',
	} );
	assert.deepEqual( checkJsxPragma( graph ).failures, [
		`${ FEATURES }/a/Unbound.jsx uses JSX without binding createElement; import { createElement } from '@wordpress/element' (webpack compiles JSX with the classic createElement pragma)`,
	] );
} );

test( 'frontendSections reads the allow-list file and reports four sections', () => {
	const root = tree( {
		[ `${ LEARNER }/cart.js` ]: 'window.jQuery;',
		[ FRONTEND_ALLOWLIST ]: JSON.stringify( {
			jquery: [
				{ file: `${ LEARNER }/cart.js`, reason: 'Legacy events.' },
			],
		} ),
	} );
	assert.deepEqual( loadFrontendAllowlist( root ).jquery.length, 1 );
	const sections = frontendSections( root );
	assert.equal( sections.length, 4 );
	assert.ok( sections.every( ( section ) => ! section.failures.length ) );
	assert.equal( sections[ 1 ].allowed.length, 1 );
	assert.deepEqual( loadFrontendAllowlist( tree( {} ) ), {} );
} );

test( 'the plugin passes the front-end rules, and every allow-list entry has a reason', () => {
	const allowlist = loadFrontendAllowlist( pluginRoot );
	for ( const section of [ 'cycles', 'jquery', 'featureImports' ] ) {
		assert.ok( Array.isArray( allowlist[ section ] ), section );
		for ( const entry of allowlist[ section ] )
			assert.ok( entry.reason?.trim(), JSON.stringify( entry ) );
	}
	for ( const section of frontendSections( pluginRoot ) )
		assert.deepEqual( section.failures, [], section.name );
} );

test( 'runArchitectureChecks includes the front-end sections', () => {
	const root = tree( {
		[ `${ FEATURES }/a/Unbound.jsx` ]: 'export const B = () => <p />;',
	} );
	const result = runArchitectureChecks( root, {
		allowlist: {},
		budgets: { assets: {} },
		frontendAllowlist: {},
	} );
	const section = result.sections.find(
		( entry ) =>
			entry.name === 'assets/src/features JSX modules bind createElement'
	);
	assert.equal( section.failures.length, 1 );
	assert.equal( result.ok, false );
} );
