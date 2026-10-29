import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import {
	completedEnrollment,
	courses,
	enrollment,
	otherStudent,
	student,
} from './fixtures';
import { students } from './index';

vi.mock( './api', () => ( {
	listStudents: vi.fn(),
	getStudent: vi.fn(),
	setBlocked: vi.fn(),
	listCourses: vi.fn(),
	listEnrollments: vi.fn(),
	enroll: vi.fn(),
	unenroll: vi.fn(),
} ) );

const as = {
	listStudents: vi.mocked( api.listStudents ),
	getStudent: vi.mocked( api.getStudent ),
	setBlocked: vi.mocked( api.setBlocked ),
	listCourses: vi.mocked( api.listCourses ),
	listEnrollments: vi.mocked( api.listEnrollments ),
	enroll: vi.mocked( api.enroll ),
	unenroll: vi.mocked( api.unenroll ),
};

function show( route: string ) {
	return renderShell( [ students ], {
		route,
		capabilities: { manage_options: true },
	} );
}
const noticeOptions = { selector: '.components-notice__content' };
function failure( status: number, code: string, message: string ) {
	return new ApiError( { status, code, message } );
}

beforeEach( () => {
	vi.clearAllMocks();
	as.listStudents.mockResolvedValue( {
		items: [ student, otherStudent ],
		total: 2,
		totalPages: 1,
	} );
	as.listCourses.mockResolvedValue( {
		items: courses,
		total: courses.length,
		totalPages: 1,
	} );
	as.getStudent.mockResolvedValue( student );
	as.listEnrollments.mockResolvedValue( {
		items: [ enrollment, completedEnrollment ],
		total: 2,
		totalPages: 1,
	} );
} );

describe( 'students route', () => {
	it( 'is hidden and makes no requests without manage_options', () => {
		renderShell( [ students ], { route: '/students' } );
		expect(
			screen.queryByRole( 'link', { name: 'Students' } )
		).not.toBeInTheDocument();
		expect( as.listStudents ).not.toHaveBeenCalled();
	} );
} );

describe( 'student list', () => {
	it( 'shows loading, then a named table with links to profiles', async () => {
		let release!: () => void;
		as.listStudents.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					release = () =>
						resolve( {
							items: [ student, otherStudent ],
							total: 2,
							totalPages: 1,
						} );
				} )
		);
		show( '/students' );
		expect(
			await screen.findByText( 'Loading students…' )
		).toBeInTheDocument();
		await waitFor( () => expect( release ).toBeDefined() );
		release();
		const table = await screen.findByRole( 'table', { name: 'Students' } );
		expect(
			within( table ).getByText( 'ada@example.com' )
		).toBeInTheDocument();
		expect(
			within( table ).getByRole( 'link', { name: 'Ada Lovelace' } )
		).toHaveAttribute(
			'href',
			expect.stringContaining( '/students?student=12' )
		);
		expect( within( table ).getByText( 'Blocked' ) ).toBeInTheDocument();
		expect( screen.getByText( '2 students found.' ) ).toBeInTheDocument();
	} );

	it( 'shows an empty state', async () => {
		as.listStudents.mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		show( '/students' );
		expect(
			await screen.findByText( 'No students found.' )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );

	it( 'shows a server error and retries', async () => {
		const user = userEvent.setup();
		as.listStudents.mockRejectedValueOnce(
			failure( 500, 'ohmylms_failed', 'The database is unavailable.' )
		);
		show( '/students' );
		expect(
			await screen.findByText( /The database is unavailable\./ )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect( await screen.findByText( 'Ada Lovelace' ) ).toBeInTheDocument();
	} );

	it( 'shows a permission error from the server', async () => {
		as.listStudents.mockRejectedValue(
			failure(
				403,
				'ohmylms_student_cannot_view',
				'Sorry, you are not allowed to view students.'
			)
		);
		show( '/students' );
		expect(
			await screen.findByText(
				/not allowed to view students/,
				noticeOptions
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );

	it( 'searches with the keyboard, filters by course and sorts', async () => {
		const user = userEvent.setup();
		show( '/students' );
		await screen.findByText( 'Ada Lovelace' );
		await user.type(
			screen.getByRole( 'textbox', { name: 'Search students' } ),
			'ada{Enter}'
		);
		await waitFor( () =>
			expect( as.listStudents ).toHaveBeenLastCalledWith(
				expect.objectContaining( { search: 'ada', page: 1 } ),
				expect.any( AbortSignal )
			)
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Filter by course' } ),
			'Geometry'
		);
		await waitFor( () =>
			expect( as.listStudents ).toHaveBeenLastCalledWith(
				expect.objectContaining( { course: 41, search: 'ada' } ),
				expect.any( AbortSignal )
			)
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Sort by' } ),
			'Newest first'
		);
		await waitFor( () =>
			expect( as.listStudents ).toHaveBeenLastCalledWith(
				expect.objectContaining( {
					orderby: 'registered',
					order: 'desc',
				} ),
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'pages through results', async () => {
		const user = userEvent.setup();
		as.listStudents.mockResolvedValue( {
			items: [ student ],
			total: 41,
			totalPages: 3,
		} );
		show( '/students' );
		await screen.findByText( 'Ada Lovelace' );
		await user.click( screen.getByRole( 'button', { name: 'Next page' } ) );
		await waitFor( () =>
			expect( as.listStudents ).toHaveBeenLastCalledWith(
				expect.objectContaining( { page: 2 } ),
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'rejects a malformed student id and still shows the list', async () => {
		show( '/students?student=abc' );
		expect(
			await screen.findByText(
				'That is not a valid student.',
				noticeOptions
			)
		).toBeInTheDocument();
		expect( await screen.findByText( 'Ada Lovelace' ) ).toBeInTheDocument();
		expect( as.getStudent ).not.toHaveBeenCalled();
	} );
} );

describe( 'student profile', () => {
	it( 'shows details and enrolments with progress as text', async () => {
		show( '/students?student=12' );
		expect(
			await screen.findByRole( 'heading', { name: 'Ada Lovelace' } )
		).toBeInTheDocument();
		expect( as.getStudent ).toHaveBeenCalledWith(
			12,
			expect.any( AbortSignal )
		);
		const table = await screen.findByRole( 'table', {
			name: 'Enrolments and progress',
		} );
		expect(
			within( table ).getByText( 'Intro to Algebra' )
		).toBeInTheDocument();
		expect( within( table ).getByText( /40%/ ) ).toBeInTheDocument();
		expect(
			within( table ).getAllByText(
				/Lessons 2\/4, quizzes 0\/1, assignments 0\/0/
			)
		).toHaveLength( 2 );
		expect( within( table ).getByText( 'Completed' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'All students' } )
		).toBeInTheDocument();
	} );

	it( 'shows not found without a retry button', async () => {
		as.getStudent.mockRejectedValue(
			failure( 404, 'ohmylms_student_not_found', 'Student not found.' )
		);
		show( '/students?student=99' );
		expect(
			await screen.findByText( /Student not found\./, noticeOptions )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
	} );

	it( 'shows a permission error', async () => {
		as.getStudent.mockRejectedValue(
			failure(
				403,
				'ohmylms_student_cannot_view',
				'Sorry, you are not allowed to view students.'
			)
		);
		show( '/students?student=12' );
		expect(
			await screen.findByText(
				/not allowed to view students/,
				noticeOptions
			)
		).toBeInTheDocument();
	} );

	it( 'validates the enrol form before sending anything', async () => {
		const user = userEvent.setup();
		show( '/students?student=12' );
		await screen.findByRole( 'table', { name: 'Enrolments and progress' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Enrol student' } )
		);
		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
			'Choose a course to enrol the student in.'
		);
		expect( as.enroll ).not.toHaveBeenCalled();
	} );

	it( 'enrols the student and refreshes the enrolments', async () => {
		const user = userEvent.setup();
		as.enroll.mockResolvedValue( {
			...enrollment,
			course_id: 42,
			title: 'Calculus',
		} );
		show( '/students?student=12' );
		await screen.findByRole( 'table', { name: 'Enrolments and progress' } );
		const calls = as.listEnrollments.mock.calls.length;
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Course' } ),
			'Calculus (draft)'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Enrol student' } )
		);
		await waitFor( () =>
			expect( as.enroll ).toHaveBeenCalledWith(
				12,
				42,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText( 'Enrolled in Calculus.', noticeOptions )
		).toBeInTheDocument();
		await waitFor( () =>
			expect( as.listEnrollments.mock.calls.length ).toBeGreaterThan(
				calls
			)
		);
		expect( as.getStudent.mock.calls.length ).toBeGreaterThan( 1 );
	} );

	it( 'shows a server conflict when enrolling twice and prevents duplicate submits', async () => {
		const user = userEvent.setup();
		let reject!: ( reason: unknown ) => void;
		as.enroll.mockImplementation(
			() =>
				new Promise( ( _resolve, fail ) => {
					reject = fail;
				} )
		);
		show( '/students?student=12' );
		await screen.findByRole( 'table', { name: 'Enrolments and progress' } );
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Course' } ),
			'Geometry'
		);
		const button = screen.getByRole( 'button', { name: 'Enrol student' } );
		await user.click( button );
		await user.click( button );
		expect( as.enroll ).toHaveBeenCalledTimes( 1 );
		expect( button ).toBeDisabled();
		reject(
			failure(
				409,
				'ohmylms_student_already_enrolled',
				'The student is already enrolled in this course.'
			)
		);
		expect(
			await screen.findByText(
				'The student is already enrolled in this course.',
				noticeOptions
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Enrol student' } )
		).toBeEnabled();
		expect(
			screen.getByRole( 'combobox', { name: 'Course' } )
		).toHaveValue( '41' );
	} );

	it( 'asks for confirmation before unenrolling and can be cancelled with Escape', async () => {
		const user = userEvent.setup();
		as.unenroll.mockResolvedValue( enrollment );
		show( '/students?student=12' );
		await screen.findByRole( 'table', { name: 'Enrolments and progress' } );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Unenrol from Intro to Algebra',
			} )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Unenrol student',
		} );
		expect( dialog ).toHaveTextContent(
			/progress in this course is permanently deleted/
		);
		expect( as.unenroll ).not.toHaveBeenCalled();
		await user.keyboard( '{Escape}' );
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
		expect( as.unenroll ).not.toHaveBeenCalled();

		await user.click(
			screen.getByRole( 'button', {
				name: 'Unenrol from Intro to Algebra',
			} )
		);
		await user.click(
			within( await screen.findByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Unenrol student',
			} )
		);
		await waitFor( () =>
			expect( as.unenroll ).toHaveBeenCalledWith(
				12,
				40,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText(
				'Unenrolled from Intro to Algebra.',
				noticeOptions
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
	} );

	it( 'keeps the dialog open and shows the error when unenrolling fails', async () => {
		const user = userEvent.setup();
		as.unenroll.mockRejectedValue(
			failure(
				500,
				'ohmylms_enrollment_delete_failed',
				'The enrolment could not be removed.'
			)
		);
		show( '/students?student=12' );
		await screen.findByRole( 'table', { name: 'Enrolments and progress' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Unenrol from Geometry' } )
		);
		const dialog = within( await screen.findByRole( 'dialog' ) );
		await user.click(
			dialog.getByRole( 'button', { name: 'Unenrol student' } )
		);
		expect(
			await dialog.findByText( 'The enrolment could not be removed.' )
		).toBeInTheDocument();
	} );

	it( 'confirms blocking, then offers unblocking and disables enrolment', async () => {
		const user = userEvent.setup();
		as.setBlocked.mockResolvedValueOnce( { ...student, blocked: true } );
		as.setBlocked.mockResolvedValueOnce( student );
		show( '/students?student=12' );
		await screen.findByRole( 'table', { name: 'Enrolments and progress' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Block student' } )
		);
		expect( as.setBlocked ).not.toHaveBeenCalled();
		await user.click(
			within( await screen.findByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Block student',
			} )
		);
		await waitFor( () =>
			expect( as.setBlocked ).toHaveBeenCalledWith(
				12,
				true,
				expect.any( AbortSignal )
			)
		);
		const unblock = await screen.findByRole( 'button', {
			name: 'Unblock student',
		} );
		expect(
			screen.getByText( 'Unblock the student before enrolling them.' )
		).toBeInTheDocument();
		await user.click( unblock );
		await waitFor( () =>
			expect( as.setBlocked ).toHaveBeenLastCalledWith(
				12,
				false,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByRole( 'button', { name: 'Block student' } )
		).toBeInTheDocument();
	} );

	it( 'shows a protected-user error when blocking is refused', async () => {
		const user = userEvent.setup();
		as.setBlocked.mockRejectedValue(
			failure(
				422,
				'ohmylms_student_protected',
				'You cannot block yourself or an administrator.'
			)
		);
		show( '/students?student=12' );
		await screen.findByRole( 'table', { name: 'Enrolments and progress' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Block student' } )
		);
		await user.click(
			within( await screen.findByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Block student',
			} )
		);
		expect(
			await screen.findByText(
				'You cannot block yourself or an administrator.',
				noticeOptions
			)
		).toBeInTheDocument();
	} );

	it( 'filters enrolments by state and shows an empty state', async () => {
		const user = userEvent.setup();
		show( '/students?student=12' );
		await screen.findByRole( 'table', { name: 'Enrolments and progress' } );
		as.listEnrollments.mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Progress' } ),
			'Completed'
		);
		await waitFor( () =>
			expect( as.listEnrollments ).toHaveBeenLastCalledWith(
				12,
				expect.objectContaining( { progress: 'completed' } ),
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText( 'No enrolments found.' )
		).toBeInTheDocument();
	} );
} );
