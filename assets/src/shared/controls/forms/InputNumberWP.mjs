import { __experimentalNumberControl as NumberControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';

/**
 * Number field: a 40px `NumberControl` of `type="number"` with no fixed input
 * width. `style` is copied into a fresh object, and every prop (`value`,
 * `onChange( value, extra )`, `min`, `max`, ...) is forwarded and can override
 * the defaults.
 *
 * @param {Object} props Props forwarded to `NumberControl`.
 * @return {import('react').ReactElement} Number control.
 */
export function InputNumberWP( props ) {
	return createElement( NumberControl, {
		__next40pxDefaultSize: true,
		__unstableInputWidth: null,
		type: 'number',
		style: { ...props.style },
		...props,
	} );
}
