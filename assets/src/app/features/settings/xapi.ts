import { __ } from '@wordpress/i18n';
import { get, post } from '../../lib/api';
import {
	asArray,
	asBoolean,
	asNumber,
	asRecord,
	asString,
	invalidResponse,
	isRecord,
} from './model';
import type { FieldErrors } from './types';

const PATH = '/ohmylms/v1/xapi/settings';

export type XapiActor = 'pseudonym' | 'user_id' | 'mbox';
export const XAPI_ACTORS: readonly XapiActor[] = [
	'pseudonym',
	'user_id',
	'mbox',
];
export type XapiAction = 'test' | 'send' | 'retry';

export interface XapiSettings {
	enabled: boolean;
	endpoint: string;
	key: string;
	actor: XapiActor;
	responses: boolean;
	retention_days: number;
	groups: Record< string, boolean >;
}

export interface XapiFailure {
	id: string;
	kind: string;
	tries: number;
	lastError: string;
	createdAt: string;
}

/** `GET /xapi/settings`; the LRS secret is never included, only `hasSecret`. */
export interface XapiSnapshot {
	settings: XapiSettings;
	hasSecret: boolean;
	locked: { endpoint: boolean; key: boolean; secret: boolean };
	groupLabels: Record< string, string >;
	counts: { pending: number; sent: number; failed: number; skipped: number };
	lastError: string;
	failures: XapiFailure[];
}

export interface XapiDraft extends XapiSettings {
	secret: string;
	clearSecret: boolean;
}

// The option stores switches as 0/1.
function flag( value: unknown ): boolean {
	if ( 'boolean' === typeof value ) {
		return value;
	}
	if ( 0 === value || 1 === value || '0' === value || '1' === value ) {
		return 1 === Number( value );
	}
	throw invalidResponse();
}

function count( value: unknown ): number {
	const number = 'string' === typeof value ? Number( value ) : value;
	return asNumber( number );
}

function parseActor( value: unknown ): XapiActor {
	const actor = asString( value ) as XapiActor;
	return XAPI_ACTORS.includes( actor ) ? actor : 'pseudonym';
}

export function parseXapi( raw: unknown ): XapiSnapshot {
	const body = asRecord( raw );
	const settings = asRecord( body.settings );
	const labels = asRecord( body.group_labels );
	const groups = isRecord( settings.groups ) ? settings.groups : {};
	const locked = asRecord( body.locked );
	const counts = asRecord( body.counts );
	const health = isRecord( body.health ) ? body.health : {};
	const groupLabels: Record< string, string > = {};
	const groupValues: Record< string, boolean > = {};
	for ( const [ key, label ] of Object.entries( labels ) ) {
		groupLabels[ key ] = asString( label );
		groupValues[ key ] = key in groups ? flag( groups[ key ] ) : true;
	}
	return {
		settings: {
			enabled: flag( settings.enabled ),
			endpoint: asString( settings.endpoint ),
			key: asString( settings.key ),
			actor: parseActor( settings.actor ),
			responses: flag( settings.responses ),
			retention_days: count( settings.retention_days ),
			groups: groupValues,
		},
		hasSecret: asBoolean( body.has_secret ),
		locked: {
			endpoint: asBoolean( locked.endpoint ),
			key: asBoolean( locked.key ),
			secret: asBoolean( locked.secret ),
		},
		groupLabels,
		counts: {
			pending: count( counts.pending ),
			sent: count( counts.sent ),
			failed: count( counts.failed ),
			skipped: count( counts.skipped ),
		},
		lastError:
			'string' === typeof health.last_error ? health.last_error : '',
		failures: asArray( body.failures ).map( ( item ) => {
			const row = asRecord( item );
			return {
				id: String( row.id ),
				kind: asString( row.kind ),
				tries: count( row.tries ),
				lastError:
					'string' === typeof row.last_error ? row.last_error : '',
				createdAt: asString( row.created_at ),
			};
		} ),
	};
}

export async function getXapi( signal?: AbortSignal ): Promise< XapiSnapshot > {
	return parseXapi( await get< unknown >( PATH, { signal } ) );
}

/**
 * Full body for `POST /xapi/settings` (every required field). An empty secret is not sent, so the
 * stored one is kept.
 *
 * @param draft Draft.
 * @return Request body.
 */
export function buildXapiBody( draft: XapiDraft ): Record< string, unknown > {
	const body: Record< string, unknown > = {
		enabled: draft.enabled,
		endpoint: draft.endpoint.trim(),
		key: draft.key.trim(),
		actor: draft.actor,
		responses: draft.responses,
		retention_days: draft.retention_days,
		groups: { ...draft.groups },
	};
	if ( draft.clearSecret ) {
		body.clear_secret = true;
	} else if ( '' !== draft.secret ) {
		body.secret = draft.secret;
	}
	return body;
}

export async function saveXapi(
	draft: XapiDraft,
	signal?: AbortSignal
): Promise< XapiSnapshot > {
	return parseXapi(
		await post< unknown >( PATH, buildXapiBody( draft ), { signal } )
	);
}

/**
 * Test the connection, send queued statements now or retry failures.
 *
 * @param action Action.
 * @param signal Cancels the request.
 * @return Outcome and the refreshed snapshot.
 */
export async function runXapiAction(
	action: XapiAction,
	signal?: AbortSignal
): Promise< { ok: boolean; message: string; snapshot: XapiSnapshot } > {
	const body = asRecord(
		await post< unknown >( `${ PATH }/${ action }`, undefined, { signal } )
	);
	return {
		ok: asBoolean( body.ok ),
		message: asString( body.message ),
		snapshot: parseXapi( body.snapshot ),
	};
}

export function xapiDraft( snapshot: XapiSnapshot ): XapiDraft {
	return {
		...snapshot.settings,
		groups: { ...snapshot.settings.groups },
		secret: '',
		clearSecret: false,
	};
}

export function xapiDirty( snapshot: XapiSnapshot, draft: XapiDraft ): boolean {
	const saved = snapshot.settings;
	return (
		'' !== draft.secret ||
		draft.clearSecret ||
		saved.enabled !== draft.enabled ||
		saved.endpoint !== draft.endpoint ||
		saved.key !== draft.key ||
		saved.actor !== draft.actor ||
		saved.responses !== draft.responses ||
		saved.retention_days !== draft.retention_days ||
		Object.keys( saved.groups ).some(
			( group ) => saved.groups[ group ] !== draft.groups[ group ]
		)
	);
}

function isHttpUrl( value: string ): boolean {
	try {
		const url = new URL( value );
		return (
			( 'http:' === url.protocol || 'https:' === url.protocol ) &&
			'' === url.username &&
			'' === url.password
		);
	} catch {
		return false;
	}
}

export function validateXapi( draft: XapiDraft ): FieldErrors {
	const errors: FieldErrors = {};
	const endpoint = draft.endpoint.trim();
	if ( '' !== endpoint && ! isHttpUrl( endpoint ) ) {
		errors.endpoint = __(
			'Enter a valid HTTP or HTTPS LRS endpoint.',
			'ohmylms'
		);
	}
	if ( draft.enabled && '' === endpoint ) {
		errors.endpoint = __(
			'Enter the LRS endpoint before turning delivery on.',
			'ohmylms'
		);
	}
	if (
		! Number.isInteger( draft.retention_days ) ||
		draft.retention_days < 1 ||
		draft.retention_days > 365
	) {
		errors.retention_days = __(
			'Enter a whole number of days from 1 to 365.',
			'ohmylms'
		);
	}
	if ( draft.key.length > 200 ) {
		errors.key = __( 'Use at most 200 characters.', 'ohmylms' );
	}
	if ( /\s/.test( draft.secret ) || draft.secret.length > 500 ) {
		errors.secret = __(
			'Use up to 500 printable characters without spaces.',
			'ohmylms'
		);
	}
	return errors;
}
