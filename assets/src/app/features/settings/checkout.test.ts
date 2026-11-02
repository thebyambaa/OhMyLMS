import { get, patch } from '../../lib/api';
import {
	buildCheckoutPatch,
	checkoutDraft,
	dropGateway,
	loadCheckout,
	moveGateway,
	parseCheckout,
	saveCheckout,
	validateCheckout,
} from './checkout';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	patch: vi.fn(),
} ) );

const checkoutBody = {
	gateway_order: [ 'offline', 'stripe', 'qpay' ],
	gateways: [
		{ id: 'offline', title: 'Offline payment', enabled: true },
		{ id: 'stripe', title: 'Stripe', enabled: false },
		{ id: 'qpay', title: 'QPay', enabled: true },
	],
	guest_checkout: true,
	login_form: true,
	content_protection: false,
	stripe_webhook: {
		enabled: false,
		mode: 'test',
		keys_configured: false,
		webhook_endpoint: null,
		confirmation: 'return_url',
		last_confirmed_order: { order_id: 41, date: '2026-10-30T10:00:00' },
	},
	revision: 'abcdef0123456789',
	changed: [],
};

beforeEach( () => vi.clearAllMocks() );

describe( 'checkout settings model', () => {
	it( 'parses the server shape and drops nothing it needs', () => {
		const { settings } = parseCheckout( checkoutBody );
		expect( settings.gatewayOrder ).toEqual( [
			'offline',
			'stripe',
			'qpay',
		] );
		expect( settings.stripe ).toEqual( {
			enabled: false,
			mode: 'test',
			keysConfigured: false,
			webhookEndpoint: null,
			confirmation: 'return_url',
			lastConfirmedOrder: { orderId: 41, date: '2026-10-30T10:00:00' },
		} );
		expect(
			parseCheckout( { ...checkoutBody, stripe_webhook: null } ).settings
				.stripe
		).toBeNull();
	} );

	it( 'rejects malformed answers', () => {
		expect( () =>
			parseCheckout( { ...checkoutBody, guest_checkout: 'yes' } )
		).toThrow();
		expect( () =>
			parseCheckout( {
				...checkoutBody,
				stripe_webhook: { ...checkoutBody.stripe_webhook, mode: 'x' },
			} )
		).toThrow();
	} );

	it( 'sends only what changed, with the loaded revision', () => {
		const { settings } = parseCheckout( checkoutBody );
		const draft = checkoutDraft( settings );
		expect( buildCheckoutPatch( settings, draft ) ).toEqual( {} );
		const moved = {
			...draft,
			gatewayOrder: moveGateway( draft.gatewayOrder, 'qpay', -1 ),
			guestCheckout: false,
		};
		expect( buildCheckoutPatch( settings, moved ) ).toEqual( {
			gateway_order: [ 'offline', 'qpay', 'stripe' ],
			guest_checkout: false,
			revision: 'abcdef0123456789',
		} );
	} );

	it( 'moves gateways up, down and by drag and drop, never off the ends', () => {
		const order = [ 'a', 'b', 'c' ];
		expect( moveGateway( order, 'a', -1 ) ).toEqual( order );
		expect( moveGateway( order, 'c', 1 ) ).toEqual( order );
		expect( moveGateway( order, 'a', 1 ) ).toEqual( [ 'b', 'a', 'c' ] );
		expect( dropGateway( order, 'c', 'a' ) ).toEqual( [ 'c', 'a', 'b' ] );
		expect( dropGateway( order, 'a', 'c' ) ).toEqual( [ 'b', 'c', 'a' ] );
		expect( dropGateway( order, 'x', 'a' ) ).toEqual( order );
	} );

	it( 'requires every registered gateway once', () => {
		const { settings } = parseCheckout( checkoutBody );
		const draft = checkoutDraft( settings );
		expect( validateCheckout( settings, draft ) ).toEqual( {} );
		expect(
			validateCheckout( settings, {
				...draft,
				gatewayOrder: [ 'offline' ],
			} ).gateway_order
		).toBeTruthy();
	} );

	it( 'reads and patches the checkout route', async () => {
		vi.mocked( get ).mockResolvedValue( checkoutBody );
		vi.mocked( patch ).mockResolvedValue( {
			...checkoutBody,
			changed: [ 'guest_checkout' ],
		} );
		await loadCheckout();
		expect( get ).toHaveBeenCalledWith( '/ohmylms/v1/settings/checkout', {
			signal: undefined,
		} );
		const saved = await saveCheckout( { guest_checkout: false } );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/checkout',
			{ guest_checkout: false },
			{ signal: undefined }
		);
		expect( saved.changed ).toEqual( [ 'guest_checkout' ] );
	} );
} );
