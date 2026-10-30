import { del, get, patch, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	createAssignment,
	getAssignment,
	getSubmission,
	gradeSubmission,
	listAssignments,
	listCourses,
	listSubmissions,
	trashAssignment,
	updateAssignment,
} from './api';
import { assignment, graded, pendingDetail } from './fixtures';
import { formFromAssignment, toInput } from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
	patch: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const invalid = expect.objectContaining( { code: 'invalid_response' } );

beforeEach( () => vi.clearAllMocks() );

describe( 'typed assignments API', () => {
	it( 'requests bounded, filtered collections', async () => {
		await listAssignments( 2, {
			search: 'essay',
			status: 'draft',
			course: 0,
		} );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/assignments',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 2,
					per_page: 20,
					search: 'essay',
					status: 'draft',
					orderby: 'created_at',
					order: 'desc',
				},
			} )
		);
		await listAssignments( 1, { search: '', status: 'all', course: 12 } );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/assignments',
			expect.any( Function ),
			expect.objectContaining( {
				query: expect.objectContaining( { course: 12 } ),
			} )
		);
		await listSubmissions( 31, 3, 'submitted' );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/assignments/31/submissions',
			expect.any( Function ),
			expect.objectContaining( {
				query: { page: 3, per_page: 20, status: 'submitted' },
			} )
		);
		await listCourses();
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/assignments/courses',
			expect.any( Function ),
			expect.objectContaining( { query: { page: 1, per_page: 50 } } )
		);
	} );
	it( 'validates each collection item', async () => {
		await listSubmissions( 31, 1, 'all' );
		const parse = vi.mocked( getCollection ).mock.calls[ 0 ]![ 1 ];
		expect( () => parse( { id: 'x' } ) ).toThrow( invalid );
	} );
	it( 'reads one assignment in edit context and rejects another id', async () => {
		vi.mocked( get ).mockResolvedValueOnce( assignment );
		await expect( getAssignment( 31 ) ).resolves.toEqual( assignment );
		expect( get ).toHaveBeenCalledWith( '/ohmylms/v1/assignments/31', {
			signal: undefined,
			query: { context: 'edit' },
		} );
		vi.mocked( get ).mockResolvedValueOnce( { ...assignment, id: 32 } );
		await expect( getAssignment( 31 ) ).rejects.toEqual( invalid );
		await expect( getAssignment( 0 ) ).rejects.toEqual( invalid );
	} );
	it( 'creates with a trimmed title and saves the full payload with its base', async () => {
		vi.mocked( post ).mockResolvedValueOnce( assignment );
		await createAssignment( '  Essay  ' );
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/assignments',
			{ title: 'Essay' },
			{ signal: undefined }
		);
		const input = toInput(
			formFromAssignment( assignment ),
			assignment.updated_at
		);
		vi.mocked( put ).mockResolvedValueOnce( assignment );
		await updateAssignment( 31, input );
		expect( put ).toHaveBeenCalledWith(
			'/ohmylms/v1/assignments/31',
			input,
			{ signal: undefined }
		);
		expect( input.base_modified ).toBe( assignment.updated_at );
	} );
	it( 'trashes and checks the answer', async () => {
		vi.mocked( del ).mockResolvedValueOnce( {
			deleted: false,
			trashed: true,
		} );
		await trashAssignment( 31 );
		expect( del ).toHaveBeenCalledWith( '/ohmylms/v1/assignments/31', {
			signal: undefined,
		} );
		vi.mocked( del ).mockResolvedValueOnce( { status: 'success' } );
		await expect( trashAssignment( 31 ) ).rejects.toEqual( invalid );
	} );
	it( 'reads and grades a submission of this assignment only', async () => {
		vi.mocked( get ).mockResolvedValueOnce( pendingDetail );
		await expect( getSubmission( 31, 501 ) ).resolves.toEqual(
			pendingDetail
		);
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/assignments/31/submissions/501',
			{ signal: undefined }
		);
		vi.mocked( get ).mockResolvedValueOnce( {
			...pendingDetail,
			assignment_id: 99,
		} );
		await expect( getSubmission( 31, 501 ) ).rejects.toEqual( invalid );

		vi.mocked( patch ).mockResolvedValueOnce( graded );
		await expect(
			gradeSubmission( 31, 501, { score: 40, feedback: 'Well done.' } )
		).resolves.toEqual( graded );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/assignments/31/submissions/501',
			{ score: 40, feedback: 'Well done.' },
			{ signal: undefined }
		);
		vi.mocked( patch ).mockResolvedValueOnce( pendingDetail );
		await expect(
			gradeSubmission( 31, 501, { score: 40, feedback: '' } )
		).rejects.toEqual( invalid );
	} );
} );
