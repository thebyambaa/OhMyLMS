import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { summary, types } from './fixtures';
import QuestionsPage from './QuestionsPage';
import type { QuestionSummary } from './types';

vi.mock( './api', async ( original ) => ( {
	...( await original< typeof import( './api' ) >() ),
	listQuestions: vi.fn(),
	listQuestionTypes: vi.fn(),
	deleteQuestions: vi.fn(),
	searchQuizzes: vi.fn(),
	addToQuiz: vi.fn(),
} ) );

const answered: QuestionSummary = {
	...summary,
	id: 813,
	name: 'Answered question',
	usage: { quizzes: 1, responses: 4 },
};
const shared: QuestionSummary = {
	...summary,
	id: 814,
	name: 'Shared question',
	usage: { quizzes: 2, responses: 0 },
};
const borrowed: QuestionSummary = {
	...summary,
	id: 815,
	name: 'Borrowed question',
	approved_version_id: 0,
	permissions: { can_edit: false, can_approve: false, can_delete: false },
};

const notice = { selector: '.components-notice__content' };

function renderPage() {
	return render(
		<MemoryRouter initialEntries={ [ '/content-hub/question-bank' ] }>
			<Routes>
				<Route
					path="/content-hub/question-bank"
					element={ <QuestionsPage /> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listQuestions ).mockResolvedValue( {
		items: [ summary, answered, shared, borrowed ],
		total: 4,
		totalPages: 1,
	} );
	vi.mocked( api.listQuestionTypes ).mockResolvedValue( types );
	vi.mocked( api.searchQuizzes ).mockResolvedValue( [
		{
			id: 77,
			title: 'Unit 4',
			status: 'draft',
			can_edit: true,
			question_count: 3,
		},
		{
			id: 78,
			title: 'Locked quiz',
			status: 'publish',
			can_edit: false,
			question_count: 1,
		},
	] );
} );

describe( 'Question bank delete', () => {
	it( 'offers delete only on questions the user may delete and explains the outcome', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table', { name: 'Questions' } );
		expect(
			screen.queryByRole( 'button', { name: 'Delete Borrowed question' } )
		).toBeNull();
		await user.click(
			screen.getByRole( 'button', { name: 'Delete Answered question' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Delete question',
		} );
		expect( dialog ).toHaveTextContent(
			'Archived instead: learners have answered it'
		);
		vi.mocked( api.deleteQuestions ).mockResolvedValue( {
			results: [ { id: 813, action: 'archived' } ],
			skipped: [],
		} );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Delete 1 question',
			} )
		);
		expect( api.deleteQuestions ).toHaveBeenCalledWith(
			[ 813 ],
			false,
			expect.any( AbortSignal )
		);
		await screen.findByText(
			'Archived 1 question that learners answered.',
			notice
		);
		expect( api.listQuestions ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'bulk deletes the selection and skips questions used in several quizzes', async () => {
		const user = userEvent.setup();
		renderPage();
		const table = await screen.findByRole( 'table', { name: 'Questions' } );
		await user.click(
			within( table ).getByRole( 'checkbox', {
				name: 'Select Solve 2x = 6',
			} )
		);
		await user.click(
			within( table ).getByRole( 'checkbox', {
				name: 'Select Shared question',
			} )
		);
		await user.selectOptions(
			screen.getAllByRole( 'combobox', { name: /bulk action/i } )[ 0 ]!,
			'delete'
		);
		await user.click(
			screen.getAllByRole( 'button', { name: 'Apply' } )[ 0 ]!
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Delete questions',
		} );
		expect( dialog ).toHaveTextContent(
			'Not deleted: it is used in 2 quizzes.'
		);
		expect( dialog ).toHaveTextContent(
			'Deleted and removed from its quiz.'
		);
		vi.mocked( api.deleteQuestions ).mockResolvedValue( {
			results: [ { id: 812, action: 'deleted' } ],
			skipped: [
				{
					id: 814,
					code: 'ohmylms_question_shared',
					message: 'Used in several quizzes.',
					quizzes: [ 1, 2 ],
				},
			],
		} );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Delete the other 1 question',
			} )
		);
		expect( api.deleteQuestions ).toHaveBeenCalledWith(
			[ 812, 814 ],
			true,
			expect.any( AbortSignal )
		);
		await screen.findByText(
			'Deleted 1 question. 1 question was not deleted.',
			notice
		);
	} );

	it( 'shows what the server refused and changes nothing', async () => {
		vi.mocked( api.deleteQuestions ).mockRejectedValueOnce(
			new ApiError( {
				status: 409,
				code: 'ohmylms_question_delete_blocked',
				message: 'One question cannot be deleted. Nothing was changed.',
				details: {
					status: 409,
					blocked: [
						{
							id: 812,
							code: 'ohmylms_question_shared',
							message: 'Placed in a second quiz meanwhile.',
							quizzes: [ 3, 4 ],
						},
					],
				},
			} )
		);
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table', { name: 'Questions' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Delete Solve 2x = 6' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Delete question',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Delete 1 question',
			} )
		);
		expect(
			await within( dialog ).findByText(
				'One question cannot be deleted. Nothing was changed.'
			)
		).toBeInTheDocument();
		expect( dialog ).toHaveTextContent(
			'Not deleted: Placed in a second quiz meanwhile.'
		);
		expect(
			within( dialog ).getByRole( 'button', {
				name: 'Delete the other 0 questions',
			} )
		).toBeDisabled();
	} );
} );

describe( 'Add to quiz', () => {
	it( 'adds the question itself to a quiz the user may edit', async () => {
		vi.mocked( api.addToQuiz ).mockResolvedValue( 812 );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table', { name: 'Questions' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Add Solve 2x = 6 to a quiz' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Add “Solve 2x = 6” to a quiz',
		} );
		await user.click(
			await within( dialog ).findByRole( 'radio', {
				name: 'Unit 4 (3 questions)',
			} )
		);
		expect(
			within( dialog ).queryByRole( 'radio', { name: /Locked quiz/ } )
		).toBeNull();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add to quiz' } )
		);
		expect( api.addToQuiz ).toHaveBeenCalledWith(
			77,
			812,
			false,
			expect.any( AbortSignal )
		);
		await screen.findByText( 'Added Solve 2x = 6 to Unit 4.', notice );
	} );

	it( 'only copies a shared question without an approved version, and retries a lost revision race once', async () => {
		vi.mocked( api.addToQuiz )
			.mockRejectedValueOnce(
				new ApiError( {
					status: 409,
					code: 'ohmylms_quiz_questions_conflict',
					message: 'Changed by someone else.',
				} )
			)
			.mockResolvedValueOnce( 990 );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table', { name: 'Questions' } );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Add Borrowed question to a quiz',
			} )
		);
		const dialog = screen.getByRole( 'dialog' );
		expect(
			within( dialog ).getByRole( 'radio', { name: /^A copy/ } )
		).toBeChecked();
		await user.click(
			await within( dialog ).findByRole( 'radio', {
				name: 'Unit 4 (3 questions)',
			} )
		);
		await user.click(
			within( dialog ).getByRole( 'radio', { name: /^This question/ } )
		);
		expect(
			within( dialog ).getByRole( 'button', { name: 'Add to quiz' } )
		).toBeDisabled();
		expect( within( dialog ).getByRole( 'alert' ) ).toHaveTextContent(
			'only a copy can be added'
		);
		await user.click(
			within( dialog ).getByRole( 'radio', { name: /^A copy/ } )
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add a copy' } )
		);
		await waitFor( () =>
			expect( api.addToQuiz ).toHaveBeenCalledTimes( 2 )
		);
		expect( api.addToQuiz ).toHaveBeenLastCalledWith(
			77,
			815,
			true,
			expect.any( AbortSignal )
		);
		await screen.findByText(
			'Added a copy of Borrowed question to Unit 4.',
			notice
		);
		expect(
			screen.getByRole( 'link', { name: 'Edit the copy' } )
		).toHaveAttribute( 'href', '/content-hub/question-bank/990' );
	} );
} );
