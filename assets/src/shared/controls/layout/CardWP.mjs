import { createElement, forwardRef, Fragment } from '@wordpress/element';
import { Card } from '@wordpress/components';
import { getCardStyle } from './model.mjs';

/**
 * Card with sizing shorthands and an optional actions row.
 *
 * @param {Object}               props
 * @param {import('react').Node} [props.children]     Card content.
 * @param {import('react').Node} [props.actions]      Rendered in `.ohmylms-card-actions`.
 * @param {boolean}              [props.fullHeight]   Minimum height 100%.
 * @param {boolean}              [props.fullWidth]    Minimum width 100%.
 * @param {string}               [props.cursor]       Cursor.
 * @param {string|number}        [props.padding]      Padding.
 * @param {string|number}        [props.margin]       Margin.
 * @param {string|number}        [props.width]        Width.
 * @param {string|number}        [props.height]       Height.
 * @param {string|number}        [props.minHeight]    Minimum height.
 * @param {string|number}        [props.borderRadius] Border radius.
 * @param {Object}               [props.style]        Overrides the shorthands.
 * @param {import('react').Ref}  ref                  Forwarded to `Card`.
 * @return {import('react').ReactElement} Card element.
 */
export const CardWP = forwardRef( function CardWP(
	{
		children,
		actions,
		fullHeight,
		fullWidth,
		cursor,
		padding,
		margin,
		style,
		width,
		height,
		minHeight,
		borderRadius,
		...rest
	},
	ref
) {
	return createElement(
		Fragment,
		null,
		createElement(
			Card,
			{
				ref,
				style: getCardStyle( {
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
				} ),
				...rest,
			},
			children,
			actions &&
				createElement(
					'div',
					{ className: 'ohmylms-card-actions' },
					actions
				)
		)
	);
} );
