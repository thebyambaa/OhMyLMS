import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import {
	TAB_IDS,
	type AcademicYear,
	type ChildLink,
	type ClassInput,
	type ClassMember,
	type ClassRole,
	type ClassStatus,
	type FormErrors,
	type GuardianInvitation,
	type Parent,
	type ParentChildren,
	type PendingLink,
	type PersonRef,
	type School,
	type SchoolClass,
	type SchoolDetail,
	type SchoolInput,
	type SchoolRef,
	type TabId,
	type Teacher,
	type YearInput,
} from './types';

/** Longest school or class name the server accepts. */
export const MAX_NAME = 190;

const CLASS_STATUSES: readonly ClassStatus[] = [ 'active', 'archived' ];
const CLASS_ROLES: readonly ClassRole[] = [ 'student', 'teacher' ];
const VERSION = /^[a-f0-9]{16}$/;
const DATE = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Error for server data that does not match the contract.
 *
 * @return A 502 `invalid_response` error.
 */
export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid account data.', 'ohmylms' ),
	} );
}

/**
 * Narrow to a plain object.
 *
 * @param value Parsed JSON.
 * @return The object.
 */
export function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}

export function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

export function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}

export function list( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value;
}

export function oneOf< T extends string >(
	value: unknown,
	allowed: readonly T[]
): T {
	const found = allowed.find( ( item ) => item === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}

export function count( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}

/**
 * Validate a positive integer identifier.
 *
 * @param value Candidate id.
 * @return The id.
 */
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

function version( value: unknown ): string {
	const found = text( value );
	if ( ! VERSION.test( found ) ) {
		throw invalidResponse();
	}
	return found;
}

/**
 * Parse a listed school.
 *
 * @param value Parsed JSON.
 * @return The school.
 */
export function parseSchool( value: unknown ): School {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		name: text( item.name ),
		slug: text( item.slug ),
		timezone: text( item.timezone ),
		status: text( item.status ),
		created_at: nullableText( item.created_at ),
		class_count: count( item.class_count ),
		student_count: count( item.student_count ),
		teacher_count: count( item.teacher_count ),
		sample: true === item.sample,
		version: version( item.version ),
	};
}

function parseYear( value: unknown ): AcademicYear {
	const item = record( value );
	const starts = text( item.starts_at );
	const ends = text( item.ends_at );
	if ( ! DATE.test( starts ) || ! DATE.test( ends ) ) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		label: text( item.label ),
		starts_at: starts,
		ends_at: ends,
		status: text( item.status ),
	};
}

/**
 * Parse one school with its academic years.
 *
 * @param value Parsed JSON.
 * @return The school.
 */
export function parseSchoolDetail( value: unknown ): SchoolDetail {
	return {
		...parseSchool( value ),
		years: list( record( value ).years ).map( parseYear ),
	};
}

/**
 * Parse a class.
 *
 * @param value Parsed JSON.
 * @return The class.
 */
export function parseClass( value: unknown ): SchoolClass {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		name: text( item.name ),
		subject: text( item.subject ),
		grade: text( item.grade ),
		status: oneOf( item.status, CLASS_STATUSES ),
		school_id: count( item.school_id ),
		school_name: nullableText( item.school_name ),
		academic_year_id: count( item.academic_year_id ),
		academic_year_label: nullableText( item.academic_year_label ),
		student_count: count( item.student_count ),
		teacher_count: count( item.teacher_count ),
		version: version( item.version ),
	};
}

/**
 * Parse a class membership.
 *
 * @param value Parsed JSON.
 * @return The member.
 */
export function parseMember( value: unknown ): ClassMember {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		user_id: positiveId( item.user_id ),
		display_name: text( item.display_name ),
		role: oneOf( item.role, CLASS_ROLES ),
		joined_at: nullableText( item.joined_at ),
	};
}

/**
 * Parse a roster person reference.
 *
 * @param value Parsed JSON.
 * @return The person.
 */
export function parsePerson( value: unknown ): PersonRef {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		display_name: text( item.display_name ),
	};
}

function parseSchoolRef( value: unknown ): SchoolRef {
	const item = record( value );
	return { id: positiveId( item.id ), name: text( item.name ) };
}

/**
 * Parse a teacher.
 *
 * @param value Parsed JSON.
 * @return The teacher.
 */
export function parseTeacher( value: unknown ): Teacher {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		display_name: text( item.display_name ),
		email: nullableText( item.email ),
		schools: list( item.schools ).map( parseSchoolRef ),
		class_count: count( item.class_count ),
	};
}

/**
 * Parse a parent.
 *
 * @param value Parsed JSON.
 * @return The parent.
 */
export function parseParent( value: unknown ): Parent {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		display_name: text( item.display_name ),
		email: nullableText( item.email ),
		child_count: count( item.child_count ),
	};
}

function parseChild( value: unknown ): ChildLink {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		school_id: positiveId( item.school_id ),
		school_name: text( item.school_name ),
		student_id: positiveId( item.student_id ),
		student_name: text( item.student_name ),
		linked_at: nullableText( item.linked_at ),
	};
}

function parsePending( value: unknown ): PendingLink {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		school_id: positiveId( item.school_id ),
		school_name: text( item.school_name ),
		student_id: count( item.student_id ),
		student_name: text( item.student_name ),
		expires_at: nullableText( item.expires_at ),
	};
}

/**
 * Parse a parent's linked children and pending invitations.
 *
 * @param value Parsed JSON.
 * @return Children and pending invitations.
 */
export function parseChildren( value: unknown ): ParentChildren {
	const item = record( value );
	return {
		children: list( item.children ).map( parseChild ),
		pending: list( item.pending ).map( parsePending ),
	};
}

/**
 * Parse a created guardian invitation.
 *
 * @param value Parsed JSON.
 * @return The invitation.
 */
export function parseInvitation( value: unknown ): GuardianInvitation {
	const item = record( value );
	const url = text( item.url );
	if (
		! /^https?:\/\//.test( url ) ||
		'boolean' !== typeof item.email_sent
	) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		school_id: positiveId( item.school_id ),
		student_id: positiveId( item.student_id ),
		url,
		expires_at: nullableText( item.expires_at ),
		email_sent: item.email_sent,
	};
}

/**
 * Require a `{ <flag>: true }` confirmation body.
 *
 * @param value Parsed JSON.
 * @param flag  Confirmation key.
 */
export function confirmed( value: unknown, flag: string ): void {
	if ( true !== record( value )[ flag ] ) {
		throw invalidResponse();
	}
}

/**
 * Selected landing tab from the `tab` query parameter.
 *
 * @param value Raw parameter.
 * @return A known tab, `schools` by default.
 */
export function tabFromParam( value: string | null ): TabId {
	return TAB_IDS.find( ( id ) => id === value ) ?? 'schools';
}

function nameError( value: string ): string | undefined {
	const length = Array.from( value.trim() ).length;
	if ( 0 === length || length > MAX_NAME ) {
		return sprintf(
			/* translators: %d: maximum name length. */
			__( 'Enter a name of 1 to %d characters.', 'ohmylms' ),
			MAX_NAME
		);
	}
	return undefined;
}

/**
 * Validate a school form before it is sent.
 *
 * @param input Form values.
 * @return Field errors; empty when valid.
 */
export function validateSchool(
	input: SchoolInput
): FormErrors< SchoolInput > {
	const errors: FormErrors< SchoolInput > = {};
	const name = nameError( input.name );
	if ( name ) {
		errors.name = name;
	}
	const zone = input.timezone.trim();
	if ( ! zone || zone.length > 80 || /\s/.test( zone ) ) {
		errors.timezone = __(
			'Enter an IANA time zone, such as Asia/Ulaanbaatar or UTC.',
			'ohmylms'
		);
	}
	return errors;
}

/**
 * Validate a class form before it is sent.
 *
 * @param input Form values.
 * @return Field errors; empty when valid.
 */
export function validateClass( input: ClassInput ): FormErrors< ClassInput > {
	const errors: FormErrors< ClassInput > = {};
	const name = nameError( input.name );
	if ( name ) {
		errors.name = name;
	}
	if ( input.subject.trim().length > 100 ) {
		errors.subject = __(
			'Use at most 100 characters for the subject.',
			'ohmylms'
		);
	}
	if ( input.grade.trim().length > 50 ) {
		errors.grade = __(
			'Use at most 50 characters for the grade.',
			'ohmylms'
		);
	}
	return errors;
}

/**
 * Whether a value is a real `YYYY-MM-DD` date.
 *
 * @param value Candidate.
 * @return True for a real calendar date.
 */
export function isCalendarDate( value: string ): boolean {
	if ( ! DATE.test( value ) ) {
		return false;
	}
	const [ year, month, day ] = value.split( '-' ).map( Number ) as [
		number,
		number,
		number,
	];
	const date = new Date( Date.UTC( year, month - 1, day ) );
	return (
		date.getUTCFullYear() === year &&
		date.getUTCMonth() === month - 1 &&
		date.getUTCDate() === day
	);
}

/**
 * Validate an academic year form.
 *
 * @param input Form values.
 * @return Field errors; empty when valid.
 */
export function validateYear( input: YearInput ): FormErrors< YearInput > {
	const errors: FormErrors< YearInput > = {};
	const label = input.label.trim();
	if ( ! label || label.length > 100 ) {
		errors.label = __( 'Enter a label of 1 to 100 characters.', 'ohmylms' );
	}
	if ( ! isCalendarDate( input.starts_at ) ) {
		errors.starts_at = __( 'Enter a real start date.', 'ohmylms' );
	}
	if ( ! isCalendarDate( input.ends_at ) ) {
		errors.ends_at = __( 'Enter a real end date.', 'ohmylms' );
	} else if ( ! errors.starts_at && input.ends_at <= input.starts_at ) {
		errors.ends_at = __(
			'The end date must be after the start date.',
			'ohmylms'
		);
	}
	return errors;
}

/**
 * Translated class role.
 *
 * @param role Role.
 * @return Label.
 */
export function roleLabel( role: ClassRole ): string {
	return 'teacher' === role
		? __( 'Teacher', 'ohmylms' )
		: __( 'Student', 'ohmylms' );
}

/**
 * Translated class status.
 *
 * @param status Status.
 * @return Label.
 */
export function classStatusLabel( status: ClassStatus ): string {
	return 'archived' === status
		? __( 'Archived', 'ohmylms' )
		: __( 'Active', 'ohmylms' );
}

/**
 * Whether an error is a stale-save conflict.
 *
 * @param error Thrown value.
 * @return True for 409 `ohmylms_account_hub_conflict`.
 */
export function isConflict( error: unknown ): boolean {
	return (
		error instanceof ApiError &&
		409 === error.status &&
		'ohmylms_account_hub_conflict' === error.code
	);
}
