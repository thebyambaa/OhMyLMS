/**
 * Whether a value is a complete date range: a two-item array whose items both parse as dates.
 *
 * The date library is passed in because the recovered application owns the single Day.js
 * instance the pickers return their values from.
 *
 * @param {unknown}                                      value Candidate range, e.g. `[ '2024-01-01', '2024-01-31' ]`.
 * @param {(value: unknown) => {isValid: () => boolean}} parse Date parser returning an object with `isValid()`, e.g. Day.js.
 * @return {boolean} True when both ends are valid dates.
 */
export function isValidDateRange( value, parse ) {
	return (
		Array.isArray( value ) &&
		2 === value.length &&
		parse( value[ 0 ] ).isValid() &&
		parse( value[ 1 ] ).isValid()
	);
}
