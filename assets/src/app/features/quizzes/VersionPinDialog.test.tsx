import '@testing-library/jest-dom/vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as bank from '../questions/public';
import type { QuestionVersion } from '../questions/public';
import { questionList, slot } from './fixtures';
import * as api from './questionsApi';
import { QuizQuestions } from './QuizQuestions';
import type { QuizQuestions as QuestionList } from './types';

vi.mock( './questionsApi', async ( original ) => ( {
	...( await original< typeof import( './questionsApi' ) >() ),
	listQuizQuestions: vi.fn(),
	pinQuizQuestion: vi.fn(),
} ) );
vi.mock( '../questions/public', async ( original ) => ( {
	...( await original< typeof import( '../questions/public' ) >() ),
	listVersions: vi.fn(),
	getQuestionVersion: vi.fn(),
	listQuestionTypes: vi.fn(),
} ) );

const notice = { selector: '.components-notice__content' };

function version(
	id: number,
	number: number,
	extra: Partial< QuestionVersion > = {}
): QuestionVersion {
	return {
		id,
		version_no: number,
		created_at: '2026-10-30T09:00:00Z',
		created_by: { id: 2, name: 'Ada' },
		is_current: false,
		is_approved: false,
		...extra,
	};
}

function pinnedTo( id: number, number: number ): QuestionList {
	return {
		...questionList,
		revision: 'aaaaaaaaaaaaaaaa',
		questions: questionList.questions.map( ( item ) =>
			item.question_id === slot.question_id
				? {
						...item,
						pinned_version_id: id,
						pinned_version_no: number,
						delivery: {
							source: 'pinned' as const,
							version_id: id,
							version_no: number,
						},
					}
				: item
		),
	};
}

function renderSection() {
	return render(
		<MemoryRouter>
			<QuizQuestions
				quizId={ 11 }
				readOnly={ false }
				onDirtyChange={ vi.fn() }
				onTotalChange={ vi.fn() }
			/>
		</MemoryRouter>
	);
}

async function openVersions( user: ReturnType< typeof userEvent.setup > ) {
	renderSection();
	await user.click(
		await screen.findByRole( 'button', {
			name: 'Choose a version of Pick the larger fraction',
		} )
	);
	return screen.findByRole( 'dialog', {
		name: 'Versions of Pick the larger fraction',
	} );
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listQuizQuestions ).mockResolvedValue( questionList );
	vi.mocked( bank.listVersions ).mockResolvedValue( {
		items: [
			version( 31, 3, { is_current: true } ),
			version( 22, 2 ),
			version( 11, 1, { is_approved: true } ),
		],
		total: 3,
		totalPages: 1,
	} );
} );

describe( 'pinning an older version', () => {
	it( 'lists the versions, shows one and pins the quiz to it', async () => {
		vi.mocked( bank.getQuestionVersion ).mockResolvedValue( {
			id: 22,
			version_no: 2,
			name: 'Pick the larger fraction',
			description: '<p>Which is larger: <strong>1/2</strong> or 3/4?</p>',
			type: 'single-choice',
			settings: { type: 'single-choice', score: { value: 2 } },
			options: [
				{ answer: '1/2', is_correct: false },
				{ answer: '3/4', is_correct: true },
			],
			created_at: '2026-10-30 09:00:00',
		} );
		vi.mocked( api.pinQuizQuestion ).mockResolvedValue( pinnedTo( 22, 2 ) );
		const user = userEvent.setup();
		const dialog = await openVersions( user );

		expect( bank.listVersions ).toHaveBeenCalledWith(
			101,
			1,
			expect.any( AbortSignal )
		);
		expect( dialog ).toHaveTextContent(
			'Learners get: Latest version (3).'
		);
		const table = await within( dialog ).findByRole( 'table', {
			name: 'Versions',
		} );
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows[ 1 ] ).toHaveTextContent( 'Learners get it now, Latest' );
		expect( rows[ 3 ] ).toHaveTextContent( 'Approved' );

		await user.click(
			within( dialog ).getByRole( 'button', { name: 'View version 2' } )
		);
		const content = await within( dialog ).findByRole( 'region', {
			name: 'Content of version 2',
		} );
		expect( bank.getQuestionVersion ).toHaveBeenCalledWith(
			101,
			22,
			expect.any( AbortSignal )
		);
		expect( content ).toHaveTextContent( 'Which is larger: 1/2 or 3/4?' );
		expect( content ).toHaveTextContent( 'Points: 2' );
		expect(
			within( content ).getByRole( 'list', { name: 'Answers' } )
		).toHaveTextContent( '3/4 (correct)' );

		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Pin version 2' } )
		);
		await waitFor( () =>
			expect(
				screen.queryByRole( 'dialog', {
					name: 'Versions of Pick the larger fraction',
				} )
			).not.toBeInTheDocument()
		);
		expect( api.pinQuizQuestion ).toHaveBeenCalledWith(
			11,
			questionList.revision,
			101,
			22,
			expect.any( AbortSignal )
		);
		expect(
			screen.getByText(
				'Pinned Pick the larger fraction to version 2.',
				notice
			)
		).toBeInTheDocument();
		expect( screen.getByText( 'Pinned to version 2' ) ).toBeVisible();
	} );

	it( 'unpins from the dialog', async () => {
		vi.mocked( api.listQuizQuestions ).mockResolvedValue(
			pinnedTo( 22, 2 )
		);
		vi.mocked( api.pinQuizQuestion ).mockResolvedValue( questionList );
		const user = userEvent.setup();
		const dialog = await openVersions( user );
		const table = await within( dialog ).findByRole( 'table' );
		expect(
			within( table ).getByRole( 'button', { name: 'Pin version 2' } )
		).toBeDisabled();
		expect( within( table ).getAllByRole( 'row' )[ 2 ] ).toHaveTextContent(
			'Pinned in this quiz'
		);

		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Unpin' } )
		);
		await waitFor( () =>
			expect( api.pinQuizQuestion ).toHaveBeenCalledWith(
				11,
				'aaaaaaaaaaaaaaaa',
				101,
				null,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText(
				'Pick the larger fraction now follows its latest usable version.',
				notice
			)
		).toBeInTheDocument();
	} );

	it( 'keeps the dialog open and reloads the list when the quiz changed', async () => {
		vi.mocked( api.pinQuizQuestion ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: api.QUESTIONS_CONFLICT,
				message: 'The questions of this quiz were changed.',
			} )
		);
		const user = userEvent.setup();
		const dialog = await openVersions( user );
		await user.click(
			await within( dialog ).findByRole( 'button', {
				name: 'Pin version 1',
			} )
		);
		expect(
			await within( dialog ).findByText(
				/The list has been reloaded and nothing was changed/,
				notice
			)
		).toBeInTheDocument();
		await waitFor( () =>
			expect( api.listQuizQuestions ).toHaveBeenCalledTimes( 2 )
		);
	} );

	it( 'offers versions only for questions the user may edit', async () => {
		renderSection();
		await screen.findByRole( 'table', { name: 'Questions in this quiz' } );
		expect(
			screen.queryByRole( 'button', {
				name: 'Choose a version of Explain why 1/2 is 2/4',
			} )
		).not.toBeInTheDocument();
	} );
} );
