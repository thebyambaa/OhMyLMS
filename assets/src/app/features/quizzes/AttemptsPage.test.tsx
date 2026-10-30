import '@testing-library/jest-dom/vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { attempt, attemptDetail, quiz } from './fixtures';
import AttemptsPage from './AttemptsPage';

vi.mock( './api', () => ( {
	getQuiz: vi.fn(),
	listAttempts: vi.fn(),
	getAttempt: vi.fn(),
	gradeAttempt: vi.fn(),
} ) );

function renderPage( route = '/quizzes/11/attempts' ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			<Routes>
				<Route
					path="/quizzes/:id/attempts"
					element={ <AttemptsPage /> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };
const rejection = ( status: number, code: string, message: string ) =>
	new ApiError( { status, code, message } );

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getQuiz ).mockResolvedValue( quiz );
	vi.mocked( api.listAttempts ).mockResolvedValue( {
		items: [
			attempt,
			{
				...attempt,
				id: 22,
				status: 'completed',
				needs_grading: false,
				score: 6,
				percentage: 100,
				student: {
					id: 10,
					name: 'Pat Pupil',
					email: 'pat@example.test',
				},
			},
		],
		total: 2,
		totalPages: 1,
	} );
	vi.mocked( api.getAttempt ).mockResolvedValue( attemptDetail );
} );

async function openAttempt(
	user: ReturnType< typeof userEvent.setup >,
	title = 'Attempt by Sam Student'
) {
	renderPage();
	await screen.findByRole( 'table', { name: 'Attempts' } );
	await user.click(
		screen.getByRole( 'button', { name: 'Review attempt by Sam Student' } )
	);
	return screen.findByRole( 'dialog', { name: title } );
}

describe( 'AttemptsPage', () => {
	it( 'lists attempts with student, status and score', async () => {
		renderPage();
		const table = await screen.findByRole( 'table', { name: 'Attempts' } );
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows ).toHaveLength( 3 );
		expect(
			within( rows[ 1 ]! ).getByText( 'sam@example.test' )
		).toBeInTheDocument();
		expect(
			within( rows[ 1 ]! ).getByText( 'Needs grading' )
		).toBeInTheDocument();
		expect(
			within( rows[ 1 ]! ).getByText( /2 \/ 6 \(33.3%\)/ )
		).toBeInTheDocument();
		expect(
			within( rows[ 1 ]! ).getByRole( 'button', {
				name: /Review attempt by Sam/,
			} )
		).toHaveTextContent( 'Grade' );
		expect(
			within( rows[ 2 ]! ).getByRole( 'button', {
				name: /Review attempt by Pat/,
			} )
		).toHaveTextContent( 'Review' );
		expect(
			await screen.findByRole( 'heading', {
				name: 'Attempts: Fractions check',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( '2 attempts found.' ) ).toBeInTheDocument();
	} );
	it( 'rejects an invalid route id', () => {
		renderPage( '/quizzes/0/attempts' );
		expect( api.listAttempts ).not.toHaveBeenCalled();
	} );
	it( 'shows an empty state', async () => {
		vi.mocked( api.listAttempts ).mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		renderPage();
		expect(
			await screen.findByText( 'No attempts found.' )
		).toBeInTheDocument();
	} );
	it( 'shows a permission error without a retry', async () => {
		vi.mocked( api.listAttempts ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_quiz_cannot_grade',
				'Only an administrator, the quiz author or the course author can review and grade attempts.'
			)
		);
		renderPage();
		expect(
			await screen.findByText( /course author can review/, notice )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).toBeNull();
		expect( screen.queryByRole( 'table' ) ).toBeNull();
	} );
	it( 'shows a server error with a retry', async () => {
		vi.mocked( api.listAttempts ).mockRejectedValueOnce(
			rejection( 500, 'x', 'The server broke.' )
		);
		const user = userEvent.setup();
		renderPage();
		await screen.findByText( /The server broke\./, notice );
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'table', { name: 'Attempts' } )
		).toBeInTheDocument();
	} );
	it( 'filters by status and searches students by keyboard', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Status' } ),
			'in-review'
		);
		await waitFor( () =>
			expect( api.listAttempts ).toHaveBeenLastCalledWith(
				11,
				1,
				{ status: 'in-review', search: '' },
				expect.any( AbortSignal )
			)
		);
		await user.type(
			screen.getByRole( 'textbox', { name: 'Search students' } ),
			' sam {Enter}'
		);
		await waitFor( () =>
			expect( api.listAttempts ).toHaveBeenLastCalledWith(
				11,
				1,
				{ status: 'in-review', search: 'sam' },
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'opens an attempt in a dialog with answers and marks', async () => {
		const user = userEvent.setup();
		const dialog = await openAttempt( user );
		expect(
			await within( dialog ).findByText( /Because they are equal\./ )
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'textbox', {
				name: 'Marks for Explain why 1/2 is 2/4 (out of 4)',
			} )
		).toHaveValue( '' );
		expect(
			within( dialog ).getByRole( 'textbox', {
				name: 'Marks for Pick the larger fraction (out of 2)',
			} )
		).toHaveValue( '2' );
		expect(
			within( dialog ).getByRole( 'button', { name: 'Save marks' } )
		).toBeDisabled();
		await user.keyboard( '{Escape}' );
		await waitFor( () => expect( dialog ).not.toBeInTheDocument() );
	} );
	it( 'refuses marks above the maximum on the client and sends nothing', async () => {
		const user = userEvent.setup();
		const dialog = await openAttempt( user );
		const field = await within( dialog ).findByRole( 'textbox', {
			name: /Marks for Explain/,
		} );
		await user.type( field, '5' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save marks' } )
		);
		expect( api.gradeAttempt ).not.toHaveBeenCalled();
		expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( field ).toHaveAccessibleDescription( /more than 4/ );
		expect(
			within( dialog ).getByText(
				'Fix the highlighted marks before saving.',
				notice
			)
		).toBeInTheDocument();
	} );
	it( 'sends only the changed marks with the note, then shows the refreshed attempt', async () => {
		const graded = {
			...attemptDetail,
			status: 'completed' as const,
			needs_grading: false,
			score: 5,
			changed: 1,
			questions: [
				attemptDetail.questions[ 0 ]!,
				{
					...attemptDetail.questions[ 1 ]!,
					awarded: 3,
					status: 'graded' as const,
				},
			],
		};
		vi.mocked( api.gradeAttempt ).mockResolvedValue( graded );
		const onList = vi.mocked( api.listAttempts );
		const user = userEvent.setup();
		const dialog = await openAttempt( user );
		await user.type(
			await within( dialog ).findByRole( 'textbox', {
				name: /Marks for Explain/,
			} ),
			'3'
		);
		expect( within( dialog ).getByRole( 'status' ) ).toHaveTextContent(
			/will be about 5/
		);
		await user.type(
			within( dialog ).getByRole( 'textbox', {
				name: 'Note (optional)',
			} ),
			'Good reasoning'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save marks' } )
		);
		await waitFor( () =>
			expect( api.gradeAttempt ).toHaveBeenCalledTimes( 1 )
		);
		expect( api.gradeAttempt ).toHaveBeenCalledWith(
			11,
			21,
			[ { question_id: 102, marks: 3 } ],
			'Good reasoning',
			expect.any( AbortSignal )
		);
		expect(
			await within( dialog ).findByText(
				'Marks saved. 1 question changed.',
				notice
			)
		).toBeInTheDocument();
		expect(
			within( dialog ).getByText( /Score: 5 of 6/ )
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'button', { name: 'Save marks' } )
		).toBeDisabled();
		await waitFor( () =>
			expect( onList.mock.calls.length ).toBeGreaterThan( 1 )
		);
	} );
	it( 'shows the server error and keeps the typed marks', async () => {
		vi.mocked( api.gradeAttempt ).mockRejectedValue(
			rejection(
				422,
				'ohmylms_grade_exceeds_max',
				'Marks must be between 0 and the maximum for the question.'
			)
		);
		const user = userEvent.setup();
		const dialog = await openAttempt( user );
		const field = await within( dialog ).findByRole( 'textbox', {
			name: /Marks for Explain/,
		} );
		await user.type( field, '3' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save marks' } )
		);
		expect(
			await within( dialog ).findByText(
				/between 0 and the maximum/,
				notice
			)
		).toBeInTheDocument();
		expect( field ).toHaveValue( '3' );
		expect(
			within( dialog ).getByRole( 'button', { name: 'Save marks' } )
		).toBeEnabled();
	} );
	it( 'shows a permission error when grading is refused', async () => {
		vi.mocked( api.gradeAttempt ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_quiz_cannot_grade',
				'Only an administrator, the quiz author or the course author can review and grade attempts.'
			)
		);
		const user = userEvent.setup();
		const dialog = await openAttempt( user );
		await user.type(
			await within( dialog ).findByRole( 'textbox', {
				name: /Marks for Explain/,
			} ),
			'1'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save marks' } )
		);
		expect(
			await within( dialog ).findByText(
				/course author can review/,
				notice
			)
		).toBeInTheDocument();
	} );
	it( 'ignores a second submit while saving', async () => {
		let finish!: () => void;
		vi.mocked( api.gradeAttempt ).mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					finish = () => resolve( { ...attemptDetail, changed: 0 } );
				} )
		);
		const user = userEvent.setup();
		const dialog = await openAttempt( user );
		const field = await within( dialog ).findByRole( 'textbox', {
			name: /Marks for Explain/,
		} );
		await user.type( field, '3{Enter}{Enter}' );
		expect( api.gradeAttempt ).toHaveBeenCalledTimes( 1 );
		expect(
			within( dialog ).getByRole( 'button', { name: 'Save marks' } )
		).toBeDisabled();
		finish();
		await within( dialog ).findByText( /Marks saved/, notice );
	} );
	it( 'does not allow grading an attempt still in progress', async () => {
		vi.mocked( api.getAttempt ).mockResolvedValue( {
			...attemptDetail,
			status: 'in-progress',
			needs_grading: false,
		} );
		const user = userEvent.setup();
		const dialog = await openAttempt( user );
		expect(
			await within( dialog ).findByText(
				/has not submitted this attempt yet/
			)
		).toBeInTheDocument();
		expect(
			within( dialog ).queryByRole( 'button', { name: 'Save marks' } )
		).toBeNull();
		expect(
			within( dialog ).getByRole( 'textbox', {
				name: /Marks for Explain/,
			} )
		).toBeDisabled();
	} );
	it( 'shows a load error inside the dialog', async () => {
		vi.mocked( api.getAttempt ).mockRejectedValue(
			rejection(
				404,
				'ohmylms_attempt_not_found',
				'Attempt not found for this quiz.'
			)
		);
		const user = userEvent.setup();
		const dialog = await openAttempt( user, 'Attempt' );
		expect(
			await within( dialog ).findByText(
				'Attempt not found for this quiz.',
				notice
			)
		).toBeInTheDocument();
	} );
	it( 'lists the grade history', async () => {
		vi.mocked( api.getAttempt ).mockResolvedValue( {
			...attemptDetail,
			questions: [
				{
					...attemptDetail.questions[ 1 ]!,
					status: 'graded',
					awarded: 3,
					history: [
						{
							awarded: 3,
							grader: 'manual',
							reviewer_id: 2,
							reviewer_name: 'Ada Author',
							reason: 'Good reasoning',
							created_at: '2026-10-30T11:00:00Z',
						},
					],
				},
			],
		} );
		const user = userEvent.setup();
		const dialog = await openAttempt( user );
		expect(
			await within( dialog ).findByText(
				/3 marks by Ada Author on .*Good reasoning/
			)
		).toBeInTheDocument();
	} );
} );
