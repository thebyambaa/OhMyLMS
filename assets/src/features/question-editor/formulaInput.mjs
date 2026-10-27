/**
 * Number fields that also accept a variable or formula such as {{a*b}}, for randomized questions.
 * A number is stored as a number; a formula (or a number still being typed) as text, which the
 * server turns into a number when it issues the question.
 */

/**
 * Props for a text input holding a number or a {{formula}}.
 *
 * @param {number|string|undefined}                  value    Stored value.
 * @param {(value: number|string|undefined) => void} onChange Receives a number, a formula string, or undefined when empty.
 * @param {Object}                                   extra    Extra input props, spread last.
 * @return {Object} Input props.
 */
export const numberOrFormula = ( value, onChange, extra = {} ) => ( {
	type: 'text',
	inputMode: 'decimal',
	value: value ?? '',
	onChange: ( text ) => {
		if ( text === '' ) {
			onChange( undefined );
		} else if ( text.includes( '{' ) ) {
			onChange( text );
		} else if ( /^-?\d*\.?\d*$/.test( text ) ) {
			onChange(
				String( Number( text ) ) === text ? Number( text ) : text
			);
		}
	},
	...extra,
} );

/**
 * A tolerance: never negative when it is a number; a formula is kept as typed.
 *
 * @param {number|string|undefined}                  value    Stored value.
 * @param {(value: number|string|undefined) => void} onChange Receives a number, a formula string, or 0 when empty.
 * @param {Object}                                   extra    Extra input props.
 * @return {Object} Input props.
 */
export const toleranceOrFormula = ( value, onChange, extra = {} ) =>
	numberOrFormula(
		value ?? 0,
		( next ) => {
			if ( next === undefined ) {
				onChange( 0 );
			} else if ( typeof next === 'number' ) {
				onChange( Math.max( 0, next ) );
			} else if ( /^\d*\.?\d*$/.test( next ) || next.includes( '{' ) ) {
				onChange( next );
			}
		},
		extra
	);

/**
 * A comma separated list of accepted answers: numbers and {{formulas}} are kept, other items dropped.
 *
 * @param {string} text Typed list.
 * @return {Array<number|string>|undefined} Items, or undefined when none.
 */
export function listToNumbersOrFormulas( text ) {
	const items = String( text || '' )
		.split( /,(?![^{]*\}\})/ )
		.map( ( item ) => item.trim() )
		.filter(
			( item ) =>
				item !== '' &&
				( /^\{\{.+\}\}$/.test( item ) ||
					Number.isFinite( Number( item ) ) )
		)
		.map( ( item ) => ( item.startsWith( '{' ) ? item : Number( item ) ) );
	return items.length ? items : undefined;
}

/**
 * The field text after inserting a variable: it replaces an empty field or a plain number, and is
 * appended to a formula already typed.
 *
 * @param {number|string|undefined} current Current value.
 * @param {string}                  name    Variable name.
 * @return {string} New value.
 */
export function insertVariable( current, name ) {
	const text = String( current ?? '' ).trim();
	const token = `{{${ name }}}`;
	return text === '' || Number.isFinite( Number( text ) )
		? token
		: `${ text }${ token }`;
}

/**
 * A question statement (HTML) after inserting a variable at its end: inside the last paragraph.
 *
 * @param {string} html Current statement.
 * @param {string} name Variable name.
 * @return {string} New statement.
 */
export function insertIntoStatement( html, name ) {
	const token = `{{${ name }}}`;
	const text = String( html ?? '' );
	if ( text.trim() === '' ) {
		return `<p>${ token }</p>`;
	}
	return /<\/p>\s*$/.test( text )
		? text.replace( /<\/p>\s*$/, ` ${ token }</p>` )
		: `${ text } ${ token }`;
}
