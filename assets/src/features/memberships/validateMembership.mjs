/**
 * Reconstructed membership form rules; the server remains authoritative.
 * @param plan
 * @param translate
 */
export function validateMembership( plan, translate = ( value ) => value ) {
	const errors = {};
	if ( ! plan?.name?.trim() ) {
		errors.name = translate( 'Name is required.', 'ohmylms' );
	}
	if ( ! Number.isFinite( Number( plan?.regular_price ) ) || Number( plan?.regular_price ) <= 0 ) {
		errors.price = translate( 'Price must be greater than 0.', 'ohmylms' );
	}
	const hasSale = plan?.sale_price !== '' && plan?.sale_price !== null && plan?.sale_price !== undefined;
	if ( hasSale && ( ! Number.isFinite( Number( plan.sale_price ) ) || Number( plan.sale_price ) < 0 || Number( plan.sale_price ) > Number( plan?.regular_price ) ) ) {
		errors.sale_price = translate(
			'Sale price cannot be greater than the subscription price.',
			'ohmylms'
		);
	}
	const date = ( value ) => String( value?.date || value || '' ).slice( 0, 10 );
	const validDate = ( value ) => /^\d{4}-\d{2}-\d{2}$/.test( value ) && Number.isFinite( Date.parse( `${ value }T00:00:00Z` ) ) && new Date( `${ value }T00:00:00Z` ).toISOString().slice( 0, 10 ) === value;
	const from = date( plan?.sale_price_dates_from );
	const to = date( plan?.sale_price_dates_to );
	if ( ( from && ! validDate( from ) ) || ( to && ! validDate( to ) ) || ( from && to && from > to ) ) {
		errors.sale_dates = translate( 'Choose valid sale dates with the end on or after the start.', 'ohmylms' );
	} else if ( hasSale && Number( plan.sale_price ) === 0 && ! to ) {
		errors.sale_dates = translate( 'Choose a Free until date for free access.', 'ohmylms' );
	}
	return errors;
}
