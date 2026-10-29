import { CheckboxControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';

/**
 * Checkbox with the plugin border colour and radius. The default `className`,
 * `style` and margin flag can be overridden, and every prop (`checked`,
 * `onChange( checked )`, `label`, ...) is forwarded.
 *
 * @param {Object} props Props forwarded to `CheckboxControl`.
 * @return {import('react').ReactElement} Checkbox control.
 */
export function CheckboxWP( props ) {
	return createElement( CheckboxControl, {
		__nextHasNoMarginBottom: true,
		className: 'ohmylms-checkbox-control',
		style: { borderColor: '#C8D2E9', borderRadius: '4px' },
		...props,
	} );
}
