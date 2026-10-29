import { Popover } from '@wordpress/components';
import { createElement } from '@wordpress/element';
import { omitProps } from './model.mjs';

/**
 * Thin wrapper around the WordPress `Popover`. Every prop except `children` is
 * passed through.
 *
 * @param {Object}                    props          Popover props.
 * @param {import('react').ReactNode} props.children Content.
 * @return {import('react').ReactElement} Popover.
 */
export function PopoverWP( props ) {
	return createElement(
		Popover,
		omitProps( props, [ 'children' ] ),
		props.children
	);
}
