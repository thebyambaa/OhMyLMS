import { __ } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type {
	Delivery,
	MappingRow,
	Webhook,
	WebhookInput,
	WebhookOptions,
} from './types';

export const EMPTY_WEBHOOK: WebhookInput = {
	name: '',
	webhook_url: '',
	trigger_event: '',
	http_method: 'POST',
	data_type: 'json',
	status: 'inactive',
	data_mapping: [],
};

export function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}
export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid webhook data.', 'ohmylms' ),
	} );
}
function string( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}
export function positiveId( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 1
	) {
		throw invalidResponse();
	}
	return value;
}
export function parseWebhook( value: unknown ): Webhook {
	const item = record( value );
	if (
		! Array.isArray( item.data_mapping ) ||
		! [ 'active', 'inactive' ].includes( String( item.status ) )
	) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		name: string( item.name ),
		webhook_url: string( item.webhook_url ),
		trigger_event: string( item.trigger_event ),
		http_method: string( item.http_method ),
		data_type: string( item.data_type ),
		status: item.status as Webhook[ 'status' ],
		secret_hint: string( item.secret_hint ),
		created_at: string( item.created_at ),
		updated_at: string( item.updated_at ),
		data_mapping: item.data_mapping.map( ( row ) => {
			const pair = record( row );
			return { key: string( pair.key ), value: string( pair.value ) };
		} ),
	};
}
export function parseOptions( value: unknown ): WebhookOptions {
	const item = record( value );
	const options = ( key: string ) => {
		const values = item[ key ];
		if (
			! Array.isArray( values ) ||
			( 'triggers' !== key && ! values.length )
		) {
			throw invalidResponse();
		}
		return values.map( ( raw ) => {
			const option = record( raw );
			return {
				label: string( option.label ),
				value: string( option.value ),
			};
		} );
	};
	return {
		triggers: options( 'triggers' ),
		methods: options( 'methods' ),
		data_types: options( 'data_types' ),
		statuses: options( 'statuses' ),
	};
}
export function parseDelivery( value: unknown ): Delivery {
	const item = record( value );
	if (
		! [ 'success', 'failed' ].includes( String( item.status ) ) ||
		'number' !== typeof item.response_code
	) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		delivery_id: string( item.delivery_id ),
		status: item.status as Delivery[ 'status' ],
		response_code: item.response_code,
		error_message: string( item.error_message ),
		created_at: string( item.created_at ),
	};
}
export function cleanInput( input: WebhookInput ): WebhookInput {
	return {
		name: input.name.trim(),
		webhook_url: input.webhook_url.trim(),
		trigger_event: input.trigger_event,
		http_method: input.http_method,
		data_type: input.data_type,
		status: input.status,
		data_mapping: input.data_mapping.map( ( row ) => ( {
			key: row.key.trim(),
			value: row.value.trim(),
		} ) ),
	};
}
export function validateInput(
	input: WebhookInput,
	options: WebhookOptions
): string | null {
	const value = cleanInput( input );
	if ( ! value.name || value.name.length > 255 ) {
		return __( 'Enter a name of 1 to 255 characters.', 'ohmylms' );
	}
	try {
		const url = new URL( value.webhook_url );
		if (
			! [ 'https:', 'http:' ].includes( url.protocol ) ||
			url.username ||
			url.password ||
			value.webhook_url.length > 2048
		) {
			throw new Error();
		}
	} catch {
		return __(
			'Enter an HTTP or HTTPS endpoint without embedded credentials.',
			'ohmylms'
		);
	}
	for ( const [ key, choices ] of [
		[ 'trigger_event', options.triggers ],
		[ 'http_method', options.methods ],
		[ 'data_type', options.data_types ],
		[ 'status', options.statuses ],
	] as const ) {
		if ( ! choices.some( ( option ) => option.value === value[ key ] ) ) {
			return __(
				'Choose a supported event, method, encoding and status.',
				'ohmylms'
			);
		}
	}
	if ( value.data_mapping.length > 50 ) {
		return __( 'Use no more than 50 mapping rows.', 'ohmylms' );
	}
	const keys = new Set< string >();
	for ( const row of value.data_mapping ) {
		if (
			! /^[A-Za-z_][A-Za-z0-9_.-]{0,99}$/.test( row.key ) ||
			! /^[A-Za-z0-9_.-]{1,100}$/.test( row.value ) ||
			keys.has( row.key )
		) {
			return __(
				'Mapping rows need unique field names and valid source fields.',
				'ohmylms'
			);
		}
		keys.add( row.key );
	}
	return null;
}
export function copyMapping( rows: MappingRow[] ): MappingRow[] {
	return rows.map( ( row ) => ( { ...row } ) );
}
