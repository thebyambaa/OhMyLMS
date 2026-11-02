import '@testing-library/jest-dom/vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Link, MemoryRouter, Route, Routes } from 'react-router-dom';
import { BootstrapProvider } from '../../bootstrap/BootstrapContext';
import { ApiError } from '../../lib/api';
import { createBootstrap } from '../../test/render';
import * as api from './api';
import { questionList, quiz, sharedSlot } from './fixtures';
import * as questionsApi from './questionsApi';
import QuizEditorPage from './QuizEditorPage';

vi.mock( './api', () => ( {
	getQuiz: vi.fn(),
	updateQuiz: vi.fn(),
} ) );
vi.mock( './questionsApi', async ( original ) => ( {
	...( await original< typeof import( './questionsApi' ) >() ),
	listQuizQuestions: vi.fn(),
	removeQuizQuestion: vi.fn(),
} ) );

function renderPage( route = '/quizzes/11' ) {
	return render(
		<BootstrapProvider value={ createBootstrap( { edit_posts: true } ) }>
			<MemoryRouter initialEntries={ [ route ] }>
				<Link to="/quizzes">Away</Link>
				<Routes>
					<Route
						path="/quizzes"
						element={ <p>List route reached</p> }
					/>
					<Route path="/quizzes/:id" element={ <QuizEditorPage /> } />
				</Routes>
			</MemoryRouter>
		</BootstrapProvider>
	);
}

const notice = { selector: '.components-notice__content' };
const rejection = ( status: number, code: string, message: string ) =>
	new ApiError( { status, code, message } );

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getQuiz ).mockResolvedValue( quiz );
	vi.mocked( questionsApi.listQuizQuestions ).mockResolvedValue(
		questionList
	);
} );

describe( 'QuizEditorPage', () => {
	it( 'shows labelled fields with the stored values and links to the other screens', async () => {
		renderPage();
		expect(
			await screen.findByRole( 'form', { name: 'Quiz settings' } )
		).toBeInTheDocument();
		expect( screen.getByRole( 'textbox', { name: /Title/ } ) ).toHaveValue(
			'Fractions check'
		);
		expect(
			screen.getByRole( 'textbox', { name: 'Time limit (minutes)' } )
		).toHaveValue( '30' );
		expect(
			screen.getByRole( 'textbox', { name: 'Passing score' } )
		).toHaveValue( '60' );
		expect(
			screen.getByRole( 'textbox', { name: 'Attempts allowed' } )
		).toHaveValue( '2' );
		expect(
			screen.getByRole( 'checkbox', { name: 'Randomise question order' } )
		).not.toBeChecked();
		expect(
			screen.getByRole( 'link', { name: 'Attempts (3)' } )
		).toHaveAttribute( 'href', '/quizzes/11/attempts' );
		expect(
			screen.queryByRole( 'link', { name: 'Edit questions' } )
		).toBeNull();
		expect(
			screen.getByRole( 'textbox', { name: 'Questions per page' } )
		).toBeDisabled();
		expect(
			await screen.findByRole( 'table', {
				name: 'Questions in this quiz',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'region', { name: 'Questions' } )
		).toHaveTextContent( '2 questions · Total marks: 5' );
		expect(
			screen.getByText( /worth 5 marks in total/ )
		).toBeInTheDocument();
	} );
	it( 'updates the total marks in the passing score help after a question change', async () => {
		vi.mocked( questionsApi.removeQuizQuestion ).mockResolvedValue( {
			...questionList,
			questions: [ { ...sharedSlot, position: 1 } ],
			totals: { ...questionList.totals, questions: 1, total_marks: 3 },
		} );
		const user = userEvent.setup();
		renderPage();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Remove Pick the larger fraction',
			} )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Remove question' } )
		);
		expect(
			await screen.findByText( /worth 3 marks in total/ )
		).toBeInTheDocument();
	} );
	it( 'disables the question actions for a trashed quiz', async () => {
		vi.mocked( api.getQuiz ).mockResolvedValue( {
			...quiz,
			status: 'trash',
		} );
		renderPage();
		await screen.findByRole( 'table', { name: 'Questions in this quiz' } );
		expect(
			screen.queryByRole( 'button', { name: 'Add from question bank' } )
		).toBeNull();
	} );
	it( 'rejects an invalid route id without requesting anything', () => {
		renderPage( '/quizzes/abc' );
		expect(
			screen.getAllByText( 'This quiz link is invalid.' ).length
		).toBeGreaterThan( 0 );
		expect( api.getQuiz ).not.toHaveBeenCalled();
	} );
	it.each( [
		[ 403, 'You cannot view this quiz.', false ],
		[ 500, 'The server broke.', true ],
	] )(
		'shows a %d load error (retry: %s)',
		async ( status, message, retry ) => {
			vi.mocked( api.getQuiz ).mockRejectedValue(
				rejection( status, 'x', message )
			);
			renderPage();
			expect(
				await screen.findByText( message, notice )
			).toBeInTheDocument();
			expect(
				Boolean( screen.queryByRole( 'button', { name: 'Try again' } ) )
			).toBe( retry );
		}
	);
	it( 'shows inline errors and does not call the server for an invalid form', async () => {
		const user = userEvent.setup();
		renderPage();
		const passing = await screen.findByRole( 'textbox', {
			name: 'Passing score',
		} );
		await user.clear( passing );
		await user.type( passing, '150' );
		await user.clear(
			screen.getByRole( 'textbox', { name: 'Attempts allowed' } )
		);
		await user.type(
			screen.getByRole( 'textbox', { name: 'Attempts allowed' } ),
			'-2'
		);
		await user.click( screen.getByRole( 'button', { name: 'Save quiz' } ) );
		expect( api.updateQuiz ).not.toHaveBeenCalled();
		expect( passing ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( passing ).toHaveAccessibleDescription( /0 to 100/ );
		expect( screen.getAllByRole( 'alert' ).length ).toBeGreaterThanOrEqual(
			2
		);
		expect(
			screen.getByText(
				'Fix the highlighted fields before saving.',
				notice
			)
		).toBeInTheDocument();
	} );
	it( 'saves the validated body with the base version and shows the saved state', async () => {
		vi.mocked( api.updateQuiz ).mockResolvedValue( {
			...quiz,
			title: 'Renamed',
			updated_at: '2026-10-30T10:00:00Z',
		} );
		const user = userEvent.setup();
		renderPage();
		const title = await screen.findByRole( 'textbox', { name: /Title/ } );
		await user.clear( title );
		await user.type( title, 'Renamed' );
		expect(
			screen.getByText( 'You have unsaved changes.' )
		).toHaveAttribute( 'role', 'status' );
		await user.click(
			screen.getByRole( 'checkbox', { name: 'Randomise question order' } )
		);
		await user.click( screen.getByRole( 'button', { name: 'Save quiz' } ) );
		await waitFor( () =>
			expect( api.updateQuiz ).toHaveBeenCalledTimes( 1 )
		);
		const [ id, input ] = vi.mocked( api.updateQuiz ).mock.calls[ 0 ]!;
		expect( id ).toBe( 11 );
		expect( input ).toMatchObject( {
			title: 'Renamed',
			base_modified: quiz.updated_at,
			settings: { randomize_questions: true, passing_score: 60 },
		} );
		expect(
			await screen.findByText( 'Quiz saved.', notice )
		).toBeInTheDocument();
		expect( screen.queryByText( 'You have unsaved changes.' ) ).toBeNull();
	} );
	it( 'ignores a second submit while saving', async () => {
		let finish!: () => void;
		vi.mocked( api.updateQuiz ).mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					finish = () => resolve( quiz );
				} )
		);
		const user = userEvent.setup();
		renderPage();
		const title = await screen.findByRole( 'textbox', { name: /Title/ } );
		await user.type( title, '!' );
		await user.type( title, '{Enter}{Enter}' );
		expect( api.updateQuiz ).toHaveBeenCalledTimes( 1 );
		expect(
			screen.getByRole( 'button', { name: 'Save quiz' } )
		).toBeDisabled();
		finish();
		await screen.findByText( 'Quiz saved.', notice );
	} );
	it( 'keeps the edits and shows the field error when the server rejects them', async () => {
		vi.mocked( api.updateQuiz ).mockRejectedValue(
			rejection(
				422,
				'ohmylms_quiz_invalid_availability',
				'The closing time must be after the opening time.'
			)
		);
		const user = userEvent.setup();
		renderPage();
		const title = await screen.findByRole( 'textbox', { name: /Title/ } );
		await user.type( title, ' v2' );
		await user.click( screen.getByRole( 'button', { name: 'Save quiz' } ) );
		expect(
			await screen.findByText( /closing time must be after/, notice )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Closes' ) ).toHaveAttribute(
			'aria-invalid',
			'true'
		);
		expect( title ).toHaveValue( 'Fractions check v2' );
	} );
	it( 'offers a reload when someone else changed the quiz', async () => {
		vi.mocked( api.updateQuiz ).mockRejectedValue(
			rejection(
				409,
				'ohmylms_quiz_conflict',
				'This quiz was changed by someone else.'
			)
		);
		const user = userEvent.setup();
		renderPage();
		await user.type(
			await screen.findByRole( 'textbox', { name: /Title/ } ),
			' v2'
		);
		await user.click( screen.getByRole( 'button', { name: 'Save quiz' } ) );
		await screen.findByText( /changed by someone else/, notice );
		await user.click(
			screen.getByRole( 'button', { name: 'Reload the quiz' } )
		);
		await waitFor( () => expect( api.getQuiz ).toHaveBeenCalledTimes( 2 ) );
	} );
	it( 'shows a permission error from the server', async () => {
		vi.mocked( api.updateQuiz ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_quiz_cannot_edit',
				'Sorry, you are not allowed to edit this quiz.'
			)
		);
		const user = userEvent.setup();
		renderPage();
		await user.type(
			await screen.findByRole( 'textbox', { name: /Title/ } ),
			'!'
		);
		await user.click( screen.getByRole( 'button', { name: 'Save quiz' } ) );
		expect(
			await screen.findByText( /not allowed to edit this quiz/, notice )
		).toBeInTheDocument();
	} );
	it( 'disables the form for a user who cannot edit', async () => {
		vi.mocked( api.getQuiz ).mockResolvedValue( {
			...quiz,
			can_edit: false,
		} );
		renderPage();
		expect(
			await screen.findByRole( 'textbox', { name: /Title/ } )
		).toBeDisabled();
		expect(
			screen.getByRole( 'button', { name: 'Save quiz' } )
		).toBeDisabled();
	} );
	it( 'asks before leaving with unsaved changes and can keep editing', async () => {
		const user = userEvent.setup();
		renderPage();
		await user.type(
			await screen.findByRole( 'textbox', { name: /Title/ } ),
			'!'
		);
		await user.click( screen.getByRole( 'link', { name: 'Away' } ) );
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Discard unsaved changes?',
		} );
		expect( screen.queryByText( 'List route reached' ) ).toBeNull();
		await user.click(
			screen.getByRole( 'button', { name: 'Keep editing' } )
		);
		expect( dialog ).not.toBeInTheDocument();
		await user.click( screen.getByRole( 'link', { name: 'Away' } ) );
		await user.click(
			await screen.findByRole( 'button', { name: 'Discard changes' } )
		);
		expect(
			await screen.findByText( 'List route reached' )
		).toBeInTheDocument();
	} );
} );
