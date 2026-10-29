import { createElement, Fragment, memo } from '@wordpress/element';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- the recovered application renders this experimental component; markup parity is tested.
import { __experimentalSpacer as Spacer } from '@wordpress/components';

/**
 * Spacer; every prop except children goes to the experimental `Spacer`.
 *
 * @param {Object}               props
 * @param {import('react').Node} [props.children] Spacer content.
 * @return {import('react').ReactElement} Spacer element.
 */
export const SpacerWP = memo( function SpacerWPRender( { children, ...rest } ) {
	return createElement(
		Fragment,
		null,
		createElement( Spacer, rest, children )
	);
} );
