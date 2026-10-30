import assert from 'node:assert/strict';
import fs from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import test from 'node:test';
import { compileExpression } from '../../assets/src/blocks/interactive-activity/expression.mjs';

const root = path.resolve( import.meta.dirname, '../..' );

const evaluate = ( latex, x, params = {} ) => {
	const result = compileExpression( latex, Object.keys( params ) );
	assert.ok( result.fn, `${ latex }: ${ result.error }` );
	return result.fn( x, params );
};

test( 'evaluates arithmetic with precedence and implicit multiplication', () => {
	assert.equal( evaluate( '2x+3', 4 ), 11 );
	assert.equal( evaluate( '-x^2', 3 ), -9 );
	assert.equal( evaluate( '2x^{2}-x', 3 ), 15 );
	assert.equal( evaluate( '(x+1)(x-1)', 3 ), 8 );
	assert.equal( evaluate( '2^{x+1}', 3 ), 16 );
	// Juxtaposition binds tighter than a typed slash, so this is 6 / (2x).
	assert.equal( evaluate( '6/2x', 3 ), 1 );
} );

test( 'accepts y = and f(x) = prefixes', () => {
	assert.equal( evaluate( 'y=x+1', 2 ), 3 );
	assert.equal( evaluate( 'f(x)=x^2', 3 ), 9 );
} );

test( 'evaluates MathLive fractions, roots and absolute values', () => {
	assert.equal( evaluate( '\\frac{x+1}{2}', 5 ), 3 );
	assert.equal( evaluate( '\\sqrt{x}', 9 ), 3 );
	assert.equal( evaluate( '\\sqrt[3]{x}', -8 ), -2 );
	assert.equal( evaluate( '\\left|x-2\\right|', 0 ), 2 );
	assert.equal( evaluate( '|x|+|x-1|', 0 ), 1 );
} );

test( 'evaluates functions and constants', () => {
	assert.ok( Math.abs( evaluate( '\\sin(\\pi x)', 0.5 ) - 1 ) < 1e-12 );
	assert.ok( Math.abs( evaluate( '\\sin x\\cos x', 1 ) - Math.sin( 1 ) * Math.cos( 1 ) ) < 1e-12 );
	assert.ok( Math.abs( evaluate( '\\sin^{2}x', 1 ) - Math.sin( 1 ) ** 2 ) < 1e-12 );
	assert.ok( Math.abs( evaluate( '\\ln(e^x)', 2 ) - 2 ) < 1e-12 );
	assert.equal( evaluate( '\\log(1000)', 0 ), 3 );
} );

test( 'binds parameters at evaluation time', () => {
	const compiled = compileExpression( 'ax^2+bx+c', [ 'a', 'b', 'c' ] );
	assert.equal( compiled.fn( 2, { a: 1, b: 0, c: 0 } ), 4 );
	assert.equal( compiled.fn( 2, { a: 2, b: 1, c: -1 } ), 9 );
} );

test( 'reports errors without throwing', () => {
	assert.equal( compileExpression( '' ).error, 'empty' );
	assert.match( compileExpression( 'x+' ).error, /Unexpected end/ );
	assert.match( compileExpression( 'a x' ).error, /Unknown symbol "a"/ );
	assert.match( compileExpression( '\\frac{x}{' ).error, /./ );
	assert.match( compileExpression( '\\unknown{x}' ).error, /Unsupported/ );
	assert.match( compileExpression( 'x@' ).error, /Unexpected "@"/ );
	assert.match( compileExpression( 'x'.repeat( 501 ) ).error, /too long/ );
} );

test( 'only the math runtime bundles MathLive; the activity uses its copy', () => {
	const sources = fs
		.readdirSync( path.join( root, 'assets/src' ), { recursive: true } )
		.filter( ( file ) => /\.(?:[cm]?jsx?|tsx?)$/.test( file ) )
		.map( ( file ) =>
			path.join( 'assets/src', file ).split( path.sep ).join( '/' )
		);
	const bundling = sources.filter( ( file ) =>
		/\bfrom\s*['"]mathlive['"]/.test(
			fs.readFileSync( path.join( root, file ), 'utf8' )
		)
	);
	// A second copy defines its own MathfieldElement, which the page's <math-field> rejects.
	assert.deepEqual( bundling, [ 'assets/src/features/math/runtime.mjs' ] );
	assert.match(
		fs.readFileSync(
			path.join( root, 'assets/src/features/math/runtime.mjs' ),
			'utf8'
		),
		/window\.OhMyLMSMath = \{[^}]*\bMathLive,/
	);
	for ( const file of [ 'function-graph.mjs', 'index.jsx' ] ) {
		assert.match(
			fs.readFileSync(
				path.join(
					root,
					'assets/src/blocks/interactive-activity',
					file
				),
				'utf8'
			),
			/from 'ohmylms\/mathlive'/
		);
	}
	const config = createRequire( import.meta.url )(
		'../../webpack.config.cjs'
	);
	const { options } = config.plugins.find(
		( plugin ) =>
			plugin.constructor.name === 'DependencyExtractionWebpackPlugin'
	);
	assert.deepEqual( options.requestToExternal( 'ohmylms/mathlive' ), [
		'OhMyLMSMath',
		'MathLive',
	] );
	assert.equal(
		options.requestToHandle( 'ohmylms/mathlive' ),
		'ohmylms-mathlive'
	);
	assert.equal( options.requestToExternal( 'mathlive' ), undefined );
	assert.equal( options.requestToExternal( 'jsxgraph' ), 'JXG' );
} );
