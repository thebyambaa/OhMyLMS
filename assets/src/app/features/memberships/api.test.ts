import { del, get, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	createMembership,
	deleteMembership,
	duplicateMembership,
	getMembership,
	getOptions,
	listCourses,
	listMembers,
	listMemberships,
	updateMembership,
} from './api';
import { member, options, plan } from './fixtures';
import { formFromMembership, toInput } from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const input = toInput( formFromMembership( plan ), 2 );

beforeEach( () => vi.clearAllMocks() );

describe( 'typed memberships API', () => {
	it( 'requests bounded, ordered, filtered collections', async () => {
		await listMemberships( 2, 'gold', 'draft' );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/memberships',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 2,
					per_page: 20,
					search: 'gold',
					status: 'draft',
					orderby: 'created_at',
					order: 'desc',
				},
			} )
		);
		await listCourses( 'alg', 3 );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/memberships/courses',
			expect.any( Function ),
			expect.objectContaining( {
				query: { search: 'alg', page: 3, per_page: 10 },
			} )
		);
		await listMembers( 7, 2 );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/memberships/7/members',
			expect.any( Function ),
			expect.objectContaining( { query: { page: 2, per_page: 20 } } )
		);
	} );
	it( 'validates each collection item', async () => {
		await listMemberships( 1, '', 'all' );
		const parse = vi.mocked( getCollection ).mock.calls[ 0 ]![ 1 ];
		expect( () => parse( { id: 'x' } ) ).toThrowError(
			expect.objectContaining( { code: 'invalid_response' } )
		);
	} );
	it( 'reads one plan in edit context and validates it', async () => {
		vi.mocked( get ).mockResolvedValueOnce( plan );
		expect( await getMembership( 7 ) ).toEqual( plan );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/memberships/7',
			expect.objectContaining( { query: { context: 'edit' } } )
		);
		vi.mocked( get ).mockResolvedValueOnce( { ...plan, id: 'x' } );
		await expect( getMembership( 7 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		vi.mocked( get ).mockResolvedValueOnce( options );
		expect( await getOptions() ).toEqual( options );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/memberships/options',
			expect.any( Object )
		);
	} );
	it( 'refuses an invalid id before any request', async () => {
		await expect( getMembership( 0 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		await expect( deleteMembership( -3 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		expect( get ).not.toHaveBeenCalled();
		expect( del ).not.toHaveBeenCalled();
	} );
	it( 'creates a plan with only a trimmed name', async () => {
		vi.mocked( post ).mockResolvedValueOnce( plan );
		expect( await createMembership( '  Gold  ' ) ).toEqual( plan );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/memberships',
			{ name: 'Gold' },
			expect.any( Object )
		);
	} );
	it( 'saves every editable field with PUT', async () => {
		vi.mocked( put ).mockResolvedValueOnce( plan );
		expect( await updateMembership( 7, input ) ).toEqual( plan );
		expect( put ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/memberships/7',
			input,
			expect.any( Object )
		);
	} );
	it( 'deletes and requires the confirmation flag', async () => {
		vi.mocked( del ).mockResolvedValueOnce( {
			deleted: true,
			previous: plan,
		} );
		await expect( deleteMembership( 7 ) ).resolves.toBeUndefined();
		expect( del ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/memberships/7',
			expect.any( Object )
		);
		vi.mocked( del ).mockResolvedValueOnce( { deleted: false } );
		await expect( deleteMembership( 7 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'duplicates with an empty POST', async () => {
		vi.mocked( post ).mockResolvedValueOnce( { ...plan, id: 8 } );
		expect( ( await duplicateMembership( 7 ) ).id ).toBe( 8 );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/memberships/7/duplicate',
			undefined,
			expect.any( Object )
		);
	} );
	it( 'keeps server errors typed', async () => {
		const failure = Object.assign( new Error( 'no' ), { status: 409 } );
		vi.mocked( del ).mockRejectedValueOnce( failure );
		await expect( deleteMembership( 7 ) ).rejects.toBe( failure );
	} );
	it( 'exposes a member parser through the list', async () => {
		await listMembers( 7, 1 );
		const parse = vi.mocked( getCollection ).mock.calls[ 0 ]![ 1 ];
		expect( parse( member ) ).toEqual( member );
	} );
} );
