/**
 * Fit every card into the available canvas with a visible margin.
 * @param {Object} points Card positions.
 * @param {number} width  Canvas width.
 * @param {number} height Canvas height.
 * @return {Object} Zoom and pan.
 */
export function fitStudioCanvas( points, width, height ) {
	const values = Object.values( points );
	const left = Math.min( ...values.map( ( point ) => point.x ) );
	const top = Math.min( ...values.map( ( point ) => point.y ) );
	const right = Math.max( ...values.map( ( point ) => point.x + 190 ) );
	const bottom = Math.max( ...values.map( ( point ) => point.y + 134 ) );
	const zoom = Math.max(
		0.2,
		Math.min(
			1,
			( width - 40 ) / ( right - left ),
			( height - 40 ) / ( bottom - top )
		)
	);
	return {
		zoom,
		pan: {
			x: ( width - ( right - left ) * zoom ) / 2 - left * zoom,
			y: ( height - ( bottom - top ) * zoom ) / 2 - top * zoom,
		},
	};
}

/**
 * Change zoom while keeping the world point under the cursor fixed.
 * @param {Object} view   Current camera.
 * @param {number} zoom   Requested scale.
 * @param {Object} anchor Canvas coordinates.
 * @return {Object} Updated camera.
 */
export function zoomStudioCanvas( view, zoom, anchor ) {
	const next = Math.max( 0.2, Math.min( 2, zoom ) );
	return {
		zoom: next,
		pan: {
			x: anchor.x - ( ( anchor.x - view.pan.x ) * next ) / view.zoom,
			y: anchor.y - ( ( anchor.y - view.pan.y ) * next ) / view.zoom,
		},
	};
}
