import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import { ajaxPost, apiFetch, emit } from 'ohmylms/interactivity';
import {
	checkoutOutcome,
	couponCode,
	gatewayFromInputId,
	isEuCountry,
	noticeModifiers,
	requiredFields,
	stateField,
	triggerLegacy,
} from './commerce-logic.mjs';

/*
 * Checkout page. One stable root (`.ohmylms-checkout-form-outer`) carries delegated handlers because
 * the order review is replaced by server-rendered fragments after coupon and tax changes. Reactive
 * bindings sit only on leaves whose children are never replaced (gateway rows, place-order buttons,
 * VAT row, log-in toggle). The server stays authoritative for totals, tax, coupons and validation.
 */

// Gateway handler registry. Classic gateway scripts register through the `ohmylms-checkout`
// inline shim before this module runs; both share window.PaymentGatewayHandlers.
window.PaymentGatewayHandlers = window.PaymentGatewayHandlers || {};
if ( 'function' !== typeof window.registerPaymentGatewayHandler ) {
	window.registerPaymentGatewayHandler = ( id, handler ) => {
		window.PaymentGatewayHandlers[ id ] = handler;
	};
}

/**
 * Register a gateway handler: optional `select(form)`, `prepare(form)` (resolve extra POST fields,
 * throw to abort with a message), `handle(response, form)` (resolve a replacement response, or
 * `false` when handled), and the legacy `open(response, $form)` / `resume($form)` hooks.
 *
 * @param {string} id      Gateway id.
 * @param {Object} handler Handler.
 */
export function registerGateway( id, handler ) {
	window.registerPaymentGatewayHandler( id, handler );
}

const gatewayHandler = ( id ) => window.PaymentGatewayHandlers[ id ];
const legacyForm = ( form ) =>
	'function' === typeof window.jQuery ? window.jQuery( form ) : form;
const checkoutForm = () =>
	document.querySelector(
		'form#ohmylms-checkout-form, form.ohmylms-checkout-form'
	);

// Server state (Commerce::checkout_state()): ajaxUrl, restRoot, restNonce, nonces, requiredFields,
// serverRequired, taxEnabled, country, gateway, busy, loginOpen, messages.
const { state } = store( 'ohmylms/checkout', {
	state: {
		get loaderDisplay() {
			return state.busy ? 'inline-block' : '';
		},
		get vatVisible() {
			return isEuCountry( state.country );
		},
		get vatDisplay() {
			return state.vatVisible ? '' : 'none';
		},
		get isGatewaySelected() {
			return getContext().gateway === state.gateway;
		},
		get paymentBoxDisplay() {
			return state.isGatewaySelected ? 'block' : '';
		},
	},
	actions: {
		submit: withSyncEvent( ( event ) => {
			const form = event.target;
			if ( ! ( form instanceof HTMLFormElement ) ) {
				return;
			}
			if ( form === checkoutForm() ) {
				event.preventDefault();
				placeOrder( form );
			} else if ( form.matches( 'form.checkout_coupon' ) ) {
				event.preventDefault();
				applyCoupon( form );
			}
		} ),
		click: withSyncEvent( ( event ) => {
			const target =
				event.target instanceof Element ? event.target : null;
			const login = target?.closest( 'a.showlogin' );
			const coupon = target?.closest( '.ohmylms-remove-coupon' );
			if ( login ) {
				event.preventDefault();
				toggleLogin( getElement().ref );
			} else if ( coupon ) {
				event.preventDefault();
				removeCoupon( coupon.dataset.coupon || '' );
			}
		} ),
		change( event ) {
			const field = event.target;
			if ( ! field?.name || field.form !== checkoutForm() ) {
				return;
			}
			if ( 'payment_method' === field.name && field.checked ) {
				selectGateway( field.value || gatewayFromInputId( field.id ) );
			} else if ( 'country' === field.name ) {
				state.country = field.value;
				if ( ! state.vatVisible ) {
					const vat = field.form?.elements.namedItem( 'vat_number' );
					if ( vat ) {
						vat.value = '';
					}
				}
				loadStates( field.value );
				recalculate();
			} else if (
				'state' === field.name ||
				'vat_number' === field.name
			) {
				recalculate();
			}
		},
		input( event ) {
			const field = event.target;
			if (
				field?.matches?.( '.ohmylms-checkout-coupon .apply-coupon' )
			) {
				field
					.closest( '.ohmylms-checkout-coupon' )
					.classList.toggle(
						'active',
						'' !== couponCode( field.value )
					);
			}
			fold( field, true );
		},
		focusin( event ) {
			fold( event.target, true );
		},
		focusout( event ) {
			fold( event.target, false );
		},
		copyOrderId: withSyncEvent( ( event ) => {
			event.preventDefault();
			copyOrderId( getElement().ref );
		} ),
	},
	callbacks: {
		init() {
			const form = checkoutForm();
			const checked = form?.querySelector(
				'input[name="payment_method"]:checked'
			);
			if ( checked ) {
				state.gateway = checked.value;
				gatewayHandler( checked.value )?.select?.( form );
			}
			if ( state.taxEnabled ) {
				recalculate();
			}
		},
		syncRequired() {
			const fields = requiredFields(
				state.requiredFields,
				state.gateway,
				state.serverRequired
			);
			for ( const [ key, required ] of Object.entries( fields ) ) {
				setRequired( key, required );
			}
		},
	},
} );

/**
 * Floating labels: a row is folded while focused or filled.
 * @param field
 * @param active
 */
function fold( field, active ) {
	if ( ! field?.matches?.( '.ohmylms-input-text' ) ) {
		return;
	}
	const row = field.closest( '.ohmylms-form-row' );
	if ( row ) {
		row.classList.toggle(
			'ohmylms-folded',
			active || '' !== String( field.value ).trim()
		);
	}
}

function setRequired( key, required ) {
	const row = document.getElementById( `${ key }_field` );
	const input = row?.querySelector( 'input, select' );
	if ( ! input ) {
		return;
	}
	const label = row.querySelector( 'label' );
	input.required = required;
	row.classList.toggle( 'validate-required', required );
	label?.querySelectorAll( '.optional' ).forEach( ( optional ) => {
		optional.hidden = required;
	} );
	let marker = label?.querySelector( 'abbr.required' );
	if ( required && label && ! marker ) {
		marker = document.createElement( 'abbr' );
		marker.className = 'required';
		marker.title = state.messages?.required || 'required';
		marker.textContent = '*';
		label.append( ' ', marker );
	} else if ( ! required && marker ) {
		marker.remove();
	}
}

function selectGateway( id ) {
	const previous = state.gateway;
	state.gateway = id;
	if ( previous === id ) {
		return;
	}
	const form = checkoutForm();
	triggerLegacy( window, 'payment_method_selected' );
	if ( form ) {
		emit( form, 'checkout-gateway-changed', { gateway: id, previous } );
	}
	gatewayHandler( id )?.select?.( form );
}

function toggleLogin( root ) {
	const login =
		root.querySelector( '.ohmylms-form-login' ) ||
		document.querySelector( '.ohmylms-form-login' );
	root.closest( '.ohmylms' )
		?.querySelectorAll( '.ohmylms-notices-wrapper' )
		.forEach( ( region ) => region.replaceChildren() );
	state.loginOpen = ! state.loginOpen;
	if ( login ) {
		login.style.display = state.loginOpen ? '' : 'none';
	}
}

/* Notices: server notices are trusted HTML from ohmylmse_print_notices(); client text is escaped. */

function noticeRegion() {
	let region = document.querySelector( '.ohmylms-notices-wrapper' );
	if ( ! region ) {
		region = document.createElement( 'div' );
		region.className = 'ohmylms-notices-wrapper';
		document
			.querySelector( '[data-wp-interactive="ohmylms/checkout"]' )
			?.before( region );
	}
	return region;
}

function clearNotices() {
	document
		.querySelectorAll( '.ohmylms-notices-wrapper' )
		.forEach( ( region ) => region.replaceChildren() );
}

function errorMarkup( text ) {
	const wrapper = document.createElement( 'div' );
	wrapper.className = 'ohmylms-error-notices';
	const group = document.createElement( 'div' );
	group.className = 'ohmylms-NoticeGroup';
	const list = document.createElement( 'ul' );
	list.className = 'ohmylms-error';
	list.setAttribute( 'role', 'alert' );
	const item = document.createElement( 'li' );
	item.textContent = text;
	list.append( item );
	group.append( list );
	wrapper.append( group );
	return wrapper;
}

/**
 * Show a checkout notice: a string is trusted server HTML, an Error/object shows its text.
 *
 * @param {string|{message:string}} notice Notice.
 */
export function showNotice( notice ) {
	const region = noticeRegion();
	region.replaceChildren();
	const box = document.createElement( 'div' );
	box.className = 'ohmylms-checkout-notice';
	if ( 'string' === typeof notice && notice ) {
		box.innerHTML = notice;
	} else {
		box.append(
			errorMarkup(
				( notice && notice.message ) || state.messages?.error || ''
			)
		);
	}
	const count = box.querySelectorAll(
		'.ohmylms-NoticeGroup .ohmylms-error li'
	).length;
	for ( const [ name, on ] of Object.entries( noticeModifiers( count ) ) ) {
		box.classList.toggle( name, on );
	}
	region.prepend( box );
	const reduce = window.matchMedia?.(
		'(prefers-reduced-motion: reduce)'
	).matches;
	window.scrollTo( {
		top: region.getBoundingClientRect().top + window.scrollY - 100,
		behavior: reduce ? 'auto' : 'smooth',
	} );
}

/* Order submission through the existing `ohmylms_checkout` AJAX action (gateways rely on wp_doing_ajax()). */

function postCheckout( form, extra ) {
	const data = new FormData( form );
	for ( const [ key, value ] of Object.entries( extra || {} ) ) {
		data.set( key, value );
	}
	data.set( 'action', 'ohmylms_checkout' );
	return ajaxPost( state.ajaxUrl, data );
}

async function placeOrder( form ) {
	if ( state.busy ) {
		return;
	}
	clearNotices();
	const gateway = state.gateway;
	if ( ! emit( form, 'checkout-before-submit', { gateway }, true ) ) {
		return;
	}
	const handler = gatewayHandler( gateway );
	if ( handler?.resume?.( legacyForm( form ) ) ) {
		return;
	}
	state.busy = true;
	try {
		const extra = handler?.prepare ? await handler.prepare( form ) : {};
		let response = await postCheckout( form, extra );
		if ( handler?.handle ) {
			response = await handler.handle( response, form );
			if ( false === response ) {
				state.busy = false;
				return;
			}
		}
		finish( form, response, handler, gateway );
	} catch ( error ) {
		state.busy = false;
		showNotice( error );
		emit( form, 'checkout-error', {
			gateway,
			message: error?.message || '',
		} );
	}
}

function finish( form, response, handler, gateway ) {
	const outcome = checkoutOutcome( response );
	if ( 'pending' === outcome.type ) {
		state.busy = false;
		handler?.open?.( response, legacyForm( form ) );
		emit( form, 'checkout-pending', { gateway } );
		return;
	}
	if ( 'redirect' === outcome.type ) {
		emit( form, 'checkout-complete', { gateway, redirect: outcome.url } );
		window.location.assign( outcome.url );
		return;
	}
	state.busy = false;
	showNotice( outcome.message || { message: state.messages?.error } );
	emit( form, 'checkout-error', { gateway, message: outcome.message } );
}

/* Order review fragments (coupons and tax) are rendered by the server. */

function applyFragments( fragments, reason ) {
	for ( const [ selector, html ] of Object.entries( fragments || {} ) ) {
		document.querySelectorAll( selector ).forEach( ( element ) => {
			element.innerHTML = html;
		} );
	}
	const total = document.querySelector(
		'.ohmylms-checkout-review-order .order-total td'
	);
	if ( total ) {
		document
			.querySelectorAll( '.ohmylms-mobile-order-button .total-price' )
			.forEach( ( element ) => {
				element.innerHTML = total.innerHTML;
			} );
	}
	const root = document.querySelector(
		'[data-wp-interactive="ohmylms/checkout"]'
	);
	if ( root ) {
		emit( root, 'checkout-totals-updated', { reason } );
	}
}

async function applyCoupon( form ) {
	const code = couponCode( form.elements.namedItem( 'coupon_code' )?.value );
	if ( ! code || form.classList.contains( 'processing' ) ) {
		return;
	}
	form.classList.add( 'processing' );
	try {
		const response = await ajaxPost( state.ajaxUrl, {
			action: 'ohmylms_apply_coupon',
			security: state.nonces?.applyCoupon,
			coupon_code: code,
		} );
		clearNotices();
		if ( response?.success && response.data?.fragments ) {
			applyFragments( response.data.fragments, 'coupon-applied' );
		} else {
			showNotice(
				response?.data?.notice ||
					response?.data?.message || {
						message: state.messages?.error,
					}
			);
		}
	} catch {
		showNotice( { message: state.messages?.error } );
	} finally {
		form.classList.remove( 'processing' );
	}
}

async function removeCoupon( code ) {
	try {
		const response = await ajaxPost( state.ajaxUrl, {
			action: 'ohmylms_remove_coupon',
			security: state.nonces?.removeCoupon,
			coupon_code: code,
		} );
		if ( response?.data?.fragments ) {
			applyFragments( response.data.fragments, 'coupon-removed' );
		}
	} catch {
		showNotice( { message: state.messages?.error } );
	}
}

let taxRequest = 0;
async function recalculate() {
	const form = checkoutForm();
	if ( ! state.taxEnabled || ! form ) {
		return;
	}
	const value = ( name ) => form.elements.namedItem( name )?.value || '';
	const ticket = ++taxRequest;
	try {
		const response = await ajaxPost( state.ajaxUrl, {
			action: 'ohmylms_calculate_tax',
			nonce: state.nonces?.tax,
			country: value( 'country' ),
			state: value( 'state' ),
			vat_number: value( 'vat_number' ),
		} );
		if ( ticket === taxRequest && response?.success ) {
			applyFragments( response.data?.fragments, 'tax' );
		}
	} catch {
		// The server recalculates tax when the order is placed.
	}
}

let statesRequest = 0;
async function loadStates( country ) {
	if ( ! country ) {
		return;
	}
	const ticket = ++statesRequest;
	let states = [];
	try {
		states = await apiFetch(
			`ohmylms-commerce/v1/countries/${ encodeURIComponent(
				country
			) }/states`,
			{ root: state.restRoot, nonce: state.restNonce }
		);
	} catch {
		states = [];
	}
	if ( ticket === statesRequest ) {
		renderStateField( stateField( states ) );
	}
}

function renderStateField( model ) {
	const current = document.getElementById( 'state' );
	const row = document.getElementById( 'state_field' );
	if ( ! current || ! row ) {
		return;
	}
	const marker = row.querySelector( '.required' );
	if ( 'select' === model.type ) {
		let select = current;
		if ( 'SELECT' !== current.tagName ) {
			select = document.createElement( 'select' );
			select.id = 'state';
			select.name = 'state';
			select.className = 'ohmylms-input-select ohmylms-input-text';
			current.replaceWith( select );
		}
		select.replaceChildren(
			...model.options.map(
				( option ) => new Option( option.label, option.value )
			)
		);
		row.classList.add( 'ohmylms-folded' );
		if ( marker ) {
			marker.style.display = 'inline';
		}
	} else if ( 'INPUT' !== current.tagName ) {
		const input = document.createElement( 'input' );
		input.type = 'text';
		input.id = 'state';
		input.name = 'state';
		input.className = 'ohmylms-input-text';
		current.replaceWith( input );
		row.classList.remove( 'ohmylms-folded', 'validate-required' );
		if ( marker ) {
			marker.style.display = 'none';
		}
	}
}

/* Thank-you page: copy the order number. */

async function copyOrderId( button ) {
	const cell = button.closest( 'td' ) || button.parentElement;
	const text = (
		cell?.querySelector( '.thankyou-order-id-text' )?.textContent || ''
	).trim();
	try {
		await window.navigator.clipboard.writeText( text );
	} catch {
		const scratch = document.createElement( 'textarea' );
		scratch.value = text;
		scratch.style.position = 'fixed';
		scratch.style.left = '-9999px';
		document.body.append( scratch );
		scratch.select();
		document.execCommand( 'copy' );
		scratch.remove();
	}
	const alert = cell?.querySelector( '.thankyou-copy-alert' );
	if ( alert ) {
		alert.style.display = 'inline-block';
		setTimeout( () => {
			alert.style.display = '';
		}, 1500 );
	}
}
