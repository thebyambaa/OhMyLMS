import { normalizeRestError } from './errors.mjs';
import { REST_NAMESPACE, buildPath } from './url.mjs';

/**
 * Resolve WordPress's `apiFetch` when a request is made, not when the module loads, so pages that
 * enqueue `wp-api-fetch` later (and tests) still work. WordPress installs the REST root and the
 * `X-WP-Nonce` middleware on this function, which is why requests only carry a `path`.
 *
 * @param {Object} options `apiFetch` options.
 * @return {Promise<unknown>} Parsed response, or the `Response` when `parse` is false.
 */
function wordPressApiFetch( options ) {
	const apiFetch = globalThis.wp?.apiFetch;
	if ( 'function' !== typeof apiFetch ) {
		return Promise.reject(
			new Error( 'The WordPress apiFetch package is not loaded.' )
		);
	}
	return apiFetch( options );
}

/**
 * Create a small REST client over `apiFetch`.
 *
 * - `request( options )` passes the options to `apiFetch` untouched (so recovered request shapes
 *   can be reproduced exactly) and turns a rejection into an object with a string `message`.
 * - `get`, `post`, `put`, `patch` and `remove` build the options for a route in the client's
 *   namespace.
 *
 * @param {Object}                          config           Client configuration.
 * @param {(...args: unknown[]) => unknown} config.apiFetch  Request function, default `wp.apiFetch`.
 * @param {string}                          config.namespace Route namespace, default `/ohmylms/v1`.
 * @param {string|(() => string)|null}      config.nonce     Optional REST nonce (or a function returning
 *                                                           one) sent as `X-WP-Nonce` when the request does
 *                                                           not set that header itself. Leave empty to use
 *                                                           WordPress's own nonce middleware.
 * @param {string}                          config.fallback  Message for errors that carry none.
 * @return {Object} Client.
 */
export function createRestClient( {
	apiFetch = wordPressApiFetch,
	namespace = REST_NAMESPACE,
	nonce = null,
	fallback,
} = {} ) {
	/**
	 * Add the configured nonce without overriding a header the caller set.
	 *
	 * @param {Object} requestOptions `apiFetch` options.
	 * @return {Object} The same object when there is nothing to add.
	 */
	function withNonce( requestOptions ) {
		const value = 'function' === typeof nonce ? nonce() : nonce;
		if ( ! value ) {
			return requestOptions;
		}
		const headers = { ...( requestOptions.headers || {} ) };
		const hasHeader = Object.keys( headers ).some(
			( name ) => 'x-wp-nonce' === name.toLowerCase()
		);
		if ( hasHeader ) {
			return requestOptions;
		}
		headers[ 'X-WP-Nonce' ] = value;
		return { ...requestOptions, headers };
	}

	/**
	 * Send a request.
	 *
	 * @param {Object} requestOptions `apiFetch` options (`path`, `method`, `data`, `parse`, ...).
	 * @return {Promise<unknown>} Response; rejects with an object that has a string `message`.
	 */
	async function request( requestOptions ) {
		try {
			return await apiFetch( withNonce( requestOptions ) );
		} catch ( error ) {
			throw await normalizeRestError( error, fallback );
		}
	}

	/**
	 * Build the path for a route in this client's namespace.
	 *
	 * @param {string} route Route such as `/webhooks/12`.
	 * @param {Object} query Optional query parameters.
	 * @return {string} Path for `apiFetch`.
	 */
	const path = ( route, query ) => buildPath( namespace, route, query );

	/**
	 * Build `apiFetch` options for a route.
	 *
	 * @param {string}      route  Route in this client's namespace.
	 * @param {string|null} method HTTP method; omitted for GET, as WordPress expects.
	 * @param {unknown}     data   Request body, left out when undefined.
	 * @param {Object}      extra  Extra `apiFetch` options; `query` is added to the path.
	 * @return {Object} Options for `request`.
	 */
	function options( route, method, data, extra ) {
		const { query, ...rest } = extra || {};
		return {
			...rest,
			path: path( route, query ),
			...( method ? { method } : {} ),
			...( undefined === data ? {} : { data } ),
		};
	}

	const withBody = ( method ) => ( route, data, extra ) =>
		request( options( route, method, data, extra ) );

	return {
		namespace,
		path,
		request,
		get: ( route, extra ) =>
			request( options( route, null, undefined, extra ) ),
		post: withBody( 'POST' ),
		put: withBody( 'PUT' ),
		patch: withBody( 'PATCH' ),
		remove: ( route, extra ) =>
			request( options( route, 'DELETE', undefined, extra ) ),
	};
}
