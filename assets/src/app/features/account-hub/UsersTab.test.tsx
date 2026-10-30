import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import AccountHubPage from './AccountHubPage';
import * as api from './accountsApi';
import { account, accountOptions, editableAccount, page } from './fixtures';

vi.mock( './accountsApi', () => ( {
	listAccounts: vi.fn(),
	getAccountOptions: vi.fn(),
	createAccount: vi.fn(),
	getAccount: vi.fn(),
	updateAccount: vi.fn(),
} ) );

const notice = { selector: '.components-notice__content' };

const admin = {
	...account,
	id: 1,
	display_name: 'Site Admin',
	username: 'admin',
	roles: [ { slug: 'administrator', label: 'Administrator' } ],
	last_login: '2026-10-30T09:00:00Z',
	can_edit: false,
};

function renderTab() {
	return render(
		<MemoryRouter initialEntries={ [ '/account-hub?tab=users' ] }>
			<AccountHubPage />
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listAccounts ).mockResolvedValue(
		page( [ admin, { ...account, blocked: true } ] )
	);
	vi.mocked( api.getAccountOptions ).mockResolvedValue( accountOptions );
	vi.mocked( api.getAccount ).mockResolvedValue( editableAccount );
} );

describe( 'All accounts tab', () => {
	it( 'lists accounts with roles and offers edit only where the server allows', async () => {
		renderTab();
		expect(
			screen.getByRole( 'tab', { name: 'All accounts' } )
		).toHaveAttribute( 'aria-selected', 'true' );
		const table = await screen.findByRole( 'table', {
			name: 'All accounts',
		} );
		const rows = within( table ).getAllByRole( 'row' );
		expect(
			within( rows[ 1 ]! ).getByText( 'Administrator' )
		).toBeVisible();
		expect(
			within( rows[ 1 ]! ).queryByRole( 'button', { name: /Edit/ } )
		).not.toBeInTheDocument();
		expect( within( rows[ 2 ]! ).getByText( 'Blocked' ) ).toBeVisible();
		expect( within( rows[ 2 ]! ).getByText( 'Never' ) ).toBeVisible();
		expect(
			within( rows[ 2 ]! ).getByRole( 'button', {
				name: 'Edit Tess Teacher',
			} )
		).toBeVisible();
		expect( api.listAccounts ).toHaveBeenCalledWith(
			1,
			'',
			{ role: '', registered: 'all', status: 'all' },
			expect.any( AbortSignal )
		);
	} );

	it( 'filters and searches from the first page', async () => {
		const user = userEvent.setup();
		renderTab();
		await screen.findByRole( 'table', { name: 'All accounts' } );
		await waitFor( () =>
			expect(
				screen.getByRole( 'link', { name: 'Teacher' } )
			).toBeInTheDocument()
		);
		await user.click(
			await screen.findByRole( 'link', { name: 'Teacher' } )
		);
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Registered' } ),
			'current_year'
		);
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Status' } ),
			'blocked'
		);
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search accounts' } ),
			' tess '
		);
		await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
		await waitFor( () =>
			expect( api.listAccounts ).toHaveBeenLastCalledWith(
				1,
				'tess',
				{
					role: 'ohmylms_teacher',
					registered: 'current_year',
					status: 'blocked',
				},
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'validates and creates an account', async () => {
		vi.mocked( api.createAccount ).mockResolvedValue( {
			...account,
			id: 50,
			display_name: 'Pat Parent',
			username: 'pat',
		} );
		const user = userEvent.setup();
		renderTab();
		await screen.findByRole( 'table', { name: 'All accounts' } );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add account' } )
		);
		const dialog = screen.getByRole( 'dialog', { name: 'Add account' } );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create account' } )
		);
		expect(
			within( dialog ).getByText( 'Enter a first name.' )
		).toBeVisible();
		expect( api.createAccount ).not.toHaveBeenCalled();
		await user.selectOptions(
			within( dialog ).getByRole( 'combobox', { name: 'Account type' } ),
			'parent'
		);
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'First name' } ),
			'Pat'
		);
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Email' } ),
			'pat@example.org'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create account' } )
		);
		expect(
			(
				await screen.findAllByText( /Account created for Pat Parent/ )
			)[ 0 ]
		).toBeVisible();
		expect( api.createAccount ).toHaveBeenCalledWith(
			{
				type: 'parent',
				first_name: 'Pat',
				last_name: '',
				email: 'pat@example.org',
				username: '',
				send_email: true,
			},
			expect.any( AbortSignal )
		);
		expect( api.listAccounts ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'hides creating when no account type is allowed', async () => {
		vi.mocked( api.getAccountOptions ).mockResolvedValue( {
			...accountOptions,
			types: [],
			can_create: false,
		} );
		renderTab();
		await screen.findByRole( 'table', { name: 'All accounts' } );
		await waitFor( () =>
			expect( api.getAccountOptions ).toHaveBeenCalled()
		);
		expect(
			screen.queryByRole( 'button', { name: 'Add account' } )
		).not.toBeInTheDocument();
	} );

	it( 'edits with the loaded version and offers a reload after a conflict', async () => {
		vi.mocked( api.updateAccount ).mockRejectedValueOnce(
			new ApiError( {
				status: 409,
				code: 'ohmylms_account_hub_conflict',
				message:
					'This account was changed by someone else. Reload it before saving.',
			} )
		);
		const user = userEvent.setup();
		renderTab();
		await user.click(
			await screen.findByRole( 'button', { name: 'Edit Tess Teacher' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Edit Tess Teacher',
		} );
		const first = await within( dialog ).findByRole( 'textbox', {
			name: 'First name',
		} );
		expect( within( dialog ).getByText( /Username: tess/ ) ).toBeVisible();
		await user.clear( first );
		await user.type( first, 'Tessa' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save account' } )
		);
		expect(
			await within( dialog ).findByText(
				/changed by someone else/,
				notice
			)
		).toBeVisible();
		expect( api.updateAccount ).toHaveBeenCalledWith(
			41,
			{
				first_name: 'Tessa',
				last_name: 'Teacher',
				email: 'tess@example.org',
			},
			'0123456789abcdef',
			false,
			expect.any( AbortSignal )
		);
		expect(
			within( dialog ).getByRole( 'button', { name: 'Save account' } )
		).toBeDisabled();
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Reload the account (discards your edits)',
			} )
		);
		await waitFor( () =>
			expect( api.getAccount ).toHaveBeenCalledTimes( 2 )
		);
		expect(
			await within( dialog ).findByRole( 'textbox', {
				name: 'First name',
			} )
		).toHaveValue( 'Tess' );
	} );

	it( 'shows a server field error on the field', async () => {
		vi.mocked( api.updateAccount ).mockRejectedValueOnce(
			new ApiError( {
				status: 409,
				code: 'ohmylms_account_hub_email_exists',
				message: 'Another account already uses this email.',
				details: { status: 409, field: 'email' },
			} )
		);
		const user = userEvent.setup();
		renderTab();
		await user.click(
			await screen.findByRole( 'button', { name: 'Edit Tess Teacher' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Edit Tess Teacher',
		} );
		await within( dialog ).findByRole( 'textbox', { name: 'Email' } );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save account' } )
		);
		expect(
			(
				await within( dialog ).findAllByText(
					'Another account already uses this email.'
				)
			).length
		).toBe( 2 );
	} );

	it( 'shows a list error with a retry', async () => {
		vi.mocked( api.listAccounts ).mockRejectedValueOnce(
			new ApiError( {
				status: 403,
				code: 'ohmylms_account_hub_cannot_list_users',
				message: 'Sorry, you are not allowed to list accounts.',
			} )
		);
		const user = userEvent.setup();
		renderTab();
		expect(
			await screen.findByText( /not allowed to list accounts/, notice )
		).toBeVisible();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'table', { name: 'All accounts' } )
		).toBeVisible();
	} );
} );
