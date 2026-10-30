import '@testing-library/jest-dom/vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import * as reports from './reportsApi';
import { reportRow } from './reportsFixtures';
import QuizReportsPage from './QuizReportsPage';

vi.mock( './api', () => ( { listCourses: vi.fn() } ) );
vi.mock( './reportsApi', () => ( {
	listQuizReports: vi.fn(),
	getQuizReportSummary: vi.fn(),
} ) );

function Location() {
	const location = useLocation();
	return <output aria-label="location">{ location.search }</output>;
}

function renderPage( route = '/reports/quizzes' ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			<Routes>
				<Route
					path="/reports/quizzes"
					element={
						<>
							<QuizReportsPage />
							<Location />
						</>
					}
				/>
			</Routes>
		</MemoryRouter>
	);
}

const row = reportRow;
const other = {
	...reportRow,
	id: 12,
	title: 'Spelling quiz',
	course: null,
	completed: 1,
	in_progress: 0,
	to_grade: 0,
	attempts: 1,
	passed: null,
	pass_rate: null,
	passing_score: 0,
	can_edit: false,
};

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( reports.listQuizReports ).mockResolvedValue( {
		items: [ row, other ],
		total: 41,
		totalPages: 3,
	} );
	vi.mocked( reports.getQuizReportSummary ).mockResolvedValue( {
		quizzes: 41,
		attempts: 90,
		learners: 30,
		to_grade: 4,
		completed: 80,
	} );
	vi.mocked( api.listCourses ).mockResolvedValue( {
		items: [ { id: 5, title: 'Maths 101', status: 'publish' } ],
		total: 1,
		totalPages: 1,
	} );
} );

it( 'shows totals and the figures of every quiz with links', async () => {
	renderPage();
	const table = await screen.findByRole( 'table', { name: 'Quiz reports' } );
	expect(
		screen.getByText(
			'41 quizzes, 90 attempts, 30 learners, 4 to grade, 80 completed'
		)
	).toBeInTheDocument();
	expect(
		screen.getAllByText( '41 quizzes found.' )[ 0 ]
	).toBeInTheDocument();
	const [ head, first, second ] = within( table ).getAllByRole( 'row' );
	if ( ! head || ! first || ! second ) {
		throw new Error( 'Expected a header and two rows.' );
	}
	expect(
		within( first ).getByRole( 'link', { name: 'Fractions check' } )
	).toHaveAttribute( 'href', '/quizzes/11/attempts' );
	expect(
		within( first ).getByRole( 'link', {
			name: 'Settings for Fractions check',
		} )
	).toHaveAttribute( 'href', '/quizzes/11' );
	expect( within( first ).getByText( 'Maths 101' ) ).toBeInTheDocument();
	expect( within( first ).getByText( '50% (1 of 2)' ) ).toBeInTheDocument();
	expect(
		within( second ).queryByRole( 'link', { name: /Settings/ } )
	).not.toBeInTheDocument();
	expect( within( second ).getAllByText( '—' ).length ).toBeGreaterThan( 0 );
	expect( reports.listQuizReports ).toHaveBeenCalledWith(
		expect.objectContaining( {
			page: 1,
			orderby: 'last_submitted',
			order: 'desc',
		} ),
		expect.any( AbortSignal )
	);
} );

it( 'reads filters from the address and keeps them there', async () => {
	const user = userEvent.setup();
	renderPage( '/reports/quizzes?activity=to_grade&page=2' );
	await screen.findByRole( 'table', { name: 'Quiz reports' } );
	expect( reports.listQuizReports ).toHaveBeenLastCalledWith(
		expect.objectContaining( { activity: 'to_grade', page: 2 } ),
		expect.any( AbortSignal )
	);
	expect( screen.getByLabelText( 'Activity' ) ).toHaveValue( 'to_grade' );

	await user.click( await screen.findByRole( 'link', { name: 'Draft' } ) );
	await waitFor( () =>
		expect( screen.getByLabelText( 'location' ) ).toHaveTextContent(
			'?status=draft&activity=to_grade'
		)
	);
	expect( reports.getQuizReportSummary ).toHaveBeenLastCalledWith(
		expect.objectContaining( { status: 'draft', activity: 'to_grade' } ),
		expect.any( AbortSignal )
	);

	await user.type( screen.getByLabelText( 'Search quizzes' ), 'frac' );
	await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
	await waitFor( () =>
		expect( reports.listQuizReports ).toHaveBeenLastCalledWith(
			expect.objectContaining( { search: 'frac', page: 1 } ),
			expect.any( AbortSignal )
		)
	);

	await user.selectOptions( screen.getByLabelText( 'Course' ), '5' );
	await user.selectOptions( screen.getByLabelText( 'Sort by' ), 'title:asc' );
	await waitFor( () =>
		expect( reports.listQuizReports ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				course: 5,
				orderby: 'title',
				order: 'asc',
			} ),
			expect.any( AbortSignal )
		)
	);
} );

it( 'pages without reloading the totals', async () => {
	const user = userEvent.setup();
	renderPage();
	await screen.findByRole( 'table', { name: 'Quiz reports' } );
	const summaries = vi.mocked( reports.getQuizReportSummary ).mock.calls
		.length;
	await user.click(
		screen.getAllByRole( 'button', { name: 'Next page' } )[ 0 ]!
	);
	await waitFor( () =>
		expect( reports.listQuizReports ).toHaveBeenLastCalledWith(
			expect.objectContaining( { page: 2 } ),
			expect.any( AbortSignal )
		)
	);
	expect( screen.getAllByLabelText( 'Current Page' )[ 0 ] ).toHaveValue(
		'2'
	);
	expect( reports.getQuizReportSummary ).toHaveBeenCalledTimes( summaries );
} );

it( 'shows an empty state', async () => {
	vi.mocked( reports.listQuizReports ).mockResolvedValue( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	renderPage();
	expect(
		await screen.findByText( 'No quizzes match these filters.' )
	).toBeInTheDocument();
} );

it( 'shows an error and retries both requests', async () => {
	const user = userEvent.setup();
	vi.mocked( reports.listQuizReports ).mockRejectedValueOnce(
		new ApiError( { status: 500, code: 'x', message: 'Server down.' } )
	);
	renderPage();
	expect(
		await screen.findByText( /Server down\./, {
			selector: '.components-notice__content',
		} )
	).toBeInTheDocument();
	await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
	expect(
		await screen.findByRole( 'table', { name: 'Quiz reports' } )
	).toBeInTheDocument();
} );
