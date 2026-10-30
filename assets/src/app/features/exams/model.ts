import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import {
	HEADER_FIELDS,
	type BankQuestion,
	type CompositionInput,
	type CompositionItem,
	type DryRunReport,
	type DryRunRow,
	type ExamDetail,
	type ExamHeader,
	type ExamOrigin,
	type ExamStatus,
	type ExamSummary,
	type FeedbackRelease,
	type FigureAsset,
	type ImportFigure,
	type ImportJob,
	type ImportJobDetail,
	type ImportPart,
	type ImportRow,
	type ImportTarget,
	type JobState,
	type PaperSettings,
	type PartKind,
	type RowStatus,
	type SettingsErrors,
	type SettingsForm,
	type SettingsInput,
	type SkillMatch,
} from './types';

/** Limits shared with the server (`Exams\Editor` in PHP). */
export const LIMITS = {
	title: 190,
	header: 190,
	instructions: 4000,
	duration: 1440,
	grace: 600,
	questions: 200,
} as const;

const STATUSES: readonly ExamStatus[] = [ 'draft', 'publish' ];
const ORIGINS: readonly ExamOrigin[] = [ 'composed', 'imported' ];
export const FEEDBACK: readonly FeedbackRelease[] = [
	'immediate',
	'after_close',
	'manual',
];
const STATES: readonly JobState[] = [
	'queued',
	'failed',
	'ready_for_extraction',
	'extracting',
	'extraction_failed',
	'ready_for_review',
	'committed',
];
export const TARGETS: readonly ImportTarget[] = [ 'exam', 'quiz', 'questions' ];
const ROW_STATUSES: readonly RowStatus[] = [
	'pending',
	'accepted',
	'rejected',
];
const PART_KINDS: readonly PartKind[] = [ 'numerical', 'text', 'written' ];
const FIGURE_STATES: readonly FigureAsset[ 'state' ][] = [
	'queued',
	'rendering',
	'ready',
	'failed',
];
const DRY_STATUSES: readonly DryRunRow[ 'status' ][] = [
	'create',
	'update',
	'unchanged',
	'error',
];

/**
 * Error thrown when the server answers with data that does not match the contract.
 *
 * @return A 502 `invalid_response` error.
 */
export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid exam data.', 'ohmylms' ),
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

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function flag( value: unknown ): boolean {
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

function amount( value: unknown ): number {
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

function link( value: unknown ): string {
	const url = text( value );
	if ( url && ! /^https?:\/\//i.test( url ) ) {
		throw invalidResponse();
	}
	return url;
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

/**
 * Validate an exam list row.
 *
 * @param value Parsed JSON.
 * @return The exam.
 */
export function parseExam( value: unknown ): ExamSummary {
	const item = record( value );
	const revision = text( item.revision );
	if ( ! /^[0-9a-f]{40}$/.test( revision ) ) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		status: oneOf( item.status, STATUSES ),
		origin: oneOf( item.origin, ORIGINS ),
		job_id: count( item.job_id ),
		quiz_id: positiveId( item.quiz_id ),
		duration_minutes: count( item.duration_minutes ),
		total_marks: amount( item.total_marks ),
		question_count: count( item.question_count ),
		released: flag( item.released ),
		updated_at: text( item.updated_at ),
		revision,
	};
}

function header( value: unknown ): ExamHeader {
	const item = record( value );
	return Object.fromEntries(
		HEADER_FIELDS.map( ( field ) => [ field, text( item[ field ] ) ] )
	) as ExamHeader;
}

function paper( value: unknown ): PaperSettings {
	const item = record( value );
	return {
		size: oneOf( item.size, [ 'a4', 'letter' ] as const ),
		margin: count( item.margin ),
		font_size: count( item.font_size ),
		font: oneOf( item.font, [ 'sans', 'serif' ] as const ),
		numbering: oneOf( item.numbering, [ 'number', 'q' ] as const ),
		show_marks: flag( item.show_marks ),
		lines_per_mark: count( item.lines_per_mark ),
		page_numbers: flag( item.page_numbers ),
		default_view: flag( item.default_view ),
		versions: count( item.versions ),
	};
}

function compositionItem( value: unknown ): CompositionItem {
	const item = record( value );
	if ( 'section' === item.kind ) {
		return {
			kind: 'section',
			title: text( item.title ),
			new_page: flag( item.new_page ),
		};
	}
	if ( 'question' !== item.kind ) {
		throw invalidResponse();
	}
	return {
		kind: 'question',
		question_id: positiveId( item.question_id ),
		name: text( item.name ),
		type: text( item.type ),
		marks: amount( item.marks ),
		approved: flag( item.approved ),
		can_edit: flag( item.can_edit ),
	};
}

/**
 * Validate the editor view of an exam.
 *
 * @param value Parsed JSON.
 * @return The exam.
 */
export function parseExamDetail( value: unknown ): ExamDetail {
	const item = record( value );
	const timing = record( item.timing );
	const links = record( item.links );
	const permissions = record( item.permissions );
	return {
		...parseExam( item ),
		header: header( item.header ),
		instructions: text( item.instructions ),
		timing: {
			duration_minutes: count( timing.duration_minutes ),
			available_from: text( timing.available_from ),
			available_until: text( timing.available_until ),
			grace_seconds: count( timing.grace_seconds ),
			feedback_release: oneOf( timing.feedback_release, FEEDBACK ),
		},
		paper: paper( item.paper ),
		composition: list( item.composition ).map( compositionItem ),
		problems: list( item.problems ).map( text ),
		warnings: list( item.warnings ).map( text ),
		links: {
			player: link( links.player ),
			preview: link( links.preview ),
			editor: link( links.editor ),
			paper: link( links.paper ),
			key: link( links.key ),
		},
		permissions: {
			can_edit: flag( permissions.can_edit ),
			can_compose: flag( permissions.can_compose ),
			can_publish: flag( permissions.can_publish ),
		},
	};
}

/**
 * Validate a bank question row.
 *
 * @param value Parsed JSON.
 * @return The question.
 */
export function parseBankQuestion( value: unknown ): BankQuestion {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		name: text( item.name ),
		type: text( item.type ),
		marks: amount( item.marks ),
		approved: flag( item.approved ),
		available: flag( item.available ),
	};
}

/**
 * Validate an import job list row.
 *
 * @param value Parsed JSON.
 * @return The job.
 */
export function parseJob( value: unknown ): ImportJob {
	const item = record( value );
	const source = record( item.source );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		kind: oneOf( item.kind, [ 'pdf', 'rows' ] as const ),
		state: oneOf( item.state, STATES ),
		target: oneOf( item.target, TARGETS ),
		attempts: count( item.attempts ),
		error: text( item.error ),
		validation_error: text( item.validation_error ),
		source: { name: text( source.name ), bytes: count( source.bytes ) },
		has_mark_scheme: flag( item.has_mark_scheme ),
		revision: count( item.revision ),
		row_count: count( item.row_count ),
		accepted_count: count( item.accepted_count ),
		rejected_count: count( item.rejected_count ),
		question_ids: list( item.question_ids ).map( positiveId ),
		exam_id: count( item.exam_id ),
		quiz_id: count( item.quiz_id ),
		updated_at: text( item.updated_at ),
		can_extract: flag( item.can_extract ),
		can_apply: flag( item.can_apply ),
	};
}

function optionalText( value: unknown ): string | undefined {
	return undefined === value ? undefined : text( value );
}

function part( value: unknown ): ImportPart {
	const item = record( value );
	const result: ImportPart = {
		id: text( item.id ),
		marks: amount( item.marks ),
	};
	for ( const key of [
		'label',
		'prompt',
		'unit',
		'rubric',
		'skills',
	] as const ) {
		const found = optionalText( item[ key ] );
		if ( undefined !== found ) {
			result[ key ] = found;
		}
	}
	if ( undefined !== item.kind ) {
		result.kind = oneOf( item.kind, PART_KINDS );
	}
	if ( undefined !== item.answer ) {
		result.answer = amount( item.answer );
	}
	if ( undefined !== item.tolerance ) {
		result.tolerance = amount( item.tolerance );
	}
	if ( undefined !== item.accepted ) {
		result.accepted = list( item.accepted ).map( text );
	}
	return result;
}

function figure( value: unknown ): ImportFigure {
	const item = record( value );
	return {
		id: text( item.id ),
		page: positiveId( item.page ),
		bounds: list( item.bounds ).map( amount ),
		part_ids: list( item.part_ids ).map( text ),
		asset_id: text( item.asset_id ),
		alt: text( item.alt ),
		not_to_scale: flag( item.not_to_scale ),
		selected: oneOf( item.selected, [ 'original', 'jsxgraph' ] as const ),
		approved: flag( item.approved ),
		...( undefined !== item.spec ? { spec: item.spec } : {} ),
	};
}

function row( value: unknown ): ImportRow {
	const item = record( value );
	return {
		index: count( item.index ),
		number: text( item.number ),
		status: oneOf( item.status, ROW_STATUSES ),
		question: text( item.question ),
		page: positiveId( item.page ),
		requires_source: flag( item.requires_source ),
		notes: text( item.notes ),
		skills: text( item.skills ),
		parts: list( item.parts ).map( part ),
		figures: list( item.figures ).map( figure ),
		figures_complete: flag( item.figures_complete ),
		marks: amount( item.marks ),
	};
}

/**
 * Validate an import job with its review rows.
 *
 * @param value Parsed JSON.
 * @return The job.
 */
export function parseJobDetail( value: unknown ): ImportJobDetail {
	const item = record( value );
	const inventory = record( item.inventory );
	return {
		...parseJob( item ),
		inventory: {
			title: text( inventory.title ),
			duration_minutes: count( inventory.duration_minutes ),
			total_marks: amount( inventory.total_marks ),
			page_count: count( inventory.page_count ),
		},
		usage: { requests: count( record( item.usage ).requests ) },
		rows: list( item.rows ).map( row ),
	};
}

/**
 * Validate a figure crop.
 *
 * @param value Parsed JSON.
 * @return The crop.
 */
export function parseFigure( value: unknown ): FigureAsset {
	const item = record( value );
	return {
		id: text( item.id ),
		state: oneOf( item.state, FIGURE_STATES ),
		attempts: count( item.attempts ),
		error: text( item.error ),
		page: count( item.page ),
		bounds: list( item.bounds ).map( amount ),
		width: count( item.width ),
		height: count( item.height ),
		url: link( item.url ),
	};
}

/**
 * Validate a skill search match.
 *
 * @param value Parsed JSON.
 * @return The skill.
 */
export function parseSkill( value: unknown ): SkillMatch {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		name: text( item.name ),
		code: text( item.code ),
		path: text( item.path ),
	};
}

/**
 * Validate a CSV/JSON dry-run report.
 *
 * @param value Parsed JSON.
 * @return The report.
 */
export function parseDryRun( value: unknown ): DryRunReport {
	const item = record( value );
	if ( true !== item.dry_run ) {
		throw invalidResponse();
	}
	const counts = record( item.counts );
	return {
		dry_run: true,
		counts: Object.fromEntries(
			Object.entries( counts ).map( ( [ key, number ] ) => [
				key,
				count( number ),
			] )
		),
		rows: list( item.rows ).map( ( entry ) => {
			const result = record( entry );
			return {
				row: count( result.row ),
				status: oneOf( result.status, DRY_STATUSES ),
				errors: list( result.errors ).map( text ),
				warnings: list( result.warnings ).map( text ),
			};
		} ),
	};
}

// Settings form.

/**
 * `datetime-local` value (UTC) from an RFC 3339 time.
 *
 * @param iso RFC 3339 time or empty.
 * @return `YYYY-MM-DDTHH:MM`, or empty.
 */
export function toLocalInput( iso: string ): string {
	return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test( iso )
		? iso.slice( 0, 16 )
		: '';
}

/**
 * RFC 3339 UTC time from a `datetime-local` value read as UTC.
 *
 * @param value Input value.
 * @return RFC 3339 time, or empty.
 */
export function fromLocalInput( value: string ): string {
	return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test( value )
		? `${ value }:00Z`
		: '';
}

/**
 * Start state of the settings form.
 *
 * @param exam Loaded exam.
 * @return The form.
 */
export function formFromExam( exam: ExamDetail ): SettingsForm {
	return {
		title: exam.title,
		duration_minutes: String( exam.duration_minutes ),
		instructions: exam.instructions,
		header: { ...exam.header },
		available_from: toLocalInput( exam.timing.available_from ),
		available_until: toLocalInput( exam.timing.available_until ),
		grace_seconds: String( exam.timing.grace_seconds ),
		feedback_release: exam.timing.feedback_release,
		paper: { ...exam.paper },
	};
}

/**
 * Whether the form differs from the saved copy.
 *
 * @param form  Current form.
 * @param saved Saved form.
 * @return True when there are unsaved changes.
 */
export function isFormDirty(
	form: SettingsForm,
	saved: SettingsForm
): boolean {
	return JSON.stringify( form ) !== JSON.stringify( saved );
}

function wholeNumber( value: string, min: number, max: number ) {
	return /^\d{1,5}$/.test( value.trim() ) &&
		Number( value ) >= min &&
		Number( value ) <= max
		? Number( value )
		: null;
}

/**
 * Validate the settings form with the server's rules.
 *
 * @param form Form state.
 * @return Field errors; empty when valid.
 */
export function validateSettings( form: SettingsForm ): SettingsErrors {
	const errors: SettingsErrors = {};
	const title = form.title.trim();
	if ( ! title || Array.from( title ).length > LIMITS.title ) {
		errors.title = sprintf(
			/* translators: %d: maximum title length. */
			__( 'Enter a title of 1 to %d characters.', 'ohmylms' ),
			LIMITS.title
		);
	}
	if ( null === wholeNumber( form.duration_minutes, 1, LIMITS.duration ) ) {
		errors.duration_minutes = sprintf(
			/* translators: %d: longest duration in minutes. */
			__( 'Enter a duration from 1 to %d minutes.', 'ohmylms' ),
			LIMITS.duration
		);
	}
	if ( null === wholeNumber( form.grace_seconds, 0, LIMITS.grace ) ) {
		errors.grace_seconds = sprintf(
			/* translators: %d: longest grace period in seconds. */
			__( 'Enter a grace period from 0 to %d seconds.', 'ohmylms' ),
			LIMITS.grace
		);
	}
	if (
		form.available_from &&
		form.available_until &&
		form.available_until <= form.available_from
	) {
		errors.timing = __(
			'The closing time must be after the opening time.',
			'ohmylms'
		);
	}
	return errors;
}

/**
 * Request body of a valid settings form.
 *
 * @param form     Valid form.
 * @param revision Revision the edit started from.
 * @return The payload.
 */
export function toSettingsInput(
	form: SettingsForm,
	revision: string
): SettingsInput {
	return {
		revision,
		title: form.title.trim(),
		duration_minutes: Number( form.duration_minutes ),
		instructions: form.instructions,
		header: Object.fromEntries(
			HEADER_FIELDS.map( ( field ) => [
				field,
				form.header[ field ].trim(),
			] )
		) as ExamHeader,
		timing: {
			available_from: fromLocalInput( form.available_from ),
			available_until: fromLocalInput( form.available_until ),
			grace_seconds: Number( form.grace_seconds ),
			feedback_release: form.feedback_release,
		},
		paper: { ...form.paper },
	};
}

// Composition.

/**
 * Request items of a composition.
 *
 * @param items Composition.
 * @return Items as the server accepts them.
 */
export function toCompositionInput(
	items: readonly CompositionItem[]
): CompositionInput[] {
	return items.map( ( item ) =>
		'section' === item.kind
			? {
					kind: 'section',
					title: item.title.trim(),
					new_page: item.new_page,
				}
			: { kind: 'question', question_id: item.question_id }
	);
}

/**
 * Move an item up or down by one place.
 *
 * @param items  Composition.
 * @param index  Item to move.
 * @param offset -1 (up) or 1 (down).
 * @return A new list; the same list when the move is out of range.
 */
export function moveItem< T >(
	items: readonly T[],
	index: number,
	offset: -1 | 1
): T[] {
	const target = index + offset;
	if (
		index < 0 ||
		index >= items.length ||
		target < 0 ||
		target >= items.length
	) {
		return [ ...items ];
	}
	const next = [ ...items ];
	const [ moved ] = next.splice( index, 1 );
	next.splice( target, 0, moved as T );
	return next;
}

/**
 * Add bank questions at the end, skipping those already in the exam.
 *
 * @param items     Composition.
 * @param questions Questions to add.
 * @return A new list.
 */
export function addQuestions(
	items: readonly CompositionItem[],
	questions: readonly BankQuestion[]
): CompositionItem[] {
	const present = new Set(
		items.flatMap( ( item ) =>
			'question' === item.kind ? [ item.question_id ] : []
		)
	);
	return [
		...items,
		...questions
			.filter( ( question ) => ! present.has( question.id ) )
			.map( ( question ): CompositionItem => ( {
				kind: 'question',
				question_id: question.id,
				name: question.name,
				type: question.type,
				marks: question.marks,
				approved: question.approved,
				can_edit: true,
			} ) ),
	];
}

/**
 * Problems the server would refuse in a composition.
 *
 * @param items Composition.
 * @return A message, or null when valid.
 */
export function compositionProblem(
	items: readonly CompositionItem[]
): string | null {
	if (
		items.some( ( item ) => 'section' === item.kind && ! item.title.trim() )
	) {
		return __( 'Give every section a title.', 'ohmylms' );
	}
	const questions = items.filter( ( item ) => 'question' === item.kind );
	if ( questions.length > LIMITS.questions ) {
		return sprintf(
			/* translators: %d: largest number of questions. */
			__( 'An exam holds at most %d questions.', 'ohmylms' ),
			LIMITS.questions
		);
	}
	return null;
}

/**
 * Total marks of the questions in a composition.
 *
 * @param items Composition.
 * @return Sum of question marks.
 */
export function compositionMarks( items: readonly CompositionItem[] ): number {
	return items.reduce(
		( sum, item ) => sum + ( 'question' === item.kind ? item.marks : 0 ),
		0
	);
}

/**
 * Add a skill reference to a skill list such as `#12;#40`.
 *
 * @param skills Current list.
 * @param id     Skill ID.
 * @return The list with `#id`, once.
 */
export function withSkill( skills: string, id: number ): string {
	const tokens = skills
		.split( /[;,]/ )
		.map( ( token ) => token.trim() )
		.filter( Boolean );
	return (
		tokens.includes( `#${ id }` ) ? tokens : [ ...tokens, `#${ id }` ]
	).join( ';' );
}

// Labels.

/**
 * Translated exam status.
 *
 * @param status Status.
 * @return Label.
 */
export function statusLabel( status: ExamStatus ): string {
	return 'publish' === status
		? __( 'Published', 'ohmylms' )
		: __( 'Draft', 'ohmylms' );
}

/**
 * Translated job state.
 *
 * @param state State.
 * @return Label.
 */
export function stateLabel( state: JobState ): string {
	const labels: Record< JobState, string > = {
		queued: __( 'Checking the upload', 'ohmylms' ),
		failed: __( 'Upload check failed', 'ohmylms' ),
		ready_for_extraction: __( 'Ready to extract', 'ohmylms' ),
		extracting: __( 'Extracting questions', 'ohmylms' ),
		extraction_failed: __( 'Extraction failed', 'ohmylms' ),
		ready_for_review: __( 'Waiting for review', 'ohmylms' ),
		committed: __( 'Saved to the question bank', 'ohmylms' ),
	};
	return labels[ state ];
}

/** How often the review screen polls a job the server is still working on. */
export const POLL_MS = 4000;

/**
 * Whether the server is still working on a job, so the review screen should poll.
 *
 * @param state State.
 * @return True while queued or extracting.
 */
export function isWorking( state: JobState ): boolean {
	return 'queued' === state || 'extracting' === state;
}

/**
 * Translated row status.
 *
 * @param status Status.
 * @return Label.
 */
export function rowStatusLabel( status: RowStatus ): string {
	if ( 'accepted' === status ) {
		return __( 'Accepted', 'ohmylms' );
	}
	return 'rejected' === status
		? __( 'Rejected', 'ohmylms' )
		: __( 'Needs review', 'ohmylms' );
}

/**
 * Translated import target.
 *
 * @param target Target.
 * @return Label.
 */
export function targetLabel( target: ImportTarget ): string {
	if ( 'quiz' === target ) {
		return __( 'A quiz', 'ohmylms' );
	}
	return 'questions' === target
		? __( 'Question bank only', 'ohmylms' )
		: __( 'An exam', 'ohmylms' );
}

/**
 * Format an ISO date for display.
 *
 * @param iso ISO 8601 date, or empty.
 * @return Localised date and time, or a dash.
 */
export function formatDate( iso: string ): string {
	const date = new Date( iso );
	return ! iso || Number.isNaN( date.getTime() )
		? '—'
		: date.toLocaleString( undefined, {
				dateStyle: 'medium',
				timeStyle: 'short',
			} );
}

/**
 * Route of an exam editor.
 *
 * @param id Exam ID.
 * @return In-app path.
 */
export function examPath( id: number ): string {
	return `/exams/${ id }`;
}

/**
 * Route of an import review.
 *
 * @param id Job ID.
 * @return In-app path.
 */
export function reviewPath( id: number ): string {
	return `/exams/imports/${ id }`;
}
