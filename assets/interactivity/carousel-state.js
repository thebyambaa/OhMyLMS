/** Pure carousel arithmetic shared by the `ohmylms/carousel` store and its tests. Slides move one at a time. */

/**
 * First slide of the last full view; later starts would leave empty space.
 * @param {number} count   Slides.
 * @param {number} perView Slides fully visible at once.
 */
export function lastIndex( count, perView ) {
	return Math.max( 0, count - Math.max( 1, perView ) );
}

/**
 * Keep an index in range; with `loop` stepping past either end wraps to the other.
 * @param {number}  index
 * @param {number}  count
 * @param {number}  perView
 * @param {boolean} loop
 */
export function clampIndex( index, count, perView, loop = false ) {
	const last = lastIndex( count, perView );
	if ( loop && index > last ) {
		return 0;
	}
	if ( loop && index < 0 ) {
		return last;
	}
	return Math.min( Math.max( index, 0 ), last );
}

/**
 * Slides fully visible in the track.
 * @param {number} trackWidth Track content width.
 * @param {number} slideWidth Width of one slide.
 * @param {number} step       Distance between the starts of two neighbouring slides.
 */
export function visibleCount( trackWidth, slideWidth, step ) {
	if ( ! ( step > 0 ) || ! ( slideWidth > 0 ) ) {
		return 1;
	}
	return Math.max(
		1,
		Math.floor( ( trackWidth - slideWidth ) / step + 1.05 )
	);
}

/**
 * Slide nearest the scroll start. RTL offsets are negative; the end of the track is always the last index.
 * @param {number} offset    Track scrollLeft.
 * @param {number} step      Distance between slide starts.
 * @param {number} maxOffset Largest scroll distance.
 * @param {number} count
 * @param {number} perView
 */
export function indexAt( offset, step, maxOffset, count, perView ) {
	const distance = Math.abs( offset );
	if ( count > 0 && maxOffset > 0 && distance >= maxOffset - 2 ) {
		return lastIndex( count, perView );
	}
	return clampIndex(
		step > 0 ? Math.round( distance / step ) : 0,
		count,
		perView
	);
}

/**
 * Navigation for a key pressed on the track; arrows follow the reading direction.
 * @param {string}  key
 * @param {boolean} rtl
 * @return {'first'|'last'|'prev'|'next'|null} Target, or null for keys the carousel ignores.
 */
export function keyTarget( key, rtl ) {
	switch ( key ) {
		case 'Home':
			return 'first';
		case 'End':
			return 'last';
		case 'ArrowRight':
			return rtl ? 'prev' : 'next';
		case 'ArrowLeft':
			return rtl ? 'next' : 'prev';
		default:
			return null;
	}
}

/**
 * Dot buttons, one per reachable start position; none when everything fits.
 * @param {number} count
 * @param {number} perView
 * @param {number} index   Current start.
 * @param {string} label   Label with `%1$d` (position) and `%2$d` (total).
 */
export function dots( count, perView, index, label ) {
	const total = lastIndex( count, perView ) + 1;
	if ( count <= perView || total < 2 ) {
		return [];
	}
	return Array.from( { length: total }, ( unused, position ) => ( {
		index: position,
		current: position === index,
		label: String( label )
			.replace( '%1$d', String( position + 1 ) )
			.replace( '%2$d', String( total ) ),
	} ) );
}

/**
 * Whether autoplay may advance now: enabled, not paused by the toggle, hover or focus, motion allowed,
 * the page visible and something to scroll.
 * @param {Object}  context      Carousel context.
 * @param {boolean} reduceMotion `prefers-reduced-motion: reduce` matches.
 * @param {boolean} hidden       document.hidden.
 */
export function canAdvance( context, reduceMotion, hidden = false ) {
	return Boolean(
		context.autoplay &&
		context.playing &&
		! context.hovered &&
		! context.focused &&
		! reduceMotion &&
		! hidden &&
		context.count > context.perView
	);
}

/**
 * Next start for autoplay: rewind to the first slide after the last view.
 * @param {number} index
 * @param {number} count
 * @param {number} perView
 */
export function autoplayTarget( index, count, perView ) {
	return index >= lastIndex( count, perView ) ? 0 : index + 1;
}
