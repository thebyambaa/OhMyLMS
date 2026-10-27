/**
 * Update a bank selection without duplicates or more than thirty questions.
 * @param ids
 * @param value
 */
export function addSelection( ids, value ) {
	const id = Number( value );
	return Number.isSafeInteger( id ) && id > 0 && ! ids.includes( id )
		? [ ...ids, id ].slice( 0, 30 )
		: ids;
}

/**
 * Move an explicitly chosen question without changing its identity.
 * @param ids
 * @param index
 * @param offset
 */
export function moveSelection( ids, index, offset ) {
	const next = index + offset;
	if ( next < 0 || next >= ids.length ) {
		return ids;
	}
	const result = [ ...ids ];
	[ result[ index ], result[ next ] ] = [ result[ next ], result[ index ] ];
	return result;
}
