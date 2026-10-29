import { createElement, memo } from '@wordpress/element';
import { Button, Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getTagClassName } from './model.mjs';

const noop = () => {};

/**
 * Tag span with an optional icon and remove button.
 *
 * The icon quirk is kept: `showIcon` renders nothing itself and hides `icon`,
 * while a truthy `icon` without `showIcon` renders an empty `Icon`.
 *
 * @param {Object}               props
 * @param {string}               [props.label]           Tag text, beats children.
 * @param {*}                    [props.state]           Ignored, not forwarded.
 * @param {boolean}              [props.removable=false] Show the remove button.
 * @param {Function}             [props.onRemove]        Remove click handler.
 * @param {*}                    [props.icon=null]       Truthy renders an Icon.
 * @param {boolean}              [props.showIcon=false]  See the quirk above.
 * @param {string}               [props.className='']    Extra classes.
 * @param {import('react').Node} [props.children]        Fallback tag text.
 * @return {import('react').ReactElement} Tag markup.
 */
export const TagWP = memo( function TagWP( {
	label,
	state,
	removable = false,
	onRemove = noop,
	icon = null,
	showIcon = false,
	className = '',
	children,
	...rest
} ) {
	return createElement(
		'span',
		{ className: getTagClassName( className ), ...rest },
		showIcon ||
			( icon &&
				createElement( Icon, { className: 'ohmylms-tag-icon' } ) ),
		createElement(
			'span',
			{ className: 'ohmylms-tag-label' },
			label || children
		),
		removable &&
			createElement(
				Button,
				{
					className: 'ohmylms-tag-remove',
					onClick: onRemove,
					label: __( 'Remove tag' ),
				},
				'×'
			)
	);
} );
