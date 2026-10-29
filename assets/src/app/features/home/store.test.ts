import { createRegistry } from '@wordpress/data';
import { ApiError, get } from '../../lib/api';
import { DEFAULT_STATE, homeStore, reducer, toContentTypes } from './store';

vi.mock( '../../lib/api', async ( importOriginal ) => ( {
	...( await importOriginal< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
} ) );

const getMock = vi.mocked( get );

const wpTypes = {
	page: { name: 'Pages', slug: 'page', description: '' },
	post: { name: 'Posts', slug: 'post', description: 'Blog posts' },
	broken: 'not an object',
	nameless: { slug: 'nameless' },
};

function createStore() {
	const registry = createRegistry();
	registry.register( homeStore );
	return registry;
}

describe( 'home store', () => {
	it( 'reducer moves through loading, ready and error', () => {
		const loading = reducer( DEFAULT_STATE, { type: 'LOAD_STARTED' } );
		expect( loading.status ).toBe( 'loading' );

		const ready = reducer( loading, { type: 'ITEMS_RECEIVED', items: [] } );
		expect( ready ).toEqual( { status: 'ready', items: [], error: null } );

		const failed = reducer( loading, {
			type: 'LOAD_FAILED',
			error: { code: 'x', message: 'm', status: 500 },
		} );
		expect( failed.status ).toBe( 'error' );
		expect( failed.error?.code ).toBe( 'x' );
	} );

	it( 'toContentTypes keeps valid entries, sorted by name', () => {
		expect(
			toContentTypes( wpTypes ).map( ( item ) => item.slug )
		).toEqual( [ 'page', 'post' ] );
		expect( toContentTypes( null ) ).toEqual( [] );
	} );

	it( 'loads through the resolver the first time the selector is used', async () => {
		getMock.mockResolvedValue( wpTypes );
		const registry = createStore();

		expect( registry.select( homeStore ).getStatus() ).toBe( 'idle' );
		const items = await registry
			.resolveSelect( homeStore )
			.getContentTypes();

		expect( getMock ).toHaveBeenCalledWith( '/wp/v2/types' );
		expect( items ).toHaveLength( 2 );
		expect( registry.select( homeStore ).getStatus() ).toBe( 'ready' );
		expect( registry.select( homeStore ).getError() ).toBeNull();
	} );

	it( 'stores a serialisable error and recovers on reload', async () => {
		getMock.mockRejectedValueOnce(
			new ApiError( {
				status: 403,
				code: 'rest_forbidden',
				message: 'Nope',
			} )
		);
		const registry = createStore();

		await registry.resolveSelect( homeStore ).getContentTypes();
		expect( registry.select( homeStore ).getStatus() ).toBe( 'error' );
		expect( registry.select( homeStore ).getError() ).toEqual( {
			code: 'rest_forbidden',
			message: 'Nope',
			status: 403,
		} );

		getMock.mockResolvedValueOnce( {} );
		await registry.dispatch( homeStore ).reload();

		expect( registry.select( homeStore ).getStatus() ).toBe( 'ready' );
		expect( registry.select( homeStore ).getContentTypes() ).toEqual( [] );
	} );
} );
