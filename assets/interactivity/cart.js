import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import { apiFetch, emit } from 'ohmylms/interactivity';
import { cartRequest, cartOutcome, triggerLegacy } from './commerce-logic.mjs';

/**
 * Buy, enroll, buy-with-points and membership buttons. PHP decorates every button it renders and
 * supplies `restRoot`, `nonce` and `messages`; prices stay on the server.
 */
const { state } = store( 'ohmylms/cart', {
	actions: {
		add: withSyncEvent( ( event ) => {
			start(
				event,
				/** @type {HTMLElement} */ ( getElement().ref ),
				getContext()
			);
		} ),
	},
} );

/**
 * @typedef {import('./commerce-logic.mjs').CartContext & {loading?: boolean, added?: boolean, purchased?: boolean}} CartButtonContext
 */

/** @typedef {{courseId: number, membershipId: number, purchaseBy: string}} CartDetail */

/**
 * @param {HTMLElement} button Cart button.
 * @return {unknown} The button, wrapped in jQuery when it is on the page.
 */
const legacyButton = ( button ) =>
	'function' === typeof window.jQuery ? window.jQuery( button ) : button;

/**
 * @param {HTMLElement} button Cart button.
 * @param {string}      text   Message; empty hides it.
 */
function say( button, text ) {
	let message = /** @type {HTMLElement|null} */ ( button.nextElementSibling );
	if ( ! message || ! message.classList.contains( 'ohmylms-cart-message' ) ) {
		if ( ! text ) {
			return;
		}
		message = document.createElement( 'p' );
		message.className = 'ohmylms-cart-message';
		message.setAttribute( 'role', 'alert' );
		button.after( message );
	}
	message.textContent = text;
	message.hidden = ! text;
}

/**
 * @param {MouseEvent}        event   Click.
 * @param {HTMLElement}       button  Cart button.
 * @param {CartButtonContext} context Button context.
 */
function start( event, button, context ) {
	if (
		event.button > 0 ||
		event.metaKey ||
		event.ctrlKey ||
		event.shiftKey ||
		event.altKey
	) {
		return;
	}
	if ( context.loading ) {
		event.preventDefault();
		return;
	}
	const body = cartRequest( context );
	// Already bought, or nothing to buy: the link (course or plan page) is the answer.
	if ( context.purchased || ! body ) {
		return;
	}
	event.preventDefault();
	const detail = {
		courseId: body.course_id || 0,
		membershipId: body.membership_id || 0,
		purchaseBy: body.purchase_by || '',
	};
	if ( ! emit( button, 'cart-before-add', detail, true ) ) {
		return;
	}
	triggerLegacy( window, 'course_adding_to_cart', [
		legacyButton( button ),
		body,
	] );
	add( button, context, body, detail );
}

/**
 * @param {HTMLElement}                             button  Cart button.
 * @param {CartButtonContext}                       context Button context.
 * @param {import('./commerce-logic.mjs').CartBody} body    REST body.
 * @param {CartDetail}                              detail  Event detail.
 */
async function add( button, context, body, detail ) {
	context.loading = true;
	context.added = false;
	say( button, '' );
	let response;
	try {
		response = await apiFetch( 'ohmylms-commerce/v1/cart/items', {
			method: 'POST',
			data: body,
			root: state.restRoot,
			nonce: state.nonce,
		} );
	} catch ( error ) {
		context.loading = false;
		const message =
			/** @type {Error} */ ( error ).message || state.messages?.error;
		emit( button, 'cart-error', { ...detail, message } );
		say( button, message );
		return;
	}
	context.loading = false;
	context.added = true;
	triggerLegacy( window, 'course_added_to_cart', [
		response,
		legacyButton( button ),
	] );
	const outcome = cartOutcome( response );
	if ( 'redirect' === outcome.type ) {
		emit( button, 'cart-added', { ...detail, redirectUrl: outcome.url } );
		window.location.assign( outcome.url );
		return;
	}
	const message = outcome.message || state.messages?.error;
	emit( button, 'cart-error', { ...detail, message } );
	say( button, message );
}

/**
 * Buttons that arrive in HTML injected after hydration (load more, filters) keep their directives
 * but are not hydrated. This delegated listener runs the same flow for them, mirroring the
 * `loading`/`added` classes the directives would bind.
 */
document.addEventListener( 'click', ( event ) => {
	if ( event.defaultPrevented || ! ( event.target instanceof Element ) ) {
		return;
	}
	const button = /** @type {HTMLElement|null} */ (
		event.target.closest( '[data-wp-interactive="ohmylms/cart"]' )
	);
	if ( button ) {
		fallback( event, button );
	}
} );

/**
 * @param {MouseEvent}  event  Click.
 * @param {HTMLElement} button Unhydrated cart button.
 */
function fallback( event, button ) {
	/** @type {CartButtonContext} */
	let flags;
	try {
		flags = JSON.parse( button.dataset.wpContext || '{}' );
	} catch {
		return;
	}
	flags.loading = button.classList.contains( 'loading' );
	const context = new Proxy( flags, {
		set( target, key, value ) {
			/** @type {Record<string|symbol, unknown>} */ ( target )[ key ] =
				value;
			if ( 'loading' === key || 'added' === key ) {
				button.classList.toggle( key, Boolean( value ) );
			}
			return true;
		},
	} );
	start( event, button, context );
}
