/**
 * Request errors in the shape `showRequestError()` (features/requestFeedback.mjs) reads: any value
 * with a string `message`.
 *
 * `apiFetch` rejects with the parsed REST error (`{ code, message, data: { status } }`), with a
 * `Response` when the request used `parse: false`, or with `{ code: 'fetch_error' }` when offline.
 * Parsed REST errors already have the right shape, so they are passed through unchanged (same
 * object identity). Everything else is converted to a `RestError`.
 */

const FALLBACK_MESSAGE =
	'The request could not be completed. Please try again.';

/**
 * An error raised by a REST request.
 */
export class RestError extends Error {
	/**
	 * @param {string} message Human-readable message.
	 * @param {Object} details Optional `code`, `status`, `data` and `cause`.
	 */
	constructor( message, details = {} ) {
		super( message );
		this.name = 'RestError';
		this.code = details.code || 'request_failed';
		this.status = details.status || 0;
		this.data = details.data;
		if ( undefined !== details.cause ) {
			this.cause = details.cause;
		}
	}
}

/**
 * Whether a value is a fetch `Response` that has not been parsed.
 *
 * @param {unknown} value Value rejected by `apiFetch`.
 * @return {boolean} True for response-like objects.
 */
export function isResponse( value ) {
	return (
		!! value &&
		'function' === typeof value.json &&
		'number' === typeof value.status &&
		undefined !== value.headers
	);
}

/**
 * Whether a value already has the shape the UI feedback helpers read.
 *
 * @param {unknown} value Value rejected by a request.
 * @return {boolean} True when it has a string `message`.
 */
export function hasMessage( value ) {
	return !! value && 'string' === typeof value.message;
}

/**
 * Convert any rejection of `apiFetch` to an object with a string `message`.
 *
 * @param {unknown} error    Rejection value.
 * @param {string}  fallback Message used when none can be read.
 * @return {Promise<{message: string}>} The same object when it already has a message, otherwise a
 *                                      `RestError`.
 */
export async function normalizeRestError( error, fallback = FALLBACK_MESSAGE ) {
	if ( hasMessage( error ) ) {
		return error;
	}
	if ( isResponse( error ) ) {
		let body = null;
		try {
			body = await (
				'function' === typeof error.clone ? error.clone() : error
			).json();
		} catch {
			body = null;
		}
		return new RestError( hasMessage( body ) ? body.message : fallback, {
			code: hasMessage( body ) && body.code ? body.code : 'http_error',
			status: error.status,
			data: body && body.data,
			cause: error,
		} );
	}
	return new RestError( fallback, { cause: error } );
}
