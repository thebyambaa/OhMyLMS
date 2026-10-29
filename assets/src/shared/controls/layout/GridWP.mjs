import { createElement, Fragment, memo } from '@wordpress/element';
import { __experimentalGrid as Grid } from '@wordpress/components';

/**
 * Grid; every prop except children goes to the experimental `Grid`.
 *
 * @param {Object}               props
 * @param {import('react').Node} [props.children] Grid content.
 * @return {import('react').ReactElement} Grid element.
 */
export const GridWP = memo( function GridWP( { children, ...rest } ) {
	return createElement(
		Fragment,
		null,
		createElement( Grid, rest, children )
	);
} );
