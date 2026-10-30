import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import { features } from '../registry';
import * as api from './subscriptionApi';
import {
	activeSubscription,
	cancelledDetail,
	pausedSubscription,
	subscriptionDetail,
} from './subscriptionFixtures';
import {
	subscriptionDetail as detailRoute,
	subscriptions,
} from './subscriptions';

vi.mock( './subscriptionApi', () => ( {
	listSubscriptions: vi.fn(),
	getSubscription: vi.fn(),
	runSubscriptionAction: vi.fn(),
} ) );

const as = {
	listSubscriptions: vi.mocked( api.listSubscriptions ),
	getSubscription: vi.mocked( api.getSubscription ),
	runSubscriptionAction: vi.mocked( api.runSubscriptionAction ),
};

function show( route: string, capabilities = { manage_options: true } ) {
	return renderShell( [ subscriptions, detailRoute ], {
		route,
		capabilities,
	} );
}
const noticeOptions = { selector: '.components-notice__content' };
function failure( status: number, code: string, message: string ) {
	return new ApiError( { status, code, message } );
}
const page = < T, >( items: T[], totalPages = 1 ) => ( {
	items,
	total: items.length,
	totalPages,
} );

beforeEach( () => {
	vi.clearAllMocks();
	as.listSubscriptions.mockResolvedValue(
		page( [ activeSubscription, pausedSubscription ] )
	);
	as.getSubscription.mockResolvedValue( subscriptionDetail );
} );

describe( 'subscription routes', () => {
	it( 'are registered with the legacy extension routes', () => {
		expect( features ).toContain( subscriptions );
		expect( features ).toContain( detailRoute );
		expect( subscriptions.extensionRoute ).toBe( '/subscriptions' );
		expect( detailRoute.extensionRoute ).toBe( '/subscription-edit/:id' );
		expect( detailRoute.hidden ).toBe( true );
		expect( subscriptions.capability ).toBe( 'manage_options' );
	} );

	it( 'are hidden and make no requests without manage_options', () => {
		show( '/subscriptions', {} as { manage_options: boolean } );
		expect(
			screen.queryByRole( 'link', { name: 'Subscriptions' } )
		).not.toBeInTheDocument();
		expect( as.listSubscriptions ).not.toHaveBeenCalled();
	} );
} );

describe( 'subscription list', () => {
	it( 'shows a named table with links, names, plan and money, but no email', async () => {
		show( '/subscriptions' );
		const table = await screen.findByRole( 'table', {
			name: 'Subscriptions',
		} );
		expect(
			within( table ).getByRole( 'link', { name: 'Subscription #501' } )
		).toHaveAttribute(
			'href',
			expect.stringContaining( '/subscriptions/501' )
		);
		expect( within( table ).getByText( 'Ada Lovelace' ) ).toBeVisible();
		expect(
			within( table ).getAllByText( 'Gold membership' )
		).toHaveLength( 2 );
		expect(
			within( table ).getByText( 'Paused (on hold)' )
		).toBeInTheDocument();
		expect(
			within( table ).getByText( /25\.00 · Every 2 weeks/ )
		).toBeInTheDocument();
		expect(
			screen.getByText( '2 subscriptions found.' )
		).toBeInTheDocument();
		expect( screen.queryByText( /@example\.com/ ) ).not.toBeInTheDocument();
	} );

	it( 'searches, filters by status and sorts', async () => {
		const user = userEvent.setup();
		show( '/subscriptions' );
		await screen.findByText( 'Ada Lovelace' );
		await user.type(
			screen.getByRole( 'textbox', { name: 'Search subscriptions' } ),
			'ada{Enter}'
		);
		await waitFor( () =>
			expect( as.listSubscriptions ).toHaveBeenLastCalledWith(
				expect.objectContaining( { search: 'ada', page: 1 } ),
				expect.any( AbortSignal )
			)
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Status' } ),
			'on-hold'
		);
		await waitFor( () =>
			expect( as.listSubscriptions ).toHaveBeenLastCalledWith(
				expect.objectContaining( { status: 'on-hold', page: 1 } ),
				expect.any( AbortSignal )
			)
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Sort by' } ),
			'asc'
		);
		await waitFor( () =>
			expect( as.listSubscriptions ).toHaveBeenLastCalledWith(
				expect.objectContaining( { order: 'asc', orderby: 'date' } ),
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'pages through results', async () => {
		const user = userEvent.setup();
		as.listSubscriptions.mockResolvedValue(
			page( [ activeSubscription ], 3 )
		);
		show( '/subscriptions' );
		await screen.findByText( 'Ada Lovelace' );
		await user.click( screen.getByRole( 'button', { name: 'Next page' } ) );
		await waitFor( () =>
			expect( as.listSubscriptions ).toHaveBeenLastCalledWith(
				expect.objectContaining( { page: 2 } ),
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'shows an empty state', async () => {
		as.listSubscriptions.mockResolvedValue( page( [] ) );
		show( '/subscriptions' );
		expect(
			await screen.findByText( 'No subscriptions found.' )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );

	it( 'shows a server error and retries', async () => {
		const user = userEvent.setup();
		as.listSubscriptions.mockRejectedValueOnce(
			failure( 500, 'x', 'The database is unavailable.' )
		);
		show( '/subscriptions' );
		expect(
			await screen.findByText(
				/The database is unavailable\./,
				noticeOptions
			)
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect( await screen.findByText( 'Ada Lovelace' ) ).toBeInTheDocument();
	} );

	it( 'shows a permission error without a retry', async () => {
		as.listSubscriptions.mockRejectedValue(
			failure(
				403,
				'ohmylms_subscription_cannot_view',
				'Sorry, you are not allowed to view subscriptions.'
			)
		);
		show( '/subscriptions' );
		expect(
			await screen.findByText(
				/not allowed to view subscriptions/,
				noticeOptions
			)
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
	} );
} );

describe( 'subscription details', () => {
	it( 'shows plan, customer name and email, schedule, orders and notes', async () => {
		show( '/subscriptions/501' );
		expect(
			await screen.findByRole( 'heading', { name: 'Subscription #501' } )
		).toBeInTheDocument();
		expect( as.getSubscription ).toHaveBeenCalledWith(
			501,
			expect.any( AbortSignal )
		);
		expect( screen.getByText( 'Gold membership' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Ada Lovelace' ) ).toBeInTheDocument();
		expect( screen.getByText( 'ada@example.com' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Offline' ) ).toBeInTheDocument();
		const orders = screen.getByRole( 'table', { name: 'Related orders' } );
		expect(
			within( orders ).getByRole( 'link', { name: 'Order #320' } )
		).toHaveAttribute( 'href', expect.stringContaining( '/orders/320' ) );
		expect( within( orders ).getByText( 'Renewal' ) ).toBeInTheDocument();
		expect(
			within( orders ).getByText( 'Original order' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'list', { name: 'Subscription notes' } )
		).toHaveTextContent( 'Subscription record created.' );
		expect(
			screen.getByRole( 'button', { name: 'Pause subscription' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Cancel subscription' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Resume subscription' } )
		).not.toBeInTheDocument();
	} );

	it( 'renders note text as text, not markup', async () => {
		as.getSubscription.mockResolvedValue( {
			...subscriptionDetail,
			notes: [
				{
					id: 10,
					content: '<img src=x onerror=alert(1)>',
					added_by: 'Admin',
					date_created: null,
				},
			],
		} );
		const { container } = show( '/subscriptions/501' );
		await screen.findByText( /<img src=x/ );
		expect( container.querySelector( 'img' ) ).toBeNull();
	} );

	it( 'asks for confirmation and sends nothing when the user backs out', async () => {
		const user = userEvent.setup();
		show( '/subscriptions/501' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Cancel subscription' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Cancel subscription',
		} );
		expect( dialog ).toHaveTextContent( /cannot be undone/ );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Keep it as it is',
			} )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect( as.runSubscriptionAction ).not.toHaveBeenCalled();
	} );

	it( 'cancels after confirmation and shows the new state', async () => {
		const user = userEvent.setup();
		as.runSubscriptionAction.mockResolvedValue( cancelledDetail );
		show( '/subscriptions/501' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Cancel subscription' } )
		);
		const dialog = screen.getByRole( 'dialog' );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Cancel subscription',
			} )
		);
		expect(
			await screen.findByText(
				'The subscription is cancelled.',
				noticeOptions
			)
		).toBeInTheDocument();
		expect( as.runSubscriptionAction ).toHaveBeenCalledTimes( 1 );
		expect( as.runSubscriptionAction ).toHaveBeenCalledWith(
			501,
			'cancel',
			expect.any( AbortSignal )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect(
			screen.getByText(
				'This subscription cannot change status any more.'
			)
		).toBeInTheDocument();
	} );

	it( 'submits once on a double click', async () => {
		const user = userEvent.setup();
		let finish: ( value: typeof subscriptionDetail ) => void = () =>
			undefined;
		as.runSubscriptionAction.mockReturnValue(
			new Promise( ( resolve ) => {
				finish = resolve;
			} )
		);
		show( '/subscriptions/501' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Pause subscription' } )
		);
		const confirm = within( screen.getByRole( 'dialog' ) ).getByRole(
			'button',
			{ name: 'Pause subscription' }
		);
		await user.dblClick( confirm );
		expect( as.runSubscriptionAction ).toHaveBeenCalledTimes( 1 );
		finish( {
			...subscriptionDetail,
			status: 'on-hold',
			allowed_actions: [ 'resume', 'cancel' ],
		} );
		expect(
			await screen.findByText(
				'The subscription is paused.',
				noticeOptions
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Resume subscription' } )
		).toBeInTheDocument();
	} );

	it( 'shows a 409 conflict in the dialog and reloads the subscription', async () => {
		const user = userEvent.setup();
		as.runSubscriptionAction.mockRejectedValue(
			failure(
				409,
				'ohmylms_subscription_invalid_transition',
				'A subscription cannot pause while it is cancelled.'
			)
		);
		show( '/subscriptions/501' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Pause subscription' } )
		);
		as.getSubscription.mockResolvedValue( cancelledDetail );
		const dialog = screen.getByRole( 'dialog' );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Pause subscription',
			} )
		);
		expect(
			await within( dialog ).findByText(
				/cannot pause while it is cancelled/
			)
		).toBeInTheDocument();
		await waitFor( () =>
			expect( as.getSubscription ).toHaveBeenCalledTimes( 2 )
		);
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Keep it as it is',
			} )
		);
		expect(
			await screen.findByText(
				'This subscription cannot change status any more.'
			)
		).toBeInTheDocument();
	} );

	it( 'shows a missing subscription without a retry', async () => {
		as.getSubscription.mockRejectedValue(
			failure(
				404,
				'ohmylms_subscription_not_found',
				'Subscription not found.'
			)
		);
		show( '/subscriptions/999' );
		expect(
			await screen.findByText( 'Subscription not found.', noticeOptions )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Back to subscriptions' } )
		).toBeInTheDocument();
	} );

	it( 'rejects a malformed id without a request', async () => {
		show( '/subscriptions/abc' );
		expect(
			await screen.findByText(
				'That is not a valid subscription.',
				noticeOptions
			)
		).toBeInTheDocument();
		expect( as.getSubscription ).not.toHaveBeenCalled();
	} );
} );
