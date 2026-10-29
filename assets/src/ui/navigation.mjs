/**
 * Return an enabled tab index for a navigation key, or null for unrelated keys.
 * @param index
 * @param count
 * @param key
 * @param vertical
 * @param rtl
 */
export function tabIndexForKey(
	index,
	count,
	key,
	vertical = false,
	rtl = false
) {
	if ( ! count ) {
		return null;
	}
	if ( key === 'Home' ) {
		return 0;
	}
	if ( key === 'End' ) {
		return count - 1;
	}
	const horizontalForward = rtl ? 'ArrowLeft' : 'ArrowRight';
	const horizontalBackward = rtl ? 'ArrowRight' : 'ArrowLeft';
	const forward = vertical ? 'ArrowDown' : horizontalForward;
	const backward = vertical ? 'ArrowUp' : horizontalBackward;
	if ( key === forward ) {
		return ( index + 1 ) % count;
	}
	if ( key === backward ) {
		return ( index - 1 + count ) % count;
	}
	return null;
}
