/**
 * Class of the switch wrapper element.
 *
 * @param {Object}  options
 * @param {string}  [options.className='']          Extra class.
 * @param {boolean} [options.loading=false]         Switch is saving.
 * @param {boolean} [options.isDefaultStyle=false]  Use the WordPress default look.
 * @param {string}  [options.variant='primary']     Colour variant.
 * @return {string} Class attribute value.
 */
export function getWrapperClass( {
	className = '',
	loading = false,
	isDefaultStyle = false,
	variant = 'primary',
} = {} ) {
	return (
		'ohmylms-switcher-wrapper ohmylms-switch-variant-' +
		variant +
		' ' +
		( loading ? 'ohmylms-is-loading-toggle' : '' ) +
		' ' +
		( isDefaultStyle ? 'ohmylms-wp-default-switch-wrapper' : '' ) +
		' ' +
		className
	);
}

/**
 * Class of the toggle control inside the wrapper.
 *
 * @param {Object}  options
 * @param {boolean} [options.isDefaultStyle=false] Use the WordPress default look.
 * @param {string}  [options.variant='primary']    Colour variant.
 * @return {string} Class attribute value.
 */
export function getToggleClass( {
	isDefaultStyle = false,
	variant = 'primary',
} = {} ) {
	return (
		'ohmylms-switcher ' +
		( isDefaultStyle ? 'ohmylms-wp-default-switch' : '' ) +
		' ohmylms-switch-variant-' +
		variant
	);
}
