import { del, get, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	createCourse,
	deleteCourse,
	duplicateCourse,
	getCourse,
	getOptions,
	listCourses,
	listMedia,
	restoreCourse,
	trashCourse,
	updateCourse,
} from './api';
import contract from './contract.json';
import { detail, options } from './fixtures';
import { formFromCourse, toInput } from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const input = toInput( formFromCourse( detail ), 2 );
const noFilters = {
	search: '',
	status: 'all',
	price_type: 'all',
	created_after: '',
	created_before: '',
};

beforeEach( () => vi.clearAllMocks() );

describe( 'typed courses API', () => {
	it( 'requests bounded, ordered collections and omits empty filters', async () => {
		await listCourses( 1, noFilters );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 1,
					per_page: 20,
					orderby: 'created_at',
					order: 'desc',
					status: 'all',
				},
			} )
		);
		await listCourses( 3, {
			search: 'alg',
			status: 'trash',
			price_type: 'paid',
			created_after: '2026-01-01',
			created_before: '2026-02-01',
		} );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 3,
					per_page: 20,
					orderby: 'created_at',
					order: 'desc',
					status: 'trash',
					search: 'alg',
					price_type: 'paid',
					created_after: '2026-01-01',
					created_before: '2026-02-01',
				},
			} )
		);
	} );
	it( 'validates each collection item', async () => {
		await listCourses( 1, noFilters );
		const parse = vi.mocked( getCollection ).mock.calls[ 0 ]![ 1 ];
		expect( () => parse( { id: 'x' } ) ).toThrowError(
			expect.objectContaining( { code: 'invalid_response' } )
		);
	} );
	it( 'reads one course in edit context and validates it', async () => {
		vi.mocked( get ).mockResolvedValueOnce( detail );
		expect( await getCourse( 7 ) ).toEqual( detail );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses/7',
			expect.objectContaining( { query: { context: 'edit' } } )
		);
		vi.mocked( get ).mockResolvedValueOnce( { ...detail, id: 'x' } );
		await expect( getCourse( 7 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		vi.mocked( get ).mockResolvedValueOnce( options );
		expect( await getOptions() ).toEqual( options );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses/options',
			expect.anything()
		);
	} );
	it( 'creates a draft with only a trimmed title', async () => {
		vi.mocked( post ).mockResolvedValueOnce( detail );
		await createCourse( '  Algebra I ' );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses',
			{ title: 'Algebra I' },
			expect.anything()
		);
	} );
	it( 'saves with PUT and validates the response', async () => {
		vi.mocked( put ).mockResolvedValueOnce( detail );
		expect( await updateCourse( 7, input ) ).toEqual( detail );
		expect( put ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses/7',
			input,
			expect.anything()
		);
		vi.mocked( put ).mockResolvedValueOnce( { nope: true } );
		await expect( updateCourse( 7, input ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'trashes, restores, duplicates and deletes with the right requests', async () => {
		vi.mocked( del ).mockResolvedValueOnce( {
			...detail,
			status: 'trash',
		} );
		expect( ( await trashCourse( 7 ) ).status ).toBe( 'trash' );
		expect( del ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses/7',
			expect.anything()
		);
		expect(
			vi.mocked( del ).mock.calls.at( -1 )?.[ 1 ]
		).not.toHaveProperty( 'query' );

		vi.mocked( post ).mockResolvedValueOnce( detail );
		await restoreCourse( 7 );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses/7/restore',
			undefined,
			expect.anything()
		);

		vi.mocked( post ).mockResolvedValueOnce( { ...detail, id: 9 } );
		expect( ( await duplicateCourse( 7 ) ).id ).toBe( 9 );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses/7/duplicate',
			undefined,
			expect.anything()
		);

		vi.mocked( del ).mockResolvedValueOnce( {
			deleted: true,
			previous: {},
		} );
		await deleteCourse( 7 );
		expect( del ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses/7',
			expect.objectContaining( { query: { force: 'true' } } )
		);
		vi.mocked( del ).mockResolvedValueOnce( { deleted: false } );
		await expect( deleteCourse( 7 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'rejects invalid ids before requesting', async () => {
		await expect( getCourse( 0 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		expect( get ).not.toHaveBeenCalled();
	} );
	it( 'lists only web images from the core media route', async () => {
		await listMedia( 'logo', 2 );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/wp/v2/media',
			expect.any( Function ),
			expect.objectContaining( {
				query: expect.objectContaining( {
					search: 'logo',
					page: 2,
					per_page: 12,
					media_type: 'image',
					mime_type: 'image/jpeg,image/png,image/gif,image/webp',
				} ),
			} )
		);
	} );
	it( 'calls only routes the contract describes', () => {
		const paths = contract.routes.map( ( route ) => route.path );
		expect( paths ).toEqual( [
			'/courses',
			'/courses',
			'/courses/options',
			'/courses/{id}',
			'/courses/{id}',
			'/courses/{id}',
			'/courses/{id}/duplicate',
			'/courses/{id}/restore',
		] );
		expect( contract.namespace ).toBe( 'ohmylms/v1' );
	} );
} );
