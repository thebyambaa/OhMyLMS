import { del, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	createQuiz,
	deleteQuiz,
	getAttempt,
	getQuiz,
	gradeAttempt,
	listAttempts,
	listCourses,
	listQuizzes,
	restoreQuiz,
	trashQuiz,
	updateQuiz,
} from './api';
import { attemptDetail, quiz } from './fixtures';
import { formFromQuiz, toInput } from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	patch: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'typed quizzes API', () => {
	it( 'requests bounded, ordered, filtered quiz collections', async () => {
		await listQuizzes( 2, 'frac', 'draft', 5 );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/quizzes',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 2,
					per_page: 20,
					search: 'frac',
					status: 'draft',
					course: 5,
					orderby: 'created_at',
					order: 'desc',
				},
			} )
		);
		await listQuizzes( 1, '', 'all', 0 );
		expect(
			vi.mocked( getCollection ).mock.calls.at( -1 )?.[ 2 ]?.query
		).toMatchObject( { course: undefined } );
		await listCourses( 'ma', 3 );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/quizzes/courses',
			expect.any( Function ),
			expect.objectContaining( {
				query: { search: 'ma', page: 3, per_page: 10 },
			} )
		);
	} );
	it( 'validates each collection item', async () => {
		await listQuizzes( 1, '', 'all', 0 );
		const parse = vi.mocked( getCollection ).mock.calls[ 0 ]![ 1 ];
		expect( () => parse( { id: 'x' } ) ).toThrowError(
			expect.objectContaining( { code: 'invalid_response' } )
		);
	} );
	it( 'reads one quiz in edit context and validates it', async () => {
		vi.mocked( get ).mockResolvedValueOnce( quiz );
		expect( await getQuiz( 11 ) ).toEqual( quiz );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/quizzes/11',
			expect.objectContaining( { query: { context: 'edit' } } )
		);
		vi.mocked( get ).mockResolvedValueOnce( { ...quiz, id: 'x' } );
		await expect( getQuiz( 11 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		await expect( getQuiz( 0 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'creates with a trimmed title only', async () => {
		vi.mocked( post ).mockResolvedValueOnce( quiz );
		await createQuiz( '  Fractions  ' );
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/quizzes',
			{ title: 'Fractions' },
			expect.anything()
		);
	} );
	it( 'saves with PATCH and the base version', async () => {
		vi.mocked( patch ).mockResolvedValueOnce( quiz );
		const input = toInput( formFromQuiz( quiz ), quiz.updated_at );
		expect( await updateQuiz( 11, input ) ).toEqual( quiz );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/quizzes/11',
			expect.objectContaining( {
				base_modified: quiz.updated_at,
				settings: expect.objectContaining( { passing_score: 60 } ),
			} ),
			expect.anything()
		);
	} );
	it( 'trashes, restores and force-deletes', async () => {
		vi.mocked( del ).mockResolvedValue( { deleted: false } );
		await trashQuiz( 11 );
		expect( del ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/quizzes/11',
			expect.not.objectContaining( { query: expect.anything() } )
		);
		vi.mocked( del ).mockResolvedValue( { deleted: true } );
		await deleteQuiz( 11 );
		expect( del ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/quizzes/11',
			expect.objectContaining( { query: { force: true } } )
		);
		vi.mocked( del ).mockResolvedValue( { nothing: true } );
		await expect( trashQuiz( 11 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		vi.mocked( patch ).mockResolvedValueOnce( quiz );
		await restoreQuiz( 11 );
		expect( patch ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/quizzes/11',
			{ status: 'draft' },
			expect.anything()
		);
	} );
	it( 'requests paged, filtered attempts', async () => {
		await listAttempts( 11, 2, { status: 'in-review', search: 'sam' } );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/quizzes/11/attempts',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 2,
					per_page: 20,
					status: 'in-review',
					search: 'sam',
					orderby: 'started_at',
					order: 'desc',
				},
			} )
		);
	} );
	it( 'reads an attempt and validates it', async () => {
		vi.mocked( get ).mockResolvedValueOnce( attemptDetail );
		expect( await getAttempt( 11, 21 ) ).toEqual( attemptDetail );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/quizzes/11/attempts/21',
			expect.anything()
		);
	} );
	it( 'sends grades and an optional trimmed note, never a total', async () => {
		vi.mocked( post ).mockResolvedValue( { ...attemptDetail, changed: 1 } );
		await gradeAttempt(
			11,
			21,
			[ { question_id: 102, marks: 3 } ],
			' Good '
		);
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/quizzes/11/attempts/21/grades',
			{ grades: [ { question_id: 102, marks: 3 } ], reason: 'Good' },
			expect.anything()
		);
		await gradeAttempt( 11, 21, [ { question_id: 102, marks: 3 } ], '  ' );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/quizzes/11/attempts/21/grades',
			{ grades: [ { question_id: 102, marks: 3 } ] },
			expect.anything()
		);
	} );
} );
