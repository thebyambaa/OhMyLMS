import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { page, school } from './fixtures';
import * as roster from './roster';
import SchoolRosterPage from './SchoolRosterPage';

vi.mock( './api', () => ( { getSchool: vi.fn() } ) );
vi.mock( './roster', async ( original ) => ( {
	...( await original< typeof import( './roster' ) >() ),
	listInvitations: vi.fn(),
	createInvitation: vi.fn(),
	revokeRosterInvitation: vi.fn(),
	listManagedStudents: vi.fn(),
	createManagedStudent: vi.fn(),
	createSetupLink: vi.fn(),
	reviewRosterImport: vi.fn(),
	applyRosterImport: vi.fn(),
} ) );

const pendingInvite: roster.RosterInvitation = {
	id: 50,
	role: 'teacher',
	email: null,
	email_hint: 't***@example.org',
	class_id: null,
	class_name: null,
	student_id: null,
	student_name: null,
	status: 'pending',
	expires_at: '2026-11-01T09:30:00Z',
	created_at: '2026-10-30T09:30:00Z',
};
const kid: roster.ManagedStudent = {
	id: 60,
	display_name: 'Kim Kid',
	username: 'student-3-abc',
	external_student_id: 'S-1',
	joined_at: '2026-10-30T09:30:00Z',
	setup_expires_at: null,
};
const link = {
	id: 90,
	url: 'https://example.test/?ohmylms_invite=abc',
	expires_at: '2026-11-01T09:30:00Z',
	email_sent: false,
};
const csv = 'role,name,email,external_student_id\nstudent,Ana,,S-2';
const reviewReport: roster.ImportReport = {
	dry_run: true,
	review: 'a'.repeat( 64 ),
	counts: {
		create_student: 1,
		invite: 0,
		existing: 0,
		pending: 0,
		error: 0,
	},
	ignored: [],
	rows: [
		{
			line: 2,
			role: 'student',
			name: 'Ana',
			email: '',
			external_student_id: 'S-2',
			action: 'create_student',
			error: null,
			user_id: null,
			username: null,
			url: null,
		},
	],
};

function renderRoster() {
	return render(
		<MemoryRouter initialEntries={ [ '/account-hub/schools/3/roster' ] }>
			<Routes>
				<Route
					path="/account-hub/schools/:id/roster"
					element={ <SchoolRosterPage /> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getSchool ).mockResolvedValue( school );
	vi.mocked( roster.listInvitations ).mockResolvedValue(
		page( [ pendingInvite ] )
	);
	vi.mocked( roster.listManagedStudents ).mockResolvedValue(
		page( [ kid ] )
	);
} );

describe( 'SchoolRosterPage', () => {
	it( 'lists invitations with masked recipients and managed students', async () => {
		renderRoster();
		expect(
			await screen.findByRole( 'heading', {
				name: 'North School: roster',
			} )
		).toBeVisible();
		const invitations = await screen.findByRole( 'table', {
			name: 'Invitations',
		} );
		expect(
			within( invitations ).getByText( 't***@example.org' )
		).toBeVisible();
		const students = await screen.findByRole( 'table', {
			name: 'Managed students',
		} );
		expect( within( students ).getByText( 'student-3-abc' ) ).toBeVisible();
		expect( roster.listInvitations ).toHaveBeenCalledWith(
			3,
			1,
			'',
			false,
			expect.any( AbortSignal )
		);
	} );

	it( 'validates the email and shows the invitation link once', async () => {
		const user = userEvent.setup();
		vi.mocked( roster.createInvitation ).mockResolvedValue( link );
		renderRoster();
		const form = within(
			await screen.findByRole( 'form', { name: 'Invite to the roster' } )
		);
		await user.type( form.getByLabelText( 'Email' ), 'nope' );
		await user.click(
			form.getByRole( 'button', { name: 'Create invitation' } )
		);
		expect(
			form.getByText( 'Enter a valid email address.' )
		).toBeVisible();
		expect( roster.createInvitation ).not.toHaveBeenCalled();
		await user.clear( form.getByLabelText( 'Email' ) );
		await user.type( form.getByLabelText( 'Email' ), 'new@example.org' );
		await user.click(
			form.getByRole( 'button', { name: 'Create invitation' } )
		);
		expect( await screen.findByLabelText( 'Single-use link' ) ).toHaveValue(
			link.url
		);
		expect( roster.createInvitation ).toHaveBeenCalledWith(
			3,
			{ role: 'teacher', email: 'new@example.org', send_email: false },
			expect.any( AbortSignal )
		);
	} );

	it( 'asks before replacing a password setup link', async () => {
		const user = userEvent.setup();
		vi.mocked( roster.createSetupLink ).mockResolvedValue( link );
		renderRoster();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Create a password setup link for Kim Kid',
			} )
		);
		expect( roster.createSetupLink ).not.toHaveBeenCalled();
		await user.click(
			within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Create link',
			} )
		);
		await waitFor( () =>
			expect( roster.createSetupLink ).toHaveBeenCalledWith(
				3,
				60,
				expect.any( AbortSignal )
			)
		);
		expect( await screen.findByLabelText( 'Single-use link' ) ).toHaveValue(
			link.url
		);
	} );

	it( 'reviews before importing and applies with the review token', async () => {
		const user = userEvent.setup();
		vi.mocked( roster.reviewRosterImport ).mockResolvedValue(
			reviewReport
		);
		vi.mocked( roster.applyRosterImport ).mockResolvedValue( {
			...reviewReport,
			dry_run: false,
			review: null,
			counts: {
				created: 1,
				invited: 0,
				existing: 0,
				pending: 0,
				error: 0,
			},
			rows: [
				{
					...reviewReport.rows[ 0 ]!,
					action: 'created',
					user_id: 61,
					username: 'student-3-xyz',
				},
			],
		} );
		renderRoster();
		const field = await screen.findByLabelText( 'CSV' );
		await user.click(
			screen.getByRole( 'button', { name: 'Review import' } )
		);
		expect(
			screen.getByText( 'Paste CSV text or choose a file.' )
		).toBeVisible();
		await user.click( field );
		await user.paste( csv );
		await user.click(
			screen.getByRole( 'button', { name: 'Review import' } )
		);
		expect(
			await screen.findByRole( 'table', { name: 'Import review' } )
		).toBeVisible();
		expect( screen.getByText( /Nothing has changed yet/ ) ).toBeVisible();
		await user.click(
			screen.getByRole( 'button', { name: 'Import reviewed rows' } )
		);
		await screen.findByRole( 'table', { name: 'Import result' } );
		expect( roster.applyRosterImport ).toHaveBeenCalledWith(
			3,
			csv,
			'a'.repeat( 64 ),
			false,
			expect.any( AbortSignal )
		);
		expect(
			screen.getByText( /sign-in name student-3-xyz/ )
		).toBeVisible();
	} );

	it( 'requires a new review after the CSV or the roster changed', async () => {
		const user = userEvent.setup();
		vi.mocked( roster.reviewRosterImport ).mockResolvedValue(
			reviewReport
		);
		vi.mocked( roster.applyRosterImport ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_account_hub_review_stale',
				message: 'Changed.',
			} )
		);
		renderRoster();
		const field = await screen.findByLabelText( 'CSV' );
		await user.click( field );
		await user.paste( csv );
		await user.click(
			screen.getByRole( 'button', { name: 'Review import' } )
		);
		await screen.findByRole( 'table', { name: 'Import review' } );
		await user.type( field, '\nstudent,Bo,,S-3' );
		expect(
			screen.getByText(
				'The CSV changed after the review. Review it again.',
				{ selector: '.components-notice__content' }
			)
		).toBeVisible();
		expect(
			screen.queryByRole( 'button', { name: 'Import reviewed rows' } )
		).not.toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Review import' } )
		);
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Import reviewed rows',
			} )
		);
		expect(
			await screen.findByText( /changed since the review/, {
				selector: '.components-notice__content',
			} )
		).toBeVisible();
		expect(
			screen.queryByRole( 'button', { name: 'Import reviewed rows' } )
		).not.toBeInTheDocument();
	} );
} );
