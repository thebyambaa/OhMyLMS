import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type {
	Assignment,
	AssignmentDetail,
	AssignmentInput,
	AssignmentSettings,
	AssignmentStatus,
	Attachment,
	CommentThread,
	CourseChoice,
	CourseRef,
	DeliverySettings,
	Drip,
	DripType,
	FormErrors,
	FormState,
	Placement,
	PrerequisiteItem,
	PrerequisiteOptions,
	PrerequisiteType,
	Resource,
	Submission,
	SubmissionComment,
	SubmissionDetail,
	SubmissionResult,
	SubmissionStatus,
	TimeUnit,
	WritableStatus,
} from './types';

/** Release schedules the server accepts. */
export const DRIP_TYPES: readonly DripType[] = [
	'enrollment-from-x-days',
	'specific-date',
	'cohort-start',
	'cohort-from-x-days',
];
const PREREQUISITE_TYPES: readonly PrerequisiteType[] = [
	'lesson',
	'quiz',
	'assignment',
	'other',
];
const RESOURCE_TYPES: readonly Resource[ 'type' ][] = [ 'file', 'link' ];
const ROLES: readonly SubmissionComment[ 'role' ][] = [
	'instructor',
	'learner',
];

const STATUSES: readonly AssignmentStatus[] = [ 'draft', 'publish', 'trash' ];
const WRITABLE: readonly WritableStatus[] = [ 'draft', 'publish' ];
const SUBMISSION_STATUSES: readonly SubmissionStatus[] = [
	'submitted',
	'graded',
];
const RESULTS: readonly SubmissionResult[] = [ 'passed', 'failed' ];
const SOURCES: readonly Placement[ 'source' ][] = [ 'chapter', 'program' ];

/** Deadline units the server accepts. */
export const TIME_UNITS: readonly TimeUnit[] = [ 'day', 'week', 'month' ];

/** Limits shared with the server (`AssignmentsController` in PHP). */
export const LIMITS = {
	title: 200,
	maxScore: 1000,
	timeLimit: 365,
	maxFiles: 20,
	maxFileSizeMb: 1024,
	feedback: 10000,
	resources: 20,
	resourceName: 200,
	resourceSize: 50,
	prerequisites: 50,
	dripDays: 3650,
	comment: 5000,
} as const;

/**
 * Build the error thrown when the server answers with data that does not match the contract.
 *
 * @return A 502 `invalid_response` error.
 */
export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __(
			'The server returned invalid assignment data.',
			'ohmylms'
		),
	} );
}

/**
 * Narrow an unknown value to a plain object or fail with an invalid response.
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

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function boolean( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function count( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}

function list( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( item ) => item === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}

function nullable< T >( value: unknown, parse: ( item: unknown ) => T ) {
	return null === value ? null : parse( value );
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

function courseRef( value: unknown ): CourseRef {
	const item = record( value );
	return { id: positiveId( item.id ), title: text( item.title ) };
}

/**
 * Validate a course offered by the list filter.
 *
 * @param value Parsed JSON.
 * @return The course.
 */
export function parseCourseChoice( value: unknown ): CourseChoice {
	const item = record( value );
	return { ...courseRef( item ), status: text( item.status ) };
}

function settings( item: Record< string, unknown > ): AssignmentSettings {
	return {
		max_score: count( item.max_score ),
		pass_score: count( item.pass_score ),
		time_limit_enabled: boolean( item.time_limit_enabled ),
		time_limit: count( item.time_limit ),
		time_limit_unit: oneOf( item.time_limit_unit, TIME_UNITS ),
		allow_file_uploads: boolean( item.allow_file_uploads ),
		max_files: count( item.max_files ),
		file_size_limit_enabled: boolean( item.file_size_limit_enabled ),
		max_file_size_mb: count( item.max_file_size_mb ),
	};
}

/**
 * Validate a listed assignment.
 *
 * @param value Parsed JSON.
 * @return The assignment.
 */
export function parseAssignment( value: unknown ): Assignment {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		slug: text( item.slug ),
		status: oneOf( item.status, STATUSES ),
		permalink: text( item.permalink ),
		author_id: count( item.author_id ),
		author_name: text( item.author_name ),
		course: nullable( item.course, courseRef ),
		...settings( item ),
		submission_count: count( item.submission_count ),
		pending_count: count( item.pending_count ),
		created_at: text( item.created_at ),
		updated_at: text( item.updated_at ),
		can_edit: boolean( item.can_edit ),
		can_delete: boolean( item.can_delete ),
	};
}

function placement( value: unknown ): Placement {
	const item = record( value );
	return {
		course_id: positiveId( item.course_id ),
		course_title: text( item.course_title ),
		chapter_id: nullable( item.chapter_id, positiveId ),
		chapter_title: nullable( item.chapter_title, text ),
		source: oneOf( item.source, SOURCES ),
	};
}

function resource( value: unknown ): Resource {
	const item = record( value );
	return {
		type: oneOf( item.type, RESOURCE_TYPES ),
		attachment_id: count( item.attachment_id ),
		name: text( item.name ),
		url: text( item.url ),
		size: text( item.size ),
	};
}

function prerequisiteItem( value: unknown ): PrerequisiteItem {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		type: oneOf( item.type, PREREQUISITE_TYPES ),
		status: text( item.status ),
	};
}

function drip( value: unknown ): Drip {
	const item = record( value );
	return {
		enable: boolean( item.enable ),
		type: oneOf( item.type, DRIP_TYPES ),
		days: count( item.days ),
		date: text( item.date ),
		time: text( item.time ),
	};
}

function delivery( item: Record< string, unknown > ): DeliverySettings {
	const prerequisites = record( item.prerequisites );
	return {
		resources: list( item.resources ).map( resource ),
		prerequisites: {
			enable: boolean( prerequisites.enable ),
			items: list( prerequisites.items ).map( prerequisiteItem ),
		},
		drip: drip( item.drip ),
		comments_enabled: boolean( item.comments_enabled ),
	};
}

/**
 * Validate an assignment read in edit context.
 *
 * @param value Parsed JSON.
 * @return The assignment with instructions, placements and delivery settings.
 */
export function parseAssignmentDetail( value: unknown ): AssignmentDetail {
	const item = record( value );
	return {
		...parseAssignment( item ),
		instructions: text( item.instructions ),
		placements: list( item.placements ).map( placement ),
		...delivery( item ),
	};
}

/**
 * Validate a page of prerequisite choices.
 *
 * @param value Parsed JSON.
 * @return The choices.
 */
export function parsePrerequisiteOptions(
	value: unknown
): PrerequisiteOptions {
	const item = record( value );
	return {
		items: list( item.items ).map( prerequisiteItem ),
		total: count( item.total ),
		pages: count( item.pages ),
		scope: oneOf( item.scope, [ 'course', 'library' ] as const ),
	};
}

/**
 * Validate one comment of a submission thread.
 *
 * @param value Parsed JSON.
 * @return The comment.
 */
export function parseComment( value: unknown ): SubmissionComment {
	const item = record( value );
	const author = record( item.author );
	return {
		id: positiveId( item.id ),
		submission_id: positiveId( item.submission_id ),
		author: {
			id: count( author.id ),
			display_name: text( author.display_name ),
		},
		role: oneOf( item.role, ROLES ),
		body: text( item.body ),
		created_at: text( item.created_at ),
		can_delete: boolean( item.can_delete ),
	};
}

/**
 * Validate a submission thread.
 *
 * @param value Parsed JSON.
 * @return The thread.
 */
export function parseThread( value: unknown ): CommentThread {
	const item = record( value );
	return {
		items: list( item.items ).map( parseComment ),
		total: count( item.total ),
		role: oneOf( item.role, ROLES ),
		comments_enabled: boolean( item.comments_enabled ),
	};
}

/**
 * Validate a listed submission.
 *
 * @param value Parsed JSON.
 * @return The submission.
 */
export function parseSubmission( value: unknown ): Submission {
	const item = record( value );
	const student = record( item.student );
	const status = oneOf( item.status, SUBMISSION_STATUSES );
	const score = nullable( item.score, count );
	if ( ( 'graded' === status ) !== ( null !== score ) ) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		assignment_id: positiveId( item.assignment_id ),
		student: {
			id: count( student.id ),
			display_name: text( student.display_name ),
		},
		course: nullable( item.course, courseRef ),
		status,
		result: nullable( item.result, ( result ) => oneOf( result, RESULTS ) ),
		score,
		max_score: count( item.max_score ),
		pass_score: count( item.pass_score ),
		submitted_at: text( item.submitted_at ),
		graded_at: nullable( item.graded_at, text ),
		attachment_count: count( item.attachment_count ),
	};
}

function attachment( value: unknown ): Attachment {
	const item = record( value );
	const url = text( item.url );
	if ( ! /^https?:\/\//i.test( url ) ) {
		throw invalidResponse();
	}
	return {
		name: text( item.name ),
		type: text( item.type ),
		size: count( item.size ),
		url,
	};
}

/**
 * Validate a submission with its text, feedback and attachments.
 *
 * @param value Parsed JSON.
 * @return The submission.
 */
export function parseSubmissionDetail( value: unknown ): SubmissionDetail {
	const item = record( value );
	return {
		...parseSubmission( item ),
		content: text( item.content ),
		feedback: text( item.feedback ),
		attachments: list( item.attachments ).map( attachment ),
	};
}

/**
 * Start state of the editor.
 *
 * @param assignment Loaded assignment.
 * @return The form.
 */
export function formFromAssignment( assignment: AssignmentDetail ): FormState {
	return {
		title: assignment.title,
		instructions: assignment.instructions,
		status: 'publish' === assignment.status ? 'publish' : 'draft',
		max_score: String( assignment.max_score ),
		pass_score: String( assignment.pass_score ),
		time_limit_enabled: assignment.time_limit_enabled,
		time_limit: String( assignment.time_limit ),
		time_limit_unit: assignment.time_limit_unit,
		allow_file_uploads: assignment.allow_file_uploads,
		max_files: String( assignment.max_files ),
		file_size_limit_enabled: assignment.file_size_limit_enabled,
		max_file_size_mb: String( assignment.max_file_size_mb ),
		resources: assignment.resources,
		prerequisites: assignment.prerequisites,
		drip: assignment.drip,
		comments_enabled: assignment.comments_enabled,
	};
}

/**
 * Whether the form differs from the saved copy.
 *
 * @param form  Current form.
 * @param saved Saved form.
 * @return True when there are unsaved changes.
 */
export function isDirty( form: FormState, saved: FormState ): boolean {
	return ( Object.keys( form ) as Array< keyof FormState > ).some(
		( key ) =>
			JSON.stringify( form[ key ] ) !== JSON.stringify( saved[ key ] )
	);
}

/**
 * Whether a string is an absolute HTTP or HTTPS URL.
 *
 * @param value Candidate.
 * @return True when it is.
 */
export function isWebUrl( value: string ): boolean {
	try {
		const url = new URL( value.trim() );
		return 'http:' === url.protocol || 'https:' === url.protocol;
	} catch {
		return false;
	}
}

function validDate( value: string ): boolean {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( value );
	if ( ! match ) {
		return false;
	}
	const date = new Date(
		Date.UTC(
			Number( match[ 1 ] ),
			Number( match[ 2 ] ) - 1,
			Number( match[ 3 ] )
		)
	);
	return date.toISOString().slice( 0, 10 ) === value;
}

/**
 * Validate the delivery settings with the server's rules.
 *
 * @param form Form state.
 * @return Field errors for `resources`, `prerequisites` and `drip`.
 */
export function validateDelivery( form: DeliverySettings ): FormErrors {
	const errors: FormErrors = {};
	if ( form.resources.length > LIMITS.resources ) {
		errors.resources = sprintf(
			/* translators: %d: maximum number of resources. */
			__( 'Add at most %d resources.', 'ohmylms' ),
			LIMITS.resources
		);
	} else {
		for ( const row of form.resources ) {
			const name = row.name.trim();
			if ( ! name || Array.from( name ).length > LIMITS.resourceName ) {
				errors.resources = __(
					'Give every resource a name of 1 to 200 characters.',
					'ohmylms'
				);
				break;
			}
			if ( 'link' === row.type && ! isWebUrl( row.url ) ) {
				errors.resources = __(
					'Links need a full address starting with http:// or https://.',
					'ohmylms'
				);
				break;
			}
			if ( 'file' === row.type && row.attachment_id < 1 ) {
				errors.resources = __(
					'Choose each file from the media library.',
					'ohmylms'
				);
				break;
			}
			if ( Array.from( row.size ).length > LIMITS.resourceSize ) {
				errors.resources = __(
					'Keep size labels to 50 characters.',
					'ohmylms'
				);
				break;
			}
		}
	}
	if ( form.prerequisites.items.length > LIMITS.prerequisites ) {
		errors.prerequisites = sprintf(
			/* translators: %d: maximum number of prerequisites. */
			__( 'Require at most %d items.', 'ohmylms' ),
			LIMITS.prerequisites
		);
	}
	const release = form.drip;
	if (
		! Number.isSafeInteger( release.days ) ||
		release.days < 0 ||
		release.days > LIMITS.dripDays
	) {
		errors.drip = rangeMessage( 0, LIMITS.dripDays );
	} else if ( '' !== release.date && ! validDate( release.date ) ) {
		errors.drip = __( 'Enter a real date.', 'ohmylms' );
	} else if (
		'' !== release.time &&
		! /^([01]\d|2[0-3]):[0-5]\d$/.test( release.time )
	) {
		errors.drip = __( 'Enter a time such as 09:30.', 'ohmylms' );
	} else if (
		release.enable &&
		'specific-date' === release.type &&
		'' === release.date
	) {
		errors.drip = __( 'Choose the date the assignment opens.', 'ohmylms' );
	}
	return errors;
}

function wholeNumber( value: string, min: number, max: number ) {
	return /^\d{1,7}$/.test( value.trim() ) &&
		Number( value ) >= min &&
		Number( value ) <= max
		? Number( value )
		: null;
}

function rangeMessage( min: number, max: number ) {
	return sprintf(
		/* translators: 1: smallest value; 2: largest value. */
		__( 'Enter a whole number from %1$d to %2$d.', 'ohmylms' ),
		min,
		max
	);
}

/**
 * Validate the editor with the server's rules.
 *
 * @param form Form state.
 * @return Field errors; empty when valid.
 */
export function validateForm( form: FormState ): FormErrors {
	const errors: FormErrors = {};
	const title = form.title.trim();
	if ( ! title || Array.from( title ).length > LIMITS.title ) {
		errors.title = sprintf(
			/* translators: %d: maximum title length. */
			__( 'Enter a title of 1 to %d characters.', 'ohmylms' ),
			LIMITS.title
		);
	}
	const max = wholeNumber( form.max_score, 1, LIMITS.maxScore );
	if ( null === max ) {
		errors.max_score = rangeMessage( 1, LIMITS.maxScore );
	}
	const pass = wholeNumber( form.pass_score, 0, LIMITS.maxScore );
	if ( null === pass ) {
		errors.pass_score = rangeMessage( 0, LIMITS.maxScore );
	} else if ( null !== max && pass > max ) {
		errors.pass_score = __(
			'The passing score cannot be higher than the maximum score.',
			'ohmylms'
		);
	}
	const checks: Array< [ keyof FormState, number ] > = [
		[ 'time_limit', LIMITS.timeLimit ],
		[ 'max_files', LIMITS.maxFiles ],
		[ 'max_file_size_mb', LIMITS.maxFileSizeMb ],
	];
	for ( const [ key, limit ] of checks ) {
		if ( null === wholeNumber( String( form[ key ] ), 1, limit ) ) {
			errors[ key ] = rangeMessage( 1, limit );
		}
	}
	return { ...errors, ...validateDelivery( form ) };
}

/**
 * Convert a valid form to the request body.
 *
 * @param form         Valid form.
 * @param baseModified The `updated_at` the edit started from.
 * @return The payload.
 */
export function toInput(
	form: FormState,
	baseModified: string
): AssignmentInput {
	return {
		title: form.title.trim(),
		instructions: form.instructions,
		status: oneOf( form.status, WRITABLE ),
		max_score: Number( form.max_score ),
		pass_score: Number( form.pass_score ),
		time_limit_enabled: form.time_limit_enabled,
		time_limit: Number( form.time_limit ),
		time_limit_unit: form.time_limit_unit,
		allow_file_uploads: form.allow_file_uploads,
		max_files: Number( form.max_files ),
		file_size_limit_enabled: form.file_size_limit_enabled,
		max_file_size_mb: Number( form.max_file_size_mb ),
		resources: form.resources.map( ( row ) => ( {
			type: row.type,
			attachment_id: 'file' === row.type ? row.attachment_id : 0,
			name: row.name.trim(),
			url: 'link' === row.type ? row.url.trim() : row.url,
			size: row.size,
		} ) ),
		prerequisites: {
			enable: form.prerequisites.enable,
			ids: form.prerequisites.items.map( ( item ) => item.id ),
		},
		drip: form.drip,
		comments_enabled: form.comments_enabled,
		base_modified: baseModified,
	};
}

/**
 * Map a server validation error to the form field it is about.
 *
 * @param error Failed request.
 * @return The field and message, or null.
 */
export function serverFieldError(
	error: ApiError
): { field: keyof FormState; message: string } | null {
	const fields: Record< string, keyof FormState > = {
		ohmylms_assignment_invalid_title: 'title',
		ohmylms_assignment_invalid_pass_score: 'pass_score',
		ohmylms_assignment_invalid_resources: 'resources',
		ohmylms_assignment_invalid_resource_file: 'resources',
		ohmylms_assignment_invalid_prerequisites: 'prerequisites',
		ohmylms_assignment_invalid_drip: 'drip',
	};
	const field = fields[ error.code ];
	return field ? { field, message: error.message } : null;
}

/**
 * Parse a score typed by a grader.
 *
 * @param value Text.
 * @param max   Largest allowed score.
 * @return The score, or an error message.
 */
export function parseScore(
	value: string,
	max: number
): { score: number } | { error: string } {
	const score = wholeNumber( value, 0, max );
	return null === score ? { error: rangeMessage( 0, max ) } : { score };
}

/**
 * Translated status label.
 *
 * @param status Assignment status.
 * @return Label.
 */
export function statusLabel( status: AssignmentStatus ): string {
	if ( 'publish' === status ) {
		return __( 'Published', 'ohmylms' );
	}
	return 'trash' === status
		? __( 'Trash', 'ohmylms' )
		: __( 'Draft', 'ohmylms' );
}

/**
 * Translated submission state.
 *
 * @param submission Submission.
 * @return Label such as "Needs grading" or "Passed".
 */
export function submissionLabel( submission: Submission ): string {
	if ( 'submitted' === submission.status ) {
		return __( 'Needs grading', 'ohmylms' );
	}
	if ( 'passed' === submission.result ) {
		return __( 'Passed', 'ohmylms' );
	}
	return 'failed' === submission.result
		? __( 'Failed', 'ohmylms' )
		: __( 'Graded', 'ohmylms' );
}

/**
 * Format an ISO date for display.
 *
 * @param iso ISO 8601 date, or null.
 * @return Localised date and time, or a dash.
 */
export function formatDate( iso: string | null ): string {
	if ( ! iso ) {
		return '—';
	}
	const date = new Date( iso );
	return Number.isNaN( date.getTime() )
		? '—'
		: date.toLocaleString( undefined, {
				dateStyle: 'medium',
				timeStyle: 'short',
			} );
}

/**
 * Format a byte count.
 *
 * @param bytes Size in bytes.
 * @return Text such as `12 KB`.
 */
export function formatSize( bytes: number ): string {
	if ( bytes >= 1048576 ) {
		return `${ ( bytes / 1048576 ).toFixed( 1 ) } MB`;
	}
	return bytes >= 1024
		? `${ Math.round( bytes / 1024 ) } KB`
		: `${ bytes } B`;
}
