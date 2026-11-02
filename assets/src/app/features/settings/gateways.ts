import { __, sprintf } from '@wordpress/i18n';
import { get, patch } from '../../lib/api';
import {
	asArray,
	asBoolean,
	asRecord,
	asString,
	invalidResponse,
	isRecord,
} from './model';
import type { FieldErrors } from './types';

const BASE = '/ohmylms/v1/settings/payment-gateways';
const SECRET_CHARS = /^[\x21-\x7E]+$/;
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export type GatewayMode = 'test' | 'live';
export type GatewayFieldType = 'text' | 'textarea' | 'toggle' | 'secret';
export type GatewayValue = string | boolean;

/** One setting of a gateway, as described by the gateway itself. */
export interface GatewayField {
	name: string;
	label: string;
	help: string;
	type: GatewayFieldType;
	/** Shown only in this mode; `null` for fields used in both. */
	mode: GatewayMode | null;
	maxLength: number | null;
	required: boolean;
}

/** A gateway as the server returns it. Credentials are never included, only `hasValue`. */
export interface Gateway {
	id: string;
	title: string;
	description: string;
	subscriptionSupport: boolean;
	enabled: boolean;
	/** `null` when the gateway has no test mode. */
	mode: GatewayMode | null;
	fields: GatewayField[];
	values: Record< string, GatewayValue >;
	secrets: Record< string, { hasValue: boolean } >;
}

/** What the form edits. A secret left empty keeps the stored one. */
export interface GatewayDraft {
	enabled: boolean;
	mode: GatewayMode | null;
	values: Record< string, GatewayValue >;
	secrets: Record< string, string >;
	clear: string[];
}

/** Body of a partial update; only what changed is sent. */
export interface GatewayPatch {
	enabled?: boolean;
	mode?: GatewayMode;
	values?: Record< string, GatewayValue >;
	secrets?: Record< string, string >;
	clear_secrets?: string[];
}

const TYPES: readonly GatewayFieldType[] = [
	'text',
	'textarea',
	'toggle',
	'secret',
];

function parseMode( value: unknown ): GatewayMode | null {
	if ( null === value || 'test' === value || 'live' === value ) {
		return value;
	}
	throw invalidResponse();
}

function parseField( raw: unknown ): GatewayField {
	const row = asRecord( raw );
	const type = asString( row.type ) as GatewayFieldType;
	if ( ! TYPES.includes( type ) ) {
		throw invalidResponse();
	}
	return {
		name: asString( row.name ),
		label: asString( row.label ),
		help: asString( row.help ),
		type,
		mode: parseMode( row.mode ),
		maxLength: 'number' === typeof row.max_length ? row.max_length : null,
		required: asBoolean( row.required ),
	};
}

/**
 * Validate one gateway returned by the server. A credential value that arrived anyway is dropped.
 *
 * @param raw Untrusted gateway.
 * @return Typed gateway.
 */
export function parseGateway( raw: unknown ): Gateway {
	const row = asRecord( raw );
	const fields = asArray( row.fields ).map( parseField );
	const rawValues = isRecord( row.values ) ? row.values : {};
	const rawSecrets = isRecord( row.secrets ) ? row.secrets : {};
	const values: Record< string, GatewayValue > = {};
	const secrets: Record< string, { hasValue: boolean } > = {};
	for ( const field of fields ) {
		if ( 'secret' === field.type ) {
			const secret = rawSecrets[ field.name ];
			secrets[ field.name ] = {
				hasValue: isRecord( secret ) && true === secret.has_value,
			};
		} else if ( 'toggle' === field.type ) {
			values[ field.name ] = asBoolean( rawValues[ field.name ] );
		} else {
			values[ field.name ] = asString( rawValues[ field.name ] );
		}
	}
	return {
		id: asString( row.id ),
		title: asString( row.title ),
		description: asString( row.description ),
		subscriptionSupport: asBoolean( row.subscription_support ),
		enabled: asBoolean( row.enabled ),
		mode: parseMode( row.mode ),
		fields,
		values,
		secrets,
	};
}

export function parseGatewayResponse( raw: unknown ): {
	gateway: Gateway;
	changed: string[];
} {
	return {
		gateway: parseGateway( raw ),
		changed: asArray( asRecord( raw ).changed ).map( asString ),
	};
}

export async function listGateways(
	signal?: AbortSignal
): Promise< Gateway[] > {
	return asArray( await get< unknown >( BASE, { signal } ) ).map(
		parseGateway
	);
}

/**
 * Save part of one gateway's settings.
 *
 * @param id      Gateway id.
 * @param changes Partial update (see `buildGatewayPatch`).
 * @param signal  Cancels the request.
 * @return The gateway as stored and the names that changed.
 */
export async function saveGateway(
	id: string,
	changes: GatewayPatch,
	signal?: AbortSignal
): Promise< { gateway: Gateway; changed: string[] } > {
	return parseGatewayResponse(
		await patch< unknown >(
			`${ BASE }/${ encodeURIComponent( id ) }`,
			changes,
			{ signal }
		)
	);
}

export function gatewayDraft( gateway: Gateway ): GatewayDraft {
	return {
		enabled: gateway.enabled,
		mode: gateway.mode,
		values: { ...gateway.values },
		secrets: {},
		clear: [],
	};
}

/**
 * Partial update for a draft: changed switches and fields, typed credentials and removals.
 *
 * @param gateway Stored gateway.
 * @param draft   Draft.
 * @return Body to send; empty when nothing changed.
 */
export function buildGatewayPatch(
	gateway: Gateway,
	draft: GatewayDraft
): GatewayPatch {
	const body: GatewayPatch = {};
	if ( draft.enabled !== gateway.enabled ) {
		body.enabled = draft.enabled;
	}
	if ( null !== draft.mode && draft.mode !== gateway.mode ) {
		body.mode = draft.mode;
	}
	const values: Record< string, GatewayValue > = {};
	for ( const [ name, value ] of Object.entries( draft.values ) ) {
		if ( name in gateway.values && value !== gateway.values[ name ] ) {
			values[ name ] = value;
		}
	}
	if ( Object.keys( values ).length ) {
		body.values = values;
	}
	const secrets: Record< string, string > = {};
	for ( const [ name, value ] of Object.entries( draft.secrets ) ) {
		if ( '' !== value && ! draft.clear.includes( name ) ) {
			secrets[ name ] = value;
		}
	}
	if ( Object.keys( secrets ).length ) {
		body.secrets = secrets;
	}
	const clear = draft.clear.filter(
		( name ) => gateway.secrets[ name ]?.hasValue
	);
	if ( clear.length ) {
		body.clear_secrets = clear;
	}
	return body;
}

export function gatewayDirty( gateway: Gateway, draft: GatewayDraft ): boolean {
	return Object.keys( buildGatewayPatch( gateway, draft ) ).length > 0;
}

/**
 * Validate a draft like the server; keys match the server's (`values.title`, `secrets.secret_key`).
 *
 * @param gateway Stored gateway (field definitions).
 * @param draft   Draft.
 * @return Field key to message.
 */
export function validateGateway(
	gateway: Gateway,
	draft: GatewayDraft
): FieldErrors {
	const errors: FieldErrors = {};
	for ( const field of gateway.fields ) {
		if ( 'secret' === field.type ) {
			const value = draft.secrets[ field.name ] ?? '';
			if (
				'' !== value &&
				( value.length > ( field.maxLength ?? 500 ) ||
					! SECRET_CHARS.test( value ) )
			) {
				errors[ `secrets.${ field.name }` ] = sprintf(
					/* translators: %d: maximum number of characters. */
					__(
						'Use up to %d printable characters without spaces.',
						'ohmylms'
					),
					field.maxLength ?? 500
				);
			}
			continue;
		}
		const value = draft.values[ field.name ];
		if ( 'toggle' === field.type || 'string' !== typeof value ) {
			continue;
		}
		const key = `values.${ field.name }`;
		if ( null !== field.maxLength && value.length > field.maxLength ) {
			errors[ key ] = sprintf(
				/* translators: %d: maximum number of characters. */
				__( 'Use at most %d characters.', 'ohmylms' ),
				field.maxLength
			);
		} else if ( field.required && '' === value.trim() ) {
			errors[ key ] = __( 'This field cannot be empty.', 'ohmylms' );
		} else if (
			field.name.endsWith( '_email' ) &&
			'' !== value.trim() &&
			! EMAIL.test( value.trim() )
		) {
			errors[ key ] = __( 'Enter a valid email address.', 'ohmylms' );
		}
	}
	return errors;
}

/**
 * Fields shown for the selected mode (fields of the other mode keep their stored values).
 *
 * @param gateway Gateway.
 * @param mode    Mode being edited.
 * @return Fields in gateway order.
 */
export function visibleFields(
	gateway: Gateway,
	mode: GatewayMode | null
): GatewayField[] {
	return gateway.fields.filter(
		( field ) => null === field.mode || null === mode || field.mode === mode
	);
}
