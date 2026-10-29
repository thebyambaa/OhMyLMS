import { createElement, forwardRef } from 'react';

/**
 * Turn a value into JSON, printing functions as `[fn]` and React elements as
 * their type name and props.
 *
 * @param {*} value Value to serialize.
 * @return {string} JSON.
 */
export function serialize( value ) {
	return JSON.stringify( value, ( key, item ) => {
		if ( 'function' === typeof item ) {
			return '[fn]';
		}
		if ( item && item.$$typeof ) {
			const type = item.type;
			return {
				element:
					'string' === typeof type
						? type
						: type?.displayName || 'component',
				props: item.props,
			};
		}
		return item;
	} );
}

/**
 * Build a stand-in component that renders a custom element carrying the
 * serialized props, followed by its children.
 *
 * @param {string} tag Custom element name.
 * @return {Function} Component.
 */
export function makeProbe( tag ) {
	const Probe = forwardRef( ( props, ref ) => {
		const { children, ...rest } = props;
		return createElement(
			tag,
			{ 'data-props': serialize( rest ), 'data-ref': ref ? 'ref' : '' },
			children
		);
	} );
	Probe.displayName = tag;
	return Probe;
}

/** Props the stubs received, for interaction checks. */
export const captured = { tabPanel: [] };

export const Button = makeProbe( 'wp-button' );
export const Modal = makeProbe( 'wp-modal' );
export const Popover = makeProbe( 'wp-popover' );
export const Notice = makeProbe( 'wp-notice' );
export const Spinner = makeProbe( 'wp-spinner' );
export const ProgressBar = makeProbe( 'wp-progress-bar' );
export const Flex = makeProbe( 'wp-flex' );
export const MenuItem = makeProbe( 'wp-menu-item' );
export const __experimentalHeading = makeProbe( 'wp-heading' );

/**
 * Icon stand-in: prints the icon element it received.
 *
 * @param {Object} props Icon props.
 * @return {Element} Probe.
 */
export function Icon( props ) {
	return createElement( makeProbe( 'wp-icon' ), props );
}

/**
 * Dropdown menu stand-in. A function child is called, as the WordPress
 * component does, so the menu items appear in the markup.
 *
 * @param {Object} props DropdownMenu props.
 * @return {Element} Probe with the rendered menu.
 */
export function DropdownMenu( props ) {
	const { children, ...rest } = props;
	return createElement(
		makeProbe( 'wp-dropdown-menu' ),
		rest,
		'function' === typeof children
			? children( { onClose: () => {} } )
			: children
	);
}

/**
 * Tab panel stand-in. Renders the tab list and the children for the first tab.
 *
 * @param {Object} props TabPanel props.
 * @return {Element} Probe.
 */
export function TabPanel( props ) {
	const { children, ...rest } = props;
	captured.tabPanel.push( props );
	return createElement(
		makeProbe( 'wp-tab-panel' ),
		rest,
		children( rest.tabs[ 0 ] )
	);
}

/**
 * Same behaviour as the `SVG` primitive: forces `aria-hidden` and
 * `focusable=false`.
 */
export const SVG = forwardRef( ( { className, isPressed, ...rest }, ref ) =>
	createElement( 'svg', {
		...rest,
		className:
			[ className, isPressed ? 'is-pressed' : '' ]
				.filter( Boolean )
				.join( ' ' ) || undefined,
		'aria-hidden': true,
		focusable: false,
		ref,
	} )
);

/**
 * Path primitive.
 *
 * @param {Object} props Path props.
 * @return {Element} Path element.
 */
export function Path( props ) {
	return createElement( 'path', props );
}

/**
 * Stand-in for the layout group's FlexWP, shaped like recovered factory 4315.
 */
export const FlexWP = forwardRef( ( { children, ...rest }, ref ) =>
	createElement( Flex, { ...rest, ref }, children )
);

SVG.displayName = 'SVG';
