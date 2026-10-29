import { createElement } from '@wordpress/element';
import { FlexItem } from '@wordpress/components';
import { getFlexItemStyle } from './model.mjs';

/**
 * Flex item with sizing shorthands; other props go to `FlexItem`.
 *
 * @param {Object}               props
 * @param {import('react').Node} [props.children]   Item content.
 * @param {boolean}              [props.fullWidth]  Width 100%.
 * @param {boolean}              [props.fullHeight] Height 100%.
 * @param {string}               [props.flex]       CSS flex shorthand.
 * @param {string|number}        [props.width]      Width.
 * @param {string|number}        [props.minWidth]   Minimum width.
 * @param {string|number}        [props.maxWidth]   Maximum width.
 * @param {Object}               [props.style]      Overrides the shorthands.
 * @return {import('react').ReactElement} FlexItem element.
 */
export function FlexItemWP( {
	children,
	fullWidth,
	fullHeight,
	flex,
	width,
	minWidth,
	maxWidth,
	style,
	...rest
} ) {
	return createElement(
		FlexItem,
		{
			style: getFlexItemStyle( {
				fullWidth,
				fullHeight,
				flex,
				width,
				minWidth,
				maxWidth,
				style,
			} ),
			...rest,
		},
		children
	);
}
