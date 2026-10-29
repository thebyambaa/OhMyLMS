import { createElement } from 'react';

/**
 * Stand-in for `wp.components`, which only exists in the WordPress admin.
 * Used for both the recovered factory and the authored table so the markup
 * comparison covers everything except the checkbox internals.
 *
 * @param {Object} props CheckboxControl props.
 * @return {Element} A plain checkbox input.
 */
export function CheckboxControl( {
	checked,
	disabled,
	className,
	style,
	onChange,
} ) {
	return createElement( 'input', {
		type: 'checkbox',
		className,
		style,
		checked: !! checked,
		disabled,
		onChange: onChange
			? ( event ) => onChange( event.target.checked )
			: undefined,
		readOnly: ! onChange,
	} );
}
