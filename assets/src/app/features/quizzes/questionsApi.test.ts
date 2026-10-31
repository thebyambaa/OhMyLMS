import { del, get, post, put } from '../../lib/api';
import { questionList } from './fixtures';
import {
	addQuizQuestions,
	createQuizQuestion,
	listQuizQuestions,
	pinQuizQuestion,
	removeQuizQuestion,
	reorderQuizQuestions,
	saveQuizPools,
	saveQuizSections,
	setQuizQuestionMarks,
} from './questionsApi';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
	del: vi.fn(),
} ) );

const BASE = '/ohmylms/v1/quizzes/11/questions';
const REVISION = questionList.revision;

beforeEach( () => vi.clearAllMocks() );

describe( 'quiz questions API', () => {
	it( 'reads and validates the list', async () => {
		vi.mocked( get ).mockResolvedValueOnce( questionList );
		expect( await listQuizQuestions( 11 ) ).toEqual( questionList );
		expect( get ).toHaveBeenCalledWith( BASE, { signal: undefined } );
	} );
	it( 'rejects a list that does not match the contract', async () => {
		vi.mocked( get ).mockResolvedValueOnce( {
			...questionList,
			revision: 'not-a-revision',
		} );
		await expect( listQuizQuestions( 11 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		vi.mocked( get ).mockResolvedValueOnce( {
			...questionList,
			questions: [ { ...questionList.questions[ 0 ], marks: '2' } ],
		} );
		await expect( listQuizQuestions( 11 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'never builds a path from an invalid id', async () => {
		await expect( listQuizQuestions( 0 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		expect( get ).not.toHaveBeenCalled();
	} );
	it( 'sends the revision with every write', async () => {
		vi.mocked( post ).mockResolvedValue( questionList );
		vi.mocked( put ).mockResolvedValue( questionList );
		vi.mocked( del ).mockResolvedValue( questionList );
		await addQuizQuestions( 11, REVISION, [ 5, 6 ], true );
		expect( post ).toHaveBeenLastCalledWith(
			BASE,
			{ question_ids: [ 5, 6 ], pin: true, revision: REVISION },
			{ signal: undefined }
		);
		await reorderQuizQuestions( 11, REVISION, [ 102, 101 ] );
		expect( put ).toHaveBeenLastCalledWith(
			`${ BASE }/order`,
			{ question_ids: [ 102, 101 ], revision: REVISION },
			{ signal: undefined }
		);
		await removeQuizQuestion( 11, REVISION, 101 );
		expect( del ).toHaveBeenLastCalledWith( `${ BASE }/101`, {
			signal: undefined,
			query: { revision: REVISION },
		} );
	} );
	it.each( [
		[ 'current' as const, { current: true, revision: REVISION } ],
		[ 40, { version_id: 40, revision: REVISION } ],
		[ null, { version_id: null, revision: REVISION } ],
	] )( 'pins with %s', async ( choice, body ) => {
		vi.mocked( put ).mockResolvedValueOnce( questionList );
		await pinQuizQuestion( 11, REVISION, 102, choice );
		expect( put ).toHaveBeenCalledWith( `${ BASE }/102/pin`, body, {
			signal: undefined,
		} );
	} );
	it( 'sets and clears marks, and saves sections and pools with the revision', async () => {
		vi.mocked( put ).mockResolvedValue( questionList );
		await setQuizQuestionMarks( 11, REVISION, 101, 2.5 );
		expect( put ).toHaveBeenLastCalledWith(
			`${ BASE }/101/marks`,
			{ marks: 2.5, revision: REVISION },
			{ signal: undefined }
		);
		await setQuizQuestionMarks( 11, REVISION, 101, null );
		expect( put ).toHaveBeenLastCalledWith(
			`${ BASE }/101/marks`,
			{ marks: null, revision: REVISION },
			{ signal: undefined }
		);
		const sections = [
			{ title: 'Part A', question_ids: [ 101 ], new_page: true },
		];
		await saveQuizSections( 11, REVISION, sections );
		expect( put ).toHaveBeenLastCalledWith(
			`${ BASE }/sections`,
			{ sections, revision: REVISION },
			{ signal: undefined }
		);
		const pools = [
			{
				title: 'Warm-up',
				skill_id: 7,
				count: 2,
				marks: 1,
				difficulty: '',
				bank_id: 0,
				include_secure: true,
			},
		];
		await saveQuizPools( 11, REVISION, pools );
		expect( put ).toHaveBeenLastCalledWith(
			`${ BASE }/pools`,
			{ pools, revision: REVISION },
			{ signal: undefined }
		);
	} );
	it( 'reads sections and marks overrides, and tolerates their absence', async () => {
		vi.mocked( get ).mockResolvedValueOnce( {
			...questionList,
			questions: [
				{
					...questionList.questions[ 0 ],
					marks: 4,
					marks_override: 4,
				},
			],
			sections: [
				{ title: 'Part A', question_ids: [ 101 ], new_page: false },
			],
		} );
		const list = await listQuizQuestions( 11 );
		expect( list.questions[ 0 ] ).toMatchObject( {
			marks: 4,
			question_marks: 2,
			marks_override: 4,
		} );
		expect( list.sections ).toEqual( [
			{ title: 'Part A', question_ids: [ 101 ], new_page: false },
		] );
		const { sections, ...older } = questionList;
		vi.mocked( get ).mockResolvedValueOnce( older );
		expect( ( await listQuizQuestions( 11 ) ).sections ).toEqual(
			sections
		);
		vi.mocked( get ).mockResolvedValueOnce( {
			...questionList,
			sections: [ { title: 'A', question_ids: [ 0 ], new_page: false } ],
		} );
		await expect( listQuizQuestions( 11 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'creates a question in the quiz and checks it is in the answer', async () => {
		const question = {
			name: 'New',
			settings: { type: 'single-choice' },
		};
		vi.mocked( post ).mockResolvedValueOnce( {
			...questionList,
			created_id: 101,
		} );
		const created = await createQuizQuestion( 11, REVISION, question );
		expect( created.created_id ).toBe( 101 );
		expect( post ).toHaveBeenCalledWith(
			`${ BASE }/new`,
			{ question, revision: REVISION },
			{ signal: undefined }
		);
		vi.mocked( post ).mockResolvedValueOnce( {
			...questionList,
			created_id: 999,
		} );
		await expect(
			createQuizQuestion( 11, REVISION, question )
		).rejects.toMatchObject( { code: 'invalid_response' } );
	} );
} );
