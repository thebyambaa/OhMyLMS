import { del, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	enroll,
	getStudent,
	listCourses,
	listEnrollments,
	listStudents,
	setBlocked,
	unenroll,
} from './api';
import { completedEnrollment, enrollment, student } from './fixtures';
import { EMPTY_QUERY } from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	patch: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'typed students API', () => {
	it( 'requests bounded, filtered student pages', async () => {
		await listStudents(
			{ ...EMPTY_QUERY, page: 2, search: 'ada', course: 40 },
			undefined
		);
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/students',
			expect.any( Function ),
			{
				signal: undefined,
				query: {
					page: 2,
					per_page: 20,
					orderby: 'name',
					order: 'asc',
					search: 'ada',
					course: 40,
				},
			}
		);
	} );
	it( 'requests at most 100 course choices', async () => {
		await listCourses( ' alg ' );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/students/courses',
			expect.any( Function ),
			expect.objectContaining( {
				query: { page: 1, per_page: 100, search: 'alg' },
			} )
		);
		await listCourses( '' );
		expect(
			vi.mocked( getCollection ).mock.calls.at( -1 )?.[ 2 ]
		).toMatchObject( { query: { page: 1, per_page: 100 } } );
	} );
	it( 'reads enrolments from the student sub-resource', async () => {
		await listEnrollments( 12, {
			page: 1,
			search: '',
			progress: 'running',
		} );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/students/12/enrollments',
			expect.any( Function ),
			expect.objectContaining( {
				query: { page: 1, per_page: 20, progress: 'running' },
			} )
		);
	} );
	it( 'validates single students and sends only the blocked flag', async () => {
		vi.mocked( get ).mockResolvedValue( { ...student, _links: {} } );
		expect( await getStudent( 12 ) ).toEqual( student );
		vi.mocked( patch ).mockResolvedValue( { ...student, blocked: true } );
		expect( ( await setBlocked( 12, true ) ).blocked ).toBe( true );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/students/12',
			{ blocked: true },
			expect.any( Object )
		);
		vi.mocked( get ).mockResolvedValue( { id: 12 } );
		await expect( getStudent( 12 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'enrols and unenrols through explicit endpoints', async () => {
		vi.mocked( post ).mockResolvedValue( enrollment );
		expect( await enroll( 12, 40 ) ).toEqual( enrollment );
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/students/12/enrollments',
			{ course_id: 40 },
			expect.any( Object )
		);
		vi.mocked( del ).mockResolvedValue( {
			deleted: true,
			previous: completedEnrollment,
		} );
		expect( await unenroll( 12, 41 ) ).toEqual( completedEnrollment );
		expect( del ).toHaveBeenCalledWith(
			'/ohmylms/v1/students/12/enrollments/41',
			expect.any( Object )
		);
		vi.mocked( del ).mockResolvedValueOnce( { deleted: false } );
		await expect( unenroll( 12, 41 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'refuses invalid ids before making a request', async () => {
		await expect( getStudent( 0 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		await expect( enroll( 12, -1 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		await expect( unenroll( 1.5, 4 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		expect( post ).not.toHaveBeenCalled();
		expect( del ).not.toHaveBeenCalled();
	} );
} );
