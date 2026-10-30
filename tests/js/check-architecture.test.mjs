import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
	Project,
	analyzeSource,
	budgetsFor,
	checkBudgets,
	checkCycles,
	checkDuplicateRuntimes,
	checkFeatureBoundaries,
	checkRawColours,
	checkRecoveredRuntime,
	findRawColours,
	formatReport,
	measureAssets,
	runArchitectureChecks,
} from '../../tools/check-architecture.mjs';

const fixtures = path.resolve(
	import.meta.dirname,
	'../fixtures/architecture'
);
const fixture = ( name ) => path.join( fixtures, name );
const edges = ( violations ) =>
	violations.map( ( { from, to } ) => `${ from } -> ${ to }` ).sort();

test( 'a clean app passes every check', () => {
	const root = fixture( 'clean' );
	const budgets = { assets: budgetsFor( measureAssets( root ).sizes ) };
	const result = runArchitectureChecks( root, { allowlist: {}, budgets } );
	assert.deepEqual(
		result.sections.flatMap( ( section ) => section.failures ),
		[]
	);
	assert.ok( result.ok );
	assert.match( formatReport( result ), /Architecture checks passed\./ );
} );

test( 'app imports that reach recovered runtime code are reported with their chain', () => {
	const project = new Project( fixture( 'recovered' ) );
	const { violations, failures } = checkRecoveredRuntime( project );
	assert.deepEqual( edges( violations ), [
		'assets/src/app/chunk.ts -> assets/src/shared/chunk.js',
		'assets/src/app/deep/b.ts -> assets/src/shared/runtime-reader.js',
		'assets/src/app/direct.ts -> assets/src/application/fragment.js',
		'assets/src/app/numeric.ts -> assets/src/shared/factory.js',
		'assets/src/app/runtime.ts -> assets/src/shared/runtime-reader.js',
		'assets/src/app/sdk.ts -> assets/src/extensions/lazyFeatures.js',
		'assets/src/app/self.ts -> assets/src/app/self.ts',
		'assets/src/app/ui.ts -> assets/src/ui/Thing.jsx',
		'assets/src/app/viaFeature.ts -> assets/src/features/legacy/index.js',
	] );
	const reasonOf = ( from ) =>
		violations.find( ( violation ) => violation.from === from ).reason;
	assert.equal(
		reasonOf( 'assets/src/app/chunk.ts' ),
		'references window.webpackChunkohmylms'
	);
	assert.equal( reasonOf( 'assets/src/app/deep/b.ts' ), 'reads readRuntime' );
	assert.equal(
		reasonOf( 'assets/src/app/direct.ts' ),
		'recovered runtime (assets/src/application/)'
	);
	assert.equal(
		reasonOf( 'assets/src/app/numeric.ts' ),
		'requires numeric webpack module ids'
	);
	assert.equal(
		reasonOf( 'assets/src/app/sdk.ts' ),
		'recovered extension SDK'
	);
	assert.equal(
		reasonOf( 'assets/src/app/ui.ts' ),
		'recovered runtime (assets/src/runtimes/)'
	);
	assert.equal(
		reasonOf( 'assets/src/app/viaFeature.ts' ),
		'listed in assets/src/features/legacy/components.json'
	);
	assert.ok(
		failures.includes(
			'assets/src/app/viaFeature.ts imports assets/src/features/legacy/index.js, which reaches recovered runtime code: assets/src/features/legacy/index.js -> assets/src/features/legacy/Widget.jsx (listed in assets/src/features/legacy/components.json)'
		),
		failures.join( '\n' )
	);
} );

test( 'allow-listed violations pass with their reason; stale entries fail', () => {
	const project = new Project( fixture( 'recovered' ) );
	const { allowed, failures } = checkRecoveredRuntime( project, [
		{
			from: 'assets/src/app/direct.ts',
			to: 'assets/src/application/fragment.js',
			reason: 'Fixture: kept until the fragment is authored.',
		},
		{
			from: 'assets/src/app/gone.ts',
			to: 'assets/src/application/fragment.js',
			reason: 'Fixture: no longer imported.',
		},
	] );
	assert.deepEqual( edges( allowed ), [
		'assets/src/app/direct.ts -> assets/src/application/fragment.js',
	] );
	assert.equal(
		allowed[ 0 ].reason,
		'Fixture: kept until the fragment is authored.'
	);
	assert.ok(
		! failures.some( ( failure ) =>
			failure.startsWith( 'assets/src/app/direct.ts' )
		)
	);
	assert.ok(
		failures.includes(
			'stale allow-list entry (no longer violated, remove it): assets/src/app/gone.ts -> assets/src/application/fragment.js'
		)
	);
} );

test( 'raw colour literals in app CSS and TSX are reported; tokens, anchors and comments are not', () => {
	const root = fs.mkdtempSync( path.join( os.tmpdir(), 'oml-colours-' ) );
	const write = ( file, text ) => {
		fs.mkdirSync( path.dirname( path.join( root, file ) ), {
			recursive: true,
		} );
		fs.writeFileSync( path.join( root, file ), text );
	};
	try {
		write(
			'assets/src/app/styles/app.css',
			[
				'/* Brand blue was #1865f2. */',
				'.a { color: var(--wp-admin-theme-color); }',
				'.b {',
				'\tbackground: #FFF;',
				'\tbox-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);',
				'}',
				'.c { border-color: hsl(10 50% 50%); }',
			].join( '\r\n' )
		);
		write(
			'assets/src/app/features/alpha/Page.tsx',
			[
				'// #abcdef in a comment is fine.',
				'export function Page() {',
				'\treturn (',
				'\t\t<>',
				'\t\t\t<a href="#add">Add</a>',
				'\t\t\t<a href={ `#/courses/${ 1 }` }>Course</a>',
				"\t\t\t<span style={ { color: '#d63638' } }>x</span>",
				'\t\t\t<rect fill="#2271b1" />',
				'\t\t\t<p>&#160;Colour #c3c4c7</p>',
				'\t\t</>',
				'\t);',
				'}',
			].join( '\n' )
		);
		write(
			'assets/src/app/features/alpha/model.ts',
			"export const BLACK = '#000000';\n"
		);
		write(
			'assets/src/app/features/alpha/Page.test.tsx',
			"const color = '#ffffff';\n"
		);
		const project = new Project( root );
		const { violations, failures, allowed } = checkRawColours( project, [
			{
				file: 'assets/src/app/features/alpha/Page.tsx',
				value: '#C3C4C7',
				reason: 'Fixture: example in help text.',
			},
			{
				file: 'assets/src/app/styles/app.css',
				value: '#123456',
				reason: 'Fixture: gone.',
			},
		] );
		assert.deepEqual(
			violations.map(
				( { file, line, value } ) => `${ file }:${ line } ${ value }`
			),
			[
				'assets/src/app/features/alpha/Page.tsx:7 #d63638',
				'assets/src/app/features/alpha/Page.tsx:8 #2271b1',
				'assets/src/app/features/alpha/Page.tsx:9 #c3c4c7',
				'assets/src/app/styles/app.css:4 #FFF',
				'assets/src/app/styles/app.css:5 rgba',
				'assets/src/app/styles/app.css:7 hsl',
			]
		);
		assert.deepEqual(
			allowed.map( ( entry ) => entry.reason ),
			[ 'Fixture: example in help text.' ]
		);
		assert.equal( failures.length, 6 );
		assert.ok(
			failures.includes(
				'stale allow-list entry (no longer violated, remove it): assets/src/app/styles/app.css #123456'
			)
		);
		assert.match( failures[ 0 ], /use a WordPress class/ );
	} finally {
		fs.rmSync( root, { recursive: true, force: true } );
	}
} );

test( 'raw colour matching skips anchors, entities and ids', () => {
	assert.deepEqual(
		findRawColours(
			'#fff #a1b2c3d4 rgb( hsla( #/x &#160; a#bad #add-new #12'
		).map( ( hit ) => hit.value ),
		[ '#fff', '#a1b2c3d4', 'rgb', 'hsla' ]
	);
} );

test( 'allow-list entries without a reason are rejected', () => {
	const root = fixture( 'graph' );
	const result = runArchitectureChecks( root, {
		allowlist: {
			crossFeature: [
				{
					from: 'assets/src/app/features/alpha/Page.tsx',
					to: 'assets/src/app/features/beta/internal.ts',
				},
			],
		},
		budgets: { assets: {} },
	} );
	assert.match(
		result.sections[ 0 ].failures.join( '\n' ),
		/crossFeature: entry .* has no reason/
	);
	assert.equal( result.ok, false );
} );

test( 'import cycles in the app are reported, type-only cycles are not', () => {
	const project = new Project( fixture( 'graph' ) );
	const { violations, failures } = checkCycles( project );
	assert.deepEqual(
		violations.map( ( violation ) => violation.files ),
		[
			[
				'assets/src/app/a.ts',
				'assets/src/app/b.ts',
				'assets/src/app/c.ts',
			],
			[ 'assets/src/app/lazy.ts' ],
		]
	);
	assert.ok(
		failures.includes(
			'import cycle: assets/src/app/a.ts -> assets/src/app/b.ts -> assets/src/app/c.ts -> assets/src/app/a.ts'
		),
		failures.join( '\n' )
	);
} );

test( 'features reach other features only through index.ts or public.ts', () => {
	const project = new Project( fixture( 'graph' ) );
	const { violations, failures } = checkFeatureBoundaries( project );
	assert.deepEqual( edges( violations ), [
		'assets/src/app/features/alpha/Page.tsx -> assets/src/app/features/beta/deep/index.ts',
		'assets/src/app/features/alpha/Page.tsx -> assets/src/app/features/beta/internal.ts',
		'assets/src/app/features/alpha/Page.tsx -> assets/src/app/features/beta/types.ts',
	] );
	assert.match(
		failures[ 0 ],
		/use that feature's index\.ts or a public\.ts$/
	);
} );

test( 'test files are not part of the shipped graph', () => {
	const project = new Project( fixture( 'clean' ) );
	assert.ok(
		project.appFiles().every( ( file ) => ! file.endsWith( '.test.tsx' ) )
	);
	assert.deepEqual( checkFeatureBoundaries( project ).violations, [] );
} );

test( 'bundled copies of React or WordPress-provided packages are reported', () => {
	const { failures } = checkDuplicateRuntimes( fixture( 'bundles' ) );
	assert.deepEqual( failures.sort(), [
		"admin-app.asset.php does not depend on the 'react-dom' external",
		'assets/dist/admin/admin-app.js carries its own runtime copy: admin-app.js.LICENSE.txt lists React',
		'assets/dist/admin/admin-app.js carries its own runtime copy: defines React __SECRET_INTERNALS',
		'assets/dist/admin/admin-app.js carries its own runtime copy: defines ReactCurrentOwner',
		'assets/dist/admin/chunks/admin-app-editor.0123456789ab.js carries its own runtime copy: bundles module id of @wordpress/components',
		'assets/dist/admin/chunks/admin-app-editor.0123456789ab.js carries its own runtime copy: bundles react-dom (source map)',
	] );
} );

test( 'budgets fail when exceeded, missing or stale and print a table', () => {
	const root = fixture( 'clean' );
	const { failures, rows } = checkBudgets( root, {
		assets: {
			'admin-app.js': { raw: 10, gzip: 10 },
			'chunks/admin-app-alpha.js': { raw: 100000, gzip: 100000 },
			'chunks/admin-app-gone.js': { raw: 100, gzip: 100 },
		},
	} );
	assert.deepEqual(
		rows.map( ( row ) => [ row[ 0 ], row[ 5 ] ] ),
		[
			[ 'admin-app.js', 'OVER' ],
			[ 'chunks/admin-app-alpha.css', 'NO BUDGET' ],
			[ 'chunks/admin-app-alpha.js', 'ok' ],
		]
	);
	assert.equal( failures.length, 3 );
	assert.match(
		failures[ 0 ],
		/^admin-app\.js exceeds its budget: raw \d+ B > 10 B, gzip \d+ B > 10 B$/
	);
	assert.match(
		failures[ 1 ],
		/^chunks\/admin-app-alpha\.css has no budget/
	);
	assert.match(
		failures[ 2 ],
		/^budget for chunks\/admin-app-gone\.js has no published asset/
	);
} );

test( 'budgets get ~10% headroom, rounded up to 100 bytes', () => {
	const sizes = new Map( [ [ 'admin-app.js', { raw: 1000, gzip: 401 } ] ] );
	assert.deepEqual( budgetsFor( sizes ), {
		'admin-app.js': { raw: 1100, gzip: 500 },
	} );
} );

test( 'asset sizes do not depend on line endings', () => {
	const measure = ( content ) => {
		const root = fs.mkdtempSync(
			path.join( os.tmpdir(), 'ohmylms-arch-' )
		);
		try {
			fs.mkdirSync( path.join( root, 'assets/dist/admin' ), {
				recursive: true,
			} );
			fs.writeFileSync(
				path.join( root, 'assets/dist/admin/admin-app.js' ),
				content
			);
			return measureAssets( root ).sizes.get( 'admin-app.js' );
		} finally {
			fs.rmSync( root, { recursive: true, force: true } );
		}
	};
	const lf = measure( 'a();\nb();\n' );
	const crlf = measure( 'a();\r\nb();\r\n' );
	assert.equal( crlf.raw, lf.raw );
	assert.equal( crlf.gzip, lf.gzip );
} );

test( 'source analysis ignores comments and marks type-only imports', () => {
	const { imports, markers } = analyzeSource(
		'example.ts',
		[
			'// readRuntime() and webpackChunkohmylms in a comment',
			"import type { A } from './a';",
			"import { type B, type C } from './b';",
			"import { D, type E } from './d';",
			"export * from './e';",
			'const lazy = () => import( /* webpackChunkName: "x" */ \'./f\' );',
		].join( '\n' )
	);
	assert.deepEqual( markers, [] );
	assert.deepEqual(
		imports
			.map( ( entry ) => [ entry.specifier, entry.kind, entry.typeOnly ] )
			.sort(),
		[
			[ './a', 'static', true ],
			[ './b', 'static', true ],
			[ './d', 'static', false ],
			[ './e', 'static', false ],
			[ './f', 'dynamic', false ],
		]
	);
} );
