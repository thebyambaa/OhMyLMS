import { TextControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';

/**
 * Single-line text field: a 40px `TextControl` without bottom margin. Every
 * prop (`label`, `value`, `onChange( value )`, `help`, ...) is forwarded and
 * can override the two defaults.
 *
 * @param {Object} props Props forwarded to `TextControl`.
 * @return {import('react').ReactElement} Text control.
 */
export function InputWP( props ) {
	return createElement( TextControl, {
		__next40pxDefaultSize: true,
		__nextHasNoMarginBottom: true,
		...props,
	} );
}
