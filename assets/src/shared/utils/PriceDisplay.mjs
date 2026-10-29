import { createElement, memo } from '@wordpress/element';
import { formatPriceAmount, getPriceLayout } from './price.mjs';

/**
 * Price with its currency symbol placed according to the store setting.
 *
 * The recovered component cached its markup on the symbol, position and amount only, so changing
 * `del` alone left the old line-through in place. This version always follows `del`; every other
 * input gives the same markup.
 *
 * @param {Object}  props
 * @param {string}  [props.currency='$']        Currency symbol.
 * @param {string}  [props.currency_pos='left'] `left`, `right`, `left_space` or `right_space`.
 * @param {number}  [props.price=0]             Amount.
 * @param {boolean} [props.del=false]           Draw a line through the price.
 * @return {import('react').ReactElement} Price markup.
 */
function PriceDisplayBase( {
	currency = '$',
	currency_pos: position = 'left',
	price = 0,
	del = false,
} ) {
	const { symbolFirst, style } = getPriceLayout( position, del );
	const symbol = createElement( 'span', null, currency );
	const amount = formatPriceAmount( price );
	return createElement(
		'span',
		{ style },
		...( symbolFirst ? [ symbol, amount ] : [ amount, symbol ] )
	);
}

export const PriceDisplay = memo( PriceDisplayBase );
