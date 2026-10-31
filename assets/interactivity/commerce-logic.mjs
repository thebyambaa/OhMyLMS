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

export function isEuCountry( country ) {
	return EU_COUNTRIES.includes( String( country || '' ).toUpperCase() );
}

const positive = ( value ) => {
	const number = Number.parseInt( value, 10 );
	return Number.isFinite( number ) && number > 0 ? number : 0;
};

/**
 * REST body for one cart button, or null when the button names nothing to buy.
 *
 * @param {Object} context Button context rendered by PHP.
 */
export function cartRequest( context ) {
	const courseId = positive( context?.courseId );
	const membershipId = positive( context?.membershipId );
	if ( ! courseId && ! membershipId ) {
		return null;
	}
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
 * What a cart answer means for the button: follow a redirect or show a message.
 *
 * @param {Object} response Cart endpoint body.
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

/** `payment_method_<id>` radio id to gateway id. */
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
 * Normalise an `ohmylms_checkout` answer: pending gateway payment, redirect, or error notice.
 *
 * @param {Object} response Checkout AJAX body.
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

/** Trimmed coupon code; empty means there is nothing to send. */
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
 * @param {string} url    Base URL.
 * @param {Object} params Parameters.
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
 * What to do with a confirmed Stripe PaymentIntent.
 *
 * @param {Object} intent    Stripe PaymentIntent.
 * @param {string} returnUrl Order thank-you URL.
 */
export function stripeIntentOutcome( intent, returnUrl ) {
	const status = intent?.status;
	if ( 'succeeded' === status || 'requires_capture' === status ) {
		return returnUrl
			? {
					type: 'redirect',
					url: withQuery( returnUrl, {
						payment_intent: intent.id,
						payment_intent_client_secret: intent.client_secret,
					} ),
			  }
			: { type: 'error', code: 'missing_return_url' };
	}
	if ( 'processing' === status ) {
		return { type: 'message', code: 'processing' };
	}
	if (
		'requires_action' === status &&
		intent.next_action?.redirect_to_url?.url
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

/**
 * Fire a legacy jQuery body event when jQuery is on the page (gateway add-ons still listen).
 *
 * @param {Window} win  Window.
 * @param {string} name Event name.
 * @param {Array}  args Extra handler arguments.
 */
export function triggerLegacy( win, name, args = [] ) {
	const jQuery = win && win.jQuery;
	if ( 'function' !== typeof jQuery || ! win.document ) {
		return false;
	}
	jQuery( win.document.body ).trigger( name, args );
	return true;
}
