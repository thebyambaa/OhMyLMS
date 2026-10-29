import { createElement, Fragment, memo } from '@wordpress/element';
import { __experimentalHeading as Heading } from '@wordpress/components';

/**
 * Heading; every prop except children goes to the experimental `Heading`.
 *
 * @param {Object}               props
 * @param {import('react').Node} [props.children] Heading content.
 * @return {import('react').ReactElement} Heading element.
 */
export const HeadingWP = memo( function HeadingWP( { children, ...rest } ) {
	return createElement(
		Fragment,
		null,
		createElement( Heading, rest, children )
	);
} );
