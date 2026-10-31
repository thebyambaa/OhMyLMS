import { __ } from '@wordpress/i18n';
import { invalidResponse, record } from '../model';

export interface ActivityEvent {
	/** Unique per event, such as `quiz_passed:12`. */
	readonly id: string;
	/** enrolled, course_completed, lesson_completed, quiz_passed, quiz_failed, quiz_completed, assignment_submitted or order. */
	readonly type: string;
	/** ISO 8601 with the site offset. */
	readonly at: string;
	/** Server-translated sentence. */
	readonly text: string;
}

export interface Activity {
	readonly events: readonly ActivityEvent[];
	readonly limit: number;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function parseEvent( value: unknown ): ActivityEvent {
	const item = record( value );
	return {
		id: text( item.id ),
		type: text( item.type ),
		at: text( item.at ),
		text: text( item.text ),
	};
}

/**
 * Validate the `activity` widget data.
 *
 * @param value Decoded `data`.
 * @return The events, newest first.
 */
export function parseActivity( value: unknown ): Activity {
	const data = record( value );
	if (
		! Array.isArray( data.events ) ||
		'number' !== typeof data.limit ||
		! Number.isSafeInteger( data.limit )
	) {
		throw invalidResponse();
	}
	return { events: data.events.map( parseEvent ), limit: data.limit };
}

const UNITS: readonly [ Intl.RelativeTimeFormatUnit, number ][] = [
	[ 'day', 86400 ],
	[ 'hour', 3600 ],
	[ 'minute', 60 ],
];

/**
 * How long ago an event happened, such as `5 minutes ago`; `just now` under a minute.
 *
 * @param at     ISO time of the event.
 * @param now    Current time in milliseconds.
 * @param locale BCP 47 locale; the browser locale when omitted.
 * @return Localised text, or '' for an unreadable time.
 */
export function timeAgo(
	at: string,
	now: number = Date.now(),
	locale?: string
): string {
	const time = Date.parse( at );
	if ( Number.isNaN( time ) ) {
		return '';
	}
	const seconds = Math.max( 0, Math.round( ( now - time ) / 1000 ) );
	const format = new Intl.RelativeTimeFormat( locale, {
		numeric: 'auto',
	} );
	for ( const [ unit, size ] of UNITS ) {
		if ( seconds >= size ) {
			return format.format( -Math.floor( seconds / size ), unit );
		}
	}
	return __( 'just now', 'ohmylms' );
}
