import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type {
	Attempt,
	AttemptDetail,
	AttemptPart,
	AttemptQuestion,
	AttemptStatus,
	CourseChoice,
	CourseRef,
	Drafts,
	FeedbackMode,
	GradeEvent,
	GradeInput,
	GradeResult,
	Quiz,
	QuizDetail,
	QuizForm,
	QuizFormErrors,
	QuizInput,
	QuizLayout,
	QuizPool,
	QuizQuestionCreated,
	QuizQuestions,
	QuizQuestionSlot,
	QuizSettings,
	QuizStatus,
} from './types';

const STATUSES: readonly QuizStatus[] = [ 'draft', 'publish', 'trash' ];
const ATTEMPT_STATUSES: readonly AttemptStatus[] = [
	'in-progress',
	'in-review',
	'completed',
];

/** Layouts the quiz player supports. */
export const LAYOUTS: readonly QuizLayout[] = [
	'one_question_per_page',
	'all_questions_in_one_page',
	'number_of_questions_per_page',
];

/** Feedback modes the server accepts. */
export const FEEDBACK_MODES: readonly FeedbackMode[] = [
	'immediate',
	'after_close',
	'manual',
];

/** Limits shared with the server (`QuizSettings` in PHP). */
export const LIMITS = {
	title: 200,
	timeLimit: 10080,
	passingScore: 100,
	attempts: 1000,
	perPage: 100,
	reason: 500,
} as const;

const MARKS_FORMAT = /^\d{1,7}(\.\d{1,4})?$/;

/**
 * Build the error thrown when the server answers with data that does not match the contract.
 *
 * @return A 502 `invalid_response` error.
 */
export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid quiz data.', 'ohmylms' ),
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

function finite( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isFinite( value ) ) {
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

function nullableText( value: unknown ): string | null {
	return null === value || undefined === value ? null : text( value );
}

function nullableNumber( value: unknown ): number | null {
	return null === value || undefined === value ? null : finite( value );
}

/**
 * Validate a course reference.
 *
 * @param value Parsed JSON.
 * @return The reference.
 */
export function parseCourseRef( value: unknown ): CourseRef {
	const item = record( value );
	return { id: positiveId( item.id ), title: text( item.title ) };
}

/**
 * Validate a course choice for the list filter.
 *
 * @param value Parsed JSON.
 * @return The choice.
 */
export function parseCourseChoice( value: unknown ): CourseChoice {
	const item = record( value );
	return { ...parseCourseRef( item ), status: text( item.status ) };
}

/**
 * Validate a listed quiz.
 *
 * @param value Parsed JSON.
 * @return The quiz.
 */
export function parseQuiz( value: unknown ): Quiz {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		slug: text( item.slug ),
		status: oneOf( item.status, STATUSES ),
		permalink: text( item.permalink ),
		author_id: count( item.author_id ),
		author_name: text( item.author_name ),
		course: null === item.course ? null : parseCourseRef( item.course ),
		question_count: count( item.question_count ),
		attempt_count: count( item.attempt_count ),
		pending_review_count: count( item.pending_review_count ),
		created_at: text( item.created_at ),
		updated_at: text( item.updated_at ),
		can_edit: boolean( item.can_edit ),
		can_delete: boolean( item.can_delete ),
		can_grade: boolean( item.can_grade ),
		native_editor_url: nullableText( item.native_editor_url ),
	};
}

/**
 * Validate quiz settings.
 *
 * @param value Parsed JSON.
 * @return The settings.
 */
export function parseSettings( value: unknown ): QuizSettings {
	const item = record( value );
	return {
		time_limit_minutes: count( item.time_limit_minutes ),
		passing_score: finite( item.passing_score ),
		attempts_allowed: count( item.attempts_allowed ),
		randomize_questions: boolean( item.randomize_questions ),
		show_correct_answers: boolean( item.show_correct_answers ),
		layout: oneOf( item.layout, LAYOUTS ),
		questions_per_page: positiveId( item.questions_per_page ),
		feedback_mode: oneOf( item.feedback_mode, FEEDBACK_MODES ),
		available_from: nullableText( item.available_from ),
		available_until: nullableText( item.available_until ),
	};
}

/**
 * Validate a quiz read with `context=edit`.
 *
 * @param value Parsed JSON.
 * @return The quiz with description and settings.
 */
export function parseQuizDetail( value: unknown ): QuizDetail {
	const item = record( value );
	return {
		...parseQuiz( value ),
		description: text( item.description ),
		settings: parseSettings( item.settings ),
		total_marks: finite( item.total_marks ),
	};
}

/**
 * Validate a listed attempt.
 *
 * @param value Parsed JSON.
 * @return The attempt.
 */
export function parseAttempt( value: unknown ): Attempt {
	const item = record( value );
	const student = record( item.student );
	return {
		id: positiveId( item.id ),
		quiz_id: positiveId( item.quiz_id ),
		course_id: count( item.course_id ),
		student: {
			id: count( student.id ),
			name: text( student.name ),
			email: text( student.email ),
		},
		status: oneOf( item.status, ATTEMPT_STATUSES ),
		score: finite( item.score ),
		max_marks: finite( item.max_marks ),
		percentage: nullableNumber( item.percentage ),
		started_at: nullableText( item.started_at ),
		submitted_at: nullableText( item.submitted_at ),
		needs_grading: boolean( item.needs_grading ),
	};
}

function parsePart( value: unknown ): AttemptPart {
	const item = record( value );
	return {
		id: text( item.id ),
		kind: text( item.kind ),
		max: finite( item.max ),
		awarded: nullableNumber( item.awarded ),
		grader: nullableText( item.grader ),
	};
}

function parseEvent( value: unknown ): GradeEvent {
	const item = record( value );
	return {
		awarded: finite( item.awarded ),
		grader: text( item.grader ),
		reviewer_id: count( item.reviewer_id ),
		reviewer_name: text( item.reviewer_name ),
		reason: text( item.reason ),
		created_at: text( item.created_at ),
	};
}

function parseQuestion( value: unknown ): AttemptQuestion {
	const item = record( value );
	return {
		question_id: positiveId( item.question_id ),
		position: positiveId( item.position ),
		title: text( item.title ),
		type: text( item.type ),
		max_marks: finite( item.max_marks ),
		awarded: finite( item.awarded ),
		status: oneOf( item.status, [ 'graded', 'in-review' ] as const ),
		correct: null === item.correct ? null : boolean( item.correct ),
		answer: list( item.answer ).map( text ),
		parts: null === item.parts ? null : list( item.parts ).map( parsePart ),
		history: list( item.history ).map( parseEvent ),
	};
}

/**
 * Validate an attempt with its questions.
 *
 * @param value Parsed JSON.
 * @return The attempt detail.
 */
export function parseAttemptDetail( value: unknown ): AttemptDetail {
	const item = record( value );
	return {
		...parseAttempt( value ),
		passing_mark: finite( item.passing_mark ),
		passed: null === item.passed ? null : boolean( item.passed ),
		versioned: boolean( item.versioned ),
		questions: list( item.questions ).map( parseQuestion ),
	};
}

/**
 * Validate the result of a grading request.
 *
 * @param value Parsed JSON.
 * @return The refreshed attempt and the number of grade events written.
 */
export function parseGradeResult( value: unknown ): GradeResult {
	return {
		...parseAttemptDetail( value ),
		changed: count( record( value ).changed ),
	};
}

/**
 * Validate the answer to a delete request.
 *
 * @param value Parsed JSON.
 * @return Whether the quiz was removed for good (`false` means moved to the trash).
 */
export function parseDeleted( value: unknown ): boolean {
	return boolean( record( value ).deleted );
}

function pad( value: number ): string {
	return String( value ).padStart( 2, '0' );
}

/**
 * Convert a UTC ISO timestamp to a `datetime-local` input value in the browser time zone.
 *
 * @param iso UTC timestamp, or `null`.
 * @return `YYYY-MM-DDTHH:mm`, or an empty string.
 */
export function toLocalInput( iso: string | null ): string {
	if ( ! iso ) {
		return '';
	}
	const date = new Date( iso );
	if ( Number.isNaN( date.getTime() ) ) {
		return '';
	}
	return `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad(
		date.getDate()
	) }T${ pad( date.getHours() ) }:${ pad( date.getMinutes() ) }`;
}

/**
 * Convert a `datetime-local` value to a UTC ISO timestamp without milliseconds.
 *
 * @param value Input value.
 * @return UTC timestamp, `null` for an empty value, or `undefined` when the value is not a date.
 */
export function fromLocalInput( value: string ): string | null | undefined {
	if ( '' === value.trim() ) {
		return null;
	}
	if ( ! /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test( value ) ) {
		return undefined;
	}
	const date = new Date( value );
	return Number.isNaN( date.getTime() )
		? undefined
		: date.toISOString().replace( /\.\d{3}Z$/, 'Z' );
}

/**
 * Format a UTC timestamp for display in the browser locale.
 *
 * @param iso UTC timestamp or `null`.
 * @return Readable date and time, or a dash.
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
 * Format marks without trailing zeros.
 *
 * @param value Marks.
 * @return Text such as `3` or `2.5`.
 */
export function formatMarks( value: number ): string {
	return String( Math.round( value * 10000 ) / 10000 );
}

/**
 * Start state of the settings form.
 *
 * @param quiz Quiz read with `context=edit`.
 * @return Form state.
 */
export function formFromQuiz( quiz: QuizDetail ): QuizForm {
	const s = quiz.settings;
	return {
		title: quiz.title,
		status: 'publish' === quiz.status ? 'publish' : 'draft',
		description: quiz.description,
		time_limit_minutes: String( s.time_limit_minutes ),
		passing_score: String( s.passing_score ),
		attempts_allowed: String( s.attempts_allowed ),
		randomize_questions: s.randomize_questions,
		show_correct_answers: s.show_correct_answers,
		layout: s.layout,
		questions_per_page: String( s.questions_per_page ),
		feedback_mode: s.feedback_mode,
		available_from: toLocalInput( s.available_from ),
		available_until: toLocalInput( s.available_until ),
	};
}

/**
 * Whether the form differs from its saved copy.
 *
 * @param current Current form.
 * @param saved   Saved form.
 * @return True when there are unsaved changes.
 */
export function isDirty( current: QuizForm, saved: QuizForm ): boolean {
	return JSON.stringify( current ) !== JSON.stringify( saved );
}

/**
 * Parse a whole number from text.
 *
 * @param value Text.
 * @param min   Smallest allowed value.
 * @param max   Largest allowed value.
 * @return The number, or `null` when the text is not a whole number in range.
 */
export function wholeNumber(
	value: string,
	min: number,
	max: number
): number | null {
	if ( ! /^\d{1,7}$/.test( value.trim() ) ) {
		return null;
	}
	const number = Number( value.trim() );
	return number >= min && number <= max ? number : null;
}

/**
 * Validate the settings form on the client. The server repeats every rule.
 *
 * @param form Form state.
 * @return Errors by field; empty when valid.
 */
export function validateForm( form: QuizForm ): QuizFormErrors {
	const errors: QuizFormErrors = {};
	const title = form.title.trim();
	if ( ! title || Array.from( title ).length > LIMITS.title ) {
		errors.title = sprintf(
			/* translators: %d: maximum title length. */
			__( 'Enter a title of 1 to %d characters.', 'ohmylms' ),
			LIMITS.title
		);
	}
	if (
		null === wholeNumber( form.time_limit_minutes, 0, LIMITS.timeLimit )
	) {
		errors.time_limit_minutes = sprintf(
			/* translators: %d: maximum minutes. */
			__(
				'Enter a whole number of minutes from 0 (no limit) to %d.',
				'ohmylms'
			),
			LIMITS.timeLimit
		);
	}
	const passing = form.passing_score.trim();
	const passingValue = /^\d{1,3}(\.\d{1,2})?$/.test( passing )
		? Number( passing )
		: NaN;
	if ( ! ( passingValue >= 0 && passingValue <= LIMITS.passingScore ) ) {
		errors.passing_score = __(
			'Enter a passing score from 0 to 100.',
			'ohmylms'
		);
	}
	if ( null === wholeNumber( form.attempts_allowed, 0, LIMITS.attempts ) ) {
		errors.attempts_allowed = sprintf(
			/* translators: %d: maximum attempts. */
			__( 'Enter a whole number of attempts from 0 to %d.', 'ohmylms' ),
			LIMITS.attempts
		);
	}
	if ( null === wholeNumber( form.questions_per_page, 1, LIMITS.perPage ) ) {
		errors.questions_per_page = sprintf(
			/* translators: %d: maximum questions per page. */
			__( 'Enter a whole number from 1 to %d.', 'ohmylms' ),
			LIMITS.perPage
		);
	}
	const from = fromLocalInput( form.available_from );
	const until = fromLocalInput( form.available_until );
	if ( undefined === from ) {
		errors.available_from = __( 'Enter a valid date and time.', 'ohmylms' );
	}
	if ( undefined === until ) {
		errors.available_until = __(
			'Enter a valid date and time.',
			'ohmylms'
		);
	}
	if (
		from &&
		until &&
		new Date( until ).getTime() <= new Date( from ).getTime()
	) {
		errors.available_until = __(
			'The closing time must be after the opening time.',
			'ohmylms'
		);
	}
	return errors;
}

/**
 * Whether there are no field errors.
 *
 * @param errors Result of {@link validateForm}.
 * @return True when valid.
 */
export function isValid( errors: QuizFormErrors ): boolean {
	return 0 === Object.keys( errors ).length;
}

/**
 * Convert a validated form to the request body.
 *
 * @param  form         Valid form state.
 * @param  baseModified The `updated_at` the quiz was loaded with.
 * @return Request body.
 * @throws {Error} When the form has not been validated.
 */
export function toInput( form: QuizForm, baseModified: string ): QuizInput {
	const minutes = wholeNumber( form.time_limit_minutes, 0, LIMITS.timeLimit );
	const attempts = wholeNumber( form.attempts_allowed, 0, LIMITS.attempts );
	const perPage = wholeNumber( form.questions_per_page, 1, LIMITS.perPage );
	const from = fromLocalInput( form.available_from );
	const until = fromLocalInput( form.available_until );
	if (
		null === minutes ||
		null === attempts ||
		null === perPage ||
		undefined === from ||
		undefined === until
	) {
		throw new Error( 'The quiz form is not valid.' );
	}
	return {
		title: form.title.trim(),
		status: form.status,
		description: form.description,
		settings: {
			time_limit_minutes: minutes,
			passing_score: Number( form.passing_score.trim() ),
			attempts_allowed: attempts,
			randomize_questions: form.randomize_questions,
			show_correct_answers: form.show_correct_answers,
			layout: form.layout,
			questions_per_page: perPage,
			feedback_mode: form.feedback_mode,
			available_from: from,
			available_until: until,
		},
		base_modified: baseModified,
	};
}

/**
 * Map a server validation error to the form field it concerns.
 *
 * @param error Error from a save.
 * @return The field and message, or `null` when the error is not about one field.
 */
export function serverFieldError(
	error: ApiError
): { field: keyof QuizForm; message: string } | null {
	const fields: Record< string, keyof QuizForm > = {
		ohmylms_quiz_invalid_title: 'title',
		ohmylms_quiz_invalid_time_limit: 'time_limit_minutes',
		ohmylms_quiz_invalid_passing_score: 'passing_score',
		ohmylms_quiz_invalid_attempts: 'attempts_allowed',
		ohmylms_quiz_invalid_questions_per_page: 'questions_per_page',
		ohmylms_quiz_invalid_availability: 'available_until',
	};
	const field = fields[ error.code ];
	return field ? { field, message: error.message } : null;
}

/**
 * Parse marks typed by a grader.
 *
 * @param value Text.
 * @param max   Largest allowed marks.
 * @return The marks, or an error message.
 */
export function parseMarks(
	value: string,
	max: number
): { marks: number } | { error: string } {
	const trimmed = value.trim();
	if ( ! MARKS_FORMAT.test( trimmed ) ) {
		return {
			error: __(
				'Enter marks as a number, for example 2 or 1.5.',
				'ohmylms'
			),
		};
	}
	const marks = Number( trimmed );
	if ( marks > max + 0.00005 ) {
		return {
			error: sprintf(
				/* translators: %s: maximum marks. */
				__( 'Marks cannot be more than %s.', 'ohmylms' ),
				formatMarks( max )
			),
		};
	}
	return { marks };
}

/**
 * Start drafts from the stored marks. Questions waiting for review start empty so an ungraded answer
 * is never submitted as zero by accident.
 *
 * @param questions Questions of the attempt.
 * @return Drafts by question id.
 */
export function draftsFromQuestions( questions: AttemptQuestion[] ): Drafts {
	const drafts: Drafts = {};
	for ( const question of questions ) {
		const pending = 'in-review' === question.status;
		const parts: Record< string, string > = {};
		for ( const part of question.parts ?? [] ) {
			parts[ part.id ] =
				null === part.awarded || pending
					? ''
					: formatMarks( part.awarded );
		}
		drafts[ question.question_id ] = {
			total: pending ? '' : formatMarks( question.awarded ),
			parts,
		};
	}
	return drafts;
}

function gradePartsQuestion(
	question: AttemptQuestion,
	parts: AttemptPart[],
	draft: Record< string, string >
): { grade?: GradeInput; error?: string } {
	const marks: Record< string, number > = {};
	let changed = false;
	for ( const part of parts ) {
		const raw = draft[ part.id ] ?? '';
		if ( '' === raw.trim() ) {
			continue;
		}
		const parsed = parseMarks( raw, part.max );
		if ( 'error' in parsed ) {
			return { error: parsed.error };
		}
		marks[ part.id ] = parsed.marks;
		changed =
			changed ||
			null === part.awarded ||
			Math.abs( part.awarded - parsed.marks ) > 0.00005;
	}
	if (
		Object.keys( marks ).length &&
		( changed || 'in-review' === question.status )
	) {
		return { grade: { question_id: question.question_id, marks } };
	}
	return {};
}

/**
 * Collect the grades to send and the errors in the drafts. Only questions whose marks changed, or that
 * were waiting for review and now have marks, are included, so saving twice sends nothing new.
 *
 * @param questions Questions of the attempt.
 * @param drafts    What the grader typed.
 * @return Grades to send and error messages by question id.
 */
export function buildGrades(
	questions: AttemptQuestion[],
	drafts: Drafts
): { grades: GradeInput[]; errors: Record< number, string > } {
	const grades: GradeInput[] = [];
	const errors: Record< number, string > = {};
	for ( const question of questions ) {
		const draft = drafts[ question.question_id ];
		if ( ! draft ) {
			continue;
		}
		if ( question.parts && question.parts.length ) {
			const result = gradePartsQuestion(
				question,
				question.parts,
				draft.parts
			);
			if ( result.error ) {
				errors[ question.question_id ] = result.error;
			} else if ( result.grade ) {
				grades.push( result.grade );
			}
			continue;
		}
		if ( '' === draft.total.trim() ) {
			continue;
		}
		const parsed = parseMarks( draft.total, question.max_marks );
		if ( 'error' in parsed ) {
			errors[ question.question_id ] = parsed.error;
		} else if (
			'in-review' === question.status ||
			Math.abs( question.awarded - parsed.marks ) > 0.00005
		) {
			grades.push( {
				question_id: question.question_id,
				marks: parsed.marks,
			} );
		}
	}
	return { grades, errors };
}

/**
 * Preview of the attempt total with the drafts applied. The server recalculates the real total.
 *
 * @param questions Questions of the attempt.
 * @param drafts    What the grader typed.
 * @return Sum of valid draft marks, falling back to the stored marks.
 */
export function previewTotal(
	questions: AttemptQuestion[],
	drafts: Drafts
): number {
	let total = 0;
	for ( const question of questions ) {
		const draft = drafts[ question.question_id ];
		const typed =
			draft && '' !== draft.total.trim()
				? parseMarks( draft.total, question.max_marks )
				: null;
		total += typed && 'marks' in typed ? typed.marks : question.awarded;
	}
	return Math.round( total * 10000 ) / 10000;
}

/**
 * Whether any draft would change the stored grades, or has an error to show.
 *
 * @param questions Questions of the attempt.
 * @param drafts    What the grader typed.
 * @return True when there is something to save.
 */
export function hasGradeChanges(
	questions: AttemptQuestion[],
	drafts: Drafts
): boolean {
	const { grades, errors } = buildGrades( questions, drafts );
	return grades.length > 0 || Object.keys( errors ).length > 0;
}

/**
 * Label of an attempt status.
 *
 * @param status Attempt status.
 * @return Translated label.
 */
export function attemptStatusLabel( status: AttemptStatus ): string {
	switch ( status ) {
		case 'in-progress':
			return __( 'In progress', 'ohmylms' );
		case 'in-review':
			return __( 'Needs grading', 'ohmylms' );
		default:
			return __( 'Completed', 'ohmylms' );
	}
}

const SLOT_STATUSES = [
	'draft',
	'approved',
	'archived',
	'unavailable',
] as const;
const DELIVERY_SOURCES = [
	'pinned',
	'current',
	'approved',
	'unavailable',
] as const;

function parseSlot( value: unknown ): QuizQuestionSlot {
	const item = record( value );
	const delivery = record( item.delivery );
	const usage = record( item.usage );
	const permissions = record( item.permissions );
	return {
		question_id: positiveId( item.question_id ),
		position: positiveId( item.position ),
		name: text( item.name ),
		type: text( item.type ),
		type_label: text( item.type_label ),
		marks: finite( item.marks ),
		section: text( item.section ),
		status: oneOf( item.status, SLOT_STATUSES ),
		version: count( item.version ),
		current_version_id: count( item.current_version_id ),
		approved_version_id: count( item.approved_version_id ),
		pinned_version_id: count( item.pinned_version_id ),
		pinned_version_no: count( item.pinned_version_no ),
		delivery: {
			source: oneOf( delivery.source, DELIVERY_SOURCES ),
			version_id: count( delivery.version_id ),
			version_no: count( delivery.version_no ),
		},
		usage: {
			quizzes: count( usage.quizzes ),
			responses: count( usage.responses ),
		},
		permissions: {
			can_edit: boolean( permissions.can_edit ),
			can_use: boolean( permissions.can_use ),
		},
		modified: text( item.modified ),
	};
}

function parsePool( value: unknown ): QuizPool {
	const item = record( value );
	const skill = record( item.skill );
	return {
		id: text( item.id ),
		title: text( item.title ),
		skill: { id: count( skill.id ), name: text( skill.name ) },
		count: count( item.count ),
		marks: finite( item.marks ),
		difficulty: text( item.difficulty ),
		bank_id: count( item.bank_id ),
		include_secure: boolean( item.include_secure ),
	};
}

/**
 * Validate the question list of a quiz.
 *
 * @param value Parsed JSON.
 * @return The slots, pools, totals and revision.
 */
export function parseQuizQuestions( value: unknown ): QuizQuestions {
	const item = record( value );
	const totals = record( item.totals );
	const revision = text( item.revision );
	if ( ! /^[0-9a-f]{16}$/.test( revision ) ) {
		throw invalidResponse();
	}
	return {
		quiz_id: positiveId( item.quiz_id ),
		revision,
		questions: list( item.questions ).map( parseSlot ),
		pools: list( item.pools ).map( parsePool ),
		totals: {
			questions: count( totals.questions ),
			fixed_marks: finite( totals.fixed_marks ),
			pool_questions: count( totals.pool_questions ),
			pool_marks: finite( totals.pool_marks ),
			total_marks: finite( totals.total_marks ),
		},
	};
}

/**
 * Validate the answer to creating a question in a quiz.
 *
 * @param value Parsed JSON.
 * @return The list and the id of the new question, which must be in it.
 */
export function parseQuizQuestionCreated(
	value: unknown
): QuizQuestionCreated {
	const parsed = parseQuizQuestions( value );
	const created = positiveId( record( value ).created_id );
	if ( ! parsed.questions.some( ( slot ) => slot.question_id === created ) ) {
		throw invalidResponse();
	}
	return { ...parsed, created_id: created };
}

/**
 * Question ids with one moved up or down.
 *
 * @param ids   Ids in order.
 * @param index Position of the question to move.
 * @param delta -1 to move up, 1 to move down.
 * @return The new order, or `null` when the move leaves the list.
 */
export function moveQuestion(
	ids: readonly number[],
	index: number,
	delta: -1 | 1
): number[] | null {
	const target = index + delta;
	if ( index < 0 || target < 0 || Math.max( index, target ) >= ids.length ) {
		return null;
	}
	const next = [ ...ids ];
	const [ moved ] = next.splice( index, 1 );
	next.splice( target, 0, moved as number );
	return next;
}

/**
 * Display name of a slot.
 *
 * @param slot Slot.
 * @return The name, or a placeholder for untitled and restricted questions.
 */
export function slotName( slot: QuizQuestionSlot ): string {
	if ( ! slot.permissions.can_use ) {
		return sprintf(
			/* translators: %d: question id. */
			__( 'Restricted question #%d', 'ohmylms' ),
			slot.question_id
		);
	}
	return slot.name.trim() || __( 'Untitled question', 'ohmylms' );
}

/**
 * Which version learners get, in words.
 *
 * @param slot Slot.
 * @return Text such as "Pinned to version 2".
 */
export function deliveryLabel( slot: QuizQuestionSlot ): string {
	const { source, version_no: number } = slot.delivery;
	switch ( source ) {
		case 'pinned':
			return sprintf(
				/* translators: %d: version number. */
				__( 'Pinned to version %d', 'ohmylms' ),
				number
			);
		case 'approved':
			return sprintf(
				/* translators: %d: version number. */
				__( 'Approved version %d', 'ohmylms' ),
				number
			);
		case 'current':
			return number
				? sprintf(
						/* translators: %d: version number. */
						__( 'Latest version (%d)', 'ohmylms' ),
						number
					)
				: __( 'Latest version', 'ohmylms' );
		default:
			return __(
				'No approved version: learners cannot get it',
				'ohmylms'
			);
	}
}

/**
 * Status of a slot when it needs attention.
 *
 * @param slot Slot.
 * @return A warning, or an empty string.
 */
export function slotWarning( slot: QuizQuestionSlot ): string {
	switch ( slot.status ) {
		case 'archived':
			return __( 'Archived', 'ohmylms' );
		case 'unavailable':
			return __( 'Deleted or in the trash', 'ohmylms' );
		default:
			return '';
	}
}

/**
 * Whether a bank question can be added, and why not.
 *
 * @param item                      List row of the question bank.
 * @param item.id                   Question id.
 * @param item.status               Lifecycle status.
 * @param item.permissions          Permissions of the current user.
 * @param item.permissions.can_edit Whether the user may edit the question.
 * @param inQuiz                    Ids already in the quiz.
 * @return `null` when it can be added, otherwise the reason.
 */
export function addBlocker(
	item: {
		id: number;
		status: string;
		permissions: { can_edit: boolean };
	},
	inQuiz: ReadonlySet< number >
): string | null {
	if ( inQuiz.has( item.id ) ) {
		return __( 'Already in this quiz', 'ohmylms' );
	}
	if ( 'archived' === item.status ) {
		return __( 'Archived', 'ohmylms' );
	}
	if ( ! item.permissions.can_edit && 'approved' !== item.status ) {
		return __( 'Shared question without an approved version', 'ohmylms' );
	}
	return null;
}

/**
 * Label of a quiz status.
 *
 * @param status Quiz status.
 * @return Translated label.
 */
export function quizStatusLabel( status: QuizStatus ): string {
	switch ( status ) {
		case 'publish':
			return __( 'Published', 'ohmylms' );
		case 'trash':
			return __( 'Trash', 'ohmylms' );
		default:
			return __( 'Draft', 'ohmylms' );
	}
}
