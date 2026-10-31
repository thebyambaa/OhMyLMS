import { store } from '@wordpress/interactivity';
import { registerGateway } from 'ohmylms/checkout';
import { stripeIntentOutcome } from './commerce-logic.mjs';

/*
 * Stripe Payment Element for the checkout's `stripe` gateway. Stripe.js stays Stripe's hosted
 * script and is loaded only when the buyer picks Stripe. The order and PaymentIntent are created
 * by the server (`ohmylms_checkout`), which also verifies the payment on the return URL.
 */
// Server state (GatewayStripeIntents::payment_scripts()): publishableKey, currency, amount (minor
// units), returnUrl, scriptUrl, messages.
const { state } = store( 'ohmylms/stripe' );

let loader = null;
let stripe = null;
let elements = null;
let card = null;
let mounting = null;

function loadStripe() {
	if ( window.Stripe ) {
		return Promise.resolve( window.Stripe );
	}
	if ( ! loader ) {
		loader = new Promise( ( resolve, reject ) => {
			const script = document.createElement( 'script' );
			script.src = state.scriptUrl || 'https://js.stripe.com/v3/';
			script.async = true;
			script.onload = () =>
				window.Stripe
					? resolve( window.Stripe )
					: reject( new Error( state.messages?.loadFailed ) );
			script.onerror = () => {
				loader = null;
				script.remove();
				reject( new Error( state.messages?.loadFailed ) );
			};
			document.head.append( script );
		} );
	}
	return loader;
}

function showMessage( text ) {
	const message = document.getElementById( 'payment-message' );
	if ( message ) {
		message.textContent = text;
		message.style.display = text ? 'block' : 'none';
	}
}

function fallbackToCard( target ) {
	const holder = document.getElementById( 'card-element' );
	if ( card || ! holder ) {
		return;
	}
	target.style.display = 'none';
	holder.style.display = '';
	card = elements.create( 'card' );
	card.mount( holder );
}

function mount() {
	if ( ! mounting ) {
		mounting = ( async () => {
			const target = document.getElementById( 'payment-element' );
			if ( ! target ) {
				throw new Error( state.messages?.unavailable );
			}
			const Stripe = await loadStripe();
			stripe = Stripe( state.publishableKey );
			elements = stripe.elements( {
				mode: 'payment',
				amount: Number( state.amount ) || 100,
				currency: state.currency || 'usd',
				loader: 'always',
				paymentMethodCreation: 'manual',
			} );
			document
				.querySelectorAll( '#stripe-payment-element-wrapper > p' )
				.forEach( ( loading ) => {
					loading.hidden = true;
				} );
			try {
				elements
					.create( 'payment', { layout: 'accordion' } )
					.mount( target );
				// Accounts without Payment Element methods for this currency render nothing.
				setTimeout( () => {
					if ( ! target.querySelector( 'iframe' ) ) {
						fallbackToCard( target );
					}
				}, 800 );
			} catch {
				fallbackToCard( target );
			}
		} )().catch( ( error ) => {
			mounting = null;
			throw error;
		} );
	}
	return mounting;
}

async function nextAction( response ) {
	const returnUrl = response.return_url || state.returnUrl;
	const result = card
		? await stripe.handleNextAction( {
				clientSecret: response.client_secret,
			} )
		: await stripe.confirmPayment( {
				elements,
				clientSecret: response.client_secret,
				confirmParams: { return_url: returnUrl },
				redirect: 'if_required',
			} );
	if ( result.error ) {
		throw new Error( result.error.message );
	}
	const outcome = stripeIntentOutcome( result.paymentIntent, returnUrl );
	if ( 'redirect' === outcome.type ) {
		return { result: 'success', redirect: outcome.url };
	}
	if ( 'message' === outcome.type ) {
		showMessage( state.messages?.processing );
		return false;
	}
	throw new Error(
		state.messages?.[ outcome.code ] || state.messages?.failed
	);
}

registerGateway( 'stripe', {
	select() {
		mount().catch( ( error ) => showMessage( error.message ) );
	},
	async prepare() {
		await mount();
		showMessage( '' );
		if ( ! card ) {
			const { error } = await elements.submit();
			if ( error ) {
				throw new Error( error.message );
			}
		}
		const { paymentMethod, error } = await stripe.createPaymentMethod(
			card ? { type: 'card', card } : { elements }
		);
		if ( error ) {
			throw new Error( error.message );
		}
		return { payment_method: 'stripe', stripe_source: paymentMethod.id };
	},
	handle( response ) {
		return 'requires_action' === response?.result
			? nextAction( response )
			: response;
	},
} );
