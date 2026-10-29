/**
 * Number text used for prices: the browser's locale, up to two decimals, no forced decimals.
 *
 * @param {number} amount Price amount.
 * @return {string} Formatted number without a currency symbol.
 */
export function formatPriceAmount( amount ) {
	return new Intl.NumberFormat( undefined, {
		minimumFractionDigits: 0,
		maximumFractionDigits: 2,
	} ).format( amount );
}

/**
 * Where the currency symbol goes and how the price is spaced.
 *
 * `position` follows the WooCommerce-style setting: `left`, `right`, `left_space` or `right_space`.
 * Any other value is treated as `left`.
 *
 * @param {string}  position Currency position setting.
 * @param {boolean} strike   Draw a line through the price.
 * @return {{symbolFirst: boolean, style: object}} Order of the parts and the wrapper style.
 */
export function getPriceLayout( position, strike ) {
	const symbolFirst = 'right' !== position && 'right_space' !== position;
	const spaced = 'right_space' === position || 'left_space' === position;
	const style = { display: 'inline-flex', flexWrap: 'wrap' };
	if ( spaced ) {
		style.gap = '6px';
	}
	style.textDecoration = strike ? 'line-through' : 'none';
	return { symbolFirst, style };
}
