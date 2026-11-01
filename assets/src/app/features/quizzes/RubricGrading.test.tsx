import '@testing-library/jest-dom/vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import * as api from './api';
import { AttemptReview } from './AttemptReview';
import { attemptDetail } from './fixtures';
import {
	buildGrades,
	draftsFromQuestions,
	rubricBreakdown,
	rubricMarks,
	scoresLabel,
} from './model';
import type { AttemptDetail, RubricCriterion } from './types';

vi.mock( './api', () => ( {
	getAttempt: vi.fn(),
	gradeAttempt: vi.fn(),
} ) );

const rubric: RubricCriterion[] = [
	{
		id: 'reasoning',
		title: 'Reasoning',
		description: 'Explains why the fractions are equal.',
		points: 3,
		levels: [
			{ points: 3, description: 'Complete' },
			{ points: 1, description: 'Partial' },
			{ points: 0, description: 'Missing' },
		],
	},
	{
		id: 'clarity',
		title: 'Clarity',
		description: '',
		points: 1,
		levels: [],
	},
];

function withRubric( maxMarks = 4 ): AttemptDetail {
	return {
		...attemptDetail,
		questions: attemptDetail.questions.map( ( question ) =>
			102 === question.question_id
				? { ...question, max_marks: maxMarks, rubric }
				: question
		),
	};
}

function renderReview() {
	return render(
		<AttemptReview
			quizId={ 11 }
			attemptId={ 21 }
			onClose={ vi.fn() }
			onGraded={ vi.fn() }
		/>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getAttempt ).mockResolvedValue( withRubric() );
} );

describe( 'rubric grading', () => {
	it( 'scores each criterion, fills in the marks and saves only the total', async () => {
		vi.mocked( api.gradeAttempt ).mockResolvedValue( {
			...withRubric(),
			changed: 1,
		} );
		const user = userEvent.setup();
		renderReview();
		const group = await screen.findByRole( 'group', {
			name: 'Rubric for Explain why 1/2 is 2/4',
		} );
		const marks = screen.getByRole( 'textbox', {
			name: /Marks for Explain/,
		} );
		expect( within( group ).getByRole( 'status' ) ).toHaveTextContent(
			'Score every criterion to fill in the marks.'
		);
		expect(
			within( group ).getByText( 'Explains why the fractions are equal.' )
		).toBeInTheDocument();

		await user.click(
			within( group ).getByRole( 'radio', { name: '3 — Complete' } )
		);
		expect( marks ).toHaveValue( '' );
		await user.type(
			within( group ).getByRole( 'textbox', {
				name: 'Clarity (out of 1)',
			} ),
			'0.5'
		);
		expect( within( group ).getByRole( 'status' ) ).toHaveTextContent(
			'Rubric score: 3.5 of 4.'
		);
		expect( marks ).toHaveValue( '3.5' );

		await user.click(
			screen.getByRole( 'button', { name: 'Save marks' } )
		);
		await waitFor( () =>
			expect( api.gradeAttempt ).toHaveBeenCalledWith(
				11,
				21,
				[
					{
						question_id: 102,
						marks: 3.5,
						rubric: { reasoning: 3, clarity: 0.5 },
					},
				],
				'',
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'reopens with the saved breakdown and shows it in the history', async () => {
		const graded = withRubric();
		graded.questions = graded.questions.map( ( question ) =>
			102 === question.question_id
				? {
						...question,
						status: 'graded' as const,
						awarded: 4,
						rubric_scores: { reasoning: 3, clarity: 1 },
						history: [
							{
								awarded: 2,
								grader: 'manual',
								reviewer_id: 2,
								reviewer_name: 'Ada',
								reason: '',
								rubric_scores: null,
								created_at: '2026-10-30T11:00:00Z',
							},
							{
								awarded: 4,
								grader: 'manual',
								reviewer_id: 2,
								reviewer_name: 'Ada',
								reason: 'Rubric',
								rubric_scores: { reasoning: 3, clarity: 1 },
								created_at: '2026-10-30T12:00:00Z',
							},
						],
					}
				: question
		);
		vi.mocked( api.getAttempt ).mockResolvedValue( graded );
		renderReview();
		const group = await screen.findByRole( 'group', {
			name: /Rubric for/,
		} );
		expect(
			within( group ).getByRole( 'radio', { name: '3 — Complete' } )
		).toBeChecked();
		expect(
			within( group ).getByRole( 'textbox', {
				name: 'Clarity (out of 1)',
			} )
		).toHaveValue( '1' );
		expect( group ).toHaveTextContent(
			'Saved with the current marks: Reasoning: 3, Clarity: 1.'
		);
		expect(
			screen.getByText( /— Rubric \(Reasoning: 3, Clarity: 1\)/ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Save marks' } )
		).toBeDisabled();
	} );

	it( 'sends a changed breakdown even when the marks stay the same', async () => {
		const even = withRubric();
		even.questions = even.questions.map( ( question ) =>
			102 === question.question_id
				? {
						...question,
						status: 'graded' as const,
						awarded: 2,
						rubric: [
							{
								id: 'a',
								title: 'Idea',
								description: '',
								points: 2,
								levels: [],
							},
							{
								id: 'b',
								title: 'Style',
								description: '',
								points: 2,
								levels: [],
							},
						],
						rubric_scores: { a: 2, b: 0 },
					}
				: question
		);
		vi.mocked( api.getAttempt ).mockResolvedValue( even );
		vi.mocked( api.gradeAttempt ).mockResolvedValue( {
			...even,
			changed: 1,
		} );
		const user = userEvent.setup();
		renderReview();
		const group = await screen.findByRole( 'group', {
			name: /Rubric for/,
		} );
		const idea = within( group ).getByRole( 'textbox', {
			name: 'Idea (out of 2)',
		} );
		await user.clear( idea );
		await user.type( idea, '1' );
		const style = within( group ).getByRole( 'textbox', {
			name: 'Style (out of 2)',
		} );
		await user.clear( style );
		await user.type( style, '1' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save marks' } )
		);
		await waitFor( () =>
			expect( api.gradeAttempt ).toHaveBeenCalledWith(
				11,
				21,
				[ { question_id: 102, marks: 2, rubric: { a: 1, b: 1 } } ],
				'',
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'sends marks typed over the rubric score without a breakdown', async () => {
		vi.mocked( api.gradeAttempt ).mockResolvedValue( {
			...withRubric(),
			changed: 1,
		} );
		const user = userEvent.setup();
		renderReview();
		const group = await screen.findByRole( 'group', {
			name: /Rubric for/,
		} );
		await user.click(
			within( group ).getByRole( 'radio', { name: '3 — Complete' } )
		);
		await user.type(
			within( group ).getByRole( 'textbox', {
				name: 'Clarity (out of 1)',
			} ),
			'1'
		);
		const marks = screen.getByRole( 'textbox', {
			name: /Marks for Explain/,
		} );
		await user.clear( marks );
		await user.type( marks, '2' );
		expect( within( group ).getByRole( 'status' ) ).toHaveTextContent(
			'The marks differ from the rubric score, so this breakdown will not be saved.'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save marks' } )
		);
		await waitFor( () =>
			expect( api.gradeAttempt ).toHaveBeenCalledWith(
				11,
				21,
				[ { question_id: 102, marks: 2 } ],
				'',
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'scales the rubric to the marks of the question in this quiz', async () => {
		vi.mocked( api.getAttempt ).mockResolvedValue( withRubric( 8 ) );
		const user = userEvent.setup();
		renderReview();
		const group = await screen.findByRole( 'group', {
			name: /Rubric for/,
		} );
		await user.click(
			within( group ).getByRole( 'radio', { name: '1 — Partial' } )
		);
		await user.type(
			within( group ).getByRole( 'textbox', {
				name: 'Clarity (out of 1)',
			} ),
			'1'
		);
		expect( within( group ).getByRole( 'status' ) ).toHaveTextContent(
			'Rubric score: 2 of 4, which is 4 of 8 marks in this quiz.'
		);
		expect(
			screen.getByRole( 'textbox', { name: /Marks for Explain/ } )
		).toHaveValue( '4' );
	} );

	it( 'reports a criterion score above its points and keeps the marks', async () => {
		const user = userEvent.setup();
		renderReview();
		const group = await screen.findByRole( 'group', {
			name: /Rubric for/,
		} );
		await user.click(
			within( group ).getByRole( 'radio', { name: '0 — Missing' } )
		);
		await user.type(
			within( group ).getByRole( 'textbox', {
				name: 'Clarity (out of 1)',
			} ),
			'2'
		);
		expect( within( group ).getByRole( 'status' ) ).toHaveTextContent(
			'Clarity: Marks cannot be more than 1.'
		);
		expect(
			screen.getByRole( 'textbox', { name: /Marks for Explain/ } )
		).toHaveValue( '' );
	} );

	it( 'shows no rubric for questions without one', async () => {
		vi.mocked( api.getAttempt ).mockResolvedValue( attemptDetail );
		renderReview();
		await screen.findByRole( 'textbox', { name: /Marks for Explain/ } );
		expect(
			screen.queryByRole( 'group', { name: /Rubric for/ } )
		).not.toBeInTheDocument();
	} );
} );

describe( 'rubric breakdown of a grade', () => {
	const question = withRubric().questions[ 1 ]!;
	it( 'is sent with the marks only when it is complete and matches them', () => {
		expect(
			rubricBreakdown( question, { reasoning: '3', clarity: '1' }, 4 )
		).toEqual( { points: { reasoning: 3, clarity: 1 } } );
		expect(
			rubricBreakdown( question, { reasoning: '3', clarity: '1' }, 3 )
		).toBeNull();
		expect( rubricBreakdown( question, { reasoning: '3' }, 3 ) ).toBeNull();
		expect(
			rubricBreakdown( question, { reasoning: '3', clarity: '9' }, 4 )
		).toHaveProperty( 'error' );
	} );
	it( 'prefills saved scores and skips an unchanged breakdown', () => {
		const saved = {
			...question,
			status: 'graded' as const,
			awarded: 2,
			rubric_scores: { reasoning: 1, clarity: 1 },
		};
		const drafts = draftsFromQuestions( [ saved ] );
		expect( drafts[ 102 ] ).toEqual( {
			total: '2',
			parts: {},
			rubric: { reasoning: '1', clarity: '1' },
		} );
		expect( buildGrades( [ saved ], drafts ).grades ).toEqual( [] );
		expect(
			draftsFromQuestions( [ { ...saved, status: 'in-review' } ] )[ 102 ]
		).not.toHaveProperty( 'rubric' );
		drafts[ 102 ]!.rubric = { reasoning: '0', clarity: '1' };
		drafts[ 102 ]!.total = '1';
		expect( buildGrades( [ saved ], drafts ).grades ).toEqual( [
			{
				question_id: 102,
				marks: 1,
				rubric: { reasoning: 0, clarity: 1 },
			},
		] );
		expect( scoresLabel( rubric, { reasoning: 1, clarity: 0.5 } ) ).toBe(
			'Reasoning: 1, Clarity: 0.5'
		);
	} );
} );

describe( 'rubricMarks', () => {
	it( 'waits for every criterion, sums and scales', () => {
		expect( rubricMarks( rubric, { reasoning: '3' }, 4 ) ).toBeNull();
		expect(
			rubricMarks( rubric, { reasoning: '3', clarity: '1' }, 4 )
		).toEqual( { score: 4, total: 4, marks: 4 } );
		expect(
			rubricMarks( rubric, { reasoning: '1', clarity: '0' }, 3 )
		).toEqual( { score: 1, total: 4, marks: 0.75 } );
		expect(
			rubricMarks( rubric, { reasoning: 'x', clarity: '0' }, 4 )
		).toHaveProperty( 'error' );
	} );
} );
