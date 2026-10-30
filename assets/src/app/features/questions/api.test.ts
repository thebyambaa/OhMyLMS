import { get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	approveQuestion,
	archiveQuestion,
	createQuestion,
	duplicateQuestion,
	getQuestion,
	listQuestions,
	listQuestionTypes,
	listVersions,
	restoreQuestion,
	updateQuestion,
} from './api';
import { question, summary, types, version } from './fixtures';
import { createInput } from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	patch: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const invalid = { code: 'invalid_response', status: 502 };

beforeEach( () => vi.clearAllMocks() );

describe( 'typed questions API', () => {
	it( 'lists with paging and only the filters that are set', async () => {
		await listQuestions( {
			page: 2,
			search: '  solve ',
			type: 'single-choice',
			difficulty: 'easy',
			status: 'archived',
		} );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/questions',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 2,
					per_page: 20,
					search: 'solve',
					type: 'single-choice',
					difficulty: 'easy',
					status: 'archived',
					orderby: 'modified',
					order: 'desc',
				},
			} )
		);
		await listQuestions( {
			page: 1,
			search: '',
			type: '',
			difficulty: '',
			status: 'active',
		} );
		expect( vi.mocked( getCollection ).mock.calls[ 1 ]![ 2 ] ).toEqual(
			expect.objectContaining( {
				query: {
					page: 1,
					per_page: 20,
					search: undefined,
					type: undefined,
					difficulty: undefined,
					status: 'active',
					orderby: 'modified',
					order: 'desc',
				},
			} )
		);
	} );

	it( 'validates each list row', async () => {
		await listQuestions( {
			page: 1,
			search: '',
			type: '',
			difficulty: '',
			status: 'active',
		} );
		const parse = vi.mocked( getCollection ).mock.calls[ 0 ]![ 1 ];
		expect( parse( summary ) ).toEqual( summary );
		expect( () => parse( { ...summary, id: '812' } ) ).toThrowError(
			expect.objectContaining( invalid )
		);
		expect( () =>
			parse( { ...summary, usage: { quizzes: -1, responses: 0 } } )
		).toThrowError( expect.objectContaining( invalid ) );
	} );

	it( 'reads the types and rejects malformed ones', async () => {
		vi.mocked( get ).mockResolvedValueOnce( types );
		expect( await listQuestionTypes() ).toEqual( types );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/questions/types',
			expect.any( Object )
		);
		vi.mocked( get ).mockResolvedValueOnce( [
			{ ...types[ 0 ], answers: 'images' },
		] );
		await expect( listQuestionTypes() ).rejects.toMatchObject( invalid );
		vi.mocked( get ).mockResolvedValueOnce( { types } );
		await expect( listQuestionTypes() ).rejects.toMatchObject( invalid );
	} );

	it( 'reads one question and validates the editor view', async () => {
		vi.mocked( get ).mockResolvedValueOnce( question );
		expect( await getQuestion( 812 ) ).toEqual( question );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/questions/812',
			expect.any( Object )
		);
		vi.mocked( get ).mockResolvedValueOnce( {
			...question,
			permissions: { can_edit: true },
		} );
		await expect( getQuestion( 812 ) ).rejects.toMatchObject( invalid );
	} );

	it( 'refuses an invalid id before any request', async () => {
		await expect( getQuestion( 0 ) ).rejects.toMatchObject( invalid );
		await expect(
			updateQuestion( -1, { base_modified: question.modified } )
		).rejects.toMatchObject( invalid );
		await expect( archiveQuestion( 1.5 ) ).rejects.toMatchObject( invalid );
		await expect( listVersions( 0, 1 ) ).rejects.toMatchObject( invalid );
		expect( get ).not.toHaveBeenCalled();
		expect( patch ).not.toHaveBeenCalled();
		expect( post ).not.toHaveBeenCalled();
		expect( getCollection ).not.toHaveBeenCalled();
	} );

	it( 'creates with POST and the given payload', async () => {
		const input = createInput( 'Solve 2x = 6', 'single-choice' );
		vi.mocked( post ).mockResolvedValueOnce( question );
		expect( await createQuestion( input ) ).toEqual( question );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/questions',
			input,
			expect.any( Object )
		);
	} );

	it( 'saves with PATCH and base_modified', async () => {
		const input = {
			base_modified: question.modified,
			name: 'Solve 2x = 8',
		};
		vi.mocked( patch ).mockResolvedValueOnce( question );
		expect( await updateQuestion( 812, input ) ).toEqual( question );
		expect( patch ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/questions/812',
			input,
			expect.any( Object )
		);
	} );

	it( 'archives and restores with an empty POST', async () => {
		vi.mocked( post ).mockResolvedValue( question );
		await archiveQuestion( 812 );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/questions/812/archive',
			undefined,
			expect.any( Object )
		);
		await restoreQuestion( 812 );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/questions/812/restore',
			undefined,
			expect.any( Object )
		);
	} );

	it( 'approves and duplicates with an empty POST and validates the result', async () => {
		vi.mocked( post ).mockResolvedValue( question );
		expect( await approveQuestion( 812 ) ).toEqual( question );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/questions/812/approve',
			undefined,
			expect.any( Object )
		);
		expect( await duplicateQuestion( 812 ) ).toEqual( question );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/questions/812/duplicate',
			undefined,
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValueOnce( { ...question, id: 0 } );
		await expect( duplicateQuestion( 812 ) ).rejects.toMatchObject(
			invalid
		);
		vi.mocked( post ).mockClear();
		await expect( approveQuestion( 0 ) ).rejects.toMatchObject( invalid );
		await expect( duplicateQuestion( -3 ) ).rejects.toMatchObject(
			invalid
		);
		expect( post ).not.toHaveBeenCalled();
	} );

	it( 'pages versions and validates them', async () => {
		await listVersions( 812, 3 );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/questions/812/versions',
			expect.any( Function ),
			expect.objectContaining( { query: { page: 3, per_page: 20 } } )
		);
		const parse = vi.mocked( getCollection ).mock.calls[ 0 ]![ 1 ];
		expect( parse( version ) ).toEqual( version );
		expect( () => parse( { ...version, is_current: 1 } ) ).toThrowError(
			expect.objectContaining( invalid )
		);
	} );

	it( 'keeps server errors typed', async () => {
		const failure = Object.assign( new Error( 'stale' ), { status: 409 } );
		vi.mocked( patch ).mockRejectedValueOnce( failure );
		await expect(
			updateQuestion( 812, { base_modified: question.modified } )
		).rejects.toBe( failure );
	} );
} );
