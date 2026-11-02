/**
 * Crop rectangles on a rendered page, as `[left, top, right, bottom]` fractions of the visible page
 * from its top-left corner: the `bounds` of `POST /exam-imports/jobs/{id}/figures` and of figure
 * placements (`rotated-cropbox-normalized-top-left`).
 */

export type Bounds = [ number, number, number, number ];

export interface Point {
	x: number;
	y: number;
}

/** Smallest side of a crop, as a fraction of the page (0.5 %). */
export const MIN_SIDE = 0.005;

/** Keyboard step: 1 % of the page, or 0.1 % with Alt. */
export const STEP = 0.01;
export const FINE_STEP = 0.001;

/** A starting selection when a row has no figure yet: the upper half, inset. */
export const DEFAULT_BOUNDS: Bounds = [ 0.1, 0.1, 0.9, 0.5 ];

function clamp( value: number ): number {
	return Math.min( 1, Math.max( 0, value ) );
}

/**
 * Round to the six decimals the server keeps (`PageRenderer::bounds()`).
 *
 * @param value Fraction.
 * @return Rounded fraction.
 */
export function round6( value: number ): number {
	return Math.round( value * 1e6 ) / 1e6;
}

/**
 * Whether a value is a valid crop: four finite fractions with left < right and top < bottom.
 *
 * @param value Anything.
 * @return True for valid bounds.
 */
export function isBounds( value: unknown ): value is Bounds {
	if ( ! Array.isArray( value ) || 4 !== value.length ) {
		return false;
	}
	if (
		! value.every(
			( item ) =>
				'number' === typeof item &&
				Number.isFinite( item ) &&
				item >= 0 &&
				item <= 1
		)
	) {
		return false;
	}
	const [ left, top, right, bottom ] = value as Bounds;
	return left < right && top < bottom;
}

/**
 * The point of a pointer event inside a box, as page fractions.
 *
 * @param rect        Box of the page image on screen.
 * @param rect.left   Left edge.
 * @param rect.top    Top edge.
 * @param rect.width  Width.
 * @param rect.height Height.
 * @param clientX     Pointer x.
 * @param clientY     Pointer y.
 * @return The point, clamped to the page.
 */
export function pointIn(
	rect: { left: number; top: number; width: number; height: number },
	clientX: number,
	clientY: number
): Point {
	if ( rect.width <= 0 || rect.height <= 0 ) {
		return { x: 0, y: 0 };
	}
	return {
		x: clamp( ( clientX - rect.left ) / rect.width ),
		y: clamp( ( clientY - rect.top ) / rect.height ),
	};
}

/**
 * The rectangle spanned by a drag, whichever way it went, or null while it is too small to be a crop.
 *
 * @param start Where the drag started.
 * @param end   Where the pointer is.
 * @return Bounds or null.
 */
export function fromDrag( start: Point, end: Point ): Bounds | null {
	const bounds: Bounds = [
		round6( clamp( Math.min( start.x, end.x ) ) ),
		round6( clamp( Math.min( start.y, end.y ) ) ),
		round6( clamp( Math.max( start.x, end.x ) ) ),
		round6( clamp( Math.max( start.y, end.y ) ) ),
	];
	return bounds[ 2 ] - bounds[ 0 ] >= MIN_SIDE &&
		bounds[ 3 ] - bounds[ 1 ] >= MIN_SIDE
		? bounds
		: null;
}

/**
 * Move a crop by a vector, keeping its size and keeping it on the page.
 *
 * @param bounds Crop.
 * @param dx     Horizontal shift.
 * @param dy     Vertical shift.
 * @return Moved crop.
 */
export function moveBy( bounds: Bounds, dx: number, dy: number ): Bounds {
	const width = bounds[ 2 ] - bounds[ 0 ];
	const height = bounds[ 3 ] - bounds[ 1 ];
	const left = Math.min( 1 - width, Math.max( 0, bounds[ 0 ] + dx ) );
	const top = Math.min( 1 - height, Math.max( 0, bounds[ 1 ] + dy ) );
	return [
		round6( left ),
		round6( top ),
		round6( left + width ),
		round6( top + height ),
	];
}

/**
 * Grow or shrink a crop from its right and bottom edges, keeping the minimum size.
 *
 * @param bounds Crop.
 * @param dx     Width change.
 * @param dy     Height change.
 * @return Resized crop.
 */
export function resizeBy( bounds: Bounds, dx: number, dy: number ): Bounds {
	return [
		bounds[ 0 ],
		bounds[ 1 ],
		round6(
			Math.min( 1, Math.max( bounds[ 0 ] + MIN_SIDE, bounds[ 2 ] + dx ) )
		),
		round6(
			Math.min( 1, Math.max( bounds[ 1 ] + MIN_SIDE, bounds[ 3 ] + dy ) )
		),
	];
}

const ARROWS: Record< string, [ number, number ] > = {
	ArrowLeft: [ -1, 0 ],
	ArrowRight: [ 1, 0 ],
	ArrowUp: [ 0, -1 ],
	ArrowDown: [ 0, 1 ],
};

/**
 * Keyboard alternative to dragging: arrows move the crop, Shift + arrows resize it, Alt makes the
 * step finer.
 *
 * @param bounds        Crop.
 * @param key           `KeyboardEvent.key`.
 * @param keys          Modifier keys.
 * @param keys.shiftKey Resize instead of move.
 * @param keys.altKey   Fine step.
 * @return The new crop, or null for other keys.
 */
export function nudge(
	bounds: Bounds,
	key: string,
	{
		shiftKey = false,
		altKey = false,
	}: { shiftKey?: boolean; altKey?: boolean }
): Bounds | null {
	const arrow = ARROWS[ key ];
	if ( ! arrow ) {
		return null;
	}
	const step = altKey ? FINE_STEP : STEP;
	return shiftKey
		? resizeBy( bounds, arrow[ 0 ] * step, arrow[ 1 ] * step )
		: moveBy( bounds, arrow[ 0 ] * step, arrow[ 1 ] * step );
}

/**
 * Bounds as percentages with at most two decimals, for the numeric fields.
 *
 * @param bounds Crop.
 * @return Left, top, right, bottom as text.
 */
export function toPercentText(
	bounds: Bounds
): [ string, string, string, string ] {
	return bounds.map( ( value ) =>
		String( Math.round( value * 10000 ) / 100 )
	) as [ string, string, string, string ];
}

/**
 * Bounds typed as percentages, or null when they are not numbers from 0 to 100 with left before right
 * and top before bottom.
 *
 * @param values Left, top, right, bottom as typed.
 * @return Bounds or null.
 */
export function fromPercentText(
	values: readonly [ string, string, string, string ]
): Bounds | null {
	if (
		! values.every( ( value ) =>
			/^\s*\d{1,3}(?:[.,]\d+)?\s*$/.test( value )
		)
	) {
		return null;
	}
	const bounds = values.map( ( value ) =>
		round6( Number( value.trim().replace( ',', '.' ) ) / 100 )
	);
	return isBounds( bounds ) ? bounds : null;
}

/**
 * Whether two crops are the same to the precision the server keeps.
 *
 * @param a First crop.
 * @param b Second crop.
 * @return True when equal.
 */
export function sameBounds(
	a: readonly number[] | undefined,
	b: readonly number[] | undefined
): boolean {
	return (
		!! a &&
		!! b &&
		4 === a.length &&
		4 === b.length &&
		a.every( ( value, index ) => round6( value ) === round6( b[ index ]! ) )
	);
}

/**
 * CSS position of a crop over the page image, in percent.
 *
 * @param bounds Crop.
 * @return Left, top, width and height.
 */
export function boxStyle( bounds: readonly number[] ): {
	left: string;
	top: string;
	width: string;
	height: string;
} {
	const [ left = 0, top = 0, right = 1, bottom = 1 ] = bounds;
	return {
		left: `${ left * 100 }%`,
		top: `${ top * 100 }%`,
		width: `${ ( right - left ) * 100 }%`,
		height: `${ ( bottom - top ) * 100 }%`,
	};
}
