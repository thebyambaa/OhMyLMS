/**
 * Typed REST client for the admin app.
 *
 * Usage:
 *
 * ```ts
 * import { get, post, ApiError } from '../../lib/api';
 *
 * const course = await get< Course >( '/ohmylms/v1/courses/12', { query: { context: 'edit' }, signal } );
 * await post< Course >( '/ohmylms/v1/courses', { title: 'New' } );
 * ```
 *
 * Rules:
 * - Paths are REST routes relative to the REST root (`/wp/v2/...`). The root URL and nonce are applied by
 *   the `wp-api-fetch` middleware registered in `bootstrap/configure-api-fetch.ts`.
 * - Success resolves with the parsed JSON body typed as `T`. The type is the caller's promise, not a
 *   runtime check; validate untrusted shapes at the boundary when it matters.
 * - Failure rejects with an `ApiError`. Never inspect messages; use `code` and `status`.
 * - Pass an `AbortSignal` from an effect cleanup to cancel a request. A cancelled request rejects with an
 *   `ApiError` whose `isAborted` is true; ignore it.
 */
import apiFetch from '@wordpress/api-fetch';
import { normalizeApiError } from './errors';
import { withQuery, type QueryParams } from './query';

/** HTTP methods the client supports. */
export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

/** Options for {@link request}. */
export interface RequestOptions {
	/** REST route, for example `/wp/v2/posts`. May already contain a query string. */
	path: string;
	/** HTTP method. Defaults to `GET`. */
	method?: HttpMethod;
	/** Query parameters appended to `path`. */
	query?: QueryParams;
	/** JSON request body. */
	data?: unknown;
	/** Cancels the request when aborted. */
	signal?: AbortSignal;
	/** Extra request headers. */
	headers?: Readonly< Record< string, string > >;
}

/** Options for the method helpers: everything except `path`, `method` and `data`. */
export type RequestExtras = Pick<
	RequestOptions,
	'query' | 'signal' | 'headers'
>;

/**
 * Perform a request and resolve with the parsed body.
 *
 * @param  options Request options.
 * @return Parsed response body.
 * @throws {Error} An `ApiError` for any failure.
 */
export async function request< T >( options: RequestOptions ): Promise< T > {
	const { path, method = 'GET', query, data, signal, headers } = options;
	try {
		return await apiFetch< T >( {
			path: withQuery( path, query ),
			method,
			...( undefined !== data ? { data } : {} ),
			...( signal ? { signal } : {} ),
			...( headers ? { headers: { ...headers } } : {} ),
		} );
	} catch ( error ) {
		throw normalizeApiError( error );
	}
}

/**
 * GET a route.
 *
 * @param path   REST route.
 * @param extras Query, signal and headers.
 * @return Parsed response body.
 */
export function get< T >(
	path: string,
	extras: RequestExtras = {}
): Promise< T > {
	return request< T >( { ...extras, path, method: 'GET' } );
}

/**
 * POST JSON to a route.
 *
 * @param path   REST route.
 * @param data   JSON body.
 * @param extras Query, signal and headers.
 * @return Parsed response body.
 */
export function post< T >(
	path: string,
	data?: unknown,
	extras: RequestExtras = {}
): Promise< T > {
	return request< T >( { ...extras, path, method: 'POST', data } );
}

/**
 * PUT JSON to a route.
 *
 * @param path   REST route.
 * @param data   JSON body.
 * @param extras Query, signal and headers.
 * @return Parsed response body.
 */
export function put< T >(
	path: string,
	data?: unknown,
	extras: RequestExtras = {}
): Promise< T > {
	return request< T >( { ...extras, path, method: 'PUT', data } );
}

/**
 * PATCH JSON to a route.
 *
 * @param path   REST route.
 * @param data   JSON body.
 * @param extras Query, signal and headers.
 * @return Parsed response body.
 */
export function patch< T >(
	path: string,
	data?: unknown,
	extras: RequestExtras = {}
): Promise< T > {
	return request< T >( { ...extras, path, method: 'PATCH', data } );
}

/**
 * DELETE a route. Named `del` because `delete` is reserved.
 *
 * @param path   REST route.
 * @param extras Query, signal and headers.
 * @return Parsed response body.
 */
export function del< T >(
	path: string,
	extras: RequestExtras = {}
): Promise< T > {
	return request< T >( { ...extras, path, method: 'DELETE' } );
}
