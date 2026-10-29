import { createElement, memo } from '@wordpress/element';
import { getBadgeClassName, getBadgeStyle } from './model.mjs';

/**
 * Badge span. Unlisted props, including `className`, are spread last on it.
 *
 * @param {Object}               props
 * @param {string}               [props.variant='default']  Adds `ohmylms-badge-{variant}`.
 * @param {import('react').Node} [props.children]           Badge content.
 * @param {string|number}        [props.padding]            Padding shorthand.
 * @param {string|number}        [props.paddingX]           Left and right padding.
 * @param {string|number}        [props.paddingY]           Top and bottom padding.
 * @param {string|number}        [props.paddingTop]         Top padding.
 * @param {string|number}        [props.paddingBottom]      Bottom padding.
 * @param {string|number}        [props.paddingLeft]        Left padding.
 * @param {string|number}        [props.paddingRight]       Right padding.
 * @param {string|number}        [props.margin]             Margin shorthand.
 * @param {string|number}        [props.marginX]            Left and right margin.
 * @param {string|number}        [props.marginY]            Top and bottom margin.
 * @param {string|number}        [props.marginTop]          Top margin.
 * @param {string|number}        [props.marginBottom]       Bottom margin.
 * @param {string|number}        [props.marginLeft]         Left margin.
 * @param {string|number}        [props.marginRight]        Right margin.
 * @param {string|number}        [props.width]              Width.
 * @param {string|number}        [props.height]             Height.
 * @param {boolean}              [props.isBorderLess=false] Borderless class.
 * @param {boolean}              [props.isRounded=false]    Rounded class.
 * @param {string}               [props.cursor='default']   Cursor.
 * @param {string}               [props.textTransform]      Text transform.
 * @param {string}               [props.color]              Text colour.
 * @param {Object}               [props.style]              Overrides the rest.
 * @return {import('react').ReactElement} Badge markup.
 */
export const BadgeWP = memo( function BadgeWPRender( {
	variant = 'default',
	children,
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
	isBorderLess = false,
	isRounded = false,
	cursor = 'default',
	textTransform,
	color,
	style,
	...rest
} ) {
	return createElement(
		'span',
		{
			className: getBadgeClassName( variant, isBorderLess, isRounded ),
			style: getBadgeStyle( {
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
			} ),
			...rest,
		},
		children
	);
} );
