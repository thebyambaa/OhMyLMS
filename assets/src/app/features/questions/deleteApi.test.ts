import { del, get, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	addToQuiz,
	deleteQuestion,
	deleteQuestions,
	searchQuizzes,
} from './api';
import {
	blockedFromError,
	deleteMessage,
	deleteOutcome,
	parseBulkDelete,
	parseQuizPlacement,
	referenceBlocker,
} from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const placement = ( revision: string, ids: number[], extra = {} ) => ( {
	revision,
	questions: ids.map( ( id ) => ( { question_id: id } ) ),
	...extra,
} );

beforeEach( () => vi.clearAllMocks() );

describe( 'question delete API', () => {
	it( 'deletes one question and validates the answer', async () => {
		vi.mocked( del ).mockResolvedValueOnce( {
			deleted: false,
			archived: true,
			previous: {},
		} );
		expect( await deleteQuestion( 812 ) ).toEqual( {
			deleted: false,
			archived: true,
		} );
		expect( del ).toHaveBeenCalledWith( '/ohmylms/v1/questions/812', {
			signal: undefined,
		} );
		vi.mocked( del ).mockResolvedValueOnce( {
			deleted: true,
			archived: true,
		} );
		await expect( deleteQuestion( 812 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'bulk deletes with skip_blocked and parses results', async () => {
		vi.mocked( post ).mockResolvedValueOnce( {
			results: [ { id: 1, action: 'deleted' } ],
			skipped: [
				{
					id: 2,
					code: 'ohmylms_question_shared',
					message: 'x',
					quizzes: [ 5, 6 ],
				},
			],
		} );
		const result = await deleteQuestions( [ 1, 2 ], true );
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/questions/delete',
			{ ids: [ 1, 2 ], skip_blocked: true },
			{ signal: undefined }
		);
		expect( result.skipped[ 0 ]?.quizzes ).toEqual( [ 5, 6 ] );
		expect( () =>
			parseBulkDelete( {
				results: [ { id: 1, action: 'trashed' } ],
				skipped: [],
			} )
		).toThrow();
	} );
	it( 'reads the blocked questions of a refused request', () => {
		expect(
			blockedFromError( {
				status: 409,
				blocked: [ { id: 3, code: 'c', message: 'm', quizzes: [] } ],
			} )
		).toEqual( [ { id: 3, code: 'c', message: 'm', quizzes: [] } ] );
		expect( blockedFromError( null ) ).toEqual( [] );
	} );
	it( 'predicts the outcome from usage and summarises results', () => {
		expect( deleteOutcome( { quizzes: 2, responses: 0 } ) ).toBe(
			'blocked'
		);
		expect( deleteOutcome( { quizzes: 1, responses: 3 } ) ).toBe(
			'archive'
		);
		expect( deleteOutcome( { quizzes: 1, responses: 0 } ) ).toBe(
			'delete'
		);
		expect(
			deleteMessage( {
				results: [
					{ id: 1, action: 'deleted' },
					{ id: 2, action: 'deleted' },
					{ id: 3, action: 'archived' },
				],
				skipped: [],
			} )
		).toBe(
			'Deleted 2 questions. Archived 1 question that learners answered.'
		);
	} );
} );

describe( 'add to quiz API', () => {
	it( 'searches quizzes by title', async () => {
		vi.mocked( getCollection ).mockResolvedValueOnce( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		await searchQuizzes( ' unit ' );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/quizzes',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					search: 'unit',
					status: 'all',
					per_page: 20,
					orderby: 'title',
					order: 'asc',
				},
			} )
		);
	} );
	it( 'reads the revision and places the question with it', async () => {
		vi.mocked( get ).mockResolvedValueOnce(
			placement( '0123456789abcdef', [ 4 ] )
		);
		vi.mocked( post ).mockResolvedValueOnce(
			placement( 'fedcba9876543210', [ 4, 812 ] )
		);
		expect( await addToQuiz( 77, 812, false ) ).toBe( 812 );
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/quizzes/77/questions',
			{
				signal: undefined,
			}
		);
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/quizzes/77/questions',
			{ question_ids: [ 812 ], revision: '0123456789abcdef' },
			{ signal: undefined }
		);
	} );
	it( 'copies with the copy route and returns the new id', async () => {
		vi.mocked( get ).mockResolvedValueOnce(
			placement( '0123456789abcdef', [] )
		);
		vi.mocked( post ).mockResolvedValueOnce(
			placement( 'fedcba9876543210', [ 990 ], {
				copied: [ { source_id: 812, id: 990 } ],
			} )
		);
		expect( await addToQuiz( 77, 812, true ) ).toBe( 990 );
		expect( vi.mocked( post ).mock.calls[ 0 ]?.[ 0 ] ).toBe(
			'/ohmylms/v1/quizzes/77/questions/copy'
		);
	} );
	it( 'rejects malformed revisions and copies', () => {
		expect( () => parseQuizPlacement( placement( 'nope', [] ) ) ).toThrow();
		expect( () =>
			parseQuizPlacement(
				placement( '0123456789abcdef', [], { copied: [ { id: 0 } ] } )
			)
		).toThrow();
	} );
	it( 'explains why a question cannot be added by reference', () => {
		const base = {
			status: 'draft' as const,
			approved_version_id: 0,
			permissions: { can_edit: true },
		};
		expect( referenceBlocker( base ) ).toBeNull();
		expect(
			referenceBlocker( { ...base, permissions: { can_edit: false } } )
		).toMatch( /only a copy/ );
		expect(
			referenceBlocker( {
				...base,
				approved_version_id: 5,
				permissions: { can_edit: false },
			} )
		).toBeNull();
		expect( referenceBlocker( { ...base, status: 'archived' } ) ).toMatch(
			/Archived/
		);
	} );
} );
