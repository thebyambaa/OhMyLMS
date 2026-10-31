import { __ } from '@wordpress/i18n';
import { ApiError } from './api';
import { createValidators } from './validation';

export type Row = Record< string, unknown >;
function invalidData(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid authoring data.', 'ohmylms' ),
	} );
}
export function invalid(): never {
	throw invalidData();
}
export const { record, positiveId, text } = createValidators( invalidData );
