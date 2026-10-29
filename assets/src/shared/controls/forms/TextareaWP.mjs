import { TextareaControl } from '@wordpress/components';
import { createElement, forwardRef } from '@wordpress/element';

/**
 * Multi-line text field: a `TextareaControl` without bottom margin. The ref
 * reaches the control; every prop (`value`, `onChange( value )`, `rows`, ...)
 * is forwarded and can override the default.
 *
 * @param {Object} props Props forwarded to `TextareaControl`.
 * @param {Object} ref   Ref forwarded to `TextareaControl`.
 * @return {import('react').ReactElement} Textarea control.
 */
export const TextareaWP = forwardRef( function TextareaWPRender( props, ref ) {
	return createElement( TextareaControl, {
		ref,
		__nextHasNoMarginBottom: true,
		...props,
	} );
} );
