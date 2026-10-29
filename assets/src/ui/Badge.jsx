import { createElement } from '@wordpress/element';
import { badgeClass } from './components.mjs';

/**
 * Shared badge for statuses and counts.
 *
 * @param {Object} props
 * @param {string} props.tone      primary, success, warning, danger or info; neutral by default.
 * @param          props.className
 * @param          props.children
 */
export function Badge( { tone, className, children, ...rest } ) {
	return createElement(
		'span',
		{ ...rest, className: badgeClass( { tone, className } ) },
		children
	);
}
