import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import { student } from './fixtures';
import { studentReport } from './index';
import * as report from './report';

vi.mock( './api', () => ( { getStudent: vi.fn() } ) );
vi.mock( './report', async ( original ) => ( {
	...( await original< typeof import( './report' ) >() ),
	getReportSummary: vi.fn(),
	listQuizAttempts: vi.fn(),
	listSubmissions: vi.fn(),
	listSkills: vi.fn(),
} ) );

const as = {
	getStudent: vi.mocked( api.getStudent ),
	summary: vi.mocked( report.getReportSummary ),
	attempts: vi.mocked( report.listQuizAttempts ),
	submissions: vi.mocked( report.listSubmissions ),
	skills: vi.mocked( report.listSkills ),
};

const summary = {
	student_id: 12,
	courses: { enrolled: 2, completed: 1, in_progress: 1 },
	quizzes: { attempts: 3, in_review: 1, average_percent: 72.5 },
	assignments: { submitted: 2, graded: 1, passed: 1, failed: 0 },
	skills: { total: 4, review_due: 1, struggling: 0 },
};

const notice = { selector: '.components-notice__content' };

function show( route: string, capabilities = { manage_options: true } ) {
	return renderShell( [ studentReport ], { route, capabilities } );
}

beforeEach( () => {
	vi.clearAllMocks();
	as.getStudent.mockResolvedValue( student );
	as.summary.mockResolvedValue( summary );
	as.attempts.mockResolvedValue( {
		items: [
			{
				id: 5,
				quiz_id: 11,
				quiz_title: 'Fractions quiz',
				course_id: 3,
				course_title: 'Algebra',
				status: 'in-review',
				score: 3,
				max_marks: 4,
				percentage: 75,
				started_at: '2026-10-01T10:00:00',
				submitted_at: '2026-10-01T10:20:00',
			},
		],
		total: 1,
		totalPages: 1,
	} );
	as.submissions.mockResolvedValue( {
		items: [
			{
				id: 9,
				assignment_id: 31,
				assignment_title: 'Essay',
				course_id: 3,
				course_title: 'Algebra',
				status: 'graded',
				result: 'passed',
				score: 60,
				max_score: 80,
				attempts: 1,
				submitted_at: '2026-10-03T09:00:00',
				graded_at: '2026-10-04T09:00:00',
			},
		],
		total: 1,
		totalPages: 1,
	} );
	as.skills.mockResolvedValue( {
		items: [
			{
				skill_id: 4,
				name: 'Adding fractions',
				level: 'practising',
				level_label: 'Practising',
				review_due: true,
				struggling: false,
				evidence_count: 4,
				recent_accuracy: 0.75,
				last_evidence_at: null,
			},
		],
		total: 1,
		totalPages: 3,
	} );
} );

describe( 'student report', () => {
	it( 'makes no requests without manage_options', () => {
		show( '/students/12/report', { manage_options: false } );
		expect( as.summary ).not.toHaveBeenCalled();
		expect( as.getStudent ).not.toHaveBeenCalled();
	} );

	it( 'shows the summary and one table per result kind', async () => {
		show( '/students/12/report' );
		expect(
			await screen.findByRole( 'heading', {
				name: 'Report: Ada Lovelace',
			} )
		).toBeInTheDocument();
		expect(
			await screen.findByText(
				/3 submitted attempts, 1 need grading, average 72[.,]5%/
			)
		).toBeInTheDocument();
		const quizzes = await screen.findByRole( 'table', {
			name: 'Quiz attempts',
		} );
		expect(
			within( quizzes ).getByRole( 'link', { name: 'Fractions quiz' } )
		).toHaveAttribute( 'href', '/quizzes/11/attempts' );
		expect( within( quizzes ).getByText( 'Needs grading' ) ).toBeVisible();
		const tasks = await screen.findByRole( 'table', {
			name: 'Assignments',
		} );
		expect(
			within( tasks ).getByRole( 'link', { name: 'Essay' } )
		).toHaveAttribute( 'href', '/assignments/31/submissions/9' );
		expect( within( tasks ).getByText( '60 / 80' ) ).toBeVisible();
		const skills = await screen.findByRole( 'table', {
			name: 'Skill progress',
		} );
		expect( within( skills ).getByText( /review due/ ) ).toBeVisible();
		expect(
			screen.queryByText( 'ada@example.com' )
		).not.toBeInTheDocument();
	} );

	it( 'pages a table and resets the page when the filter changes', async () => {
		show( '/students/12/report' );
		const skills = await screen.findByRole( 'region', {
			name: 'Skill progress',
		} );
		await within( skills ).findByRole( 'table' );
		await userEvent.click(
			within( skills ).getAllByRole( 'button', {
				name: 'Next page',
			} )[ 0 ]!
		);
		await waitFor( () =>
			expect( as.skills ).toHaveBeenLastCalledWith(
				12,
				2,
				'all',
				expect.any( AbortSignal )
			)
		);
		await userEvent.selectOptions(
			within( skills ).getByLabelText( 'Show skills' ),
			'review_due'
		);
		await waitFor( () =>
			expect( as.skills ).toHaveBeenLastCalledWith(
				12,
				1,
				'review_due',
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'explains a missing student and a malformed id', async () => {
		as.getStudent.mockRejectedValue(
			new ApiError( {
				status: 404,
				code: 'ohmylms_student_not_found',
				message: 'Student not found.',
			} )
		);
		show( '/students/12/report' );
		expect(
			await screen.findByText( 'Student not found.', notice )
		).toBeVisible();
	} );

	it( 'refuses an id that is not a number', () => {
		show( '/students/abc/report' );
		expect(
			screen.getByText( 'That is not a valid student.', notice )
		).toBeVisible();
		expect( as.summary ).not.toHaveBeenCalled();
	} );
} );
