import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
	MemoryRouter,
	Route,
	Routes,
	useLocation,
	useParams,
} from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { question, summary, types } from './fixtures';
import QuestionsPage from './QuestionsPage';
import type { QuestionSummary } from './types';

vi.mock( './api', () => ( {
	listQuestions: vi.fn(),
	listQuestionTypes: vi.fn(),
	archiveQuestion: vi.fn(),
	restoreQuestion: vi.fn(),
	duplicateQuestion: vi.fn(),
} ) );

const archived: QuestionSummary = {
	...summary,
	id: 813,
	name: '',
	type: 'graded-reader',
	type_label: 'Graded reader',
	status: 'archived',
	version: 1,
	difficulty: 'challenge',
	usage: { quizzes: 2, responses: 14 },
};

function EditorRoute() {
	const { search } = useLocation();
	return (
		<>
			<p>Editor for { useParams().id }</p>
			<p data-testid="search">{ search }</p>
		</>
	);
}

function renderPage() {
	return render(
		<MemoryRouter initialEntries={ [ '/content-hub/question-bank' ] }>
			<Routes>
				<Route
					path="/content-hub/question-bank"
					element={ <QuestionsPage /> }
				/>
				<Route
					path="/content-hub/question-bank/:id"
					element={ <EditorRoute /> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

function lastQuery() {
	return vi.mocked( api.listQuestions ).mock.lastCall![ 0 ];
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listQuestions ).mockResolvedValue( {
		items: [ summary, archived ],
		total: 2,
		totalPages: 1,
	} );
	vi.mocked( api.listQuestionTypes ).mockResolvedValue( types );
} );

describe( 'QuestionsPage', () => {
	it( 'lists questions with type, version, difficulty and usage', async () => {
		renderPage();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading questions'
		);
		const table = await screen.findByRole( 'table', { name: 'Questions' } );
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows ).toHaveLength( 4 );
		const first = within( rows[ 1 ]! );
		expect(
			first.getByRole( 'link', { name: 'Solve 2x = 6' } )
		).toHaveAttribute( 'href', '/content-hub/question-bank/812' );
		expect( first.getByText( 'Single Choice' ) ).toBeInTheDocument();
		expect( first.getByText( 'v3 · draft' ) ).toBeInTheDocument();
		expect( first.getByText( 'Easy' ) ).toBeInTheDocument();
		expect( first.getByText( '1 quiz · 0 responses' ) ).toBeInTheDocument();
		const second = within( rows[ 2 ]! );
		expect(
			second.getByRole( 'link', { name: 'Untitled question' } )
		).toBeInTheDocument();
		expect( second.getByText( 'Graded reader' ) ).toBeInTheDocument();
		expect( second.getByText( 'v1 · archived' ) ).toBeInTheDocument();
		expect(
			second.getByText( '2 quizzes · 14 responses' )
		).toBeInTheDocument();
		expect(
			second.getByRole( 'button', { name: 'Restore Untitled question' } )
		).toBeInTheDocument();
		expect(
			screen.getAllByText( '2 questions found.' )[ 0 ]
		).toBeInTheDocument();
		expect( lastQuery() ).toEqual( {
			page: 1,
			search: '',
			type: '',
			difficulty: '',
			status: 'active',
		} );
	} );

	it( 'hides lifecycle actions without edit permission', async () => {
		vi.mocked( api.listQuestions ).mockResolvedValue( {
			items: [
				{
					...summary,
					permissions: { can_edit: false, can_approve: false },
				},
			],
			total: 1,
			totalPages: 1,
		} );
		renderPage();
		await screen.findByRole( 'table' );
		expect(
			screen.queryByRole( 'button', { name: /Archive/ } )
		).not.toBeInTheDocument();
	} );

	it( 'sends search and filters as the query', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search questions' } ),
			'solve{Enter}'
		);
		await waitFor( () =>
			expect( lastQuery() ).toMatchObject( { search: 'solve' } )
		);
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Type' } ),
			'multiple-choice'
		);
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Difficulty' } ),
			'challenge'
		);
		await user.click(
			await screen.findByRole( 'link', { name: 'Not approved' } )
		);
		await waitFor( () =>
			expect( lastQuery() ).toEqual( {
				page: 1,
				search: 'solve',
				type: 'multiple-choice',
				difficulty: 'challenge',
				status: 'draft',
			} )
		);
		expect(
			await screen.findByRole( 'link', { name: /^Not approved/ } )
		).toHaveAttribute( 'aria-current', 'page' );
	} );

	it( 'steps back a page when the page is past the end', async () => {
		vi.mocked( api.listQuestions ).mockResolvedValue( {
			items: [ summary ],
			total: 21,
			totalPages: 2,
		} );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		vi.mocked( api.listQuestions ).mockRejectedValueOnce(
			new ApiError( {
				status: 400,
				code: 'ohmylms_question_invalid_page',
				message: 'Past the end.',
			} )
		);
		await user.click(
			screen.getAllByRole( 'button', { name: 'Next page' } )[ 0 ]!
		);
		await waitFor( () => expect( lastQuery().page ).toBe( 1 ) );
		expect( api.listQuestions ).toHaveBeenCalledTimes( 3 );
		expect( screen.queryByText( 'Past the end.' ) ).not.toBeInTheDocument();
	} );

	it( 'archives a question and reloads the list', async () => {
		vi.mocked( api.archiveQuestion ).mockResolvedValue( {
			...question,
			status: 'archived',
		} );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', { name: 'Archive Solve 2x = 6' } )
		);
		expect(
			await screen.findByText( 'Archived Solve 2x = 6.', notice )
		).toBeInTheDocument();
		expect( api.archiveQuestion ).toHaveBeenCalledWith(
			812,
			expect.any( AbortSignal )
		);
		expect( api.restoreQuestion ).not.toHaveBeenCalled();
		await waitFor( () =>
			expect( api.listQuestions ).toHaveBeenCalledTimes( 2 )
		);
	} );

	it( 'duplicates a question, reloads the list and links to the copy', async () => {
		vi.mocked( api.duplicateQuestion ).mockResolvedValue( {
			...question,
			id: 900,
			name: 'Solve 2x = 6 (copy)',
		} );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', { name: 'Duplicate Solve 2x = 6' } )
		);
		expect(
			await screen.findByText(
				/Duplicated Solve 2x = 6 as Solve 2x = 6 \(copy\)\./,
				notice
			)
		).toBeInTheDocument();
		expect( api.duplicateQuestion ).toHaveBeenCalledWith(
			812,
			expect.any( AbortSignal )
		);
		await waitFor( () =>
			expect( api.listQuestions ).toHaveBeenCalledTimes( 2 )
		);
		await user.click(
			screen.getByRole( 'link', { name: 'Edit the copy' } )
		);
		expect(
			await screen.findByText( 'Editor for 900' )
		).toBeInTheDocument();
	} );

	it( 'offers duplicate on rows the user cannot edit', async () => {
		vi.mocked( api.listQuestions ).mockResolvedValue( {
			items: [
				{
					...summary,
					permissions: { can_edit: false, can_approve: false },
				},
			],
			total: 1,
			totalPages: 1,
		} );
		renderPage();
		await screen.findByRole( 'table' );
		expect(
			screen.getByRole( 'button', { name: 'Duplicate Solve 2x = 6' } )
		).toBeEnabled();
	} );

	it( 'shows a duplicate failure', async () => {
		vi.mocked( api.duplicateQuestion ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_question_cannot_duplicate',
				message:
					'Sorry, you are not allowed to duplicate this question.',
			} )
		);
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Duplicate Untitled question',
			} )
		);
		expect(
			await screen.findByText(
				'Sorry, you are not allowed to duplicate this question.',
				notice
			)
		).toBeInTheDocument();
	} );

	it( 'shows a lifecycle failure', async () => {
		vi.mocked( api.restoreQuestion ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_question_cannot_edit',
				message: 'Not allowed.',
			} )
		);
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', { name: 'Restore Untitled question' } )
		);
		expect(
			await screen.findByText( 'Not allowed.', notice )
		).toBeInTheDocument();
	} );

	describe( 'create', () => {
		async function openDialog() {
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table' );
			await user.click(
				screen.getByRole( 'button', { name: 'New question' } )
			);
			const dialog = await screen.findByRole( 'dialog' );
			return { user, dialog };
		}

		it.each( [
			'single-choice',
			'numerical',
			'set-clock',
			'graded-reader',
		] )(
			'opens an unsaved %s draft without calling the server',
			async ( type ) => {
				const { user, dialog } = await openDialog();
				expect(
					within( dialog ).getByRole( 'combobox', { name: 'Type' } )
				).toHaveValue( 'single-choice' );
				await user.selectOptions(
					within( dialog ).getByRole( 'combobox', { name: 'Type' } ),
					type
				);
				await user.type(
					within( dialog ).getByRole( 'textbox', { name: 'Name' } ),
					' New question & more {Enter}'
				);
				expect(
					await screen.findByText( 'Editor for new' )
				).toBeInTheDocument();
				expect( screen.getByTestId( 'search' ) ).toHaveTextContent(
					`?${ new URLSearchParams( {
						type,
						name: 'New question & more',
					} ) }`
				);
				expect(
					screen.queryByRole( 'dialog' )
				).not.toBeInTheDocument();
			}
		);

		it( 'requires a name before continuing', async () => {
			const { user, dialog } = await openDialog();
			await user.click(
				within( dialog ).getByRole( 'button', {
					name: 'Continue',
				} )
			);
			expect(
				await within( dialog ).findByRole( 'alert' )
			).toHaveTextContent( 'Enter a question of 1 to 500 characters.' );
			expect(
				within( dialog ).getByRole( 'textbox', { name: 'Name' } )
			).toHaveAttribute( 'aria-invalid', 'true' );
			expect(
				screen.queryByText( /Editor for/ )
			).not.toBeInTheDocument();
		} );
	} );
} );
