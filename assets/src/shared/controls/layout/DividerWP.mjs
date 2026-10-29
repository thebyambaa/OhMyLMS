import { createElement } from '@wordpress/element';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- the recovered application renders this experimental component; markup parity is tested.
import { __experimentalDivider as Divider } from '@wordpress/components';
import { getDividerStyle } from './model.mjs';

/**
 * Divider whose colour defaults to #f4f5f7 and is set through `style.color`.
 *
 * @param {Object} props
 * @param {string} [props.color] Divider colour.
 * @param {Object} [props.style] Style that overrides the colour.
 * @return {import('react').ReactElement} Divider element.
 */
export function DividerWP( { color, ...rest } ) {
	return createElement( Divider, {
		...rest,
		style: getDividerStyle( color, rest.style ),
	} );
}
