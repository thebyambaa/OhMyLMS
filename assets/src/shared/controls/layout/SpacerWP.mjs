import { createElement, Fragment, memo } from '@wordpress/element';
import { __experimentalSpacer as Spacer } from '@wordpress/components';

/**
 * Spacer; every prop except children goes to the experimental `Spacer`.
 *
 * @param {Object}               props
 * @param {import('react').Node} [props.children] Spacer content.
 * @return {import('react').ReactElement} Spacer element.
 */
export const SpacerWP = memo( function SpacerWP( { children, ...rest } ) {
	return createElement(
		Fragment,
		null,
		createElement( Spacer, rest, children )
	);
} );
