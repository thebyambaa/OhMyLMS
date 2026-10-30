import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import AccountHubPage from './AccountHubPage';
import * as api from './api';
import {
	children,
	invitation,
	page,
	parentAccount,
	person,
	school,
	schoolClass,
	teacher,
} from './fixtures';

vi.mock( './api', () => ( {
	listSchools: vi.fn(),
	createSchool: vi.fn(),
	listClasses: vi.fn(),
	listTeachers: vi.fn(),
	listParents: vi.fn(),
	getChildren: vi.fn(),
	listSchoolStudents: vi.fn(),
	linkChild: vi.fn(),
	unlinkChild: vi.fn(),
	revokeInvitation: vi.fn(),
} ) );

const notice = { selector: '.components-notice__content' };

function Where() {
	const location = useLocation();
	return <p data-testid="where">{ location.pathname + location.search }</p>;
}

function renderHub( entry = '/account-hub' ) {
	return render(
		<MemoryRouter initialEntries={ [ entry ] }>
			<Routes>
				<Route
					path="/account-hub"
					element={
						<>
							<AccountHubPage />
							<Where />
						</>
					}
				/>
				<Route
					path="/account-hub/schools/:id"
					element={ <p>School editor reached</p> }
				/>
				<Route path="/students" element={ <p>Students reached</p> } />
			</Routes>
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listSchools ).mockResolvedValue( page( [ school ] ) );
	vi.mocked( api.listClasses ).mockResolvedValue(
		page( [
			schoolClass,
			{
				...schoolClass,
				id: 10,
				name: 'Home group',
				school_id: 0,
				school_name: null,
				academic_year_id: 0,
				academic_year_label: null,
				status: 'archived' as const,
			},
		] )
	);
	vi.mocked( api.listTeachers ).mockResolvedValue(
		page( [ teacher, { ...teacher, id: 13, email: null, schools: [] } ] )
	);
	vi.mocked( api.listParents ).mockResolvedValue( page( [ parentAccount ] ) );
	vi.mocked( api.getChildren ).mockResolvedValue( children );
} );

describe( 'AccountHubPage', () => {
	it( 'opens on schools with a link to the students screen', async () => {
		const user = userEvent.setup();
		renderHub();
		const table = await screen.findByRole( 'table', { name: 'Schools' } );
		const row = within( table ).getAllByRole( 'row' )[ 1 ]!;
		expect(
			within( row ).getByRole( 'link', { name: 'North School' } )
		).toHaveAttribute( 'href', '/account-hub/schools/3' );
		expect( within( row ).getByText( 'Asia/Ulaanbaatar' ) ).toBeVisible();
		expect(
			screen.getByRole( 'tab', { name: 'Schools' } )
		).toHaveAttribute( 'aria-selected', 'true' );
		expect( api.listSchools ).toHaveBeenCalledWith(
			1,
			'',
			expect.any( AbortSignal )
		);
		await user.click(
			screen.getByRole( 'link', { name: 'Open students' } )
		);
		expect( screen.getByText( 'Students reached' ) ).toBeInTheDocument();
	} );
	it( 'searches schools from the first page', async () => {
		const user = userEvent.setup();
		renderHub();
		await screen.findByRole( 'table', { name: 'Schools' } );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search schools' } ),
			' north '
		);
		await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
		await waitFor( () =>
			expect( api.listSchools ).toHaveBeenLastCalledWith(
				1,
				'north',
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'switches tabs through the tab parameter', async () => {
		const user = userEvent.setup();
		renderHub();
		await screen.findByRole( 'table', { name: 'Schools' } );
		await user.click( screen.getByRole( 'tab', { name: 'Classes' } ) );
		const table = await screen.findByRole( 'table', { name: 'Classes' } );
		expect( screen.getByTestId( 'where' ) ).toHaveTextContent(
			'/account-hub?tab=classes'
		);
		expect( within( table ).getByText( 'Independent' ) ).toBeVisible();
		expect( within( table ).getByText( 'Archived' ) ).toBeVisible();
		expect(
			within( table ).getByRole( 'link', { name: 'Algebra' } )
		).toHaveAttribute( 'href', '/account-hub/classes/9' );
		await user.click(
			await screen.findByRole( 'link', { name: 'Archived' } )
		);
		await waitFor( () =>
			expect( api.listClasses ).toHaveBeenLastCalledWith(
				1,
				'',
				'archived',
				undefined,
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'lists teachers with schools and only the emails the server sent', async () => {
		renderHub( '/account-hub?tab=teachers' );
		const table = await screen.findByRole( 'table', { name: 'Teachers' } );
		const rows = within( table ).getAllByRole( 'row' );
		expect(
			within( rows[ 1 ]! ).getByText( 'tess@example.org' )
		).toBeVisible();
		expect(
			within( rows[ 1 ]! ).getByText( 'North School' )
		).toBeVisible();
		expect( within( rows[ 2 ]! ).getByText( '—' ) ).toBeVisible();
		expect( within( rows[ 2 ]! ).getByText( 'No school' ) ).toBeVisible();
	} );
	it( 'validates and creates a school, then opens its editor', async () => {
		vi.mocked( api.createSchool ).mockResolvedValue( school );
		const user = userEvent.setup();
		renderHub();
		await screen.findByRole( 'table', { name: 'Schools' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Add school' } )
		);
		const dialog = screen.getByRole( 'dialog', { name: 'Add school' } );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create school' } )
		);
		expect(
			within( dialog ).getByText( /Enter a name of 1 to 190 characters/ )
		).toBeVisible();
		expect( api.createSchool ).not.toHaveBeenCalled();
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'School name' } ),
			'North School'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create school' } )
		);
		expect(
			await screen.findByText( 'School editor reached' )
		).toBeVisible();
		expect( api.createSchool ).toHaveBeenCalledWith(
			{ name: 'North School', timezone: 'UTC' },
			expect.any( AbortSignal )
		);
	} );
	it( 'shows a server error in the create dialog', async () => {
		vi.mocked( api.createSchool ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_account_hub_invalid_timezone',
				message: 'Choose a valid time zone.',
			} )
		);
		const user = userEvent.setup();
		renderHub();
		await screen.findByRole( 'table', { name: 'Schools' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Add school' } )
		);
		const dialog = screen.getByRole( 'dialog', { name: 'Add school' } );
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'School name' } ),
			'North'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create school' } )
		);
		expect(
			await within( dialog ).findByText(
				'Choose a valid time zone.',
				notice
			)
		).toBeVisible();
	} );
	it( 'shows a list error with a retry', async () => {
		vi.mocked( api.listSchools ).mockRejectedValueOnce(
			new ApiError( {
				status: 403,
				code: 'ohmylms_account_hub_cannot_view',
				message:
					'Sorry, you are not allowed to view schools and classes.',
			} )
		);
		const user = userEvent.setup();
		renderHub();
		expect(
			await screen.findByText( /not allowed to view schools/, notice )
		).toBeVisible();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'table', { name: 'Schools' } )
		).toBeVisible();
	} );
	it( 'manages a parent’s children: unlink, revoke and invite to link', async () => {
		vi.mocked( api.listSchoolStudents ).mockResolvedValue(
			page( [ person ] )
		);
		vi.mocked( api.linkChild ).mockResolvedValue( invitation );
		vi.mocked( api.unlinkChild ).mockResolvedValue( undefined );
		vi.mocked( api.revokeInvitation ).mockResolvedValue( undefined );
		const user = userEvent.setup();
		renderHub( '/account-hub?tab=parents' );
		await screen.findByRole( 'table', { name: 'Parents' } );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Manage children of Pat Parent',
			} )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Children of Pat Parent',
		} );
		const linked = await within( dialog ).findByRole( 'list', {
			name: 'Linked children',
		} );
		expect( linked ).toHaveTextContent( 'Ana Student (North School)' );
		await user.click(
			within( linked ).getByRole( 'button', {
				name: 'Unlink Ana Student',
			} )
		);
		expect( api.unlinkChild ).not.toHaveBeenCalled();
		await user.click(
			within( linked ).getByRole( 'button', {
				name: 'Confirm: unlink Ana Student',
			} )
		);
		await waitFor( () =>
			expect( api.unlinkChild ).toHaveBeenCalledWith(
				30,
				70,
				expect.any( AbortSignal )
			)
		);
		await user.click(
			await within( dialog ).findByRole( 'button', {
				name: 'Revoke invitation for Bo Student',
			} )
		);
		await waitFor( () =>
			expect( api.revokeInvitation ).toHaveBeenCalledWith(
				30,
				80,
				expect.any( AbortSignal )
			)
		);

		await waitFor( () =>
			expect(
				within( dialog ).getByRole( 'combobox', { name: 'School' } )
			).toHaveValue( '3' )
		);
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Student name' } ),
			'Bo'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Find student' } )
		);
		await waitFor( () =>
			expect( api.listSchoolStudents ).toHaveBeenCalledWith(
				3,
				'Bo',
				expect.any( AbortSignal )
			)
		);
		await user.click(
			await within( dialog ).findByRole( 'button', {
				name: 'Invite to link Bo Student',
			} )
		);
		expect(
			await within( dialog ).findByDisplayValue( invitation.url )
		).toBeVisible();
		expect( api.linkChild ).toHaveBeenCalledWith(
			30,
			3,
			22,
			expect.any( AbortSignal )
		);
	} );
} );
