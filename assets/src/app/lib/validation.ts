import { ApiError } from './api';

/**
 * What a validator throws when a response breaks its contract: the message of an
 * `invalid_response` `ApiError` (status 502), or a factory for the error, so every feature keeps
 * its own wording ("The server returned invalid exam data.").
 */
export type InvalidResponse = string | ( () => Error );

/**
 * The error for a response that breaks its contract.
 *
 * @param invalid Message or error factory.
 * @return The error to throw.
 */
export function invalidResponseError( invalid: InvalidResponse ): Error {
	return 'string' === typeof invalid
		? new ApiError( {
				code: 'invalid_response',
				status: 502,
				message: invalid,
			} )
		: invalid();
}

/**
 * A plain object (not `null`, not an array).
 *
 * @param value   Decoded JSON.
 * @param invalid Message or error factory for anything else.
 * @return The object.
 */
export function record(
	value: unknown,
	invalid: InvalidResponse
): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponseError( invalid );
	}
	return value as Record< string, unknown >;
}

/**
 * A string (the empty string included).
 *
 * @param value   Decoded JSON.
 * @param invalid Message or error factory for anything else.
 * @return The string.
 */
export function text( value: unknown, invalid: InvalidResponse ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponseError( invalid );
	}
	return value;
}

/**
 * A safe integer of at least 1.
 *
 * @param value   Decoded JSON.
 * @param invalid Message or error factory for anything else.
 * @return The ID.
 */
export function positiveId( value: unknown, invalid: InvalidResponse ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 1
	) {
		throw invalidResponseError( invalid );
	}
	return value;
}

export interface Validators {
	record: ( value: unknown ) => Record< string, unknown >;
	text: ( value: unknown ) => string;
	positiveId: ( value: unknown ) => number;
}

/**
 * `record`, `text` and `positiveId` bound to one feature's error, for its parsers:
 * `const { record, text } = createValidators( invalidResponse );`.
 *
 * @param invalid Message or error factory.
 * @return The bound validators.
 */
export function createValidators( invalid: InvalidResponse ): Validators {
	return {
		record: ( value ) => record( value, invalid ),
		text: ( value ) => text( value, invalid ),
		positiveId: ( value ) => positiveId( value, invalid ),
	};
}
