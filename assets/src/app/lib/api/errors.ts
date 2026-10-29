/**
 * Typed API errors.
 *
 * Every failure of the API client is an {@link ApiError}, whether it came from a WordPress `WP_Error`
 * JSON body, a network failure, an aborted request or something unexpected. Callers branch on `code` and
 * `status`, never on message text.
 */
import { __ } from '@wordpress/i18n';

/** Stable `code` values the client itself produces (WordPress adds its own, for example `rest_forbidden`). */
export const API_ERROR_CODES = {
	network: 'fetch_error',
	aborted: 'request_aborted',
	invalidJson: 'invalid_json',
	unknown: 'unknown_error',
} as const;

/** Error thrown by every request that does not succeed. */
export class ApiError extends Error {
	/** HTTP status, or `0` when no response was received (network failure, abort). */
	readonly status: number;

	/** Machine-readable code, for example `rest_forbidden` or `fetch_error`. */
	readonly code: string;

	/** The `data` member of the server's `WP_Error`, when present. */
	readonly details: unknown;

	constructor( {
		status,
		code,
		message,
		details,
	}: {
		status: number;
		code: string;
		message: string;
		details?: unknown;
	} ) {
		super( message );
		this.name = 'ApiError';
		this.status = status;
		this.code = code;
		this.details = details;
	}

	/** True when the request was cancelled through its AbortSignal. */
	get isAborted(): boolean {
		return API_ERROR_CODES.aborted === this.code;
	}

	/** True when no HTTP response was received. */
	get isNetworkError(): boolean {
		return API_ERROR_CODES.network === this.code;
	}
}

/** Shape of a serialised `WP_Error` as returned by the REST API (`data` is optional). */
interface WpErrorBody {
	code: string;
	message: string;
	data?: unknown;
}

function isRecord( value: unknown ): value is Record< string, unknown > {
	return 'object' === typeof value && null !== value;
}

function isWpErrorBody( value: unknown ): value is WpErrorBody {
	return (
		isRecord( value ) &&
		'string' === typeof value.code &&
		'string' === typeof value.message
	);
}

function statusFrom( data: unknown ): number | undefined {
	return isRecord( data ) && 'number' === typeof data.status
		? data.status
		: undefined;
}

/**
 * Convert anything thrown by `@wordpress/api-fetch` into an {@link ApiError}.
 *
 * @param error Value that was thrown or rejected.
 * @return Normalised error. An `ApiError` is returned unchanged.
 */
export function normalizeApiError( error: unknown ): ApiError {
	if ( error instanceof ApiError ) {
		return error;
	}

	if ( isRecord( error ) && 'AbortError' === error.name ) {
		return new ApiError( {
			status: 0,
			code: API_ERROR_CODES.aborted,
			message: __( 'The request was cancelled.', 'ohmylms' ),
		} );
	}

	if ( isWpErrorBody( error ) ) {
		const isTransport =
			API_ERROR_CODES.network === error.code ||
			API_ERROR_CODES.invalidJson === error.code;
		return new ApiError( {
			status: isTransport ? 0 : ( statusFrom( error.data ) ?? 500 ),
			code: error.code,
			message: error.message,
			details: error.data,
		} );
	}

	if ( error instanceof Error ) {
		return new ApiError( {
			status: 0,
			code: API_ERROR_CODES.unknown,
			message: error.message,
		} );
	}

	return new ApiError( {
		status: 0,
		code: API_ERROR_CODES.unknown,
		message: __( 'An unexpected error occurred.', 'ohmylms' ),
	} );
}
