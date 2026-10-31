import { invalidResponse, record } from '../model';

export type AttentionTone = 'error' | 'warning' | 'info';

export interface AttentionItem {
	/** Stable key, such as `grading` or `stalling_course:12`; snoozes use it. */
	readonly key: string;
	readonly urgency: number;
	readonly tone: AttentionTone;
	readonly title: string;
	readonly detail: string;
	readonly count: number;
	/** Internal app route the user may open, or null. */
	readonly action: { readonly label: string; readonly route: string } | null;
}

export interface NeedsAttention {
	readonly items: readonly AttentionItem[];
	/** Items of this scope the user snoozed. */
	readonly snoozed: number;
	/** Days a submission may wait before it is listed. */
	readonly gradingDays: number;
}

const TONES: readonly AttentionTone[] = [ 'error', 'warning', 'info' ];

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function integer( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isSafeInteger( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function parseItem( value: unknown ): AttentionItem {
	const item = record( value );
	const tone = TONES.find( ( option ) => option === item.tone );
	const key = text( item.key );
	if ( ! tone || ! /^[a-z][a-z0-9_]*(:\d+)?$/.test( key ) ) {
		throw invalidResponse();
	}
	let action: AttentionItem[ 'action' ] = null;
	if ( null !== item.action && undefined !== item.action ) {
		const raw = record( item.action );
		const route = text( raw.route );
		if ( ! route.startsWith( '/' ) ) {
			throw invalidResponse();
		}
		action = { label: text( raw.label ), route };
	}
	return {
		key,
		urgency: integer( item.urgency ),
		tone,
		title: text( item.title ),
		detail: text( item.detail ),
		count: integer( item.count ),
		action,
	};
}

/**
 * Validate the `needs-attention` widget data.
 *
 * @param value Decoded `data`.
 * @return The queue.
 */
export function parseNeedsAttention( value: unknown ): NeedsAttention {
	const data = record( value );
	if ( ! Array.isArray( data.items ) ) {
		throw invalidResponse();
	}
	return {
		items: data.items.map( parseItem ),
		snoozed: integer( data.snoozed ),
		gradingDays: integer( data.grading_days ),
	};
}
