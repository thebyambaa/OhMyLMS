import { createElement, Fragment, memo } from '@wordpress/element';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- the recovered application renders this experimental component; markup parity is tested.
import { __experimentalSurface as Surface } from '@wordpress/components';
import { getSurfaceStyle } from './model.mjs';

/**
 * Surface with height shorthands; other props go to the experimental `Surface`.
 *
 * @param {Object}               props
 * @param {import('react').Node} [props.children]   Surface content.
 * @param {boolean}              [props.fullHeight] Minimum height 100%.
 * @param {string|number}        [props.minHeight]  Minimum height.
 * @param {Object}               [props.style]      Overrides the shorthands.
 * @return {import('react').ReactElement} Surface element.
 */
export const SurfaceWP = memo( function SurfaceWPRender( {
	children,
	fullHeight,
	minHeight,
	style,
	...rest
} ) {
	return createElement(
		Fragment,
		null,
		createElement(
			Surface,
			{
				...rest,
				style: getSurfaceStyle( { fullHeight, minHeight, style } ),
			},
			children
		)
	);
} );
