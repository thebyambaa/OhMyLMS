import { createElement, forwardRef, Fragment, memo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ButtonWP } from '../controls/actions/ButtonWP.mjs';
import { PlusIcon } from './PlusIcon.mjs';

const noop = () => {};

/**
 * Primary button with a plus icon, used for "Add New" actions.
 *
 * `className` is accepted and ignored, as in the recovered application. Other props (disabled,
 * variant, icon, ...) go to the button and can override the icon and variant.
 *
 * @param {Object}     props
 * @param {string}     [props.label]     Button text, "Add New" by default.
 * @param {() => void} [props.onClick]   Click handler.
 * @param {string}     [props.className] Ignored.
 * @param {Object}     ref               Forwarded to the button.
 * @return {import('react').ReactElement} Button.
 */
const AddButtonBase = forwardRef( function AddButton( props, ref ) {
	const {
		label = __( 'Add New', 'ohmylms' ),
		onClick = noop,
		...rest
	} = props;
	delete rest.className;
	return createElement(
		Fragment,
		null,
		createElement(
			ButtonWP,
			{
				ref,
				onClick,
				icon: createElement( PlusIcon, null ),
				variant: 'primary',
				...rest,
			},
			label
		)
	);
} );

export const AddButton = memo( AddButtonBase );
