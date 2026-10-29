import { Button } from '@wordpress/components';
import { createElement, forwardRef } from '@wordpress/element';
import { getButtonBusy, getButtonStyle } from './model.mjs';

/**
 * Button used across the admin. Wraps the WordPress `Button`:
 * - `loading` (or `isBusy`) sets `isBusy`.
 * - `padding` sets the padding; `primary` and `secondary` buttons that are not
 *   `small` get `6px 24px` by default.
 * - The `outline` variant gets a transparent, bordered look.
 * - `style` is merged last. Every other prop, including `variant`, `size`,
 *   `icon` and `disabled`, is passed to the WordPress `Button`, and the ref is
 *   forwarded.
 *
 * @param {Object}                    props           Button props.
 * @param {boolean}                   [props.loading] Shows the busy state.
 * @param {boolean}                   [props.isBusy]  Shows the busy state.
 * @param {string|number}             [props.padding] Explicit padding.
 * @param {Object}                    [props.style]   Inline style.
 * @param {import('react').ReactNode} props.children  Label.
 * @param {import('react').Ref}       ref             Ref to the button.
 * @return {import('react').ReactElement} Button.
 */
function ButtonWPBase( props, ref ) {
	const { loading, isBusy, children, padding, style, ...rest } = props;

	return createElement(
		Button,
		{
			ref,
			...rest,
			isBusy: getButtonBusy( loading, isBusy ),
			style: getButtonStyle( {
				padding,
				variant: rest.variant,
				size: rest.size,
				style,
			} ),
		},
		children
	);
}

export const ButtonWP = forwardRef( ButtonWPBase );
