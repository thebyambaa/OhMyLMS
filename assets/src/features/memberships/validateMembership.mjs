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
	if ( parseFloat( plan?.regular_price ) <= 0 ) {
		errors.price = translate( 'Price must be greater than 0.', 'ohmylms' );
	}
	if (
		plan?.sale_price &&
		parseFloat( plan.sale_price ) > parseFloat( plan?.regular_price )
	) {
		errors.sale_price = translate(
			'Sale price cannot be greater than the subscription price.',
			'ohmylms'
		);
	}
	return errors;
}
