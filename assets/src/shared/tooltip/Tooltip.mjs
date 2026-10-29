import { Tooltip as WpTooltip } from '@wordpress/components';
import { createElement } from '@wordpress/element';
import { getTooltipProps } from './model.mjs';

const iconStyle = { lineHeight: 0 };

/**
 * Tooltip around an icon. Wraps the children in a `span.tooltip-icon` and shows
 * `text` (or `title`) on hover and focus.
 *
 * @param {Object}                    props             Component props.
 * @param {import('react').ReactNode} props.children    Trigger content.
 * @param {string}                    [props.title]     Tooltip text when `text` is empty.
 * @param {string}                    [props.text]      Tooltip text.
 * @param {string}                    [props.placement] Tooltip placement, `top` by default.
 * @return {import('react').ReactElement} Tooltip.
 */
export function Tooltip( props ) {
	return createElement(
		WpTooltip,
		getTooltipProps( props ),
		createElement(
			'span',
			{ style: iconStyle, className: 'tooltip-icon' },
			props.children
		)
	);
}
