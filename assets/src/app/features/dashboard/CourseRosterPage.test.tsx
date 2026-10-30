import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import { completedStudent, student } from './fixtures';
import { courseRoster } from './index';
import * as roster from './roster';

vi.mock( './api', () => ( { listCourseStudents: vi.fn() } ) );
vi.mock( './roster', async ( original ) => ( {
	...( await original< typeof import( './roster' ) >() ),
	enrollByEmail: vi.fn(),
	bulkUnenroll: vi.fn(),
	sendReminders: vi.fn(),
} ) );

const as = {
	list: vi.mocked( api.listCourseStudents ),
	enroll: vi.mocked( roster.enrollByEmail ),
	bulk: vi.mocked( roster.bulkUnenroll ),
	remind: vi.mocked( roster.sendReminders ),
};

const notice = { selector: '.components-notice__content' };

function show( route = '/reports/courses/40/students' ) {
	return renderShell( [ courseRoster ], {
		route,
		capabilities: { manage_options: true },
	} );
}

beforeEach( () => {
	vi.clearAllMocks();
	as.list.mockResolvedValue( {
		items: [ student, completedStudent ],
		total: 2,
		totalPages: 1,
	} );
} );

describe( 'course roster', () => {
	it( 'is not reachable without manage_options', () => {
		renderShell( [ courseRoster ], {
			route: '/reports/courses/40/students',
		} );
		expect( as.list ).not.toHaveBeenCalled();
	} );

	it( 'enrols by e-mail after validating it and reloads the list', async () => {
		as.enroll.mockResolvedValue( { user_id: 14, name: 'Alan Turing' } );
		show();
		await screen.findByRole( 'table', { name: 'Students of this course' } );
		const input = screen.getByLabelText( 'E-mail address' );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Enrol' } )
		);
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'Enter the e-mail address of the student.'
		);
		expect( as.enroll ).not.toHaveBeenCalled();
		await userEvent.type( input, 'alan@example.com' );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Enrol' } )
		);
		expect(
			await screen.findByText( 'Alan Turing was enrolled.', notice )
		).toBeVisible();
		expect( as.enroll ).toHaveBeenCalledWith(
			40,
			'alan@example.com',
			expect.any( AbortSignal )
		);
		await waitFor( () => expect( as.list ).toHaveBeenCalledTimes( 2 ) );
	} );

	it( 'shows server errors of the enrol form', async () => {
		as.enroll.mockRejectedValue(
			new ApiError( {
				status: 404,
				code: 'ohmylms_roster_user_not_found',
				message: 'No user has this e-mail address.',
			} )
		);
		show();
		await userEvent.type(
			await screen.findByLabelText( 'E-mail address' ),
			'nobody@example.com'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Enrol' } )
		);
		expect(
			await screen.findByText(
				'No user has this e-mail address.',
				notice
			)
		).toBeVisible();
	} );

	it( 'sends a fixed reminder to selected students after confirmation', async () => {
		as.remind.mockResolvedValue( {
			sent: [ 12 ],
			skipped: [ { user_id: 13, reason: 'completed' } ],
			remaining: 48,
		} );
		show();
		const table = await screen.findByRole( 'table', {
			name: 'Students of this course',
		} );
		await userEvent.click(
			within( table ).getByLabelText( 'Select all on this page' )
		);
		await userEvent.selectOptions(
			screen.getByLabelText( 'Bulk action' ),
			'remind:continue'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Apply to 2 selected' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Send reminders',
		} );
		expect( as.remind ).not.toHaveBeenCalled();
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Confirm' } )
		);
		expect(
			await screen.findByText(
				'1 reminder sent. Skipped: already completed (1).',
				notice
			)
		).toBeVisible();
		expect( as.remind ).toHaveBeenCalledWith(
			40,
			'continue',
			[ 12, 13 ],
			expect.any( AbortSignal )
		);
	} );

	it( 'unenrols selected students and keeps the dialog open on failure', async () => {
		as.bulk
			.mockRejectedValueOnce(
				new ApiError( {
					status: 500,
					code: 'boom',
					message: 'Server failure.',
				} )
			)
			.mockResolvedValueOnce( {
				results: [ { user_id: 12, ok: true, code: null } ],
			} );
		show();
		const table = await screen.findByRole( 'table', {
			name: 'Students of this course',
		} );
		await userEvent.click(
			within( table ).getByLabelText( 'Select Ada Lovelace' )
		);
		await userEvent.selectOptions(
			screen.getByLabelText( 'Bulk action' ),
			'unenroll'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Apply to 1 selected' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Unenrol students',
		} );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Confirm' } )
		);
		expect(
			await within( dialog ).findByText( 'Server failure.', notice )
		).toBeVisible();
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Confirm' } )
		);
		expect(
			await screen.findByText( '1 student unenrolled.', notice )
		).toBeVisible();
		expect( as.bulk ).toHaveBeenLastCalledWith(
			40,
			[ 12 ],
			expect.any( AbortSignal )
		);
	} );

	it( 'rejects a malformed course id', () => {
		show( '/reports/courses/x/students' );
		expect(
			screen.getByText( 'That is not a valid course.', notice )
		).toBeVisible();
		expect( as.list ).not.toHaveBeenCalled();
	} );
} );
