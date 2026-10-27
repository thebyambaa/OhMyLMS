import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { mathsPreset } from '../../assets/src/features/question-editor/mathsGenerator.mjs';

test( 'maths generator rejects invalid ranges and unsupported topics', () => {
	for ( const [ min, max ] of [
		[ 0, 9 ],
		[ 9, 2 ],
		[ 1, 1001 ],
		[ 1.5, 9 ],
		[ NaN, 9 ],
		[ 1, Infinity ],
	] )
		assert.equal( mathsPreset( 'addition', min, max ), null );
	assert.equal( mathsPreset( 'unknown', 1, 10 ), null );
} );

test( 'maths presets use the shared private variable and answer contract', () => {
	const fixtures = JSON.parse(
		fs.readFileSync( 'tests/fixtures/maths-generator.json', 'utf8' )
	);
	for ( const [ kind, fixture ] of Object.entries( fixtures ) ) {
		const preset = mathsPreset( kind, 2, 9 );
		assert.deepEqual( JSON.parse( JSON.stringify( preset ) ), fixture );
		assert.equal( preset.settings.type, 'numerical' );
		assert.equal( preset.settings.template.set[ 0 ].path, 'answer' );
		assert.equal( preset.settings.answers, undefined );
		assert.ok(
			preset.settings.template.variables.every(
				( variable ) =>
					variable.type === 'expr' ||
					variable.min > 0 ||
					kind.startsWith( 'quadratic' ) ||
					[ 'discriminant', 'gradient', 'y-intercept', 'midpoint' ].includes(
						kind
					)
			)
		);
	}
} );

test( 'new activity modes have both server compatibility and Gutenberg controls', () => {
	const php = fs.readFileSync(
		'includes/Practice/ActivityModes.php',
		'utf8'
	);
	const jsx = fs.readFileSync(
		'assets/src/blocks/question-player/index.jsx',
		'utf8'
	);
	const runtime = fs.readFileSync(
		'assets/interactivity/game-player.mjs',
		'utf8'
	);
	const modes = [
		...php.matchAll(
			/'([a-z-]+)'\s*=>\s*'(?:deck|match|choice|sort|magnets)'/g
		),
	].map( ( match ) => match[ 1 ] );
	assert.equal( modes.length, 16 );
	for ( const mode of modes ) {
		assert.ok( jsx.includes( `'${ mode }'` ) );
		assert.ok( runtime.includes( mode ) );
	}
} );

test( 'pythagorean and quadratic presets build the answer from the construction', () => {
	const triple = mathsPreset( 'pythagorean', 2, 9 ).settings.template;
	assert.deepEqual( triple.constraints, [ 'm>n', 'gcd(m,n)=1', 'mod(m-n,2)=1' ] );
	assert.equal( mathsPreset( 'pythagorean', 5, 5 ), null );
	const quadratic = mathsPreset( 'quadratic', 2, 9 ).settings.template;
	assert.ok( quadratic.constraints.includes( 'p!=q' ) );
	assert.ok( quadratic.constraints.includes( 'abs(p)>=2' ) );
	assert.equal( quadratic.set[ 0 ].expr, 'max(p,q)' );
} );

test( 'every number field of the visual question editors accepts a {{variable}}', () => {
	const jsx = fs.readFileSync(
		'assets/src/features/question-editor/VisualEditors.jsx',
		'utf8'
	);
	assert.ok( ! /\.\.\.number\(/.test( jsx ), 'a plain number-only input is back' );
	assert.ok( ( jsx.match( /\.\.\.numberOrFormula\(/g ) || [] ).length >= 20 );
	// The live preview must not show a variable as a value.
	assert.ok( jsx.includes( "typeof settings.values?.[ category.id ] === 'number'" ) );
} );

test( 'repeated-root, discriminant, missing-side and simultaneous presets are built from their construction', () => {
	const repeated = mathsPreset( 'quadratic-repeated', 2, 9 ).settings.template;
	assert.deepEqual( repeated.variables.slice( 1 ).map( ( v ) => v.expr ), [ '-2*p', 'p*p' ] );
	assert.equal( repeated.set[ 0 ].expr, 'p' );
	assert.equal( mathsPreset( 'discriminant', 2, 9 ).settings.template.set[ 0 ].expr, 'b*b-4*a*c' );
	assert.equal( mathsPreset( 'pythagorean-leg', 2, 9 ).settings.template.set[ 0 ].expr, 'b' );
	assert.equal( mathsPreset( 'pythagorean-leg', 4, 4 ), null );
	const simultaneous = mathsPreset( 'simultaneous', 2, 9 ).settings.template;
	assert.deepEqual( simultaneous.constraints, [ 'a*d-b*c!=0' ] );
	assert.equal( simultaneous.set[ 0 ].expr, 'x' );
} );

test( 'topic presets are built from the answer and refuse ranges that are too small', () => {
	const kinds = [
		'equivalent-fraction',
		'simplify-fraction',
		'unlike-fractions',
		'percent-increase',
		'percent-decrease',
		'reverse-percentage',
		'simple-interest',
		'gradient',
		'y-intercept',
		'midpoint',
		'share-ratio',
		'speed',
	];
	for ( const kind of kinds ) {
		const preset = mathsPreset( kind, 2, 9 );
		assert.ok( preset, kind );
		assert.ok( preset.prompt.includes( '{{' ), kind );
		assert.equal( preset.settings.template.set[ 0 ].path, 'answer' );
	}
	for ( const kind of [ 'equivalent-fraction', 'simplify-fraction', 'share-ratio' ] ) {
		assert.equal( mathsPreset( kind, 5, 5 ), null, kind );
	}
	assert.equal( mathsPreset( 'unlike-fractions', 2, 3 ), null );
	assert.ok( mathsPreset( 'unlike-fractions', 2, 4 ) );
	assert.ok( mathsPreset( 'speed', 5, 5 ) );
} );
