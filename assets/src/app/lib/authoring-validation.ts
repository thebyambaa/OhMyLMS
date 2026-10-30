import { __ } from '@wordpress/i18n';
import { ApiError } from './api';

export type Row = Record< string, unknown >;
export function invalid(): never {
	throw new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid authoring data.', 'ohmylms' ),
	} );
}
export function record( value: unknown ): Row {
	if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
		return invalid();
	}
	return value as Row;
}
export function positiveId( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) > 0
		? Number( value )
		: invalid();
}
export function text( value: unknown ): string {
	return typeof value === 'string' ? value : invalid();
}
