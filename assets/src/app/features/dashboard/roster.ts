import { __, _n, sprintf } from '@wordpress/i18n';
import { post } from '../../lib/api';
import { invalidResponse, positiveId, record } from './model';

/** Fixed reminder templates; the server owns their text. */
export type ReminderTemplate = 'start' | 'continue' | 'complete';

/** Most students of one reminder request (the server refuses more). */
export const MAX_REMINDER_BATCH = 20;
/** Most students of one bulk unenrol request (the server refuses more). */
export const MAX_BULK = 50;

export type SkipReason =
	| 'not_enrolled'
	| 'blocked'
	| 'completed'
	| 'recently_reminded'
	| 'rate_limited'
	| 'no_email'
	| 'failed';

const SKIP_REASONS: readonly SkipReason[] = [
	'not_enrolled',
	'blocked',
	'completed',
	'recently_reminded',
	'rate_limited',
	'no_email',
	'failed',
];

export interface ReminderResult {
	sent: number[];
	skipped: { user_id: number; reason: SkipReason }[];
	remaining: number;
}

export interface BulkResult {
	results: { user_id: number; ok: boolean; code: string | null }[];
}

export interface EnrolledByEmail {
	user_id: number;
	name: string;
}

function ids( value: unknown ): number[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value.map( positiveId );
}

export function parseReminderResult( value: unknown ): ReminderResult {
	const item = record( value );
	if ( ! Array.isArray( item.skipped ) ) {
		throw invalidResponse();
	}
	const remaining = item.remaining;
	if (
		'number' !== typeof remaining ||
		! Number.isSafeInteger( remaining )
	) {
		throw invalidResponse();
	}
	return {
		sent: ids( item.sent ),
		skipped: item.skipped.map( ( entry ) => {
			const skip = record( entry );
			const reason = SKIP_REASONS.find( ( one ) => one === skip.reason );
			if ( ! reason ) {
				throw invalidResponse();
			}
			return { user_id: positiveId( skip.user_id ), reason };
		} ),
		remaining,
	};
}

export function parseBulkResult( value: unknown ): BulkResult {
	const item = record( value );
	if ( ! Array.isArray( item.results ) ) {
		throw invalidResponse();
	}
	return {
		results: item.results.map( ( entry ) => {
			const result = record( entry );
			if (
				'boolean' !== typeof result.ok ||
				( null !== result.code && 'string' !== typeof result.code )
			) {
				throw invalidResponse();
			}
			return {
				user_id: positiveId( result.user_id ),
				ok: result.ok,
				code: result.code as string | null,
			};
		} ),
	};
}

export function parseEnrolledByEmail( value: unknown ): EnrolledByEmail {
	const item = record( value );
	if ( 'string' !== typeof item.name ) {
		throw invalidResponse();
	}
	return { user_id: positiveId( item.user_id ), name: item.name };
}

const base = ( courseId: number ) =>
	`/ohmylms/v1/analytics/courses/${ positiveId( courseId ) }`;

export async function enrollByEmail(
	courseId: number,
	email: string,
	signal?: AbortSignal
) {
	return parseEnrolledByEmail(
		await post< unknown >(
			`${ base( courseId ) }/enrollments`,
			{ email: email.trim() },
			{ signal }
		)
	);
}

export async function bulkUnenroll(
	courseId: number,
	userIds: number[],
	signal?: AbortSignal
) {
	return parseBulkResult(
		await post< unknown >(
			`${ base( courseId ) }/enrollments/bulk`,
			{ action: 'unenroll', user_ids: userIds.slice( 0, MAX_BULK ) },
			{ signal }
		)
	);
}

export async function sendReminders(
	courseId: number,
	template: ReminderTemplate,
	userIds: number[],
	signal?: AbortSignal
) {
	return parseReminderResult(
		await post< unknown >(
			`${ base( courseId ) }/reminders`,
			{ template, user_ids: userIds.slice( 0, MAX_REMINDER_BATCH ) },
			{ signal }
		)
	);
}

/**
 * Labels of the reminder templates.
 *
 * @return Choices for a select control.
 */
export function getTemplateChoices(): {
	value: ReminderTemplate;
	label: string;
}[] {
	return [
		{
			value: 'continue',
			label: __( 'Reminder: continue the course', 'ohmylms' ),
		},
		{
			value: 'start',
			label: __( 'Reminder: start the course', 'ohmylms' ),
		},
		{
			value: 'complete',
			label: __( 'Reminder: finish the course', 'ohmylms' ),
		},
	];
}

/**
 * Check an e-mail address before sending it.
 *
 * @param email Typed address.
 * @return Error message, or null when it looks valid.
 */
export function validateEmail( email: string ): string | null {
	const value = email.trim();
	if ( ! value ) {
		return __( 'Enter the e-mail address of the student.', 'ohmylms' );
	}
	return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) && value.length <= 100
		? null
		: __( 'Enter a valid e-mail address.', 'ohmylms' );
}

/**
 * One sentence about a reminder request.
 *
 * @param result Server result.
 * @return Translated summary.
 */
export function describeReminderResult( result: ReminderResult ): string {
	const counts = new Map< SkipReason, number >();
	for ( const skip of result.skipped ) {
		counts.set( skip.reason, ( counts.get( skip.reason ) ?? 0 ) + 1 );
	}
	const labels: Record< SkipReason, string > = {
		not_enrolled: __( 'not enrolled', 'ohmylms' ),
		blocked: __( 'blocked', 'ohmylms' ),
		completed: __( 'already completed', 'ohmylms' ),
		recently_reminded: __( 'reminded in the last day', 'ohmylms' ),
		rate_limited: __( 'hourly limit reached', 'ohmylms' ),
		no_email: __( 'no valid e-mail address', 'ohmylms' ),
		failed: __( 'sending failed', 'ohmylms' ),
	};
	const sent = sprintf(
		/* translators: %d: number of reminders sent. */
		_n(
			'%d reminder sent.',
			'%d reminders sent.',
			result.sent.length,
			'ohmylms'
		),
		result.sent.length
	);
	if ( ! counts.size ) {
		return sent;
	}
	const skipped = Array.from( counts.entries() )
		.map( ( [ reason, total ] ) => `${ labels[ reason ] } (${ total })` )
		.join( ', ' );
	return sprintf(
		/* translators: 1: sentence about sent reminders, 2: list of skip reasons with counts. */
		__( '%1$s Skipped: %2$s.', 'ohmylms' ),
		sent,
		skipped
	);
}

/**
 * One sentence about a bulk unenrol request.
 *
 * @param result Server result.
 * @return Translated summary.
 */
export function describeBulkResult( result: BulkResult ): string {
	const done = result.results.filter( ( entry ) => entry.ok ).length;
	const failed = result.results.length - done;
	const text = sprintf(
		/* translators: %d: number of students unenrolled. */
		_n(
			'%d student unenrolled.',
			'%d students unenrolled.',
			done,
			'ohmylms'
		),
		done
	);
	return failed
		? sprintf(
				/* translators: 1: sentence about unenrolled students, 2: number of failures. */
				__( '%1$s %2$d could not be unenrolled.', 'ohmylms' ),
				text,
				failed
			)
		: text;
}
