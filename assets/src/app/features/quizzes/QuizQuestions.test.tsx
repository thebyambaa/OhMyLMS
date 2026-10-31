import '@testing-library/jest-dom/vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { BootstrapProvider } from '../../bootstrap/BootstrapContext';
import { ApiError } from '../../lib/api';
import { createBootstrap } from '../../test/render';
import * as bank from '../questions/public';
import * as bankApi from '../questions/api';
import type { QuestionSummary, QuestionType } from '../questions/public';
import { questionList, sharedSlot, slot } from './fixtures';
import * as api from './questionsApi';
import { QuizQuestions } from './QuizQuestions';
import type { QuizQuestions as QuestionList } from './types';

vi.mock( './questionsApi', async ( original ) => ( {
	...( await original< typeof import( './questionsApi' ) >() ),
	listQuizQuestions: vi.fn(),
	addQuizQuestions: vi.fn(),
	createQuizQuestion: vi.fn(),
	reorderQuizQuestions: vi.fn(),
	removeQuizQuestion: vi.fn(),
	pinQuizQuestion: vi.fn(),
	setQuizQuestionMarks: vi.fn(),
	saveQuizSections: vi.fn(),
	saveQuizPools: vi.fn(),
} ) );
vi.mock( '../questions/public', async ( original ) => ( {
	...( await original< typeof import( '../questions/public' ) >() ),
	listQuestions: vi.fn(),
	listQuestionTypes: vi.fn(),
	listBanks: vi.fn(),
} ) );
vi.mock( '../questions/api', async ( original ) => ( {
	...( await original< typeof import( '../questions/api' ) >() ),
	listBanks: vi.fn(),
	searchSkills: vi.fn(),
	getSkills: vi.fn(),
} ) );

const types: QuestionType[] = [
	{
		id: 'single-choice',
		label: 'Single Choice',
		format: 'ohmylms-options',
		answers: 'options',
		manual: false,
		snapshot: true,
		builtin: true,
	},
];

function summary(
	id: number,
	name: string,
	extra: Partial< QuestionSummary > = {}
): QuestionSummary {
	return {
		id,
		uuid: '',
		name,
		type: 'single-choice',
		type_label: 'Single Choice',
		marks: 1,
		status: 'draft',
		post_status: 'publish',
		version: 1,
		current_version_id: id * 10,
		approved_version_id: 0,
		approved_is_current: false,
		bank_id: 0,
		difficulty: 'standard',
		source: '',
		family_id: '',
		secure: false,
		skills: [],
		usage: { quizzes: 0, responses: 0 },
		author: { id: 2, name: 'Ada' },
		modified: '2026-10-30T09:00:00Z',
		permissions: { can_edit: true, can_approve: true },
		...extra,
	};
}

const notice = { selector: '.components-notice__content' };
const onDirtyChange = vi.fn();
const onTotalChange = vi.fn();

function renderSection( readOnly = false ) {
	return render(
		<BootstrapProvider value={ createBootstrap( { edit_posts: true } ) }>
			<MemoryRouter>
				<QuizQuestions
					quizId={ 11 }
					readOnly={ readOnly }
					onDirtyChange={ onDirtyChange }
					onTotalChange={ onTotalChange }
				/>
			</MemoryRouter>
		</BootstrapProvider>
	);
}

function listWith( patch: Partial< QuestionList > ): QuestionList {
	return { ...questionList, revision: 'fedcba9876543210', ...patch };
}

function rowNames() {
	return within( screen.getByRole( 'table' ) )
		.getAllByRole( 'rowheader' )
		.map( ( cell ) => cell.textContent ?? '' );
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listQuizQuestions ).mockResolvedValue( questionList );
	vi.mocked( bank.listQuestionTypes ).mockResolvedValue( types );
	vi.mocked( bank.listBanks ).mockResolvedValue( [
		{ id: 4, name: 'Shared maths bank' },
	] );
	vi.mocked( bankApi.listBanks ).mockResolvedValue( [] );
	vi.mocked( bankApi.getSkills ).mockResolvedValue( [] );
} );

describe( 'QuizQuestions', () => {
	it( 'lists the questions with type, marks, version and the total', async () => {
		renderSection();
		const table = await screen.findByRole( 'table', {
			name: 'Questions in this quiz',
		} );
		expect( rowNames()[ 0 ] ).toBe( 'Pick the larger fraction' );
		expect( rowNames()[ 1 ] ).toMatch( /Shared question \(read-only\)/ );
		expect(
			within( table ).getByText( 'Latest version (3)' )
		).toBeVisible();
		expect(
			within( table ).getByText( 'Pinned to version 1' )
		).toBeVisible();
		expect(
			screen.getByText( /2 questions · Total marks: 5/ )
		).toBeInTheDocument();
		expect( onTotalChange ).toHaveBeenCalledWith( 5 );
		expect(
			screen.getByRole( 'button', {
				name: 'Move Pick the larger fraction up',
			} )
		).toBeDisabled();
		expect(
			screen.getByRole( 'button', {
				name: 'Move Explain why 1/2 is 2/4 down',
			} )
		).toBeDisabled();
		expect(
			screen.getByRole( 'link', {
				name: 'Edit Pick the larger fraction',
			} )
		).toHaveAttribute( 'href', '/content-hub/question-bank/101' );
		expect(
			screen.queryByRole( 'link', {
				name: 'Edit Explain why 1/2 is 2/4',
			} )
		).toBeNull();
	} );
	it( 'shows the list without actions when read-only', async () => {
		renderSection( true );
		await screen.findByRole( 'table' );
		expect( screen.queryByRole( 'button' ) ).toBeNull();
	} );
	it( 'shows read-only random pools in the total', async () => {
		vi.mocked( api.listQuizQuestions ).mockResolvedValue(
			listWith( {
				pools: [
					{
						id: 'pool-1',
						title: 'Warm-up',
						skill: { id: 7, name: 'Fractions' },
						count: 3,
						marks: 2,
						difficulty: '',
						bank_id: 0,
						include_secure: true,
					},
				],
				totals: {
					...questionList.totals,
					pool_questions: 3,
					pool_marks: 6,
					total_marks: 11,
				},
			} )
		);
		renderSection();
		const pools = await screen.findByRole( 'table', {
			name: 'Random pools',
		} );
		expect( within( pools ).getByText( 'Fractions' ) ).toBeVisible();
		expect(
			screen.getByText( /includes 3 random questions worth 6 marks/ )
		).toBeInTheDocument();
	} );
	it( 'moves a question with the revision and keeps keyboard focus on it', async () => {
		vi.mocked( api.reorderQuizQuestions ).mockResolvedValue(
			listWith( {
				questions: [
					{ ...sharedSlot, position: 1 },
					{ ...slot, position: 2 },
				],
			} )
		);
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Move Explain why 1/2 is 2/4 up',
			} )
		);
		expect( api.reorderQuizQuestions ).toHaveBeenCalledWith(
			11,
			questionList.revision,
			[ 102, 101 ],
			expect.any( AbortSignal )
		);
		await screen.findByText(
			'Moved Explain why 1/2 is 2/4 to position 1.',
			notice
		);
		expect( rowNames()[ 0 ] ).toMatch( /^Explain why/ );
		await waitFor( () =>
			expect( document.activeElement ).toHaveAccessibleName(
				'Move Explain why 1/2 is 2/4 down'
			)
		);
	} );
	it( 'reloads instead of overwriting when someone else changed the list', async () => {
		vi.mocked( api.reorderQuizQuestions ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: api.QUESTIONS_CONFLICT,
				message:
					'The questions of this quiz were changed by someone else.',
			} )
		);
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Move Pick the larger fraction down',
			} )
		);
		expect(
			await screen.findByText(
				/changed by someone else.*reloaded/,
				notice
			)
		).toBeInTheDocument();
		await waitFor( () =>
			expect( api.listQuizQuestions ).toHaveBeenCalledTimes( 2 )
		);
	} );
	it( 'removes a question only after confirmation', async () => {
		vi.mocked( api.removeQuizQuestion ).mockResolvedValue(
			listWith( {
				questions: [ { ...sharedSlot, position: 1 } ],
				totals: {
					...questionList.totals,
					questions: 1,
					total_marks: 3,
				},
			} )
		);
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Remove Pick the larger fraction',
			} )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Remove question from quiz?',
		} );
		expect( dialog ).toHaveTextContent( /stays in the question bank/ );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Cancel' } )
		);
		expect( api.removeQuizQuestion ).not.toHaveBeenCalled();
		await user.click(
			screen.getByRole( 'button', {
				name: 'Remove Pick the larger fraction',
			} )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Remove question' } )
		);
		expect( api.removeQuizQuestion ).toHaveBeenCalledWith(
			11,
			questionList.revision,
			101,
			expect.any( AbortSignal )
		);
		await screen.findByText(
			'Removed Pick the larger fraction from the quiz.',
			notice
		);
		expect( screen.queryByRole( 'dialog' ) ).toBeNull();
		expect(
			screen.getByText( /1 question · Total marks: 3/ )
		).toBeVisible();
	} );
	it( 'pins the version used now and unpins', async () => {
		vi.mocked( api.pinQuizQuestion ).mockResolvedValue( questionList );
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Unpin Explain why 1/2 is 2/4',
			} )
		);
		expect( api.pinQuizQuestion ).toHaveBeenLastCalledWith(
			11,
			questionList.revision,
			102,
			null,
			expect.any( AbortSignal )
		);
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Pin Pick the larger fraction to the version used now',
			} )
		);
		expect( api.pinQuizQuestion ).toHaveBeenLastCalledWith(
			11,
			questionList.revision,
			101,
			'current',
			expect.any( AbortSignal )
		);
	} );
	it( 'adds bank questions from the picker and blocks the ones that cannot be added', async () => {
		vi.mocked( bank.listQuestions ).mockResolvedValue( {
			items: [
				summary( 101, 'Pick the larger fraction' ),
				summary( 7, 'Shared draft', {
					permissions: { can_edit: false, can_approve: false },
				} ),
				summary( 8, 'Fresh question' ),
			],
			total: 3,
			totalPages: 1,
		} );
		vi.mocked( api.addQuizQuestions ).mockResolvedValue( questionList );
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Add from question bank',
			} )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Add questions from the question bank',
		} );
		const already = await within( dialog ).findByRole( 'checkbox', {
			name: /Pick the larger fraction/,
		} );
		expect( already ).toBeDisabled();
		expect(
			within( dialog ).getByRole( 'checkbox', { name: /Shared draft/ } )
		).toBeDisabled();
		await user.click(
			within( dialog ).getByRole( 'checkbox', {
				name: 'Approved questions only',
			} )
		);
		await waitFor( () =>
			expect( bank.listQuestions ).toHaveBeenLastCalledWith(
				expect.objectContaining( { status: 'approved', page: 1 } ),
				expect.any( AbortSignal )
			)
		);
		await user.click(
			await within( dialog ).findByRole( 'checkbox', {
				name: /Fresh question/,
			} )
		);
		await user.click(
			within( dialog ).getByRole( 'checkbox', {
				name: 'Pin to the version used now',
			} )
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add 1 question' } )
		);
		expect( api.addQuizQuestions ).toHaveBeenCalledWith(
			11,
			questionList.revision,
			[ 8 ],
			true,
			expect.any( AbortSignal )
		);
		await screen.findByText( 'Added 1 question.', notice );
		expect( screen.queryByRole( 'dialog' ) ).toBeNull();
	} );
	it( 'keeps the picker open with the error when adding fails', async () => {
		vi.mocked( bank.listQuestions ).mockResolvedValue( {
			items: [ summary( 8, 'Fresh question' ) ],
			total: 1,
			totalPages: 1,
		} );
		vi.mocked( api.addQuizQuestions ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'quiz_question_unapproved',
				message: '"Fresh question" has no approved version yet.',
			} )
		);
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Add from question bank',
			} )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await user.click(
			await within( dialog ).findByRole( 'checkbox', {
				name: /Fresh question/,
			} )
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add 1 question' } )
		);
		expect(
			await within( dialog ).findByText( /no approved version yet/ )
		).toBeVisible();
	} );
	it( 'creates a question with the question editor and places it in the quiz', async () => {
		vi.mocked( api.createQuizQuestion ).mockResolvedValue( {
			...listWith( {
				questions: [
					slot,
					sharedSlot,
					{
						...slot,
						question_id: 103,
						position: 3,
						name: 'Halves and quarters',
					},
				],
			} ),
			created_id: 103,
		} );
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', { name: 'Create question' } )
		);
		const naming = await screen.findByRole( 'dialog', {
			name: 'Create question',
		} );
		await user.click(
			within( naming ).getByRole( 'button', { name: 'Continue' } )
		);
		expect( within( naming ).getByRole( 'alert' ) ).toHaveTextContent(
			/Enter a question/
		);
		await user.type(
			within( naming ).getByRole( 'textbox', { name: /Name/ } ),
			'Halves and quarters'
		);
		await within( naming ).findByRole( 'combobox', { name: /Type/ } );
		await user.click(
			within( naming ).getByRole( 'button', { name: 'Continue' } )
		);
		const editor = await screen.findByRole( 'form', { name: 'Question' } );
		expect( onDirtyChange ).toHaveBeenLastCalledWith( true );
		expect( screen.queryByRole( 'table' ) ).toBeNull();
		await user.click(
			within( editor ).getByRole( 'button', {
				name: 'Create and add to quiz',
			} )
		);
		await screen.findByText(
			'Created Halves and quarters and added it to the quiz.',
			notice
		);
		const [ quizId, revision, input ] = vi.mocked( api.createQuizQuestion )
			.mock.calls[ 0 ]!;
		expect( quizId ).toBe( 11 );
		expect( revision ).toBe( questionList.revision );
		expect( input ).toMatchObject( {
			name: 'Halves and quarters',
			settings: { type: 'single-choice' },
		} );
		expect( rowNames()[ 2 ] ).toBe( 'Halves and quarters' );
		expect( onDirtyChange ).toHaveBeenLastCalledWith( false );
	} );
	it( 'cancels a question draft without saving', async () => {
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', { name: 'Create question' } )
		);
		const naming = await screen.findByRole( 'dialog' );
		await user.type(
			within( naming ).getByRole( 'textbox', { name: /Name/ } ),
			'Draft'
		);
		await within( naming ).findByRole( 'combobox', { name: /Type/ } );
		await user.click(
			within( naming ).getByRole( 'button', { name: 'Continue' } )
		);
		await screen.findByRole( 'form', { name: 'Question' } );
		await user.click( screen.getByRole( 'button', { name: 'Cancel' } ) );
		expect( await screen.findByRole( 'table' ) ).toBeInTheDocument();
		expect( api.createQuizQuestion ).not.toHaveBeenCalled();
		expect( onDirtyChange ).toHaveBeenLastCalledWith( false );
	} );
	it( 'sets the marks of a question in this quiz and goes back to its points', async () => {
		const overridden = listWith( {
			questions: [ { ...slot, marks: 5, marks_override: 5 }, sharedSlot ],
			totals: { ...questionList.totals, total_marks: 8 },
		} );
		vi.mocked( api.setQuizQuestionMarks )
			.mockResolvedValueOnce( overridden )
			.mockResolvedValueOnce( listWith( {} ) );
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Change the marks of Pick the larger fraction',
			} )
		);
		let dialog = await screen.findByRole( 'dialog', {
			name: 'Marks of Pick the larger fraction in this quiz',
		} );
		const field = within( dialog ).getByRole( 'spinbutton', {
			name: 'Marks in this quiz',
		} );
		await user.clear( field );
		await user.type( field, '-2' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save marks' } )
		);
		expect( within( dialog ).getByRole( 'alert' ) ).toHaveTextContent(
			/from 0 to 10000/
		);
		expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( api.setQuizQuestionMarks ).not.toHaveBeenCalled();
		await user.clear( field );
		await user.type( field, '5' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save marks' } )
		);
		await screen.findByText(
			'Pick the larger fraction is now worth 5 in this quiz.',
			notice
		);
		expect( api.setQuizQuestionMarks ).toHaveBeenLastCalledWith(
			11,
			questionList.revision,
			101,
			5,
			expect.any( AbortSignal )
		);
		expect( screen.queryByRole( 'dialog' ) ).toBeNull();
		expect(
			screen.getByText( 'Set in this quiz (question: 2)' )
		).toBeVisible();
		expect( onTotalChange ).toHaveBeenLastCalledWith( 8 );

		await user.click(
			screen.getByRole( 'button', {
				name: 'Change the marks of Pick the larger fraction',
			} )
		);
		dialog = await screen.findByRole( 'dialog' );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Use the question’s points (2)',
			} )
		);
		await waitFor( () =>
			expect( api.setQuizQuestionMarks ).toHaveBeenLastCalledWith(
				11,
				'fedcba9876543210',
				101,
				null,
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'edits the sections of the paper', async () => {
		vi.mocked( api.saveQuizSections ).mockResolvedValue(
			listWith( {
				sections: [
					{ title: 'Part A', question_ids: [ 102 ], new_page: true },
				],
				questions: [ slot, { ...sharedSlot, section: 'Part A' } ],
			} )
		);
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', { name: 'Edit sections' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Sections',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add section' } )
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save sections' } )
		);
		expect( within( dialog ).getByRole( 'alert' ) ).toHaveTextContent(
			'Give every section a title.'
		);
		await user.type(
			within( dialog ).getByRole( 'textbox', {
				name: 'Title of section 1',
			} ),
			'Part A'
		);
		await user.click(
			within( dialog ).getByRole( 'checkbox', {
				name: 'Start on a new page',
			} )
		);
		await user.selectOptions(
			within( dialog ).getByRole( 'combobox', {
				name: /^2\. Explain why/,
			} ),
			'Part A'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save sections' } )
		);
		await screen.findByText( 'Sections saved.', notice );
		expect( api.saveQuizSections ).toHaveBeenCalledWith(
			11,
			questionList.revision,
			[ { title: 'Part A', question_ids: [ 102 ], new_page: true } ],
			expect.any( AbortSignal )
		);
		expect( screen.getByText( 'Section: Part A' ) ).toBeVisible();
	} );
	it( 'edits the random pools and reports a shortage from the server', async () => {
		vi.mocked( bankApi.searchSkills ).mockResolvedValue( [
			{ id: 7, name: 'Fractions', code: 'F1' },
		] );
		vi.mocked( api.saveQuizPools )
			.mockRejectedValueOnce(
				new ApiError( {
					status: 409,
					code: 'quiz_pool_shortage',
					message: 'The random pool needs 3 approved questions.',
				} )
			)
			.mockResolvedValueOnce(
				listWith( {
					pools: [
						{
							id: 'pool-1',
							title: 'Warm-up',
							skill: { id: 7, name: 'Fractions' },
							count: 2,
							marks: 1,
							difficulty: 'easy',
							bank_id: 4,
							include_secure: true,
						},
					],
				} )
			);
		const user = userEvent.setup();
		renderSection();
		await user.click(
			await screen.findByRole( 'button', { name: 'Edit random pools' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Random pools',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add pool' } )
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save pools' } )
		);
		expect( within( dialog ).getByRole( 'alert' ) ).toHaveTextContent(
			'Choose a skill.'
		);
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Title' } ),
			'Warm-up'
		);
		await user.type(
			within( dialog ).getByRole( 'textbox', {
				name: 'Search skills for Skill of pool 1',
			} ),
			'frac'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Search' } )
		);
		await user.click(
			await within( dialog ).findByRole( 'button', {
				name: 'Add Fractions (F1)',
			} )
		);
		const count = within( dialog ).getByRole( 'spinbutton', {
			name: 'Questions to draw',
		} );
		await user.clear( count );
		await user.type( count, '2' );
		await user.selectOptions(
			within( dialog ).getByRole( 'combobox', { name: 'Difficulty' } ),
			'easy'
		);
		await within( dialog ).findByRole( 'option', {
			name: 'Shared maths bank',
		} );
		await user.selectOptions(
			within( dialog ).getByRole( 'combobox', { name: 'Bank' } ),
			'4'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save pools' } )
		);
		expect(
			await within( dialog ).findByText(
				'The random pool needs 3 approved questions.'
			)
		).toBeVisible();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save pools' } )
		);
		await screen.findByText( 'Random pools saved.', notice );
		expect( api.saveQuizPools ).toHaveBeenLastCalledWith(
			11,
			questionList.revision,
			[
				{
					title: 'Warm-up',
					skill_id: 7,
					count: 2,
					marks: 1,
					difficulty: 'easy',
					bank_id: 4,
					include_secure: true,
				},
			],
			expect.any( AbortSignal )
		);
		const pools = screen.getByRole( 'table', { name: 'Random pools' } );
		expect( within( pools ).getByText( 'Fractions' ) ).toBeVisible();
	} );
	it( 'offers a retry when the list cannot be loaded', async () => {
		vi.mocked( api.listQuizQuestions ).mockRejectedValueOnce(
			new ApiError( { status: 500, code: 'x', message: 'Broken.' } )
		);
		const user = userEvent.setup();
		renderSection();
		await screen.findByText( 'Broken.', notice );
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect( await screen.findByRole( 'table' ) ).toBeInTheDocument();
	} );
} );
