import { __ } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type { QueryParams } from '../../lib/api';
import type {
	CourseOption,
	Counter,
	Enrollment,
	EnrollmentQuery,
	SortOrder,
	Student,
	StudentOrderBy,
	StudentQuery,
} from './types';

export const PER_PAGE = 20;
export const COURSE_OPTION_LIMIT = 100;

export const EMPTY_QUERY: StudentQuery = {
	page: 1,
	search: '',
	course: null,
	orderby: 'name',
	order: 'asc',
};
export const EMPTY_ENROLLMENT_QUERY: EnrollmentQuery = {
	page: 1,
	search: '',
	progress: 'all',
};

export interface SortChoice {
	value: string;
	label: string;
	orderby: StudentOrderBy;
	order: SortOrder;
}

/**
 * Sort choices offered by the list. The `value` is what the select holds.
 *
 * @return The choices, with translated labels.
 */
export function getSortChoices(): SortChoice[] {
	return [
		{
			value: 'name:asc',
			label: __( 'Name (A to Z)', 'ohmylms' ),
			orderby: 'name',
			order: 'asc',
		},
		{
			value: 'name:desc',
			label: __( 'Name (Z to A)', 'ohmylms' ),
			orderby: 'name',
			order: 'desc',
		},
		{
			value: 'registered:desc',
			label: __( 'Newest first', 'ohmylms' ),
			orderby: 'registered',
			order: 'desc',
		},
		{
			value: 'registered:asc',
			label: __( 'Oldest first', 'ohmylms' ),
			orderby: 'registered',
			order: 'asc',
		},
		{
			value: 'courses_enrolled:desc',
			label: __( 'Most courses', 'ohmylms' ),
			orderby: 'courses_enrolled',
			order: 'desc',
		},
		{
			value: 'email:asc',
			label: __( 'Email (A to Z)', 'ohmylms' ),
			orderby: 'email',
			order: 'asc',
		},
	];
}

export function sortValue( query: StudentQuery ): string {
	return `${ query.orderby }:${ query.order }`;
}

export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid student data.', 'ohmylms' ),
	} );
}

export function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}
function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}
function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}
function flag( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
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
export function positiveId( value: unknown ): number {
	const id = count( value );
	if ( id < 1 ) {
		throw invalidResponse();
	}
	return id;
}
function counter( value: unknown ): Counter {
	const item = record( value );
	return { completed: count( item.completed ), total: count( item.total ) };
}
function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( option ) => option === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}

/**
 * Validate one student from the server. Unknown properties (such as `_links`) are dropped.
 *
 * @param value Decoded JSON.
 * @return The student.
 */
export function parseStudent( value: unknown ): Student {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		name: text( item.name ),
		email: text( item.email ),
		avatar_url: text( item.avatar_url ),
		registered: nullableText( item.registered ),
		last_login: nullableText( item.last_login ),
		blocked: flag( item.blocked ),
		enrolled_courses: count( item.enrolled_courses ),
		completed_courses: count( item.completed_courses ),
	};
}

/**
 * Validate one enrolment from the server.
 *
 * @param value Decoded JSON.
 * @return The enrolment.
 */
export function parseEnrollment( value: unknown ): Enrollment {
	const item = record( value );
	const percent = count( item.percent_complete );
	if ( percent > 100 ) {
		throw invalidResponse();
	}
	return {
		course_id: positiveId( item.course_id ),
		title: text( item.title ),
		status: oneOf( item.status, [ 'enrolled', 'banned' ] as const ),
		progress: oneOf( item.progress, [ 'running', 'completed' ] as const ),
		enrolled_at: nullableText( item.enrolled_at ),
		completed_at: nullableText( item.completed_at ),
		lessons: counter( item.lessons ),
		quizzes: counter( item.quizzes ),
		assignments: counter( item.assignments ),
		percent_complete: percent,
	};
}

export function parseCourseOption( value: unknown ): CourseOption {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		status: text( item.status ),
	};
}

/**
 * Validate the body of an unenrol response.
 *
 * @param value Decoded JSON.
 * @return The enrolment that was removed.
 */
export function parseUnenrolled( value: unknown ): Enrollment {
	const item = record( value );
	if ( true !== item.deleted ) {
		throw invalidResponse();
	}
	return parseEnrollment( item.previous );
}

/**
 * Query parameters for the student list. Blank searches and the "all courses" filter are omitted.
 *
 * @param query Current list state.
 * @return Parameters for `GET /students`.
 */
export function buildStudentQuery( query: StudentQuery ): QueryParams {
	const search = query.search.trim();
	return {
		page: query.page,
		per_page: PER_PAGE,
		orderby: query.orderby,
		order: query.order,
		...( search ? { search } : {} ),
		...( null === query.course ? {} : { course: query.course } ),
	};
}

export function buildEnrollmentQuery( query: EnrollmentQuery ): QueryParams {
	const search = query.search.trim();
	return {
		page: query.page,
		per_page: PER_PAGE,
		...( search ? { search } : {} ),
		...( 'all' === query.progress ? {} : { progress: query.progress } ),
	};
}

/**
 * Parse a student ID from a route parameter.
 *
 * @param value Raw parameter.
 * @return A positive integer, or null when the value is absent or not a plain positive integer.
 */
export function parseStudentId(
	value: string | null | undefined
): number | null {
	if ( ! value || ! /^[1-9]\d{0,14}$/.test( value ) ) {
		return null;
	}
	return Number( value );
}

/**
 * Route of a student's profile.
 *
 * @param id Student ID.
 * @return App route (the query string keeps the profile inside the single `/students` feature).
 */
export function studentPath( id: number ): string {
	return `/students?student=${ id }`;
}

/**
 * Format an ISO site-time date for display.
 *
 * @param value ISO string from the API, or null.
 * @return A localised date, or an empty string.
 */
export function formatDate( value: string | null ): string {
	if ( ! value ) {
		return '';
	}
	const date = new Date( value );
	return Number.isNaN( date.getTime() )
		? ''
		: new Intl.DateTimeFormat( undefined, { dateStyle: 'medium' } ).format(
				date
			);
}

/**
 * Validate the enrol form.
 *
 * @param courseId Selected course ID, or null.
 * @return An error message, or null when the form can be submitted.
 */
export function validateEnrollment( courseId: number | null ): string | null {
	return null === courseId || courseId < 1
		? __( 'Choose a course to enrol the student in.', 'ohmylms' )
		: null;
}

/**
 * Label for the state of an enrolment: access problems win over learning state.
 *
 * @param item Enrolment.
 * @return Translated label.
 */
export function enrollmentStatus( item: Enrollment ): string {
	if ( 'banned' === item.status ) {
		return __( 'Blocked', 'ohmylms' );
	}
	return 'completed' === item.progress
		? __( 'Completed', 'ohmylms' )
		: __( 'In progress', 'ohmylms' );
}
