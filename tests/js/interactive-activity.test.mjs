import assert from 'node:assert/strict';
import test from 'node:test';
import { compileExpression } from '../../assets/src/blocks/interactive-activity/expression.mjs';

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
