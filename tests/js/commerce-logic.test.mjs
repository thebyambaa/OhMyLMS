import test from 'node:test';
import assert from 'node:assert/strict';
import {
	cartOutcome,
	cartRequest,
	checkoutOutcome,
	couponCode,
	gatewayFromInputId,
	isEuCountry,
	noticeModifiers,
	requiredFields,
	stateField,
	stripeIntentOutcome,
	triggerLegacy,
	withQuery,
} from '../../assets/interactivity/commerce-logic.mjs';

test( 'cart buttons send only what the server needs', () => {
	assert.deepEqual( cartRequest( { courseId: 12, quantity: '2' } ), {
		course_id: 12,
		quantity: 2,
	} );
	assert.deepEqual(
		cartRequest( { courseId: 12, purchaseBy: 'point', quantity: 0 } ),
		{ course_id: 12, quantity: 1, purchase_by: 'point' }
	);
	assert.deepEqual( cartRequest( { courseId: 3, membershipId: 9 } ), {
		membership_id: 9,
		quantity: 1,
	} );
	assert.equal( cartRequest( { courseId: 'x' } ), null );
	assert.equal( cartRequest( {} ), null );
	assert.equal( cartRequest( null ), null );
} );

test( 'cart answers redirect only on success with a URL', () => {
	assert.deepEqual(
		cartOutcome( { status: 'success', redirect_url: '/checkout/?nocache=1' } ),
		{ type: 'redirect', url: '/checkout/?nocache=1' }
	);
	assert.deepEqual(
		cartOutcome( { status: 'error', message: 'Not purchasable' } ),
		{ type: 'error', message: 'Not purchasable' }
	);
	assert.deepEqual( cartOutcome( { status: 'success' } ), {
		type: 'error',
		message: '',
	} );
	assert.deepEqual(
		cartOutcome( { success: false, data: { message: 'Invalid' } } ),
		{ type: 'error', message: 'Invalid' }
	);
} );

test( 'VAT field shows for EU countries only', () => {
	assert.equal( isEuCountry( 'DE' ), true );
	assert.equal( isEuCountry( 'de' ), true );
	assert.equal( isEuCountry( 'US' ), false );
	assert.equal( isEuCountry( 'GB' ), false );
	assert.equal( isEuCountry( '' ), false );
	assert.equal( isEuCountry( undefined ), false );
} );

test( 'gateway switching escalates and relaxes only managed fields', () => {
	const map = { qpay: [ 'phone' ], offline: [], card: [ 'phone', 'city' ] };
	assert.deepEqual( requiredFields( map, 'qpay' ), {
		phone: true,
		city: false,
	} );
	assert.deepEqual( requiredFields( map, 'offline' ), {
		phone: false,
		city: false,
	} );
	assert.deepEqual( requiredFields( map, 'card' ), {
		phone: true,
		city: true,
	} );
	// A field the server always requires is never relaxed by a gateway switch.
	assert.deepEqual( requiredFields( map, 'offline', [ 'phone' ] ), {
		phone: true,
		city: false,
	} );
	assert.deepEqual( requiredFields( {}, 'qpay' ), {} );
	assert.deepEqual( requiredFields( null, '' ), {} );
	assert.equal( gatewayFromInputId( 'payment_method_stripe' ), 'stripe' );
	assert.equal( gatewayFromInputId( undefined ), '' );
} );

test( 'checkout answers map to pending, redirect or error', () => {
	assert.deepEqual(
		checkoutOutcome( { result: 'success', payment_status: 'pending' } ),
		{ type: 'pending' }
	);
	assert.deepEqual(
		checkoutOutcome( { result: 'success', redirect: '/thanks/1' } ),
		{ type: 'redirect', url: '/thanks/1' }
	);
	assert.deepEqual(
		checkoutOutcome( { success: true, redirect: '/funnel/step/1' } ),
		{ type: 'redirect', url: '/funnel/step/1' }
	);
	assert.deepEqual(
		checkoutOutcome( {
			result: 'failure',
			message: '<ul class="ohmylms-error"><li>x</li></ul>',
			reload: true,
		} ),
		{
			type: 'error',
			message: '<ul class="ohmylms-error"><li>x</li></ul>',
			reload: true,
		}
	);
	assert.deepEqual(
		checkoutOutcome( { status: 'error', message: 'No cart item found.' } ),
		{ type: 'error', message: 'No cart item found.', reload: false }
	);
	assert.deepEqual( checkoutOutcome( { result: 'success' } ), {
		type: 'error',
		message: '',
		reload: false,
	} );
	assert.deepEqual( checkoutOutcome( null ), { type: 'error', message: '' } );
} );

test( 'notice modifiers follow the error count', () => {
	assert.deepEqual( noticeModifiers( 0 ), {
		'single-error': false,
		'more-then-two-error': false,
	} );
	assert.deepEqual( noticeModifiers( 1 ), {
		'single-error': true,
		'more-then-two-error': false,
	} );
	assert.deepEqual( noticeModifiers( 3 ), {
		'single-error': false,
		'more-then-two-error': true,
	} );
} );

test( 'coupon codes are trimmed and empty codes are not sent', () => {
	assert.equal( couponCode( '  SAVE10 ' ), 'SAVE10' );
	assert.equal( couponCode( '   ' ), '' );
	assert.equal( couponCode( undefined ), '' );
} );

test( 'state field becomes a select only when the country has states', () => {
	assert.deepEqual( stateField( [] ), { type: 'text', options: [] } );
	assert.deepEqual( stateField( null ), { type: 'text', options: [] } );
	assert.deepEqual( stateField( [ { code: 'CA', title: 'California' } ] ), {
		type: 'select',
		options: [ { value: 'CA', label: 'California' } ],
	} );
} );

test( 'Stripe intents redirect to the order page with the intent reference', () => {
	assert.deepEqual(
		stripeIntentOutcome(
			{ id: 'pi_1', client_secret: 'cs_1', status: 'succeeded' },
			'http://800.local/checkout/ohmylms-order-received/5/?key=k'
		),
		{
			type: 'redirect',
			url: 'http://800.local/checkout/ohmylms-order-received/5/?key=k&payment_intent=pi_1&payment_intent_client_secret=cs_1',
		}
	);
	assert.equal(
		stripeIntentOutcome( { status: 'processing' }, '/x' ).type,
		'message'
	);
	assert.deepEqual(
		stripeIntentOutcome(
			{
				status: 'requires_action',
				next_action: {
					redirect_to_url: { url: 'https://hooks.stripe.com/3ds' },
				},
			},
			'/x'
		),
		{ type: 'redirect', url: 'https://hooks.stripe.com/3ds' }
	);
	assert.deepEqual(
		stripeIntentOutcome( { status: 'requires_payment_method' }, '/x' ),
		{ type: 'error', code: 'payment_failed' }
	);
	assert.deepEqual( stripeIntentOutcome( undefined, '/x' ), {
		type: 'error',
		code: 'no_intent',
	} );
	assert.deepEqual(
		stripeIntentOutcome( { id: 'pi', status: 'succeeded' }, '' ),
		{ type: 'error', code: 'missing_return_url' }
	);
} );

test( 'query helper keeps relative URLs relative', () => {
	assert.equal(
		withQuery( '/checkout/?a=1', { b: 2, c: '' } ),
		'/checkout/?a=1&b=2'
	);
} );

test( 'legacy jQuery events fire only when jQuery is present', () => {
	const calls = [];
	const body = {};
	const win = {
		document: { body },
		jQuery: ( target ) => ( {
			trigger: ( name, args ) => calls.push( [ target, name, args ] ),
		} ),
	};
	assert.equal( triggerLegacy( win, 'course_added_to_cart', [ 1 ] ), true );
	assert.deepEqual( calls, [ [ body, 'course_added_to_cart', [ 1 ] ] ] );
	assert.equal( triggerLegacy( { document: { body } }, 'x' ), false );
} );
