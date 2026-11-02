import { del, get, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	addMember,
	archiveClass,
	createClass,
	createSchool,
	createYear,
	getChildren,
	getClass,
	getSchool,
	linkChild,
	listCandidates,
	listClasses,
	listMembers,
	listParents,
	listSchools,
	listSchoolStudents,
	listTeachers,
	removeMember,
	restoreClass,
	revokeInvitation,
	unlinkChild,
	updateClass,
	updateSchool,
} from './api';
import {
	children,
	invitation,
	member,
	school,
	schoolClass,
	teacher,
} from './fixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const BASE = '/ohmylms/v1/account-hub';

beforeEach( () => vi.clearAllMocks() );

describe( 'typed account hub API', () => {
	it( 'requests bounded, searchable collections', async () => {
		await listSchools( 2, 'north' );
		expect( getCollection ).toHaveBeenLastCalledWith(
			`${ BASE }/schools`,
			expect.any( Function ),
			expect.objectContaining( {
				query: { page: 2, per_page: 20, search: 'north' },
			} )
		);
		await listClasses( 1, 'alg', 'archived', 3 );
		expect( getCollection ).toHaveBeenLastCalledWith(
			`${ BASE }/classes`,
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 1,
					per_page: 20,
					search: 'alg',
					status: 'archived',
					school_id: 3,
				},
			} )
		);
		await listClasses( 1, '', 'all' );
		expect(
			vi.mocked( getCollection ).mock.lastCall?.[ 2 ]?.query
		).not.toHaveProperty( 'school_id' );
		await listMembers( 9, 2 );
		expect( getCollection ).toHaveBeenLastCalledWith(
			`${ BASE }/classes/9/members`,
			expect.any( Function ),
			expect.objectContaining( { query: { page: 2, per_page: 20 } } )
		);
		await listCandidates( 9, 'teacher', 'te' );
		expect( getCollection ).toHaveBeenLastCalledWith(
			`${ BASE }/classes/9/candidates`,
			expect.any( Function ),
			expect.objectContaining( {
				query: { role: 'teacher', search: 'te', page: 1, per_page: 10 },
			} )
		);
		await listSchoolStudents( 3, 'an' );
		expect( vi.mocked( getCollection ).mock.lastCall?.[ 0 ] ).toBe(
			`${ BASE }/schools/3/students`
		);
		await listTeachers( 1, '' );
		expect( vi.mocked( getCollection ).mock.lastCall?.[ 0 ] ).toBe(
			`${ BASE }/teachers`
		);
		await listParents( 1, '' );
		expect( vi.mocked( getCollection ).mock.lastCall?.[ 0 ] ).toBe(
			`${ BASE }/parents`
		);
	} );
	it( 'validates collection items with the matching parser', async () => {
		await listTeachers( 1, '' );
		const parse = vi.mocked( getCollection ).mock.calls[ 0 ]![ 1 ];
		expect( parse( teacher ) ).toEqual( teacher );
		expect( () => parse( { ...teacher, id: 'x' } ) ).toThrowError(
			expect.objectContaining( { code: 'invalid_response' } )
		);
	} );
	it( 'reads, creates and saves schools with their version', async () => {
		vi.mocked( get ).mockResolvedValueOnce( school );
		expect( await getSchool( 3 ) ).toEqual( school );
		expect( get ).toHaveBeenLastCalledWith(
			`${ BASE }/schools/3`,
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValueOnce( school );
		await createSchool( { name: '  North  ', timezone: ' UTC ' } );
		expect( post ).toHaveBeenLastCalledWith(
			`${ BASE }/schools`,
			{ name: 'North', timezone: 'UTC' },
			expect.any( Object )
		);
		vi.mocked( put ).mockResolvedValueOnce( school );
		await updateSchool(
			3,
			{ name: 'North', timezone: 'UTC' },
			school.version
		);
		expect( put ).toHaveBeenLastCalledWith(
			`${ BASE }/schools/3`,
			{ name: 'North', timezone: 'UTC', version: school.version },
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValueOnce( school );
		await createYear( 3, {
			label: ' 2026 ',
			starts_at: '2026-09-01',
			ends_at: '2027-06-30',
		} );
		expect( post ).toHaveBeenLastCalledWith(
			`${ BASE }/schools/3/years`,
			{ label: '2026', starts_at: '2026-09-01', ends_at: '2027-06-30' },
			expect.any( Object )
		);
	} );
	it( 'creates, saves, archives and restores classes', async () => {
		vi.mocked( post ).mockResolvedValue( schoolClass );
		vi.mocked( put ).mockResolvedValue( schoolClass );
		vi.mocked( get ).mockResolvedValue( schoolClass );
		await createClass( 3, {
			name: ' Algebra ',
			subject: 'Math',
			grade: '5',
			academic_year_id: 5,
		} );
		expect( post ).toHaveBeenLastCalledWith(
			`${ BASE }/schools/3/classes`,
			{
				name: 'Algebra',
				subject: 'Math',
				grade: '5',
				academic_year_id: 5,
			},
			expect.any( Object )
		);
		expect( await getClass( 9 ) ).toEqual( schoolClass );
		await updateClass(
			9,
			{ name: 'Algebra', subject: '', grade: '' },
			schoolClass.version
		);
		expect( put ).toHaveBeenLastCalledWith(
			`${ BASE }/classes/9`,
			{
				name: 'Algebra',
				subject: '',
				grade: '',
				version: schoolClass.version,
			},
			expect.any( Object )
		);
		await archiveClass( 9 );
		expect( post ).toHaveBeenLastCalledWith(
			`${ BASE }/classes/9/archive`,
			undefined,
			expect.any( Object )
		);
		await restoreClass( 9 );
		expect( post ).toHaveBeenLastCalledWith(
			`${ BASE }/classes/9/restore`,
			undefined,
			expect.any( Object )
		);
	} );
	it( 'adds and removes members by role', async () => {
		vi.mocked( post ).mockResolvedValueOnce( member );
		expect( await addMember( 9, 21, 'student' ) ).toEqual( member );
		expect( post ).toHaveBeenLastCalledWith(
			`${ BASE }/classes/9/members`,
			{ user_id: 21, role: 'student' },
			expect.any( Object )
		);
		vi.mocked( del ).mockResolvedValueOnce( { removed: true } );
		await removeMember( 9, 21, 'teacher' );
		expect( del ).toHaveBeenLastCalledWith(
			`${ BASE }/classes/9/members/21`,
			expect.objectContaining( { query: { role: 'teacher' } } )
		);
		vi.mocked( del ).mockResolvedValueOnce( {} );
		await expect( removeMember( 9, 21, 'student' ) ).rejects.toMatchObject(
			{ code: 'invalid_response' }
		);
	} );
	it( 'links, unlinks and revokes for a parent', async () => {
		vi.mocked( get ).mockResolvedValueOnce( children );
		expect( await getChildren( 30 ) ).toEqual( children );
		vi.mocked( post ).mockResolvedValueOnce( invitation );
		expect( await linkChild( 30, 3, 22 ) ).toEqual( invitation );
		expect( post ).toHaveBeenLastCalledWith(
			`${ BASE }/parents/30/children`,
			{ school_id: 3, student_id: 22 },
			expect.any( Object )
		);
		vi.mocked( del ).mockResolvedValue( { revoked: true } );
		await unlinkChild( 30, 70 );
		expect( del ).toHaveBeenLastCalledWith(
			`${ BASE }/parents/30/children/70`,
			expect.any( Object )
		);
		await revokeInvitation( 30, 80 );
		expect( del ).toHaveBeenLastCalledWith(
			`${ BASE }/parents/30/invitations/80`,
			expect.any( Object )
		);
	} );
	it( 'refuses invalid ids before any request', async () => {
		await expect( getSchool( 0 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		await expect( addMember( 9, -1, 'student' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		expect( get ).not.toHaveBeenCalled();
		expect( post ).not.toHaveBeenCalled();
	} );
} );
