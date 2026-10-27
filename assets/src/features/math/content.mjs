/** Explicit, versioned markers survive the existing HTML and plain-text content models. */
export const equationPattern = () =>
	/\[\[ohmylms-math:latex:(inline|display)\]\]([\s\S]{1,2000}?)\[\[\/ohmylms-math\]\]/g;

/**
 * Read explicit markers and, in question regions, conventional LaTeX delimiters.
 * Escaped dollars and ordinary currency prose remain text.
 * @param {string}  text      Source text.
 * @param {boolean} delimited Whether the region opts in to LaTeX delimiters.
 * @return {Array} Source-preserving equation ranges.
 */
export function contentEquations( text, delimited = false ) {
	const pattern = delimited
		? /\[\[ohmylms-math:latex:(inline|display)\]\]([\s\S]{1,2000}?)\[\[\/ohmylms-math\]\]|(?<!\\)\$\$([\s\S]{1,2000}?)\$\$|\\\[([\s\S]{1,2000}?)\\\]|\\\(([^\n]{1,2000}?)\\\)|(?<![\\$])\$(?![\s$])((?:\\.|[^$\n]){1,2000}?)(?<!\s)\$(?!\$)/g
		: equationPattern();
	return [ ...String( text ).matchAll( pattern ) ].map( ( match ) => ( {
		index: match.index,
		raw: match[ 0 ],
		mode: match[ 1 ] || ( match[ 3 ] || match[ 4 ] ? 'display' : 'inline' ),
		source:
			match[ 2 ] ?? match[ 3 ] ?? match[ 4 ] ?? match[ 5 ] ?? match[ 6 ],
	} ) );
}

/**
 * Check presentation source without weakening WordPress HTML sanitization.
 * @param {string} source Source.
 * @return {boolean} Safe presentation source.
 */
export const safeMathSource = ( source ) =>
	source.length <= 2000 &&
	! /\\(?:href|htmlData|class|style|cssId|htmlId|htmlStyle|url|includegraphics|def|newcommand)\b/.test(
		source
	);

/**
 * Preserve the author's source, without evaluating or canonicalizing it.
 * @param {string} latex Source.
 * @param {string} mode  Layout.
 * @return {string} Content marker.
 */
export function equationMarker( latex, mode = 'inline' ) {
	if ( ! latex || latex.length > 2000 || latex.includes( '[[/' ) ) {
		throw new Error( 'Invalid equation source.' );
	}
	return `[[ohmylms-math:latex:${ mode === 'display' ? 'display' : 'inline' }]]${ latex }[[/ohmylms-math]]`;
}

/**
 * Recognize double braces typed in MathLive without changing other LaTeX.
 * @param {string} source MathLive source.
 * @return {string} Source with canonical randomization tokens.
 */
export function normalizeTemplateTokens( source ) {
	return String( source ).replace(
		/(?:\\left\s*)?\\(?:lbrace|\{)\s*(?:\\left\s*)?\\(?:lbrace|\{)\s*([^{}\\]{1,240}?)\s*(?:\\right\s*)?\\(?:rbrace|\})\s*(?:\\right\s*)?\\(?:rbrace|\})/g,
		( match, body ) => `{{${ body.trim() }}}`
	);
}

/**
 * Show stored randomization tokens as locked MathLive prompts.
 * @param {string} source Original template source.
 * @return {Object} LaTeX and a reversible token map.
 */
export function templateLatex( source ) {
	const tokens = [];
	const latex = normalizeTemplateTokens( source ).replace(
		/\{\{([^{}]{1,240})\}\}/g,
		( token, body ) => {
			const id = tokens.length;
			tokens.push( token );
			return `\\placeholder[omlvar${ id }]{${ body.replace( /[^a-z0-9+*/().:-]/gi, '' ) }}`;
		}
	);
	return { latex, tokens };
}

/**
 * Restore the randomization tokens, never the current preview value.
 * @param {string} latex  MathLive source.
 * @param {Array}  tokens Original tokens.
 * @return {string} Stored source.
 */
export function restoreTemplateTokens( latex, tokens ) {
	return normalizeTemplateTokens( latex ).replace(
		/\\placeholder\[omlvar(\d+)\](?:\[locked\])?\{[^{}]*\}/g,
		( match, id ) => tokens[ Number( id ) ] || match
	);
}
