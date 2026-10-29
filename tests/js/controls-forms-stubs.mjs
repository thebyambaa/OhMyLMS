import { createElement, forwardRef, isValidElement } from 'react';

/** Every props object the probes received, newest last. */
export const received = [];

/**
 * Serialise props for markup comparison. Functions print as `[fn]` and React
 * elements as their type and props.
 *
 * @param {string} key   Property name.
 * @param {*}      value Property value.
 * @return {*} JSON-safe value.
 */
function replacer( key, value ) {
	if ( 'function' === typeof value ) {
		return '[fn]';
	}
	if ( undefined === value ) {
		return '[undefined]';
	}
	if ( isValidElement( value ) ) {
		return {
			element:
				'string' === typeof value.type
					? value.type
					: value.type?.displayName ||
						value.type?.name ||
						'anonymous',
			key: value.key,
			props: value.props,
		};
	}
	return value;
}

/**
 * Create a probe standing in for a `wp.components` export. It renders a custom
 * element holding the serialised props and ref presence, and records the props.
 *
 * @param {string} name Component name.
 * @return {Function} Probe component.
 */
function probe( name ) {
	const Probe = forwardRef( function ProbeComponent( props, ref ) {
		const { children, ...rest } = props;
		received.push( { name, props } );
		return createElement(
			`probe-${ name }`,
			{
				'data-props': JSON.stringify( rest, replacer ),
				'data-ref': ref ? 'yes' : 'no',
				'data-has-children': undefined === children ? 'no' : 'yes',
			},
			children
		);
	} );
	Probe.displayName = name;
	return Probe;
}

export const TextControl = probe( 'TextControl' );
export const __experimentalNumberControl = probe( 'NumberControl' );
export const SelectControl = probe( 'SelectControl' );
export const TextareaControl = probe( 'TextareaControl' );
export const CheckboxControl = probe( 'CheckboxControl' );
export const RadioControl = probe( 'RadioControl' );
export const __experimentalToggleGroupControl = probe( 'ToggleGroupControl' );
export const __experimentalToggleGroupControlOption = probe(
	'ToggleGroupControlOption'
);
export const __experimentalToggleGroupControlOptionIcon = probe(
	'ToggleGroupControlOptionIcon'
);
export const SearchControl = probe( 'SearchControl' );
export const FormFileUpload = probe( 'FormFileUpload' );
