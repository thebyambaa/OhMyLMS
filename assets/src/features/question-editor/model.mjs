/**
 * Reorder without mutating the store's option objects.
 * @param options
 * @param from
 * @param to
 */
export function moveOption( options, from, to ) {
	if (
		! Number.isInteger( from ) ||
		! Number.isInteger( to ) ||
		from < 0 ||
		to < 0 ||
		from >= options.length ||
		to >= options.length
	) {
		return options;
	}
	const reordered = [ ...options ];
	const [ moved ] = reordered.splice( from, 1 );
	reordered.splice( to, 0, moved );
	return reordered.map( ( option, index ) => ( {
		...option,
		order_number: index + 1,
	} ) );
}
