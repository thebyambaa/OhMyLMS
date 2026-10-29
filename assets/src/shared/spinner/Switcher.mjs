import { Spinner, ToggleControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';
import { getToggleClass, getWrapperClass } from './model.mjs';

/**
 * Toggle switch that shows a spinner and disables itself while loading.
 *
 * Extra props (checked, onChange, label, ...) go to the ToggleControl.
 *
 * @param {Object}  props
 * @param {string}  [props.className='']         Extra wrapper class.
 * @param {boolean} [props.loading=false]        Show the spinner and disable.
 * @param {boolean} [props.isDefaultStyle=false] Use the WordPress default look.
 * @param {string}  [props.variant='primary']    Colour variant.
 * @return {import('react').ReactElement} Switch markup.
 */
export function Switcher( {
	className = '',
	loading = false,
	isDefaultStyle = false,
	variant = 'primary',
	...rest
} ) {
	return createElement(
		'div',
		{
			className: getWrapperClass( {
				className,
				loading,
				isDefaultStyle,
				variant,
			} ),
			style: { position: 'relative', display: 'inline-block' },
		},
		createElement( ToggleControl, {
			className: getToggleClass( { isDefaultStyle, variant } ),
			__nextHasNoMarginBottom: true,
			disabled: loading,
			...rest,
		} ),
		loading &&
			createElement(
				'div',
				{ className: 'ohmylms-switcher-spinner' },
				createElement( Spinner, null )
			)
	);
}
