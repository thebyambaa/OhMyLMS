import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import {
	counts,
	coupon,
	courses,
	detail,
	flatCoupon,
	notes,
	order,
	otherOrder,
	pendingDetail,
} from './fixtures';
import { coupons, orderDetail, orders } from './index';

vi.mock( './api', () => ( {
	listOrders: vi.fn(),
	getStatusCounts: vi.fn(),
	getOrder: vi.fn(),
	changeOrderStatus: vi.fn(),
	trashOrder: vi.fn(),
	listNotes: vi.fn(),
	addNote: vi.fn(),
	listCoupons: vi.fn(),
	createCoupon: vi.fn(),
	updateCoupon: vi.fn(),
	deleteCoupon: vi.fn(),
	listCourses: vi.fn(),
} ) );

const as = {
	listOrders: vi.mocked( api.listOrders ),
	getStatusCounts: vi.mocked( api.getStatusCounts ),
	getOrder: vi.mocked( api.getOrder ),
	changeOrderStatus: vi.mocked( api.changeOrderStatus ),
	trashOrder: vi.mocked( api.trashOrder ),
	listNotes: vi.mocked( api.listNotes ),
	addNote: vi.mocked( api.addNote ),
	listCoupons: vi.mocked( api.listCoupons ),
	createCoupon: vi.mocked( api.createCoupon ),
	updateCoupon: vi.mocked( api.updateCoupon ),
	deleteCoupon: vi.mocked( api.deleteCoupon ),
	listCourses: vi.mocked( api.listCourses ),
};

function show( route: string, capabilities = { manage_options: true } ) {
	return renderShell( [ orders, orderDetail, coupons ], {
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
	as.listOrders.mockResolvedValue( page( [ order, otherOrder ] ) );
	as.getStatusCounts.mockResolvedValue( counts );
	as.getOrder.mockResolvedValue( detail );
	as.listNotes.mockResolvedValue( notes );
	as.listCoupons.mockResolvedValue( page( [ coupon, flatCoupon ] ) );
	as.listCourses.mockResolvedValue( page( courses ) );
} );

describe( 'commerce routes', () => {
	it( 'are hidden and make no requests without manage_options', () => {
		show( '/orders', {} as { manage_options: boolean } );
		expect(
			screen.queryByRole( 'link', { name: 'Orders' } )
		).not.toBeInTheDocument();
		expect( as.listOrders ).not.toHaveBeenCalled();
	} );

	it( 'make orders and coupons Commerce tabs, and the order detail a screen of the Orders tab', async () => {
		show( '/orders' );
		await screen.findByRole( 'table', { name: 'Orders' } );
		expect( orders ).toMatchObject( { group: 'commerce', tab: 'orders' } );
		expect( coupons ).toMatchObject( {
			group: 'commerce',
			tab: 'coupons',
		} );
		expect( orderDetail ).toMatchObject( {
			group: 'commerce',
			tab: 'orders',
			hidden: true,
		} );
	} );
} );

describe( 'order list', () => {
	it( 'shows a named table with links, money in the order currency and no email', async () => {
		show( '/orders' );
		const table = await screen.findByRole( 'table', { name: 'Orders' } );
		expect(
			within( table ).getByRole( 'link', { name: 'Order #311' } )
		).toHaveAttribute( 'href', expect.stringContaining( '/orders/311' ) );
		expect( within( table ).getByText( /49.50/ ) ).toBeInTheDocument();
		expect(
			within( table ).getByText( 'Pending payment' )
		).toBeInTheDocument();
		expect(
			screen.getAllByText( '2 orders found.' )[ 0 ]
		).toBeInTheDocument();
		expect( screen.queryByText( /@example\.com/ ) ).not.toBeInTheDocument();
		expect(
			await screen.findByRole( 'link', { name: 'Completed (4)' } )
		).toBeInTheDocument();
	} );

	it( 'shows loading, then an empty state', async () => {
		as.listOrders.mockResolvedValue( page( [] ) );
		show( '/orders' );
		expect( await screen.findByText( 'No orders found.' ) ).toHaveClass(
			'colspanchange'
		);
	} );

	it( 'shows a server error and retries', async () => {
		const user = userEvent.setup();
		as.listOrders.mockRejectedValueOnce(
			failure( 500, 'x', 'The database is unavailable.' )
		);
		show( '/orders' );
		expect(
			await screen.findByText(
				/The database is unavailable\./,
				noticeOptions
			)
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect( await screen.findByText( 'Ada Lovelace' ) ).toBeInTheDocument();
	} );

	it( 'shows a permission error from the server', async () => {
		as.listOrders.mockRejectedValue(
			failure(
				403,
				'ohmylms_order_cannot_view',
				'Sorry, you are not allowed to view orders.'
			)
		);
		show( '/orders' );
		expect(
			await screen.findByText(
				/not allowed to view orders/,
				noticeOptions
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );

	it( 'filters and searches with the keyboard', async () => {
		const user = userEvent.setup();
		show( '/orders' );
		await screen.findByText( 'Ada Lovelace' );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search orders' } ),
			'ada{Enter}'
		);
		await waitFor( () =>
			expect( as.listOrders ).toHaveBeenLastCalledWith(
				expect.objectContaining( { search: 'ada', page: 1 } ),
				expect.any( AbortSignal )
			)
		);
		await user.click(
			await screen.findByRole( 'link', { name: /^Pending payment/ } )
		);
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Payment method' } ),
			'stripe'
		);
		await waitFor( () =>
			expect( as.listOrders ).toHaveBeenLastCalledWith(
				expect.objectContaining( {
					status: 'pending',
					paymentMethod: 'stripe',
					search: 'ada',
				} ),
				expect.any( AbortSignal )
			)
		);
		await user.type(
			await screen.findByLabelText( 'From date' ),
			'2026-02-01'
		);
		await waitFor( () =>
			expect( as.listOrders ).toHaveBeenLastCalledWith(
				expect.objectContaining( { after: '2026-02-01' } ),
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'does not send a reversed date range', async () => {
		const user = userEvent.setup();
		show( '/orders' );
		await screen.findByText( 'Ada Lovelace' );
		await user.type(
			await screen.findByLabelText( 'From date' ),
			'2026-03-01'
		);
		await waitFor( () =>
			expect( as.listOrders ).toHaveBeenLastCalledWith(
				expect.objectContaining( { after: '2026-03-01' } ),
				expect.any( AbortSignal )
			)
		);
		const calls = as.listOrders.mock.calls.length;
		await user.type(
			await screen.findByLabelText( 'To date' ),
			'2026-02-01'
		);
		expect(
			await screen.findByText(
				'The start date must not be after the end date.',
				noticeOptions
			)
		).toBeInTheDocument();
		expect( as.listOrders.mock.calls.length ).toBe( calls );
	} );

	it( 'pages through results', async () => {
		const user = userEvent.setup();
		as.listOrders.mockResolvedValue( {
			items: [ order ],
			total: 41,
			totalPages: 3,
		} );
		show( '/orders' );
		await screen.findByText( 'Ada Lovelace' );
		await user.click(
			screen.getAllByRole( 'link', { name: 'Next page' } )[ 0 ]!
		);
		await waitFor( () =>
			expect( as.listOrders ).toHaveBeenLastCalledWith(
				expect.objectContaining( { page: 2 } ),
				expect.any( AbortSignal )
			)
		);
	} );
} );

describe( 'order details', () => {
	it( 'shows items, totals, billing, refunds and related orders', async () => {
		show( '/orders/311' );
		expect(
			await screen.findByRole( 'heading', { name: 'Order #311' } )
		).toBeInTheDocument();
		expect( as.getOrder ).toHaveBeenCalledWith(
			311,
			expect.any( AbortSignal )
		);
		const items = await screen.findByRole( 'table', {
			name: 'Order items',
		} );
		expect(
			within( items ).getByText( 'Intro to Algebra' )
		).toBeInTheDocument();
		expect( screen.getByText( 'Coupon SPRING10' ) ).toBeInTheDocument();
		expect( screen.getByText( /^-.*10.00$/ ) ).toBeInTheDocument();
		expect( screen.getByText( 'ada@example.com' ) ).toBeInTheDocument();
		expect( screen.getByText( /Goodwill/ ) ).toBeInTheDocument();
		expect(
			screen.getByText( /Refunds are not issued from this screen/ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Order #320' } )
		).toHaveAttribute( 'href', expect.stringContaining( '/orders/320' ) );
		expect(
			await screen.findByText( 'Called the student' )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: /refund/i } )
		).not.toBeInTheDocument();
	} );

	it( 'rejects a malformed order id without a request', async () => {
		show( '/orders/abc' );
		expect(
			await screen.findByText(
				'That is not a valid order.',
				noticeOptions
			)
		).toBeInTheDocument();
		expect( as.getOrder ).not.toHaveBeenCalled();
	} );

	it( 'shows not found without a retry button', async () => {
		as.getOrder.mockRejectedValue(
			failure( 404, 'ohmylms_order_not_found', 'Order not found.' )
		);
		show( '/orders/99' );
		expect(
			await screen.findByText( /Order not found\./, noticeOptions )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Back to orders' } )
		).toBeInTheDocument();
	} );

	it( 'only offers the statuses the server allows and confirms before changing', async () => {
		const user = userEvent.setup();
		as.getOrder.mockResolvedValue( pendingDetail );
		as.changeOrderStatus.mockResolvedValue( {
			...pendingDetail,
			status: 'completed',
			allowed_statuses: [ 'cancelled' ],
		} );
		show( '/orders/312' );
		const select = await screen.findByRole( 'combobox', {
			name: 'New status',
		} );
		expect(
			within( select )
				.getAllByRole( 'option' )
				.map( ( option ) => option.textContent )
		).toEqual( [
			'Choose a status',
			'Processing',
			'On hold',
			'Completed',
			'Cancelled',
		] );
		const update = screen.getByRole( 'button', { name: 'Update status' } );
		expect( update ).toBeDisabled();
		await user.selectOptions( select, 'completed' );
		await user.click( update );
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Change order status',
		} );
		expect( as.changeOrderStatus ).not.toHaveBeenCalled();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Change status' } )
		);
		await waitFor( () =>
			expect( as.changeOrderStatus ).toHaveBeenCalledWith(
				312,
				'completed',
				expect.any( AbortSignal )
			)
		);
		expect( as.changeOrderStatus ).toHaveBeenCalledTimes( 1 );
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
		expect( screen.getAllByText( 'Completed' ).length ).toBeGreaterThan(
			0
		);
	} );

	it( 'warns that cancelling removes access and prevents double submits', async () => {
		const user = userEvent.setup();
		let release!: () => void;
		as.changeOrderStatus.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					release = () =>
						resolve( {
							...detail,
							status: 'cancelled',
							allowed_statuses: [ 'pending' ],
						} );
				} )
		);
		show( '/orders/311' );
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'New status' } ),
			'cancelled'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Update status' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		expect(
			within( dialog ).getByText(
				/removes the access the student received/
			)
		).toBeInTheDocument();
		const confirm = within( dialog ).getByRole( 'button', {
			name: 'Change status',
		} );
		await user.dblClick( confirm );
		expect( as.changeOrderStatus ).toHaveBeenCalledTimes( 1 );
		expect( confirm ).toBeDisabled();
		release();
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
	} );

	it( 'shows a conflict from the server and keeps the dialog open', async () => {
		const user = userEvent.setup();
		as.changeOrderStatus.mockRejectedValue(
			failure(
				409,
				'ohmylms_order_invalid_transition',
				'An order cannot change from completed to cancelled.'
			)
		);
		show( '/orders/311' );
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'New status' } ),
			'cancelled'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Update status' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Change status' } )
		);
		expect(
			await within( dialog ).findByText(
				/cannot change from completed to cancelled/
			)
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'button', { name: 'Change status' } )
		).toBeEnabled();
	} );

	it( 'shows no status control for a refunded order', async () => {
		as.getOrder.mockResolvedValue( {
			...detail,
			status: 'refunded',
			allowed_statuses: [],
		} );
		show( '/orders/311' );
		expect(
			await screen.findByText(
				'This order cannot change status any more.'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'combobox', { name: 'New status' } )
		).not.toBeInTheDocument();
	} );

	it( 'adds a plain note, validates empty input and shows server errors', async () => {
		const user = userEvent.setup();
		as.addNote.mockRejectedValueOnce(
			failure(
				500,
				'ohmylms_order_note_failed',
				'The note could not be saved.'
			)
		);
		as.addNote.mockResolvedValueOnce( {
			id: 6,
			content: 'Second note',
			added_by: 'Admin',
			date_created: '2026-10-30T11:00:00',
		} );
		show( '/orders/311' );
		await screen.findByText( 'Called the student' );
		const add = screen.getByRole( 'button', { name: 'Add note' } );
		await user.click( add );
		expect(
			await screen.findByText( 'Write a note first.' )
		).toBeInTheDocument();
		expect( as.addNote ).not.toHaveBeenCalled();

		await user.type(
			screen.getByRole( 'textbox', { name: 'Add a note' } ),
			'Second note'
		);
		await user.click( add );
		expect(
			await screen.findByText(
				'The note could not be saved.',
				noticeOptions
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'textbox', { name: 'Add a note' } )
		).toHaveValue( 'Second note' );
		await user.click( add );
		await waitFor( () =>
			expect(
				within(
					screen.getByRole( 'list', { name: 'Order notes' } )
				).getAllByRole( 'listitem' )
			).toHaveLength( 2 )
		);
		expect( as.addNote ).toHaveBeenLastCalledWith(
			311,
			'Second note',
			expect.any( AbortSignal )
		);
		expect(
			screen.getByRole( 'textbox', { name: 'Add a note' } )
		).toHaveValue( '' );
	} );

	it( 'confirms before trashing and returns to the list', async () => {
		const user = userEvent.setup();
		as.trashOrder.mockResolvedValue( 311 );
		show( '/orders/311' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Move to trash' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Move order to trash',
		} );
		expect( as.trashOrder ).not.toHaveBeenCalled();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Cancel' } )
		);
		expect( as.trashOrder ).not.toHaveBeenCalled();
		await user.click(
			screen.getByRole( 'button', { name: 'Move to trash' } )
		);
		await user.click(
			within( await screen.findByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Move to trash',
			} )
		);
		await waitFor( () =>
			expect( as.trashOrder ).toHaveBeenCalledWith(
				311,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByRole( 'table', { name: 'Orders' } )
		).toBeInTheDocument();
	} );
} );

describe( 'coupons', () => {
	it( 'lists coupons with discounts, usage and status', async () => {
		show( '/coupons' );
		const table = await screen.findByRole( 'table', { name: 'Coupons' } );
		expect( within( table ).getByText( 'SPRING15' ) ).toBeInTheDocument();
		expect( within( table ).getByText( '15%' ) ).toBeInTheDocument();
		expect( within( table ).getByText( /10.00/ ) ).toBeInTheDocument();
		expect( within( table ).getByText( '3 of 100' ) ).toBeInTheDocument();
		expect( within( table ).getByText( 'Disabled' ) ).toBeInTheDocument();
		expect( within( table ).getByText( 'Never' ) ).toBeInTheDocument();
	} );

	it( 'shows an empty state and a server error with retry', async () => {
		const user = userEvent.setup();
		as.listCoupons.mockRejectedValueOnce( failure( 500, 'x', 'Broken.' ) );
		show( '/coupons' );
		expect(
			await screen.findByText( /Broken\./, noticeOptions )
		).toBeInTheDocument();
		as.listCoupons.mockResolvedValue( page( [] ) );
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByText( 'No coupons found.' )
		).toBeInTheDocument();
	} );

	it( 'shows a permission error', async () => {
		as.listCoupons.mockRejectedValue(
			failure(
				403,
				'ohmylms_coupon_cannot_view',
				'Sorry, you are not allowed to view coupons.'
			)
		);
		show( '/coupons' );
		expect(
			await screen.findByText(
				/not allowed to view coupons/,
				noticeOptions
			)
		).toBeInTheDocument();
	} );

	it( 'validates the form without sending a request', async () => {
		const user = userEvent.setup();
		show( '/coupons' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add coupon' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Add coupon',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create coupon' } )
		);
		expect(
			await within( dialog ).findByText( 'Enter a coupon code.' )
		).toBeInTheDocument();
		expect(
			within( dialog ).getByText( 'Enter a discount greater than zero.' )
		).toBeInTheDocument();
		expect( as.createCoupon ).not.toHaveBeenCalled();

		await user.type( within( dialog ).getByLabelText( 'Code' ), 'SPRING' );
		await user.type(
			within( dialog ).getByLabelText( 'Discount (%)' ),
			'150'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create coupon' } )
		);
		expect(
			await within( dialog ).findByText(
				'A percentage cannot exceed 100.'
			)
		).toBeInTheDocument();
		expect( as.createCoupon ).not.toHaveBeenCalled();
	} );

	it( 'creates a coupon with the keyboard, refreshes the list and closes', async () => {
		const user = userEvent.setup();
		as.createCoupon.mockResolvedValue( coupon );
		show( '/coupons' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add coupon' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Add coupon',
		} );
		await user.type(
			within( dialog ).getByLabelText( 'Code' ),
			'SPRING15'
		);
		await user.type(
			within( dialog ).getByLabelText( 'Discount (%)' ),
			'15'
		);
		await user.type(
			within( dialog ).getByLabelText( 'Total usage limit' ),
			'100{Enter}'
		);
		await waitFor( () =>
			expect( as.createCoupon ).toHaveBeenCalledTimes( 1 )
		);
		expect( as.createCoupon.mock.calls[ 0 ]?.[ 0 ] ).toMatchObject( {
			code: 'SPRING15',
			discount_type: 'percent',
			amount: '15',
			usage_limit: 100,
			course_id_type: 'all',
			course_ids: [],
		} );
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
		expect(
			screen.getByText( 'Coupon created.', noticeOptions )
		).toBeInTheDocument();
		expect( as.listCoupons.mock.calls.length ).toBeGreaterThan( 1 );
	} );

	it( 'keeps the input and shows the server message when a code exists', async () => {
		const user = userEvent.setup();
		as.createCoupon.mockRejectedValue(
			failure(
				409,
				'ohmylms_coupon_code_exists',
				'A coupon with this code already exists.'
			)
		);
		show( '/coupons' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add coupon' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await user.type(
			within( dialog ).getByLabelText( 'Code' ),
			'SPRING15'
		);
		await user.type(
			within( dialog ).getByLabelText( 'Discount (%)' ),
			'15'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create coupon' } )
		);
		expect(
			await within( dialog ).findByText( /already exists/ )
		).toBeInTheDocument();
		expect( within( dialog ).getByLabelText( 'Code' ) ).toHaveValue(
			'SPRING15'
		);
		expect(
			within( dialog ).getByRole( 'button', { name: 'Create coupon' } )
		).toBeEnabled();
	} );

	it( 'requires courses when the coupon applies to selected courses', async () => {
		const user = userEvent.setup();
		as.createCoupon.mockResolvedValue( coupon );
		show( '/coupons' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add coupon' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await user.type( within( dialog ).getByLabelText( 'Code' ), 'ALG' );
		await user.type(
			within( dialog ).getByLabelText( 'Discount (%)' ),
			'10'
		);
		await user.selectOptions(
			within( dialog ).getByLabelText( 'Applies to' ),
			'selected_course'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create coupon' } )
		);
		expect(
			await within( dialog ).findByText( 'Choose at least one course.' )
		).toBeInTheDocument();
		await user.click(
			await within( dialog ).findByLabelText( 'Geometry' )
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create coupon' } )
		);
		await waitFor( () =>
			expect( as.createCoupon ).toHaveBeenCalledTimes( 1 )
		);
		expect( as.createCoupon.mock.calls[ 0 ]?.[ 0 ] ).toMatchObject( {
			course_id_type: 'selected_course',
			course_ids: [ 41 ],
		} );
	} );

	it( 'edits an existing coupon', async () => {
		const user = userEvent.setup();
		as.updateCoupon.mockResolvedValue( coupon );
		show( '/coupons' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Edit SPRING15' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Edit coupon',
		} );
		const amount = within( dialog ).getByLabelText( 'Discount (%)' );
		expect( amount ).toHaveValue( '15.00' );
		await user.clear( amount );
		await user.type( amount, '20{Enter}' );
		await waitFor( () =>
			expect( as.updateCoupon ).toHaveBeenCalledWith(
				57,
				expect.objectContaining( { amount: '20', code: 'SPRING15' } ),
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'confirms before deleting and handles a server failure', async () => {
		const user = userEvent.setup();
		as.deleteCoupon.mockRejectedValueOnce(
			failure(
				500,
				'ohmylms_coupon_not_deleted',
				'The coupon could not be deleted.'
			)
		);
		as.deleteCoupon.mockResolvedValueOnce( 57 );
		show( '/coupons' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Delete SPRING15' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Delete coupon',
		} );
		expect( as.deleteCoupon ).not.toHaveBeenCalled();
		const confirm = within( dialog ).getByRole( 'button', {
			name: 'Delete coupon',
		} );
		await user.click( confirm );
		expect(
			await within( dialog ).findByText(
				'The coupon could not be deleted.'
			)
		).toBeInTheDocument();
		await user.click( confirm );
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
		expect( as.deleteCoupon ).toHaveBeenCalledTimes( 2 );
		expect(
			screen.getByText( 'Coupon deleted.', noticeOptions )
		).toBeInTheDocument();
	} );
} );
