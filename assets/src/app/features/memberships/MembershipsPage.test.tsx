import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { plan, summary } from './fixtures';
import MembershipsPage from './MembershipsPage';

vi.mock( './api', () => ( {
	listMemberships: vi.fn(),
	createMembership: vi.fn(),
	duplicateMembership: vi.fn(),
	deleteMembership: vi.fn(),
} ) );

const second = {
	...summary,
	id: 8,
	name: 'Draft plan',
	status: 'draft' as const,
	sale_price: null,
	sale_start_date: null,
	sale_end_date: null,
	price: '29.50',
	billing_period: 'one_time' as const,
	all_learning_content: true,
	course_count: 9,
	member_count: 0,
};

function renderPage() {
	return render(
		<MemoryRouter initialEntries={ [ '/memberships' ] }>
			<Routes>
				<Route path="/memberships" element={ <MembershipsPage /> } />
				<Route
					path="/memberships/:id"
					element={ <p>Editor route reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

function rejection( status: number, code: string, message: string ) {
	return new ApiError( { status, code, message } );
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listMemberships ).mockResolvedValue( {
		items: [ summary, second ],
		total: 2,
		totalPages: 1,
	} );
} );

describe( 'MembershipsPage', () => {
	it( 'lists plans with price, billing, courses and members', async () => {
		renderPage();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading membership plans'
		);
		const table = await screen.findByRole( 'table', {
			name: 'Membership plans',
		} );
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows ).toHaveLength( 3 );
		const first = within( rows[ 1 ]! );
		expect(
			first.getByRole( 'link', { name: 'All access' } )
		).toHaveAttribute( 'href', '/memberships/7' );
		expect( first.getByText( 'Published' ) ).toBeInTheDocument();
		expect( first.getByText( /19.00/ ) ).toBeInTheDocument();
		expect( first.getByText( /29.50/ ).tagName ).toBe( 'S' );
		expect( first.getByText( 'Every month' ) ).toBeInTheDocument();
		expect( first.getByText( '3' ) ).toBeInTheDocument();
		const other = within( rows[ 2 ]! );
		expect( other.getByText( 'Draft' ) ).toBeInTheDocument();
		expect( other.getByText( 'One time' ) ).toBeInTheDocument();
		expect( other.getByText( 'All (9)' ) ).toBeInTheDocument();
		expect( screen.getByText( '2 plans found.' ) ).toBeInTheDocument();
	} );
	it( 'shows an empty state', async () => {
		vi.mocked( api.listMemberships ).mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		renderPage();
		expect(
			await screen.findByText( 'No membership plans found.' )
		).toBeInTheDocument();
	} );
	it( 'shows a server error with a retry that reloads', async () => {
		vi.mocked( api.listMemberships ).mockRejectedValueOnce(
			rejection( 500, 'rest_error', 'The server broke.' )
		);
		const user = userEvent.setup();
		renderPage();
		expect(
			await screen.findByText( /The server broke\./, notice )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'table', { name: 'Membership plans' } )
		).toBeInTheDocument();
	} );
	it( 'shows a permission error', async () => {
		vi.mocked( api.listMemberships ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_membership_cannot_view',
				'Sorry, you are not allowed to view membership plans.'
			)
		);
		renderPage();
		expect(
			await screen.findByText(
				/not allowed to view membership plans/,
				notice
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );
	it( 'searches by keyboard and filters by status', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.type(
			screen.getByRole( 'textbox', { name: 'Search membership plans' } ),
			'gold{Enter}'
		);
		await waitFor( () =>
			expect( api.listMemberships ).toHaveBeenLastCalledWith(
				1,
				'gold',
				'all',
				expect.any( AbortSignal )
			)
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Status' } ),
			'draft'
		);
		await waitFor( () =>
			expect( api.listMemberships ).toHaveBeenLastCalledWith(
				1,
				'gold',
				'draft',
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'pages through results', async () => {
		vi.mocked( api.listMemberships ).mockResolvedValue( {
			items: [ summary ],
			total: 30,
			totalPages: 2,
		} );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click( screen.getByRole( 'button', { name: 'Next page' } ) );
		await waitFor( () =>
			expect( api.listMemberships ).toHaveBeenLastCalledWith(
				2,
				'',
				'all',
				expect.any( AbortSignal )
			)
		);
	} );

	describe( 'create', () => {
		async function openDialog() {
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table' );
			await user.click(
				screen.getByRole( 'button', { name: 'Add plan' } )
			);
			return { user, dialog: await screen.findByRole( 'dialog' ) };
		}
		it( 'creates a plan and opens its editor', async () => {
			vi.mocked( api.createMembership ).mockResolvedValue( {
				...plan,
				id: 42,
			} );
			const { user } = await openDialog();
			await user.type(
				screen.getByRole( 'textbox', { name: 'Plan name' } ),
				'Gold{Enter}'
			);
			expect(
				await screen.findByText( 'Editor route reached' )
			).toBeInTheDocument();
			expect( api.createMembership ).toHaveBeenCalledWith(
				'Gold',
				expect.any( AbortSignal )
			);
		} );
		it( 'rejects a blank name without calling the server', async () => {
			const { user } = await openDialog();
			await user.click(
				screen.getByRole( 'button', { name: 'Create plan' } )
			);
			expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
				'Enter a name of 1 to 200 characters.'
			);
			expect( api.createMembership ).not.toHaveBeenCalled();
		} );
		it( 'keeps the dialog open and shows a permission error', async () => {
			vi.mocked( api.createMembership ).mockRejectedValue(
				rejection(
					403,
					'ohmylms_membership_cannot_create',
					'Sorry, you are not allowed to create membership plans.'
				)
			);
			const { user, dialog } = await openDialog();
			await user.type(
				screen.getByRole( 'textbox', { name: 'Plan name' } ),
				'Gold'
			);
			await user.click(
				screen.getByRole( 'button', { name: 'Create plan' } )
			);
			expect(
				await within( dialog ).findByText(
					/not allowed to create membership plans/
				)
			).toBeInTheDocument();
			expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
		} );
		it( 'closes with Escape', async () => {
			const { user } = await openDialog();
			await user.keyboard( '{Escape}' );
			await waitFor( () =>
				expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
			);
		} );
	} );

	it( 'duplicates a plan and reloads the list', async () => {
		vi.mocked( api.duplicateMembership ).mockResolvedValue( {
			...plan,
			id: 99,
		} );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', { name: 'Duplicate All access' } )
		);
		expect(
			await screen.findByText(
				'Duplicated All access as a draft.',
				notice
			)
		).toBeInTheDocument();
		expect( api.duplicateMembership ).toHaveBeenCalledWith(
			7,
			expect.any( AbortSignal )
		);
		await waitFor( () =>
			expect( api.listMemberships ).toHaveBeenCalledTimes( 2 )
		);
	} );
	it( 'shows a duplicate failure', async () => {
		vi.mocked( api.duplicateMembership ).mockRejectedValue(
			rejection(
				500,
				'ohmylms_membership_create_failed',
				'Could not copy.'
			)
		);
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', { name: 'Duplicate All access' } )
		);
		expect(
			await screen.findByText( 'Could not copy.', notice )
		).toBeInTheDocument();
	} );

	describe( 'delete', () => {
		it( 'asks for confirmation, then deletes and reloads', async () => {
			vi.mocked( api.deleteMembership ).mockResolvedValue( undefined );
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table' );
			await user.click(
				screen.getByRole( 'button', { name: 'Delete Draft plan' } )
			);
			const dialog = await screen.findByRole( 'dialog' );
			expect( dialog ).toHaveTextContent(
				'Permanently delete Draft plan?'
			);
			expect( api.deleteMembership ).not.toHaveBeenCalled();
			await user.click(
				within( dialog ).getByRole( 'button', { name: 'Delete plan' } )
			);
			expect(
				await screen.findByText( 'Plan deleted.', notice )
			).toBeInTheDocument();
			expect( api.deleteMembership ).toHaveBeenCalledWith(
				8,
				expect.any( AbortSignal )
			);
			await waitFor( () =>
				expect( api.listMemberships ).toHaveBeenCalledTimes( 2 )
			);
		} );
		it( 'can be cancelled and then does nothing', async () => {
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table' );
			await user.click(
				screen.getByRole( 'button', { name: 'Delete All access' } )
			);
			await user.click(
				within( await screen.findByRole( 'dialog' ) ).getByRole(
					'button',
					{
						name: 'Cancel',
					}
				)
			);
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
			expect( api.deleteMembership ).not.toHaveBeenCalled();
		} );
		it( 'shows the conflict when the plan still has members', async () => {
			vi.mocked( api.deleteMembership ).mockRejectedValue(
				rejection(
					409,
					'ohmylms_membership_has_members',
					'This plan has active members.'
				)
			);
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table' );
			await user.click(
				screen.getByRole( 'button', { name: 'Delete All access' } )
			);
			const dialog = await screen.findByRole( 'dialog' );
			await user.click(
				within( dialog ).getByRole( 'button', { name: 'Delete plan' } )
			);
			expect(
				await within( dialog ).findByText(
					'This plan has active members.'
				)
			).toBeInTheDocument();
			expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
			expect( api.listMemberships ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
