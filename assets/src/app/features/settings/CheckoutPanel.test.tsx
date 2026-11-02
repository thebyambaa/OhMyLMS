import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { get, patch } from '../../lib/api';
import { CheckoutPanel } from './CheckoutPanel';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	patch: vi.fn(),
} ) );

const body = {
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
		last_confirmed_order: null,
	},
	revision: 'abcdef0123456789',
	changed: [],
};

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( get ).mockResolvedValue( body );
} );

describe( 'checkout settings panel', () => {
	it( 'shows the gateway order, the switches and the read-only Stripe status', async () => {
		render( <CheckoutPanel active onDirty={ vi.fn() } /> );
		const order = await screen.findByRole( 'list', {
			name: 'Payment method order',
		} );
		expect(
			within( order )
				.getAllByRole( 'listitem' )
				.map( ( item ) => item.querySelector( 'strong' )?.textContent )
		).toEqual( [ 'Offline payment', 'Stripe', 'QPay' ] );
		expect(
			screen.getByRole( 'button', { name: 'Move Offline payment up' } )
		).toHaveAttribute( 'aria-disabled', 'true' );
		expect(
			screen.getByRole( 'checkbox', { name: 'Guest checkout' } )
		).toBeChecked();
		expect( screen.getByText( 'None registered' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Test (sandbox)' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Save changes' } )
		).toHaveAttribute( 'aria-disabled', 'true' );
	} );

	it( 'saves only the changes with the loaded revision and reports dirty state', async () => {
		const onDirty = vi.fn();
		vi.mocked( patch ).mockResolvedValue( {
			...body,
			gateway_order: [ 'offline', 'qpay', 'stripe' ],
			guest_checkout: false,
			revision: '0123456789abcdef',
			changed: [ 'gateway_order', 'guest_checkout' ],
		} );
		const user = userEvent.setup();
		render( <CheckoutPanel active onDirty={ onDirty } /> );
		await user.click(
			await screen.findByRole( 'button', { name: 'Move QPay up' } )
		);
		await user.click(
			screen.getByRole( 'checkbox', { name: 'Guest checkout' } )
		);
		expect( onDirty ).toHaveBeenLastCalledWith( true );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/checkout',
			{
				gateway_order: [ 'offline', 'qpay', 'stripe' ],
				guest_checkout: false,
				revision: 'abcdef0123456789',
			},
			expect.anything()
		);
		expect(
			await screen.findByText( 'Settings saved.', notice )
		).toBeInTheDocument();
		expect( onDirty ).toHaveBeenLastCalledWith( false );
	} );

	it( 'warns when visitors could neither buy as guests nor log in', async () => {
		const user = userEvent.setup();
		render( <CheckoutPanel active onDirty={ vi.fn() } /> );
		await user.click(
			await screen.findByRole( 'checkbox', { name: 'Guest checkout' } )
		);
		await user.click(
			screen.getByRole( 'checkbox', { name: 'Login form on checkout' } )
		);
		expect(
			screen.getByText(
				/see only a link to the WordPress login page/,
				notice
			)
		).toBeInTheDocument();
	} );
} );
