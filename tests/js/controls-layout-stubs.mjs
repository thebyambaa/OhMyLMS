import { createElement } from 'react';

/**
 * Serialise props for a probe, replacing functions with a marker.
 *
 * @param {string} key   Property name.
 * @param {*}      value Property value.
 * @return {*} JSON-safe value.
 */
function replacer( key, value ) {
	return 'function' === typeof value ? '[Function]' : value;
}

/**
 * Build a stand-in for a `wp.components` component.
 *
 * It renders a custom element carrying every received prop (except
 * children) as JSON, followed by the children, so two renders are equal only
 * when the same props reached the component in the same order.
 *
 * @param {string} name Component name, used as the custom element tag.
 * @return {Function} Probe component.
 */
function makeProbe( name ) {
	return function Probe( { children, ...rest } ) {
		return createElement(
			'probe-' + name,
			{ 'data-props': JSON.stringify( rest, replacer ) },
			children
		);
	};
}

export const Flex = makeProbe( 'flex' );
export const FlexItem = makeProbe( 'flex-item' );
export const FlexBlock = makeProbe( 'flex-block' );
export const __experimentalSpacer = makeProbe( 'spacer' );
export const __experimentalGrid = makeProbe( 'grid' );
export const __experimentalSurface = makeProbe( 'surface' );
export const __experimentalDivider = makeProbe( 'divider' );
export const __experimentalText = makeProbe( 'text' );
export const __experimentalHeading = makeProbe( 'heading' );
export const Card = makeProbe( 'card' );
export const Icon = makeProbe( 'icon' );
export const Button = makeProbe( 'button' );
