import test from 'node:test';
import assert from 'node:assert/strict';
import {
	equationMarker,
	equationPattern,
	contentEquations,
	templateLatex,
	restoreTemplateTokens,
	normalizeTemplateTokens,
} from '../../assets/src/features/math/content.mjs';
import { inspectFormula } from '../../assets/src/features/math/teacherCompute.mjs';

test( 'question regions accept LaTeX delimiters without changing currency or source', () => {
	const text = String.raw`Costs $5 and $10. Use $x^2$, $$\frac{1}{2}$$, \(a+b\) and \[\sqrt{y}\]. Escaped \$20.`;
	assert.equal( contentEquations( text ).length, 0 );
	const matches = contentEquations( text, true );
	assert.deepEqual(
		matches.map( ( match ) => match.source ),
		[ 'x^2', String.raw`\frac{1}{2}`, 'a+b', String.raw`\sqrt{y}` ]
	);
	assert.deepEqual(
		matches.map( ( match ) => match.mode ),
		[ 'inline', 'display', 'inline', 'display' ]
	);
	for ( const match of matches ) {
		assert.equal(
			text.slice( match.index, match.index + match.raw.length ),
			match.raw
		);
	}
	assert.equal( contentEquations( equationMarker( '$x$' ), true ).length, 1 );
	assert.equal(
		contentEquations( '$' + 'x'.repeat( 2001 ) + '$', true ).length,
		0
	);
} );

test( 'explicit markers preserve LaTeX, layout and dollar prose', () => {
	const latex = String.raw`\frac{\sqrt{x^{12}+1}}{\frac{1}{2}}`;
	const content = `Costs $5. ${ equationMarker( latex, 'display' ) }`;
	const matches = [ ...content.matchAll( equationPattern() ) ];
	assert.equal( matches.length, 1 );
	assert.equal( matches[ 0 ][ 1 ], 'display' );
	assert.equal( matches[ 0 ][ 2 ], latex );
	assert.equal(
		JSON.parse( JSON.stringify( { content } ) ).content,
		content
	);
	assert.throws( () => equationMarker( 'x'.repeat( 2001 ) ) );
} );

test( 'randomized equation tokens round trip without evaluation', () => {
	const original = String.raw`\frac{{{a}}x+{{b:+}}}{\sqrt{{{a*b}}}}`;
	const { latex, tokens } = templateLatex( original );
	assert.equal( tokens.length, 3 );
	assert.equal( restoreTemplateTokens( latex, tokens ), original );
	assert.equal(
		restoreTemplateTokens( latex.replaceAll( ']{', '][locked]{' ), tokens ),
		original
	);
} );

test( 'pinned Compute Engine provides advisory syntax without replacing source', () => {
	const source = String.raw`\frac{1}{2}+\sqrt{x^2+1}`;
	assert.equal( inspectFormula( source ).valid, true );
	assert.equal( source, String.raw`\frac{1}{2}+\sqrt{x^2+1}` );
	assert.equal( inspectFormula( 'x'.repeat( 2001 ) ).valid, false );
} );

test( 'lightweight syntax inspection detects nested parser errors without grading values', () => {
	assert.equal(
		inspectFormula( String.raw`\sqrt{\frac{1}{2}}` ).valid,
		true
	);
	assert.equal( inspectFormula( String.raw`\frac{1}{0}` ).valid, true );
	assert.equal( inspectFormula( 'x+' ).valid, false );
	assert.equal( inspectFormula( String.raw`\sqrt{` ).valid, false );
	assert.equal( inspectFormula( String.raw`\frac{1}` ).valid, false );
} );

test( 'typed MathLive double braces become tokens without altering other mathematics', () => {
	const source = String.raw`\frac{\left\lbrace\left\lbrace a\right\rbrace\right\rbrace}{\sqrt{x^{2}+1}}`;
	const canonical = String.raw`\frac{{{a}}}{\sqrt{x^{2}+1}}`;
	assert.equal( normalizeTemplateTokens( source ), canonical );
	assert.equal( restoreTemplateTokens( source, [] ), canonical );
	const { latex, tokens } = templateLatex( source );
	assert.deepEqual( tokens, [ '{{a}}' ] );
	assert.equal( restoreTemplateTokens( latex, tokens ), canonical );
	assert.equal(
		normalizeTemplateTokens( String.raw`\{\{a*b:+\}\}` ),
		'{{a*b:+}}'
	);
	assert.equal(
		normalizeTemplateTokens( String.raw`\left\lbrace a\right\rbrace` ),
		String.raw`\left\lbrace a\right\rbrace`
	);
} );
