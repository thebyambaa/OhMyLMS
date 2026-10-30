import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useParams } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { question, summary, types } from './fixtures';
import QuestionsPage from './QuestionsPage';
import type { QuestionSummary } from './types';

vi.mock( './api', () => ( {
	listQuestions: vi.fn(),
	listQuestionTypes: vi.fn(),
	createQuestion: vi.fn(),
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
	return <p>Editor for { useParams().id }</p>;
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
		expect( rows ).toHaveLength( 3 );
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
		expect( screen.getByText( '2 questions found.' ) ).toBeInTheDocument();
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
			screen.getByRole( 'textbox', { name: 'Search questions' } ),
			'solve{Enter}'
		);
		await waitFor( () =>
			expect( lastQuery() ).toMatchObject( { search: 'solve' } )
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Type' } ),
			'multiple-choice'
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Difficulty' } ),
			'challenge'
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Status' } ),
			'draft'
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
			screen.getByRole( 'option', { name: 'Not approved' } )
		).toBeInTheDocument();
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
		await user.click( screen.getByRole( 'button', { name: 'Next page' } ) );
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
			[
				'single-choice',
				[
					{ answer: 'Option 1', is_correct: true, order_number: 0 },
					{ answer: 'Option 2', is_correct: false, order_number: 1 },
				],
			],
			[
				'true-false',
				[
					{ answer: 'True', is_correct: true, order_number: 0 },
					{ answer: 'False', is_correct: false, order_number: 1 },
				],
			],
			[
				'reorder',
				[
					{ answer: 'Option 1', is_correct: false, order_number: 0 },
					{ answer: 'Option 2', is_correct: false, order_number: 1 },
				],
			],
			[
				'matching',
				[
					{
						answer: 'Option 1',
						is_correct: false,
						order_number: 0,
						matching_data: { label: 'Match 1' },
					},
					{
						answer: 'Option 2',
						is_correct: false,
						order_number: 1,
						matching_data: { label: 'Match 2' },
					},
				],
			],
			[ 'graded-reader', undefined ],
		] )(
			'creates a %s question and opens its editor',
			async ( type, options ) => {
				vi.mocked( api.createQuestion ).mockResolvedValue( {
					...question,
					id: 900,
				} );
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
					' New question {Enter}'
				);
				expect(
					await screen.findByText( 'Editor for 900' )
				).toBeInTheDocument();
				const expected = {
					name: 'New question',
					settings: { type, score: { enabled: true, value: 1 } },
					...( options ? { options } : {} ),
				};
				expect( api.createQuestion ).toHaveBeenCalledWith(
					expected,
					expect.any( AbortSignal )
				);
			}
		);

		it( 'requires a name without calling the server', async () => {
			const { user, dialog } = await openDialog();
			await user.click(
				within( dialog ).getByRole( 'button', {
					name: 'Create question',
				} )
			);
			expect(
				await within( dialog ).findByRole( 'alert' )
			).toHaveTextContent( 'Enter a question of 1 to 500 characters.' );
			expect(
				within( dialog ).getByRole( 'textbox', { name: 'Name' } )
			).toHaveAttribute( 'aria-invalid', 'true' );
			expect( api.createQuestion ).not.toHaveBeenCalled();
		} );

		it( 'stays open and shows server errors inline', async () => {
			vi.mocked( api.createQuestion ).mockRejectedValueOnce(
				new ApiError( {
					status: 422,
					code: 'ohmylms_question_invalid_name',
					message: 'Enter a question name.',
				} )
			);
			vi.mocked( api.createQuestion ).mockRejectedValueOnce(
				new ApiError( {
					status: 403,
					code: 'ohmylms_question_cannot_create',
					message: 'Sorry, you cannot create questions.',
				} )
			);
			const { user, dialog } = await openDialog();
			const name = within( dialog ).getByRole( 'textbox', {
				name: 'Name',
			} );
			await user.type( name, 'Q' );
			await user.click(
				within( dialog ).getByRole( 'button', {
					name: 'Create question',
				} )
			);
			expect(
				await within( dialog ).findByRole( 'alert' )
			).toHaveTextContent( 'Enter a question name.' );
			await user.type( name, 'Q' );
			await user.click(
				within( dialog ).getByRole( 'button', {
					name: 'Create question',
				} )
			);
			expect(
				await within( dialog ).findByText(
					'Sorry, you cannot create questions.'
				)
			).toBeInTheDocument();
			expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
		} );
	} );
} );
