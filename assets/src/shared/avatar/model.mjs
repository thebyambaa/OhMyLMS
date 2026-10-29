/**
 * Up to two upper-case initials from a display name.
 *
 * @param {string} alt Display name.
 * @return {string} Initials, or an empty string.
 */
export function getInitials( alt ) {
	if ( ! alt ) {
		return '';
	}
	return alt
		.trim()
		.split( ' ' )
		.map( ( word ) => word[ 0 ] )
		.join( '' )
		.substring( 0, 2 )
		.toUpperCase();
}

/**
 * Border radius for a shape; only circles are rounded.
 *
 * @param {string} shape Avatar shape.
 * @return {string} CSS border radius.
 */
export function getBorderRadius( shape ) {
	return 'circle' === shape ? '50%' : '0';
}

/**
 * Inline style of the image.
 *
 * @param {string} shape Avatar shape.
 * @param {Object} style Caller overrides.
 * @return {Object} Style object.
 */
export function getImageStyle( shape, style ) {
	return { borderRadius: getBorderRadius( shape ), ...style };
}

/**
 * Inline style of the initials fallback.
 *
 * @param {string|number} size  Avatar size in pixels.
 * @param {string}        shape Avatar shape.
 * @param {Object}        style Caller overrides.
 * @return {Object} Style object.
 */
export function getFallbackStyle( size, shape, style ) {
	return {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		width: size + 'px',
		height: size + 'px',
		fontSize: Math.floor( size / 2.5 ) + 'px',
		fontWeight: 600,
		background: '#e0e0e0',
		color: '#555',
		borderRadius: getBorderRadius( shape ),
		...style,
	};
}
