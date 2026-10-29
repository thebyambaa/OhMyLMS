import apiFetch from '@wordpress/api-fetch';
import { getCollection } from './collection';

vi.mock( '@wordpress/api-fetch', () => ( { default: vi.fn() } ) );
const request = vi.mocked( apiFetch );

describe( 'WordPress collection requests', () => {
	it( 'retains pagination headers and cancellation and validates every record', async () => {
		request.mockResolvedValue(
			new Response( '[{"id":1}]', {
				headers: { 'X-WP-Total': '21', 'X-WP-TotalPages': '2' },
			} )
		);
		const controller = new AbortController();
		const parseItem = vi.fn( ( value: unknown ) => value );
		const result = await getCollection( '/test/items', parseItem, {
			query: { page: 2, per_page: 20 },
			signal: controller.signal,
		} );
		expect( result ).toEqual( {
			items: [ { id: 1 } ],
			total: 21,
			totalPages: 2,
		} );
		expect( parseItem ).toHaveBeenCalledOnce();
		expect( request ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/test/items?page=2&per_page=20',
				signal: controller.signal,
				parse: false,
			} )
		);
	} );
	it( 'rejects missing or malformed pagination and malformed collection bodies', async () => {
		for ( const response of [
			new Response( '[]' ),
			new Response( '[]', {
				headers: { 'X-WP-Total': '-1', 'X-WP-TotalPages': '2' },
			} ),
			new Response( '{}', {
				headers: { 'X-WP-Total': '1', 'X-WP-TotalPages': '1' },
			} ),
		] ) {
			request.mockResolvedValueOnce( response );
			await expect(
				getCollection( '/test/items', ( item ) => item )
			).rejects.toMatchObject( { code: 'invalid_response' } );
		}
	} );
} );
