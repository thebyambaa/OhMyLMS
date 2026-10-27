import { LatexSyntax } from '@cortex-js/compute-engine/latex-syntax';

const syntax = new LatexSyntax();

/**
 * Parser errors can be nested inside a MathJSON function or dictionary.
 * @param {Array|Object|string|number|null} value Parsed expression.
 * @return {boolean} Whether the expression contains a syntax error.
 */
function hasSyntaxError( value ) {
	if ( Array.isArray( value ) ) {
		return value[ 0 ] === 'Error' || value.some( hasSyntaxError );
	}
	return (
		value !== null &&
		typeof value === 'object' &&
		Object.values( value ).some( hasSyntaxError )
	);
}

/**
 * Advisory syntax preview only; original LaTeX is never replaced or graded here.
 * @param {string} source Formula.
 * @return {Object} Preview.
 */
export function inspectFormula( source ) {
	if ( source.length > 2000 ) {
		return { valid: false, latex: '' };
	}
	const expression = syntax.parse( source );
	return {
		valid: expression !== null && ! hasSyntaxError( expression ),
		latex: expression === null ? '' : syntax.serialize( expression ),
	};
}
