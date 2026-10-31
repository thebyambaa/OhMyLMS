import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../../lib/api';

export const COHORT_PER_PAGE = 20;
export const COHORT_TITLE_MAX = 190;
export const COHORT_CAPACITY_MAX = 100000;

export type CohortScope = 'course' | 'membership';
export type CohortStatus = 'active' | 'archived';
export type CohortState = 'upcoming' | 'running' | 'ended';

/** A cohort as `GET /ohmylms/v1/cohorts` returns it. Dates are site time without offset. */
export interface Cohort {
	id: number;
	title: string;
	scope: CohortScope;
	course_id: number;
	membership_id: number;
	target_title: string;
	start_date: string | null;
	end_date: string | null;
	enrollment_start: string | null;
	enrollment_end: string | null;
	capacity: number | null;
	member_count: number;
	seats_left: number | null;
	status: CohortStatus;
	state: CohortState;
	enrollment_open: boolean;
	close_access_at_end: boolean;
}

export interface CohortMember {
	user_id: number;
	name: string;
	login: string;
	source: string;
	joined_at: string | null;
}

export interface Choice {
	id: number;
	title: string;
}

export interface Candidate {
	user_id: number;
	name: string;
	login: string;
}

/** The editor's fields; dates are `YYYY-MM-DDTHH:MM` (datetime-local) or empty. */
export interface CohortDraft {
	title: string;
	scope: CohortScope;
	targetId: number;
	targetTitle: string;
	start: string;
	end: string;
	enrollmentStart: string;
	enrollmentEnd: string;
	capacity: string;
	status: CohortStatus;
	closeAccessAtEnd: boolean;
}

export function invalidCohortResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid cohort data.', 'ohmylms' ),
	} );
}

function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidCohortResponse();
	}
	return value as Record< string, unknown >;
}
function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidCohortResponse();
	}
	return value;
}
function nullableText( value: unknown ): string | null {
	return null === value || undefined === value ? null : text( value );
}
function int( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isSafeInteger( value ) ) {
		throw invalidCohortResponse();
	}
	return value;
}
function nullableInt( value: unknown ): number | null {
	return null === value ? null : int( value );
}
function flag( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidCohortResponse();
	}
	return value;
}
function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( option ) => option === value );
	if ( undefined === found ) {
		throw invalidCohortResponse();
	}
	return found;
}

export function parseCohort( value: unknown ): Cohort {
	const row = record( value );
	return {
		id: int( row.id ),
		title: text( row.title ),
		scope: oneOf( row.scope, [ 'course', 'membership' ] as const ),
		course_id: int( row.course_id ),
		membership_id: int( row.membership_id ),
		target_title: text( row.target_title ),
		start_date: nullableText( row.start_date ),
		end_date: nullableText( row.end_date ),
		enrollment_start: nullableText( row.enrollment_start ),
		enrollment_end: nullableText( row.enrollment_end ),
		capacity: nullableInt( row.capacity ),
		member_count: int( row.member_count ),
		seats_left: nullableInt( row.seats_left ),
		status: oneOf( row.status, [ 'active', 'archived' ] as const ),
		state: oneOf( row.state, [ 'upcoming', 'running', 'ended' ] as const ),
		enrollment_open: flag( row.enrollment_open ),
		close_access_at_end: flag( row.close_access_at_end ),
	};
}

export function parseMember( value: unknown ): CohortMember {
	const row = record( value );
	return {
		user_id: int( row.user_id ),
		name: text( row.name ),
		login: text( row.login ),
		source: text( row.source ),
		joined_at: nullableText( row.joined_at ),
	};
}

export function parseChoice( value: unknown ): Choice {
	const row = record( value );
	return { id: int( row.id ), title: text( row.title ) };
}

export function parseCandidate( value: unknown ): Candidate {
	const row = record( value );
	return {
		user_id: int( row.user_id ),
		name: text( row.name ),
		login: text( row.login ),
	};
}

export function parseSync( value: unknown ): {
	added: number;
	full: boolean;
	cohort: Cohort;
} {
	const row = record( value );
	return {
		added: int( row.added ),
		full: flag( row.full ),
		cohort: parseCohort( row.cohort ),
	};
}

/**
 * Server date (`YYYY-MM-DDTHH:MM:SS`) to a datetime-local value.
 * @param value
 */
function toInput( value: string | null ): string {
	return value ? value.slice( 0, 16 ) : '';
}

export function emptyCohortDraft(): CohortDraft {
	return {
		title: '',
		scope: 'course',
		targetId: 0,
		targetTitle: '',
		start: '',
		end: '',
		enrollmentStart: '',
		enrollmentEnd: '',
		capacity: '',
		status: 'active',
		closeAccessAtEnd: false,
	};
}

export function cohortDraft( cohort: Cohort ): CohortDraft {
	return {
		title: cohort.title,
		scope: cohort.scope,
		targetId:
			'course' === cohort.scope ? cohort.course_id : cohort.membership_id,
		targetTitle: cohort.target_title,
		start: toInput( cohort.start_date ),
		end: toInput( cohort.end_date ),
		enrollmentStart: toInput( cohort.enrollment_start ),
		enrollmentEnd: toInput( cohort.enrollment_end ),
		capacity: null === cohort.capacity ? '' : String( cohort.capacity ),
		status: cohort.status,
		closeAccessAtEnd: cohort.close_access_at_end,
	};
}

/**
 * Validate a draft like the server. Keys match the server's `data.params`.
 *
 * @param draft Draft.
 * @return Field to message.
 */
export function validateCohort( draft: CohortDraft ): Record< string, string > {
	const errors: Record< string, string > = {};
	const title = draft.title.trim();
	if ( ! title || Array.from( title ).length > COHORT_TITLE_MAX ) {
		errors.title = sprintf(
			/* translators: %d: maximum length. */
			__( 'Enter a title of 1 to %d characters.', 'ohmylms' ),
			COHORT_TITLE_MAX
		);
	}
	if ( draft.targetId < 1 ) {
		errors[ 'course' === draft.scope ? 'course_id' : 'membership_id' ] =
			'course' === draft.scope
				? __( 'Choose a course.', 'ohmylms' )
				: __( 'Choose a membership plan.', 'ohmylms' );
	}
	if ( ! draft.start ) {
		errors.start_date = __( 'Enter the start date.', 'ohmylms' );
	}
	if ( draft.end && draft.start && draft.end <= draft.start ) {
		errors.end_date = __( 'The end must be after the start.', 'ohmylms' );
	}
	if ( draft.enrollmentEnd && draft.end && draft.enrollmentEnd > draft.end ) {
		errors.enrollment_end = __(
			'Enrolment must close before the cohort ends.',
			'ohmylms'
		);
	}
	if (
		draft.enrollmentStart &&
		draft.enrollmentEnd &&
		draft.enrollmentStart >= draft.enrollmentEnd
	) {
		errors.enrollment_start = __(
			'Enrolment must open before it closes.',
			'ohmylms'
		);
	}
	if ( '' !== draft.capacity.trim() ) {
		const capacity = Number( draft.capacity );
		if (
			! Number.isInteger( capacity ) ||
			capacity < 1 ||
			capacity > COHORT_CAPACITY_MAX
		) {
			errors.capacity = sprintf(
				/* translators: %d: maximum capacity. */
				__(
					'Enter a capacity from 1 to %d, or leave it empty for unlimited seats.',
					'ohmylms'
				),
				COHORT_CAPACITY_MAX
			);
		}
	}
	return errors;
}

/**
 * Request body for a create (`current` null) or the changed fields of an update.
 *
 * @param draft   Draft.
 * @param current Stored cohort when editing.
 * @return JSON body.
 */
export function cohortBody(
	draft: CohortDraft,
	current: Cohort | null = null
): Record< string, unknown > {
	const full: Record< string, unknown > = {
		title: draft.title.trim(),
		start_date: draft.start || null,
		end_date: draft.end || null,
		enrollment_start: draft.enrollmentStart || null,
		enrollment_end: draft.enrollmentEnd || null,
		capacity:
			'' === draft.capacity.trim() ? null : Number( draft.capacity ),
		status: draft.status,
		close_access_at_end: draft.closeAccessAtEnd,
	};
	if ( ! current ) {
		return {
			scope: draft.scope,
			[ 'course' === draft.scope ? 'course_id' : 'membership_id' ]:
				draft.targetId,
			...full,
		};
	}
	const before = cohortBody( cohortDraft( current ), null );
	const changes: Record< string, unknown > = {};
	for ( const [ key, value ] of Object.entries( full ) ) {
		if ( before[ key ] !== value ) {
			changes[ key ] = value;
		}
	}
	return changes;
}

/**
 * Plain label of a server date.
 *
 * @param value ISO date-time or null.
 * @return `YYYY-MM-DD HH:MM`, or an em dash.
 */
export function cohortDate( value: string | null ): string {
	return value ? value.slice( 0, 16 ).replace( 'T', ' ' ) : '—';
}

export function stateLabel( cohort: Cohort ): string {
	if ( 'archived' === cohort.status ) {
		return __( 'Archived', 'ohmylms' );
	}
	switch ( cohort.state ) {
		case 'running':
			return __( 'Running', 'ohmylms' );
		case 'ended':
			return __( 'Ended', 'ohmylms' );
		default:
			return __( 'Upcoming', 'ohmylms' );
	}
}

export function seatsLabel( cohort: Cohort ): string {
	return null === cohort.capacity
		? sprintf(
				/* translators: %d: members. */
				__( '%d (no limit)', 'ohmylms' ),
				cohort.member_count
			)
		: sprintf(
				/* translators: 1: members, 2: capacity. */
				__( '%1$d of %2$d', 'ohmylms' ),
				cohort.member_count,
				cohort.capacity
			);
}

export function sourceLabel( source: string ): string {
	switch ( source ) {
		case 'purchase':
			return __( 'Purchase', 'ohmylms' );
		case 'enrolment':
			return __( 'Enrolment', 'ohmylms' );
		case 'membership':
			return __( 'Plan purchase', 'ohmylms' );
		case 'automation':
			return __( 'Automation', 'ohmylms' );
		case 'sync':
			return __( 'Window sync', 'ohmylms' );
		default:
			return __( 'Added by hand', 'ohmylms' );
	}
}
