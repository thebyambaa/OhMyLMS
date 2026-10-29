import test from 'node:test';
import assert from 'node:assert/strict';
import {
	RestError,
	REST_NAMESPACE,
	buildPath,
	buildQuery,
	createRestClient,
	getRestClient,
	hasMessage,
	isResponse,
	normalizeRestError,
	restUrl,
} from '../../assets/src/shared/api/index.mjs';
import { restUrl as featureRestUrl } from '../../assets/src/features/restUrl.mjs';

/**
 * A scripted `apiFetch` that records the options it receives (by reference).
 *
 * @param {Array} steps `{ result }` or `{ error }` per call.
 * @return {{fetch: Function, seen: Array}} Mock.
 */
function mockFetch( steps ) {
	const queue = [ ...steps ];
	const seen = [];
	return {
		seen,
		fetch( options ) {
			seen.push( options );
			const step = queue.shift() || { result: null };
			return 'error' in step
				? Promise.reject( step.error )
				: Promise.resolve( step.result );
		},
	};
}

/**
 * A fetch `Response` double.
 *
 * @param {number} status HTTP status.
 * @param {*}      body   JSON body, or an `Error` for an unreadable body.
 * @return {Object} Response double with `clone`.
 */
function responseDouble( status, body ) {
	const make = () => ( {
		status,
		headers: new Map(),
		json: () =>
			body instanceof Error
				? Promise.reject( body )
				: Promise.resolve( body ),
		clone: make,
	} );
	return make();
}

test( 'request passes the options to apiFetch untouched and returns the result', async () => {
	const { fetch, seen } = mockFetch( [ { result: { id: 1 } } ] );
	const client = createRestClient( { apiFetch: fetch } );
	const options = {
		path: '/ohmylms/v1/things',
		method: 'POST',
		data: { a: 1 },
	};
	assert.deepStrictEqual( await client.request( options ), { id: 1 } );
	assert.strictEqual(
		seen[ 0 ],
		options,
		'the same options object, no copy'
	);
	assert.deepStrictEqual( Object.keys( seen[ 0 ] ), [
		'path',
		'method',
		'data',
	] );
} );

test( 'route helpers build apiFetch options: GET has no method, query goes into the path', async () => {
	const { fetch, seen } = mockFetch( [ {}, {}, {}, {}, {}, {} ] );
	const client = createRestClient( { apiFetch: fetch } );
	await client.get( '/courses', {
		query: { page: 2, search: '', status: undefined },
	} );
	await client.get( 'courses/9', { parse: false } );
	await client.post( '/courses', { title: 'A' } );
	await client.put( '/courses/9', { title: 'B' } );
	await client.patch( '/courses/9', { title: 'C' }, { query: { force: 1 } } );
	await client.remove( '/courses/9' );
	assert.deepStrictEqual( seen, [
		{ path: '/ohmylms/v1/courses?page=2' },
		{ path: '/ohmylms/v1/courses/9', parse: false },
		{ path: '/ohmylms/v1/courses', method: 'POST', data: { title: 'A' } },
		{ path: '/ohmylms/v1/courses/9', method: 'PUT', data: { title: 'B' } },
		{
			path: '/ohmylms/v1/courses/9?force=1',
			method: 'PATCH',
			data: { title: 'C' },
		},
		{ path: '/ohmylms/v1/courses/9', method: 'DELETE' },
	] );
	assert.equal( client.namespace, REST_NAMESPACE );
	assert.equal( client.path( '/x', { a: 'b c' } ), '/ohmylms/v1/x?a=b+c' );
} );

test( 'a post without a body sends no data key', async () => {
	const { fetch, seen } = mockFetch( [ {} ] );
	await createRestClient( { apiFetch: fetch } ).post(
		'/content-hub/lessons/3/duplicate'
	);
	assert.deepStrictEqual( seen[ 0 ], {
		path: '/ohmylms/v1/content-hub/lessons/3/duplicate',
		method: 'POST',
	} );
} );

test( 'a custom namespace is used for route helpers', async () => {
	const { fetch, seen } = mockFetch( [ {} ] );
	await createRestClient( {
		apiFetch: fetch,
		namespace: '/ohmylms-commerce/v1/',
	} ).get( '/orders' );
	assert.equal( seen[ 0 ].path, '/ohmylms-commerce/v1/orders' );
} );

test( 'the nonce is added as X-WP-Nonce unless the request sets one', async () => {
	const { fetch, seen } = mockFetch( [ {}, {}, {}, {} ] );
	const client = createRestClient( { apiFetch: fetch, nonce: 'abc123' } );
	const original = { path: '/ohmylms/v1/a', headers: { 'X-Test': '1' } };
	await client.request( original );
	await client.request( { path: '/ohmylms/v1/b' } );
	await client.request( {
		path: '/ohmylms/v1/c',
		headers: { 'x-wp-nonce': 'own' },
	} );
	assert.deepStrictEqual( seen[ 0 ].headers, {
		'X-Test': '1',
		'X-WP-Nonce': 'abc123',
	} );
	assert.deepStrictEqual(
		original.headers,
		{ 'X-Test': '1' },
		'input is not modified'
	);
	assert.deepStrictEqual( seen[ 1 ].headers, { 'X-WP-Nonce': 'abc123' } );
	assert.deepStrictEqual( seen[ 2 ].headers, { 'x-wp-nonce': 'own' } );
	let current = 'first';
	const dynamic = createRestClient( {
		apiFetch: fetch,
		nonce: () => current,
	} );
	current = 'second';
	await dynamic.request( { path: '/ohmylms/v1/d' } );
	assert.equal( seen[ 3 ].headers[ 'X-WP-Nonce' ], 'second' );
} );

test( 'a parsed REST error is rethrown as the same object', async () => {
	const rest = {
		code: 'rest_forbidden',
		message: 'Not allowed.',
		data: { status: 403 },
	};
	const { fetch } = mockFetch( [ { error: rest } ] );
	await assert.rejects(
		createRestClient( { apiFetch: fetch } ).request( { path: '/x' } ),
		( error ) => error === rest
	);
	const plainError = new Error( 'Offline' );
	assert.strictEqual( await normalizeRestError( plainError ), plainError );
	assert.ok(
		hasMessage( { message: '' } ),
		'an empty message is still a message'
	);
} );

test( 'an unparsed error response becomes a RestError with the server message and status', async () => {
	const response = responseDouble( 404, {
		code: 'rest_no_route',
		message: 'No route was found.',
		data: { status: 404 },
	} );
	const { fetch } = mockFetch( [ { error: response } ] );
	const error = await createRestClient( { apiFetch: fetch } )
		.request( { path: '/x', parse: false } )
		.catch( ( failure ) => failure );
	assert.ok( error instanceof RestError );
	assert.equal( error.message, 'No route was found.' );
	assert.equal( error.code, 'rest_no_route' );
	assert.equal( error.status, 404 );
	assert.deepStrictEqual( error.data, { status: 404 } );
	assert.strictEqual( error.cause, response );
	// The shape showRequestError() reads: the first value with a string message.
	const shown = [ error ].find(
		( value ) => 'string' === typeof value?.message
	);
	assert.equal( shown.message, 'No route was found.' );
} );

test( 'unreadable bodies and non-error rejections use the fallback message', async () => {
	const broken = responseDouble( 502, new SyntaxError( 'bad json' ) );
	const fromResponse = await normalizeRestError( broken, 'Try again.' );
	assert.equal( fromResponse.message, 'Try again.' );
	assert.equal( fromResponse.code, 'http_error' );
	assert.equal( fromResponse.status, 502 );
	const fromString = await normalizeRestError( 'boom' );
	assert.ok( fromString instanceof RestError );
	assert.equal(
		fromString.message,
		'The request could not be completed. Please try again.'
	);
	assert.equal( fromString.cause, 'boom' );
	const noBody = await normalizeRestError( undefined, 'Nothing.' );
	assert.equal( noBody.message, 'Nothing.' );
	assert.equal( isResponse( broken ), true );
	assert.equal( isResponse( { message: 'x' } ), false );
	assert.equal( isResponse( null ), false );
} );

test( 'the client fallback message is used when no message can be read', async () => {
	const { fetch } = mockFetch( [ { error: null } ] );
	const error = await createRestClient( {
		apiFetch: fetch,
		fallback: 'Saving failed.',
	} )
		.request( { path: '/x' } )
		.catch( ( failure ) => failure );
	assert.equal( error.message, 'Saving failed.' );
} );

test( 'without wp.apiFetch the default client rejects instead of throwing synchronously', async () => {
	const saved = globalThis.wp;
	delete globalThis.wp;
	try {
		const promise = createRestClient().request( { path: '/x' } );
		assert.ok( promise instanceof Promise );
		await assert.rejects( promise, {
			message: 'The WordPress apiFetch package is not loaded.',
		} );
	} finally {
		if ( undefined !== saved ) {
			globalThis.wp = saved;
		}
	}
} );

test( 'getRestClient returns one client that resolves wp.apiFetch per request', async () => {
	assert.strictEqual( getRestClient(), getRestClient() );
	const saved = globalThis.wp;
	const { fetch, seen } = mockFetch( [ { result: 'ok' } ] );
	globalThis.wp = { apiFetch: fetch };
	try {
		assert.equal( await getRestClient().get( '/ping' ), 'ok' );
		assert.deepStrictEqual( seen, [ { path: '/ohmylms/v1/ping' } ] );
	} finally {
		if ( undefined === saved ) {
			delete globalThis.wp;
		} else {
			globalThis.wp = saved;
		}
	}
} );

test( 'URL helpers: restUrl matches features/restUrl.mjs, query and path building', () => {
	const roots = [
		'https://example.com/wp-json/',
		'https://example.com/wp-json',
		'https://example.com/index.php?rest_route=/',
	];
	const paths = [
		'/ohmylms/v1/courses',
		'ohmylms/v1/courses?per_page=5',
		'/ohmylms/v1/courses/3?context=edit&_locale=user',
	];
	for ( const root of roots ) {
		for ( const path of paths ) {
			assert.equal( restUrl( root, path ), featureRestUrl( root, path ) );
		}
	}
	assert.equal(
		restUrl( roots[ 2 ], paths[ 1 ] ),
		'https://example.com/index.php?rest_route=/ohmylms/v1/courses&per_page=5'
	);
	assert.equal(
		buildQuery( { a: 1, b: '', c: null, d: undefined, e: 0, f: false } ),
		'?a=1&e=0&f=false'
	);
	assert.equal( buildQuery( {} ), '' );
	assert.equal( buildQuery(), '' );
	assert.equal(
		buildPath( '/ohmylms/v1/', 'x', { q: 'a&b' } ),
		'/ohmylms/v1/x?q=a%26b'
	);
} );
