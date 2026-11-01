/** Pure commerce helpers shared by ohmylms/cart, ohmylms/checkout and ohmylms/stripe. The server owns prices, tax and coupons. */

export const EU_COUNTRIES = Object.freeze( [
	'AT',
	'BE',
	'BG',
	'HR',
	'CY',
	'CZ',
	'DK',
	'EE',
	'FI',
	'FR',
	'DE',
	'GR',
	'HU',
	'IE',
	'IT',
	'LV',
	'LT',
	'LU',
	'MT',
	'NL',
	'PL',
	'PT',
	'RO',
	'SK',
	'SI',
	'ES',
	'SE',
] );

/**
 * @param {unknown} country Country code.
 * @return {boolean} Whether it is an EU member state.
 */
export function isEuCountry( country ) {
	return EU_COUNTRIES.includes( String( country || '' ).toUpperCase() );
}

/**
 * @param {unknown} value Number or numeric string.
 * @return {number} Positive integer, or 0.
 */
const positive = ( value ) => {
	const number = Number.parseInt( /** @type {string} */ ( value ), 10 );
	return Number.isFinite( number ) && number > 0 ? number : 0;
};

/**
 * @typedef {Object} CartContext
 * @property {unknown} [courseId]     Course to add.
 * @property {unknown} [membershipId] Membership to add.
 * @property {unknown} [quantity]     Quantity.
 * @property {unknown} [purchaseBy]   `point` to pay with points.
 */

/**
 * @typedef {Object} CartBody
 * @property {number} [membership_id] Membership.
 * @property {number} [course_id]     Course.
 * @property {number} [quantity]      Quantity (always set by `cartRequest()`).
 * @property {string} [purchase_by]   `point`.
 */

/**
 * REST body for one cart button, or null when the button names nothing to buy.
 *
 * @param {CartContext|null|undefined} context Button context rendered by PHP.
 * @return {CartBody|null} Body.
 */
export function cartRequest( context ) {
	const courseId = positive( context?.courseId );
	const membershipId = positive( context?.membershipId );
	if ( ! courseId && ! membershipId ) {
		return null;
	}
	/** @type {CartBody} */
	const body = membershipId
		? { membership_id: membershipId }
		: { course_id: courseId };
	body.quantity = positive( context?.quantity ) || 1;
	if ( 'point' === context?.purchaseBy ) {
		body.purchase_by = 'point';
	}
	return body;
}

/**
 * @typedef {Object} CartResponse
 * @property {unknown}             [status]       `success` when added.
 * @property {unknown}             [redirect_url] Where to go next.
 * @property {unknown}             [message]      Error message.
 * @property {{message?: unknown}} [data]         WordPress error data.
 */

/**
 * What a cart answer means for the button: follow a redirect or show a message.
 *
 * @param {CartResponse|null|undefined} response Cart endpoint body.
 * @return {{type: 'redirect', url: string}|{type: 'error', message: string}} Outcome.
 */
export function cartOutcome( response ) {
	if ( response?.status === 'success' && response.redirect_url ) {
		return { type: 'redirect', url: String( response.redirect_url ) };
	}
	return {
		type: 'error',
		message: String( response?.message || response?.data?.message || '' ),
	};
}

/**
 * `payment_method_<id>` radio id to gateway id.
 *
 * @param {unknown} id Radio id.
 * @return {string} Gateway id.
 */
export function gatewayFromInputId( id ) {
	return String( id || '' ).replace( /^payment_method_/, '' );
}

/**
 * Required state of every field any gateway can escalate. Fields the server rendered as
 * required stay required; the server validates the gateway's fields again on submit.
 *
 * @param {Object<string,string[]>} map            Gateway id to field keys.
 * @param {string}                  gateway        Selected gateway id.
 * @param {string[]}                serverRequired Fields always required.
 * @return {Object<string,boolean>} Field key to required.
 */
export function requiredFields( map, gateway, serverRequired = [] ) {
	/** @type {Object<string,boolean>} */
	const fields = {};
	const wanted = new Set( ( map && map[ gateway ] ) || [] );
	for ( const list of Object.values( map || {} ) ) {
		for ( const field of list || [] ) {
			fields[ field ] =
				wanted.has( field ) || serverRequired.includes( field );
		}
	}
	return fields;
}

/**
 * @typedef {Object} CheckoutResponse
 * @property {unknown} [payment_status] `pending` while a gateway finishes the payment.
 * @property {unknown} [success]        `wp_send_json_*` flag.
 * @property {unknown} [result]         Legacy `success` flag.
 * @property {unknown} [redirect]       Where to go next.
 * @property {unknown} [redirect_url]   Where to go next (legacy).
 * @property {unknown} [message]        Notice markup.
 * @property {unknown} [messages]       Notice markup (legacy).
 * @property {unknown} [reload]         Reload the page after the notice.
 */

/**
 * @typedef {{type: 'pending'}|{type: 'redirect', url: string}|{type: 'error', message: string, reload?: boolean}} CheckoutOutcome
 */

/**
 * Normalise an `ohmylms_checkout` answer: pending gateway payment, redirect, or error notice.
 *
 * @param {CheckoutResponse|null|undefined} response Checkout AJAX body.
 * @return {CheckoutOutcome} Outcome.
 */
export function checkoutOutcome( response ) {
	if ( ! response || 'object' !== typeof response ) {
		return { type: 'error', message: '' };
	}
	if ( 'pending' === response.payment_status ) {
		return { type: 'pending' };
	}
	const succeeded = true === response.success || 'success' === response.result;
	const redirect = response.redirect || response.redirect_url;
	if ( succeeded && redirect ) {
		return { type: 'redirect', url: String( redirect ) };
	}
	return {
		type: 'error',
		message: String( response.message || response.messages || '' ),
		reload: Boolean( response.reload ),
	};
}

/**
 * Modifier classes for the checkout notice, matching the legacy CSS.
 *
 * @param {number} count Number of error list items.
 */
export function noticeModifiers( count ) {
	return {
		'single-error': 1 === count,
		'more-then-two-error': count > 2,
	};
}

/**
 * Trimmed coupon code; empty means there is nothing to send.
 *
 * @param {unknown} value Field value.
 * @return {string} Code.
 */
export function couponCode( value ) {
	return String( value || '' ).trim();
}

/**
 * State/province control for a country's states list.
 *
 * @param {Array<{code:string,title:string}>} states States from the server.
 */
export function stateField( states ) {
	if ( ! Array.isArray( states ) || ! states.length ) {
		return { type: 'text', options: [] };
	}
	return {
		type: 'select',
		options: states.map( ( item ) => ( {
			value: String( item.code ),
			label: String( item.title ),
		} ) ),
	};
}

/**
 * Add query parameters to an absolute URL.
 *
 * @param {string}                  url    Base URL.
 * @param {Object<string, unknown>} params Parameters.
 * @return {string} URL.
 */
export function withQuery( url, params ) {
	const target = new URL( url, 'http://localhost' );
	for ( const [ key, value ] of Object.entries( params ) ) {
		if ( value !== undefined && value !== null && value !== '' ) {
			target.searchParams.set( key, String( value ) );
		}
	}
	return /^[a-z][a-z0-9+.-]*:/i.test( url )
		? target.toString()
		: target.pathname + target.search + target.hash;
}

/**
 * @typedef {Object} StripeIntent
 * @property {string} [status]        PaymentIntent status.
 * @property {string} [id]            PaymentIntent id.
 * @property {string} [client_secret] Client secret.
 * @property {{redirect_to_url?: {url?: string}}|null} [next_action] Required customer action.
 */

/**
 * @typedef {{type: 'redirect', url: string}|{type: 'message', code: string}|{type: 'error', code: string}} StripeOutcome
 */

/**
 * What to do with a confirmed Stripe PaymentIntent.
 *
 * @param {StripeIntent|null|undefined} intent    Stripe PaymentIntent.
 * @param {string}                      returnUrl Order thank-you URL.
 * @return {StripeOutcome} Outcome.
 */
export function stripeIntentOutcome( intent, returnUrl ) {
	const status = intent?.status;
	if ( 'succeeded' === status || 'requires_capture' === status ) {
		return returnUrl
			? {
					type: 'redirect',
					url: withQuery( returnUrl, {
						payment_intent: intent?.id,
						payment_intent_client_secret: intent?.client_secret,
					} ),
			  }
			: { type: 'error', code: 'missing_return_url' };
	}
	if ( 'processing' === status ) {
		return { type: 'message', code: 'processing' };
	}
	if (
		'requires_action' === status &&
		intent?.next_action?.redirect_to_url?.url
	) {
		return {
			type: 'redirect',
			url: String( intent.next_action.redirect_to_url.url ),
		};
	}
	return {
		type: 'error',
		code: status ? 'payment_failed' : 'no_intent',
	};
}