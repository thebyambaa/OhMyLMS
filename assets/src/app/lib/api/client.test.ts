import { ApiError, del, get, patch, post, put, request } from './index';

function jsonResponse( body: unknown, status = 200 ): Response {
	return new Response( JSON.stringify( body ), {
		status,
		headers: { 'Content-Type': 'application/json' },
	} );
}

function mockFetch( implementation: typeof fetch ) {
	return vi.spyOn( globalThis, 'fetch' ).mockImplementation( implementation );
}

async function caught( promise: Promise< unknown > ): Promise< ApiError > {
	try {
		await promise;
	} catch ( error ) {
		if ( error instanceof ApiError ) {
			return error;
		}
		throw error;
	}
	throw new Error( 'Expected the request to fail.' );
}

describe( 'request', () => {
	it( 'resolves with the parsed body and builds the URL from path and query', async () => {
		const spy = mockFetch( () =>
			Promise.resolve( jsonResponse( { id: 7 } ) )
		);

		const result = await get< { id: number } >( '/wp/v2/things', {
			query: { per_page: 5 },
		} );

		expect( result ).toEqual( { id: 7 } );
		const [ url, init ] = spy.mock.calls[ 0 ] ?? [];
		expect( String( url ) ).toBe( '/wp/v2/things?per_page=5&_locale=user' );
		expect( init?.method ).toBe( 'GET' );
	} );

	it( 'sends JSON bodies with the right method for each helper', async () => {
		const spy = mockFetch( () => Promise.resolve( jsonResponse( {} ) ) );

		await post( '/a', { x: 1 } );
		await put( '/a', { x: 2 } );
		await patch( '/a', { x: 3 } );
		await del( '/a' );

		// @wordpress/api-fetch sends PUT, PATCH and DELETE as POST with an override header.
		const methods = spy.mock.calls.map( ( call ) => {
			const headers = new Headers( call[ 1 ]?.headers );
			return headers.get( 'X-HTTP-Method-Override' ) ?? call[ 1 ]?.method;
		} );
		expect( methods ).toEqual( [ 'POST', 'PUT', 'PATCH', 'DELETE' ] );
		expect( spy.mock.calls[ 0 ]?.[ 1 ]?.body ).toBe( '{"x":1}' );
	} );

	it( 'sends a multipart body untouched', async () => {
		const spy = mockFetch( () =>
			Promise.resolve( jsonResponse( { id: 3 } ) )
		);
		const body = new FormData();
		body.append( 'file', new Blob( [ 'x' ] ), 'a.pdf' );

		const result = await request( {
			path: '/ohmylms/v1/upload',
			method: 'POST',
			body,
		} );

		expect( result ).toEqual( { id: 3 } );
		const init = spy.mock.calls[ 0 ]?.[ 1 ];
		expect( init?.method ).toBe( 'POST' );
		expect( init?.body ).toBe( body );
		expect( new Headers( init?.headers ).get( 'Content-Type' ) ).toBeNull();
	} );

	it( 'maps a WP_Error response to ApiError', async () => {
		mockFetch( () =>
			Promise.resolve(
				jsonResponse(
					{
						code: 'rest_forbidden',
						message: 'Sorry, you are not allowed to do that.',
						data: { status: 403 },
					},
					403
				)
			)
		);

		const error = await caught( request( { path: '/ohmylms/v1/x' } ) );

		expect( error ).toBeInstanceOf( ApiError );
		expect( error.status ).toBe( 403 );
		expect( error.code ).toBe( 'rest_forbidden' );
		expect( error.message ).toBe(
			'Sorry, you are not allowed to do that.'
		);
		expect( error.details ).toEqual( { status: 403 } );
		expect( error.isNetworkError ).toBe( false );
	} );

	it( 'maps a network failure to status 0 and fetch_error', async () => {
		mockFetch( () => Promise.reject( new TypeError( 'Failed to fetch' ) ) );

		const error = await caught( get( '/wp/v2/things' ) );

		expect( error.status ).toBe( 0 );
		expect( error.code ).toBe( 'fetch_error' );
		expect( error.isNetworkError ).toBe( true );
	} );

	it( 'maps an invalid JSON body to invalid_json', async () => {
		mockFetch( () =>
			Promise.resolve(
				new Response( '<html>oops</html>', { status: 200 } )
			)
		);

		const error = await caught( get( '/wp/v2/things' ) );

		expect( error.code ).toBe( 'invalid_json' );
		expect( error.status ).toBe( 0 );
	} );

	it( 'rejects with an aborted ApiError when the signal fires', async () => {
		mockFetch(
			( _url, init ) =>
				new Promise( ( _resolve, reject ) => {
					init?.signal?.addEventListener( 'abort', () =>
						reject( new DOMException( 'Aborted', 'AbortError' ) )
					);
				} )
		);
		const controller = new AbortController();

		const pending = caught(
			get( '/wp/v2/things', { signal: controller.signal } )
		);
		controller.abort();
		const error = await pending;

		expect( error.isAborted ).toBe( true );
		expect( error.code ).toBe( 'request_aborted' );
		expect( error.status ).toBe( 0 );
	} );
} );
