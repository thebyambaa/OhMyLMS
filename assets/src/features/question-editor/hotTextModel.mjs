/**
 * Hot text model: a passage where options are marked [[id]] and listed in tokens.
 * Legacy questions only have tokens; they read as a passage of the tokens in order.
 */
/** @typedef {{ id: string, text: string }} HotTextToken */
/** @typedef {{ passage?: string, tokens?: HotTextToken[], correct?: string[], scoring?: string, [key: string]: unknown }} HotTextSettings */

export const HOT_TEXT_MARK = /\[\[([A-Za-z0-9_-]+)\]\]/g;

/**
 * Normalise settings to the passage form (migrate legacy token lists on read).
 * @param {HotTextSettings} [settings]
 * @return {HotTextSettings & { passage: string, tokens: HotTextToken[], correct: string[], scoring: string }} Normalised settings.
 */
export function hotTextNormalize( settings = {} ) {
	const tokens = Array.isArray( settings.tokens ) ? settings.tokens : [];
	const passage =
		typeof settings.passage === 'string' && settings.passage.trim()
			? settings.passage
			: tokens.map( ( token ) => '[[' + token.id + ']]' ).join( ' ' );
	return {
		...settings,
		passage,
		tokens,
		correct: Array.isArray( settings.correct ) ? settings.correct : [],
		scoring: settings.scoring === 'all' ? 'all' : 'partial',
	};
}

/**
 * The author-facing passage: options written as [[option text]].
 * @param {HotTextSettings} settings
 * @return {string} Authoring passage.
 */
export function hotTextToAuthoring( settings ) {
	const { passage, tokens } = hotTextNormalize( settings );
	const byId = new Map( tokens.map( ( token ) => [ token.id, token.text ] ) );
	return passage.replace(
		HOT_TEXT_MARK,
		( /** @type {string} */ match, /** @type {string} */ id ) =>
			byId.has( id ) ? '[[' + byId.get( id ) + ']]' : match
	);
}

/**
 * Parse the authoring passage back to settings, keeping ids (and correct marks)
 * stable: same text keeps its id, else the option at the same position keeps its id.
 * @param {string}          text       Authoring passage.
 * @param {HotTextSettings} [previous] Previous settings.
 */
export function hotTextFromAuthoring( text, previous = {} ) {
	const old = hotTextNormalize( previous );
	const oldOrder = [ ...old.passage.matchAll( HOT_TEXT_MARK ) ]
		.map( ( match ) => old.tokens.find( ( t ) => t.id === match[ 1 ] ) )
		.filter(
			/**
			 * @param  t
			 * @return {t is HotTextToken} Whether the token exists.
			 */ ( t ) => Boolean( t )
		);
	const found = [ ...text.matchAll( /\[\[([\s\S]*?)\]\]/g ) ].map(
		( match ) => match[ 1 ]
	);
	/** @type {Set<string>} */
	const used = new Set();
	/** @type {Array<string|null>} */
	const ids = found.map( ( optionText ) => {
		const same = oldOrder.find(
			( t ) => t.text === optionText && ! used.has( t.id )
		);
		if ( same ) {
			used.add( same.id );
			return same.id;
		}
		return null;
	} );
	ids.forEach( ( id, index ) => {
		if ( id ) {
			return;
		}
		const atPosition = oldOrder[ index ];
		if ( atPosition && ! used.has( atPosition.id ) ) {
			used.add( atPosition.id );
			ids[ index ] = atPosition.id;
		}
	} );
	let next =
		Math.max(
			0,
			...old.tokens.map(
				( t ) => Number( String( t.id ).replace( /\D/g, '' ) ) || 0
			)
		) + 1;
	ids.forEach( ( id, index ) => {
		if ( ! id ) {
			while ( used.has( 't' + next ) ) {
				next++;
			}
			ids[ index ] = 't' + next;
			used.add( ids[ index ] );
		}
	} );
	let position = 0;
	const passage = text.replace(
		/\[\[([\s\S]*?)\]\]/g,
		() => '[[' + ids[ position++ ] + ']]'
	);
	const tokens = found.map( ( optionText, index ) => ( {
		id: ids[ index ],
		text: optionText,
	} ) );
	return {
		passage,
		tokens,
		correct: old.correct.filter( ( id ) => used.has( id ) ),
		scoring: old.scoring,
	};
}

/**
 * Split a passage into text and option parts for rendering.
 * @param {HotTextSettings} settings
 * @return {Array<{ text?: string, token?: HotTextToken }>} Parts.
 */
export function hotTextParts( settings ) {
	const { passage, tokens } = hotTextNormalize( settings );
	const byId = new Map( tokens.map( ( token ) => [ token.id, token ] ) );
	/** @type {Array<{ text?: string, token?: HotTextToken }>} */
	const parts = [];
	let last = 0;
	for ( const match of passage.matchAll( HOT_TEXT_MARK ) ) {
		if ( ( match.index ?? 0 ) > last ) {
			parts.push( { text: passage.slice( last, match.index ) } );
		}
		parts.push(
			byId.has( match[ 1 ] )
				? { token: byId.get( match[ 1 ] ) }
				: { text: match[ 0 ] }
		);
		last = ( match.index ?? 0 ) + match[ 0 ].length;
	}
	if ( last < passage.length ) {
		parts.push( { text: passage.slice( last ) } );
	}
	return parts;
}

/**
 * Partial credit: (correct picks - wrong picks) / total correct, floored at 0.
 * @param {string[]} picked
 * @param {string[]} correct
 * @param {string}   [scoring]
 */
export function hotTextScore( picked, correct, scoring = 'partial' ) {
	const key = new Set( correct );
	const hits = picked.filter( ( id ) => key.has( id ) ).length;
	const wrong = picked.length - hits;
	const exact = hits === key.size && wrong === 0;
	if ( scoring === 'all' ) {
		return exact ? 1 : 0;
	}
	return key.size ? Math.max( 0, ( hits - wrong ) / key.size ) : 0;
}
