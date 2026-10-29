import { SelectControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';

/**
 * Drop-down: a 40px `SelectControl` without bottom margin. Without `options`
 * it shows one disabled "Select an Option" entry. `suffixIcon` becomes the
 * control's `suffix` (an explicit `suffix` prop still wins), `children` are
 * passed on as children, and the remaining props (`value`,
 * `onChange( value )`, `label`, ...) are forwarded.
 *
 * @param {Object}                    props              Component props.
 * @param {import('react').ReactNode} [props.children]   Extra children.
 * @param {import('react').ReactNode} [props.suffixIcon] Suffix content.
 * @param {Array}                     [props.options]    Select options.
 * @return {import('react').ReactElement} Select control.
 */
export function SelectWP( {
	children,
	suffixIcon,
	options = [ { disabled: true, label: 'Select an Option', value: '' } ],
	...rest
} ) {
	return createElement(
		SelectControl,
		{
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
			options,
			suffix: suffixIcon,
			...rest,
		},
		children
	);
}
