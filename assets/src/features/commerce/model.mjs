/**
 * Model helpers and validation logic for Commerce modules (Orders, Subscriptions, Coupons).
 */

/**
 * Validate coupon fields for creation and updating.
 * @param {Object}                   coupon    - The coupon payload.
 * @param {(text: string) => string} [__=t=>t] - i18n translation helper.
 * @return {Object} Map of field name to error message.
 */
export function validateCoupon( coupon, __ = ( t ) => t ) {
	const errors = {};
	if ( ! coupon || typeof coupon !== 'object' ) {
		errors.title = __( 'Coupon title is required.', 'ohmylms' );
		return errors;
	}

	const title = String( coupon.title || coupon.name || '' ).trim();
	if ( ! title ) {
		errors.title = __( 'Coupon title is required.', 'ohmylms' );
	}

	const code = String( coupon.code || '' ).trim();
	if ( ! code ) {
		errors.code = __( 'Coupon code is required.', 'ohmylms' );
	}

	const amount = Number( coupon.amount );
	if ( ! Number.isFinite( amount ) || amount <= 0 ) {
		errors.amount = __( 'Amount must be greater than 0.', 'ohmylms' );
	} else if ( coupon.discount_type === 'percent' && amount > 100 ) {
		errors.amount = __(
			'Percentage discount cannot exceed 100%.',
			'ohmylms'
		);
	}

	if ( coupon.date_expires && coupon.date_start ) {
		const start = new Date( coupon.date_start.date ?? coupon.date_start );
		const end = new Date( coupon.date_expires.date ?? coupon.date_expires );
		if (
			! Number.isFinite( start.getTime() ) ||
			! Number.isFinite( end.getTime() ) ||
			end <= start
		) {
			errors.date_expires = __(
				'Expiry date must be after start date.',
				'ohmylms'
			);
		}
	}

	for ( const field of [ 'usage_limit', 'usage_limit_per_user' ] ) {
		if (
			coupon[ field ] !== undefined &&
			( ! Number.isInteger( Number( coupon[ field ] ) ) ||
				Number( coupon[ field ] ) <= 0 )
		) {
			errors[ field ] = __(
				'Usage limit must be a positive whole number.',
				'ohmylms'
			);
		}
	}
	if (
		coupon.course_id_type === 'selected_course' &&
		! coupon.course_ids?.length
	) {
		errors.course_ids = __( 'Select at least one course.', 'ohmylms' );
	}

	return errors;
}

/**
 * Generate a random uppercase alphanumeric coupon code.
 * @param {number} [length=8]
 * @return {string} Generated coupon code.
 */
export function generateCouponCode( length = 8 ) {
	const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	let result = '';
	for ( let i = 0; i < length; i++ ) {
		result += chars.charAt( Math.floor( Math.random() * chars.length ) );
	}
	return result;
}

/**
 * Validate refund fields before submission.
 * @param {Object}                   refund    - { amount, reason }
 * @param {number}                   maxAmount - Maximum allowable refund amount.
 * @param {(text: string) => string} [__=t=>t]
 * @return {{ valid: boolean, error?: string }} Validation result.
 */
export function validateRefund( refund, maxAmount, __ = ( t ) => t ) {
	const amount = Number( refund?.amount );
	if ( ! Number.isFinite( amount ) || amount <= 0 ) {
		return {
			valid: false,
			error: __( 'Refund amount must be greater than 0.', 'ohmylms' ),
		};
	}
	if ( ! Number.isFinite( maxAmount ) || amount > maxAmount ) {
		return {
			valid: false,
			error: __(
				'Refund amount cannot exceed remaining order total.',
				'ohmylms'
			),
		};
	}
	const reason = ( refund?.reason || '' ).trim();
	if ( ! reason ) {
		return {
			valid: false,
			error: __( 'Refund reason is required.', 'ohmylms' ),
		};
	}
	return { valid: true };
}

/**
 * Determine Badge variant for an order status.
 * @param {string} status
 * @return {'success' | 'warning' | 'danger' | 'secondary' | 'default'} Badge variant.
 */
export function getOrderStatusVariant( status ) {
	switch ( status ) {
		case 'completed':
			return 'success';
		case 'pending':
		case 'on-hold':
			return 'warning';
		case 'cancelled':
		case 'refunded':
			return 'danger';
		case 'processing':
			return 'secondary';
		default:
			return 'default';
	}
}

/**
 * Determine Badge variant for a subscription status.
 * @param {string} status
 * @return {'success' | 'warning' | 'danger' | 'default'} Badge variant.
 */
export function getSubscriptionStatusVariant( status ) {
	switch ( status ) {
		case 'active':
			return 'success';
		case 'pending':
		case 'on-hold':
			return 'warning';
		case 'cancelled':
		case 'expired':
			return 'danger';
		default:
			return 'default';
	}
}
