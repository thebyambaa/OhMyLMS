import { RadioControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';

/**
 * Radio group: a `RadioControl` that receives the props unchanged
 * (`selected`, `options`, `onChange( value )`, `label`, ...).
 *
 * @param {Object} props Props forwarded to `RadioControl`.
 * @return {import('react').ReactElement} Radio control.
 */
export function RadioWP( props ) {
	return createElement( RadioControl, props );
}
