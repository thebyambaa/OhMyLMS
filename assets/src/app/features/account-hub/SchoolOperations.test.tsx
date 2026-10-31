import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import {
	guardianInvite,
	guardianLink,
	invitation,
	noSample,
	page,
	person,
	plan,
	removal,
	report,
	rolloverResult,
	sampleSchool,
	school,
	schoolClass,
} from './fixtures';
import * as operations from './operationsApi';
import { SampleSchoolActions } from './SampleSchoolActions';
import SchoolEditorPage from './SchoolEditorPage';

vi.mock( './api', () => ( {
	getSchool: vi.fn(),
	updateSchool: vi.fn(),
	createYear: vi.fn(),
	listClasses: vi.fn(),
	createClass: vi.fn(),
	listSchoolStudents: vi.fn(),
	listParents: vi.fn(),
} ) );

vi.mock( './operationsApi', () => ( {
	archiveSchool: vi.fn(),
	restoreSchool: vi.fn(),
	getRemoval: vi.fn(),
	deleteSchool: vi.fn(),
	listGuardians: vi.fn(),
	inviteGuardian: vi.fn(),
	unlinkGuardian: vi.fn(),
	revokeGuardianInvitation: vi.fn(),
	getReport: vi.fn(),
	exportReport: vi.fn(),
	previewRollover: vi.fn(),
	applyRollover: vi.fn(),
	getSampleSchool: vi.fn(),
	createSampleSchool: vi.fn(),
	removeSampleSchool: vi.fn(),
} ) );

const notice = { selector: '.components-notice__content' };

function Where() {
	const location = useLocation();
	return <p data-testid="where">{ location.pathname + location.search }</p>;
}

function renderSchool( entry = '/account-hub/schools/3' ) {
	return render(
		<MemoryRouter initialEntries={ [ entry ] }>
			<Routes>
				<Route
					path="/account-hub/schools/:id"
					element={
						<>
							<SchoolEditorPage />
							<Where />
						</>
					}
				/>
				<Route path="/account-hub" element={ <Where /> } />
			</Routes>
		</MemoryRouter>
	);
}

const twoYears = {
	...school,
	years: [
		{
			id: 6,
			label: '2027-2028',
			starts_at: '2027-09-01',
			ends_at: '2028-06-30',
			status: 'active',
		},
		...school.years,
	],
};

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getSchool ).mockResolvedValue( school );
	vi.mocked( api.listClasses ).mockResolvedValue( page( [ schoolClass ] ) );
	vi.mocked( operations.listGuardians ).mockResolvedValue(
		page( [ guardianLink, guardianInvite ] )
	);
	vi.mocked( operations.getReport ).mockResolvedValue( report );
	vi.mocked( operations.getRemoval ).mockResolvedValue( removal );
} );

describe( 'school panels', () => {
	it( 'links the sub-screens of a school', async () => {
		const user = userEvent.setup();
		renderSchool();
		const nav = await screen.findByRole( 'navigation', {
			name: 'School sections',
		} );
		expect(
			within( nav ).getByRole( 'link', { name: 'Details and classes' } )
		).toHaveAttribute( 'aria-current', 'page' );
		await user.click(
			within( nav ).getByRole( 'link', { name: 'Report' } )
		);
		expect( screen.getByTestId( 'where' ) ).toHaveTextContent(
			'/account-hub/schools/3?panel=report'
		);
		expect(
			await screen.findByRole( 'region', { name: 'School report' } )
		).toBeVisible();
	} );
} );

describe( 'school removal', () => {
	it( 'archives after a confirmation and shows the school read only', async () => {
		vi.mocked( operations.archiveSchool ).mockResolvedValue( {
			...school,
			status: 'archived',
			version: 'aaaaaaaaaaaaaaaa',
		} );
		const user = userEvent.setup();
		renderSchool();
		await user.click(
			await screen.findByRole( 'button', { name: 'Archive school…' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Archive this school?',
		} );
		expect(
			within( dialog ).getByText( /come back when you restore it/ )
		).toBeVisible();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Archive school' } )
		);
		expect(
			await screen.findByText(
				/This school is archived. It is read only/,
				notice
			)
		).toBeVisible();
		expect( operations.archiveSchool ).toHaveBeenCalledWith(
			3,
			expect.any( AbortSignal )
		);
		expect(
			screen.getByRole( 'textbox', { name: 'School name' } )
		).toBeDisabled();
		expect(
			screen.queryByRole( 'form', { name: 'Add academic year' } )
		).toBeNull();
		expect(
			screen.getByRole( 'button', { name: 'Restore school' } )
		).toBeVisible();
	} );
	it( 'deletes an archived school only after the name is typed', async () => {
		vi.mocked( api.getSchool ).mockResolvedValue( {
			...school,
			status: 'archived',
		} );
		vi.mocked( operations.deleteSchool ).mockResolvedValue( undefined );
		const user = userEvent.setup();
		renderSchool();
		await user.click(
			await screen.findByRole( 'button', { name: 'Delete permanently…' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Delete this school permanently?',
		} );
		expect(
			await within( dialog ).findByText(
				/3 course enrolments granted by class rosters are cancelled/
			)
		).toBeVisible();
		expect(
			within( dialog ).getByText(
				/No account is deleted: the 4 accounts this school created stay/
			)
		).toBeVisible();
		expect(
			within( dialog ).getByText( /2 classes with their rosters/ )
		).toBeVisible();
		const confirm = within( dialog ).getByRole( 'button', {
			name: 'Delete school permanently',
		} );
		expect( confirm ).toBeDisabled();
		await user.type(
			within( dialog ).getByRole( 'textbox', {
				name: 'Type "North School" to confirm',
			} ),
			'North'
		);
		expect( confirm ).toBeDisabled();
		await user.type(
			within( dialog ).getByRole( 'textbox', {
				name: 'Type "North School" to confirm',
			} ),
			' School'
		);
		await user.click( confirm );
		await waitFor( () =>
			expect( screen.getByTestId( 'where' ) ).toHaveTextContent(
				'/account-hub?tab=schools'
			)
		);
		expect( operations.deleteSchool ).toHaveBeenCalledWith(
			3,
			'North School',
			expect.any( AbortSignal )
		);
	} );
	it( 'keeps the dialog open with the server error', async () => {
		vi.mocked( api.getSchool ).mockResolvedValue( {
			...school,
			status: 'archived',
		} );
		vi.mocked( operations.deleteSchool ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_account_hub_confirmation_mismatch',
				message: 'Type the school name exactly to delete it.',
			} )
		);
		const user = userEvent.setup();
		renderSchool();
		await user.click(
			await screen.findByRole( 'button', { name: 'Delete permanently…' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await user.type(
			await within( dialog ).findByRole( 'textbox' ),
			'North School'
		);
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Delete school permanently',
			} )
		);
		expect(
			await within( dialog ).findByText(
				'Type the school name exactly to delete it.',
				notice
			)
		).toBeVisible();
	} );
} );

describe( 'guardians', () => {
	it( 'lists links and invitations with minimal fields and filters by class', async () => {
		const user = userEvent.setup();
		renderSchool( '/account-hub/schools/3?panel=guardians&class_id=9' );
		const table = await screen.findByRole( 'table', {
			name: 'Guardians of this school',
		} );
		expect( within( table ).getByText( 'Pat Parent' ) ).toBeVisible();
		expect(
			within( table ).getByText( 'Invited: g***@example.org' )
		).toBeVisible();
		expect( operations.listGuardians ).toHaveBeenCalledWith(
			3,
			1,
			'',
			'all',
			9,
			expect.any( AbortSignal )
		);
		await user.click( screen.getByRole( 'link', { name: /Linked/ } ) );
		await waitFor( () =>
			expect( operations.listGuardians ).toHaveBeenLastCalledWith(
				3,
				1,
				'',
				'active',
				9,
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'unlinks after a confirmation', async () => {
		vi.mocked( operations.unlinkGuardian ).mockResolvedValue( undefined );
		const user = userEvent.setup();
		renderSchool( '/account-hub/schools/3?panel=guardians' );
		const table = await screen.findByRole( 'table', {
			name: 'Guardians of this school',
		} );
		await user.click(
			within( table ).getByRole( 'button', { name: 'Unlink' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Unlink this guardian?',
		} );
		expect(
			within( dialog ).getByText( /Pat Parent will no longer see/ )
		).toBeVisible();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Unlink' } )
		);
		await waitFor( () =>
			expect( operations.unlinkGuardian ).toHaveBeenCalledWith(
				3,
				70,
				expect.any( AbortSignal )
			)
		);
		expect( operations.listGuardians ).toHaveBeenCalledTimes( 2 );
	} );
	it( 'invites a guardian by email and shows the link once', async () => {
		vi.mocked( api.listSchoolStudents ).mockResolvedValue(
			page( [ person ] )
		);
		vi.mocked( operations.inviteGuardian ).mockResolvedValue( invitation );
		const user = userEvent.setup();
		renderSchool( '/account-hub/schools/3?panel=guardians' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Invite a guardian' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Invite a guardian',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Create invitation',
			} )
		);
		expect(
			within( dialog ).getByText( 'Find and choose a student first.' )
		).toBeVisible();
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Student name' } ),
			'Bo'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Find student' } )
		);
		await within( dialog ).findByRole( 'option', { name: 'Bo Student' } );
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Guardian email' } ),
			'guardian@example.org'
		);
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Create invitation',
			} )
		);
		expect(
			await screen.findByDisplayValue( invitation.url )
		).toBeVisible();
		expect( operations.inviteGuardian ).toHaveBeenCalledWith(
			3,
			{
				student_id: 22,
				email: 'guardian@example.org',
				send_email: false,
			},
			expect.any( AbortSignal )
		);
	} );
} );

describe( 'school report', () => {
	it( 'shows the summary, classes and mastery and downloads the CSV', async () => {
		const created = vi.fn( () => 'blob:report' );
		const revoked = vi.fn();
		Object.assign( URL, {
			createObjectURL: created,
			revokeObjectURL: revoked,
		} );
		vi.mocked( operations.exportReport ).mockResolvedValue( {
			filename: 'school-report-north-school-2026-10-31.csv',
			content_type: 'text/csv;charset=utf-8',
			csv: '"Class"\r\n',
		} );
		const user = userEvent.setup();
		renderSchool( '/account-hub/schools/3?panel=report' );
		const summary = await screen.findByRole( 'table', {
			name: 'Whole school',
		} );
		expect(
			within( summary ).getByText( '25% (1 of 4 enrolments)' )
		).toBeVisible();
		expect(
			within( summary ).getByText( 'Active learners (last 30 days)' )
		).toBeVisible();
		expect(
			screen.getByRole( 'table', { name: 'Report by class' } )
		).toHaveTextContent( 'Algebra' );
		expect(
			screen.getByRole( 'table', { name: 'Mastery by skill' } )
		).toHaveTextContent( 'F1 Fractions' );
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Activity period' } ),
			'7'
		);
		await waitFor( () =>
			expect( operations.getReport ).toHaveBeenLastCalledWith(
				3,
				7,
				expect.any( AbortSignal )
			)
		);
		await user.click(
			await screen.findByRole( 'button', { name: 'Download CSV' } )
		);
		await waitFor( () => expect( created ).toHaveBeenCalled() );
		expect( operations.exportReport ).toHaveBeenCalledWith(
			3,
			7,
			expect.any( AbortSignal )
		);
		expect( revoked ).toHaveBeenCalledWith( 'blob:report' );
	} );
} );

describe( 'year rollover', () => {
	it( 'asks for a second year first', async () => {
		renderSchool( '/account-hub/schools/3?panel=rollover' );
		expect(
			await screen.findByText( /needs two active academic years/ )
		).toBeVisible();
	} );
	it( 'previews with a suggested grade map and applies the reviewed plan', async () => {
		vi.mocked( api.getSchool ).mockResolvedValue( twoYears );
		vi.mocked( operations.previewRollover ).mockResolvedValue( plan );
		vi.mocked( operations.applyRollover ).mockResolvedValue(
			rolloverResult
		);
		const user = userEvent.setup();
		renderSchool( '/account-hub/schools/3?panel=rollover' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Preview rollover' } )
		);
		const table = await screen.findByRole( 'table', {
			name: 'Rollover preview',
		} );
		expect( within( table ).getByText( '6A (grade 6)' ) ).toBeVisible();
		expect( operations.previewRollover ).toHaveBeenNthCalledWith(
			1,
			3,
			{
				from_year_id: 5,
				to_year_id: 6,
				grade_map: [],
				copy_teachers: false,
			},
			expect.any( AbortSignal )
		);
		const suggested = {
			from_year_id: 5,
			to_year_id: 6,
			grade_map: [ { from: '5', to: '6' } ],
			copy_teachers: false,
		};
		expect( operations.previewRollover ).toHaveBeenNthCalledWith(
			2,
			3,
			suggested,
			expect.any( AbortSignal )
		);
		expect(
			screen.getByRole( 'textbox', { name: 'Grade 5 becomes' } )
		).toHaveValue( '6' );
		await user.click(
			screen.getByRole( 'button', { name: 'Apply rollover…' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Roll over 2026-2027 to 2027-2028?',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Apply rollover' } )
		);
		expect(
			await screen.findByText(
				'Rolled over from 2026-2027 to 2027-2028.'
			)
		).toBeVisible();
		expect( operations.applyRollover ).toHaveBeenCalledWith(
			3,
			suggested,
			plan.review,
			expect.any( AbortSignal )
		);
	} );
	it( 'drops the preview when the grade map changes', async () => {
		vi.mocked( api.getSchool ).mockResolvedValue( twoYears );
		vi.mocked( operations.previewRollover ).mockResolvedValue( plan );
		const user = userEvent.setup();
		renderSchool( '/account-hub/schools/3?panel=rollover' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Preview rollover' } )
		);
		await screen.findByRole( 'table', { name: 'Rollover preview' } );
		await user.clear(
			screen.getByRole( 'textbox', { name: 'Grade 5 becomes' } )
		);
		expect(
			screen.queryByRole( 'table', { name: 'Rollover preview' } )
		).toBeNull();
		expect(
			screen.queryByRole( 'button', { name: 'Apply rollover…' } )
		).toBeNull();
	} );
} );

describe( 'sample school', () => {
	it( 'creates the sample school', async () => {
		vi.mocked( operations.getSampleSchool ).mockResolvedValue( noSample );
		vi.mocked( operations.createSampleSchool ).mockResolvedValue(
			sampleSchool
		);
		const changed = vi.fn();
		const user = userEvent.setup();
		render(
			<MemoryRouter>
				<SampleSchoolActions onChange={ changed } />
			</MemoryRouter>
		);
		await user.click(
			await screen.findByRole( 'button', { name: 'Add sample school' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Add a sample school?',
		} );
		expect( within( dialog ).getByText( /cannot sign in/ ) ).toBeVisible();
		vi.mocked( operations.getSampleSchool ).mockResolvedValue(
			sampleSchool
		);
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Add sample school',
			} )
		);
		expect(
			await screen.findByText(
				/Sample school ready: 2 classes and 2 sample people/,
				notice
			)
		).toBeVisible();
		expect( changed ).toHaveBeenCalled();
		expect(
			await screen.findByRole( 'button', {
				name: 'Remove sample school',
			} )
		).toBeVisible();
	} );
	it( 'removes it in one step and refuses while it is in use', async () => {
		vi.mocked( operations.getSampleSchool ).mockResolvedValue( {
			...sampleSchool,
			removal: {
				allowed: false,
				remove: [],
				skip: [],
				blockers: [ 'Invitations were sent for the school' ],
			},
		} );
		const user = userEvent.setup();
		const first = render(
			<MemoryRouter>
				<SampleSchoolActions onChange={ vi.fn() } />
			</MemoryRouter>
		);
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Remove sample school',
			} )
		);
		let dialog = screen.getByRole( 'dialog', {
			name: 'Remove the sample school?',
		} );
		expect(
			within( dialog ).getByText( 'Invitations were sent for the school' )
		).toBeVisible();
		expect(
			within( dialog ).getByRole( 'button', {
				name: 'Remove sample school',
			} )
		).toBeDisabled();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Cancel' } )
		);
		vi.mocked( operations.getSampleSchool ).mockResolvedValue(
			sampleSchool
		);
		vi.mocked( operations.removeSampleSchool ).mockResolvedValue(
			undefined
		);
		first.unmount();
		render(
			<MemoryRouter>
				<SampleSchoolActions onChange={ vi.fn() } />
			</MemoryRouter>
		);
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Remove sample school',
			} )
		);
		dialog = screen.getByRole( 'dialog', {
			name: 'Remove the sample school?',
		} );
		expect(
			within( dialog ).getByText( 'Account "Sample Teacher" (#60)' )
		).toBeVisible();
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Remove sample school',
			} )
		);
		expect(
			await screen.findByText(
				'The sample school and its sample accounts were removed.',
				notice
			)
		).toBeVisible();
		expect( operations.removeSampleSchool ).toHaveBeenCalledTimes( 1 );
	} );
} );
