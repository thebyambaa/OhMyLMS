/**
 * Add `value` to `target` under `key` when `value` is truthy.
 *
 * @param {Object}  target Style object being built.
 * @param {string}  key    CSS property name.
 * @param {unknown} value  Candidate value.
 * @return {Object} The same target object.
 */
function setIfTruthy( target, key, value ) {
	if ( value ) {
		target[ key ] = value;
	}
	return target;
}

/**
 * Build the inline style of a FlexItem. Later values win, `style` last.
 *
 * @param {Object}        props
 * @param {boolean}       [props.fullWidth]  Width 100%.
 * @param {boolean}       [props.fullHeight] Height 100%.
 * @param {string}        [props.flex]       CSS flex shorthand.
 * @param {string|number} [props.width]      Width.
 * @param {string|number} [props.minWidth]   Minimum width.
 * @param {string|number} [props.maxWidth]   Maximum width.
 * @param {Object}        [props.style]      Style that overrides the rest.
 * @return {Object} Inline style.
 */
export function getFlexItemStyle( {
	fullWidth,
	fullHeight,
	flex,
	width,
	minWidth,
	maxWidth,
	style,
} ) {
	return {
		...( fullWidth ? { width: '100%' } : {} ),
		...( fullHeight ? { height: '100%' } : {} ),
		...( flex ? { flex } : {} ),
		...( width ? { width } : {} ),
		...( minWidth ? { minWidth } : {} ),
		...( maxWidth ? { maxWidth } : {} ),
		...style,
	};
}

/**
 * Build the inline style of a Card. Later values win, `style` last.
 *
 * @param {Object}        props
 * @param {boolean}       [props.fullHeight]   Minimum height 100%.
 * @param {boolean}       [props.fullWidth]    Minimum width 100%.
 * @param {string}        [props.cursor]       Cursor.
 * @param {string|number} [props.padding]      Padding.
 * @param {string|number} [props.margin]       Margin.
 * @param {string|number} [props.width]        Width.
 * @param {string|number} [props.height]       Height.
 * @param {string|number} [props.minHeight]    Minimum height.
 * @param {string|number} [props.borderRadius] Border radius.
 * @param {Object}        [props.style]        Style that overrides the rest.
 * @return {Object} Inline style.
 */
export function getCardStyle( {
	fullHeight,
	fullWidth,
	cursor,
	padding,
	margin,
	width,
	height,
	minHeight,
	borderRadius,
	style,
} ) {
	return {
		...( fullHeight ? { minHeight: '100%' } : {} ),
		...( fullWidth ? { minWidth: '100%' } : {} ),
		...( cursor ? { cursor } : {} ),
		...( padding ? { padding } : {} ),
		...( margin ? { margin } : {} ),
		...( width ? { width } : {} ),
		...( height ? { height } : {} ),
		...( minHeight ? { minHeight } : {} ),
		...( borderRadius ? { borderRadius } : {} ),
		...style,
	};
}

/**
 * Build the inline style of a Surface.
 *
 * @param {Object}        props
 * @param {boolean}       [props.fullHeight] Minimum height 100%.
 * @param {string|number} [props.minHeight]  Minimum height, beats fullHeight.
 * @param {Object}        [props.style]      Style that overrides the rest.
 * @return {Object} Inline style.
 */
export function getSurfaceStyle( { fullHeight, minHeight, style } ) {
	return {
		...( fullHeight ? { minHeight: '100%' } : {} ),
		...( minHeight ? { minHeight } : {} ),
		...style,
	};
}

/**
 * Build the inline style of a Divider.
 *
 * @param {string} [color] Divider colour, defaults to #f4f5f7.
 * @param {Object} [style] Style that overrides the colour.
 * @return {Object} Inline style.
 */
export function getDividerStyle( color, style ) {
	return { color: color || '#f4f5f7', ...style };
}

/**
 * Build the class list of a Badge.
 *
 * @param {string}  variant      Visual variant.
 * @param {boolean} isBorderLess Remove the border.
 * @param {boolean} isRounded    Use rounded corners.
 * @return {string} Class attribute.
 */
export function getBadgeClassName( variant, isBorderLess, isRounded ) {
	return [
		'ohmylms-badge',
		'ohmylms-badge-' + variant,
		isBorderLess && 'ohmylms-badge-borderless',
		isRounded && 'ohmylms-badge-rounded',
	]
		.filter( Boolean )
		.join( ' ' );
}

/**
 * Build the inline style of a Badge from its spacing shorthands.
 *
 * Specific sides beat axes, which beat the shorthand; `style` wins over all.
 *
 * @param {Object} props Badge props (padding*, margin*, width, height, cursor,
 *                       textTransform, color, style).
 * @return {Object} Inline style.
 */
export function getBadgeStyle( props ) {
	const {
		padding,
		paddingX,
		paddingY,
		paddingTop,
		paddingBottom,
		paddingLeft,
		paddingRight,
		margin,
		marginX,
		marginY,
		marginTop,
		marginBottom,
		marginLeft,
		marginRight,
		width,
		height,
		cursor,
		textTransform,
		color,
		style,
	} = props;
	let result = {};

	setIfTruthy( result, 'padding', padding );
	if ( paddingX ) {
		result.paddingLeft = paddingX;
		result.paddingRight = paddingX;
	}
	if ( paddingY ) {
		result.paddingTop = paddingY;
		result.paddingBottom = paddingY;
	}
	setIfTruthy( result, 'paddingTop', paddingTop );
	setIfTruthy( result, 'paddingBottom', paddingBottom );
	setIfTruthy( result, 'paddingLeft', paddingLeft );
	setIfTruthy( result, 'paddingRight', paddingRight );
	setIfTruthy( result, 'width', width );
	setIfTruthy( result, 'height', height );
	setIfTruthy( result, 'cursor', cursor );
	setIfTruthy( result, 'margin', margin );
	if ( marginX ) {
		result.marginLeft = marginX;
		result.marginRight = marginX;
	}
	if ( marginY ) {
		result.marginTop = marginY;
		result.marginBottom = marginY;
	}
	setIfTruthy( result, 'marginTop', marginTop );
	setIfTruthy( result, 'marginBottom', marginBottom );
	setIfTruthy( result, 'marginLeft', marginLeft );
	setIfTruthy( result, 'marginRight', marginRight );
	setIfTruthy( result, 'textTransform', textTransform );
	setIfTruthy( result, 'color', color );
	if ( style ) {
		result = { ...result, ...style };
	}
	return result;
}

/**
 * Build the class attribute of a Tag.
 *
 * @param {string} [className=''] Extra classes.
 * @return {string} Class attribute (keeps the trailing space when empty).
 */
export function getTagClassName( className = '' ) {
	return 'ohmylms-tag ' + className;
}
