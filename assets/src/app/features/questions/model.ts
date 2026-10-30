import { __, _n, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type {
	Difficulty,
	FormErrors,
	FormOption,
	FormState,
	MatchingData,
	OptionInput,
	QuestionCreateInput,
	QuestionDetail,
	QuestionLifecycle,
	QuestionOption,
	QuestionSettings,
	QuestionStatus,
	QuestionSummary,
	QuestionType,
	QuestionUpdateInput,
	QuestionUsage,
	QuestionVersion,
	StatusFilter,
	UserRef,
} from './types';

const STATUSES: readonly QuestionStatus[] = [ 'draft', 'approved', 'archived' ];
const ANSWERS = [ 'options', 'text' ] as const;

/** Difficulty bands, easiest first. */
export const DIFFICULTIES: readonly Difficulty[] = [
	'easy',
	'standard',
	'challenge',
];

/** Built-in option types whose options this editor edits; the server checks their option rules. */
export const OPTION_EDITOR_TYPES = [
	'single-choice',
	'multiple-choice',
	'true-false',
	'reorder',
	'matching',
] as const;

/** Longest question name the server accepts. */
export const MAX_NAME = 500;

/** Longest question body the server accepts. */
export const MAX_DESCRIPTION = 65535;

/** Most options per question. */
export const MAX_OPTIONS = 100;

/** Longest option text. */
export const MAX_ANSWER = 10000;

/** Longest matching label. */
export const MAX_LABEL = 1000;

/**
 * Build the error thrown when the server answers with data that does not match the contract.
 *
 * @return A 502 `invalid_response` error.
 */
export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid question data.', 'ohmylms' ),
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

function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( item ) => item === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}

function list( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
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

function integer( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isSafeInteger( value ) ) {
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

function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}

/**
 * An object member that PHP may also encode as an empty array (or omit as `null`).
 *
 * @param value Parsed JSON.
 * @return The object; empty for `null` or an empty list.
 */
function looseRecord( value: unknown ): Record< string, unknown > {
	if ( null === value || ( Array.isArray( value ) && ! value.length ) ) {
		return {};
	}
	return record( value );
}

function parseUserRef( value: unknown ): UserRef {
	const item = record( value );
	return { id: count( item.id ), name: text( item.name ) };
}

function parseUsage( value: unknown ): QuestionUsage {
	const item = record( value );
	return {
		quizzes: count( item.quizzes ),
		responses: count( item.responses ),
	};
}

function parseLifecycle( item: Record< string, unknown > ): QuestionLifecycle {
	return {
		status: oneOf( item.status, STATUSES ),
		post_status: text( item.post_status ),
		version: count( item.version ),
		current_version_id: count( item.current_version_id ),
		approved_version_id: count( item.approved_version_id ),
		approved_is_current: boolean( item.approved_is_current ),
	};
}

/**
 * Validate a list row.
 *
 * @param value Parsed JSON.
 * @return The summary.
 */
export function parseQuestionSummary( value: unknown ): QuestionSummary {
	const item = record( value );
	const permissions = record( item.permissions );
	return {
		id: positiveId( item.id ),
		uuid: text( item.uuid ),
		name: text( item.name ),
		type: text( item.type ),
		type_label: text( item.type_label ),
		marks: finite( item.marks ),
		...parseLifecycle( item ),
		bank_id: count( item.bank_id ),
		difficulty: text( item.difficulty ),
		source: text( item.source ),
		family_id: text( item.family_id ),
		secure: boolean( item.secure ),
		skills: list( item.skills ).map( positiveId ),
		usage: parseUsage( item.usage ),
		author: parseUserRef( item.author ),
		modified: text( item.modified ),
		permissions: {
			can_edit: boolean( permissions.can_edit ),
			can_approve: boolean( permissions.can_approve ),
		},
	};
}

function parseMatching( value: unknown ): MatchingData | null {
	if ( null === value ) {
		return null;
	}
	const item = looseRecord( value );
	const matching: MatchingData = {};
	if ( undefined !== item.label ) {
		matching.label = text( item.label );
	}
	if ( undefined !== item.image_url ) {
		matching.image_url = text( item.image_url );
	}
	return matching;
}

/**
 * Validate one answer option of the editor view.
 *
 * @param value Parsed JSON.
 * @return The option.
 */
export function parseQuestionOption( value: unknown ): QuestionOption {
	const item = record( value );
	return {
		id: count( item.id ),
		answer: text( item.answer ),
		is_correct: boolean( item.is_correct ),
		order_number: integer( item.order_number ),
		image_id: count( item.image_id ),
		image_url: text( item.image_url ),
		matching_data: parseMatching( item.matching_data ),
	};
}

/**
 * Validate the editor view of a question.
 *
 * @param value Parsed JSON.
 * @return The question with settings, options and permissions.
 */
export function parseQuestionDetail( value: unknown ): QuestionDetail {
	const item = record( value );
	const bank = record( item.bank );
	const permissions = record( item.permissions );
	return {
		id: positiveId( item.id ),
		uuid: text( item.uuid ),
		name: text( item.name ),
		description: text( item.description ),
		type: text( item.type ),
		settings: looseRecord( item.settings ),
		options: list( item.options ).map( parseQuestionOption ),
		skills: looseRecord( item.skills ),
		bank: {
			bank_id: count( bank.bank_id ),
			difficulty: text( bank.difficulty ),
			source: text( bank.source ),
			family_id: text( bank.family_id ),
			secure: boolean( bank.secure ),
		},
		thumbnail_id: count( item.thumbnail_id ),
		video_id: count( item.video_id ),
		extension_settings: looseRecord( item.extension_settings ),
		...parseLifecycle( item ),
		usage: parseUsage( item.usage ),
		quizzes: list( item.quizzes ).map( ( raw ) => {
			const quiz = record( raw );
			return {
				id: positiveId( quiz.id ),
				name: text( quiz.name ),
				can_edit: boolean( quiz.can_edit ),
			};
		} ),
		permissions: {
			can_edit: boolean( permissions.can_edit ),
			can_approve: boolean( permissions.can_approve ),
			can_delete: boolean( permissions.can_delete ),
		},
		modified: text( item.modified ),
	};
}

/**
 * Validate one version row.
 *
 * @param value Parsed JSON.
 * @return The version.
 */
export function parseQuestionVersion( value: unknown ): QuestionVersion {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		version_no: count( item.version_no ),
		created_at: text( item.created_at ),
		created_by: parseUserRef( item.created_by ),
		is_current: boolean( item.is_current ),
		is_approved: boolean( item.is_approved ),
	};
}

/**
 * Validate one registered question type.
 *
 * @param value Parsed JSON.
 * @return The type.
 */
export function parseQuestionType( value: unknown ): QuestionType {
	const item = record( value );
	return {
		id: text( item.id ),
		label: text( item.label ),
		format: nullableText( item.format ),
		answers: null === item.answers ? null : oneOf( item.answers, ANSWERS ),
		manual: boolean( item.manual ),
		snapshot: boolean( item.snapshot ),
		builtin: boolean( item.builtin ),
	};
}

/**
 * Validate the type list.
 *
 * @param value Parsed JSON.
 * @return The types.
 */
export function parseQuestionTypes( value: unknown ): QuestionType[] {
	return list( value ).map( parseQuestionType );
}

/**
 * Whether this editor edits the options of a type.
 *
 * @param type Question type id.
 * @return True for the built-in option types with structural rules.
 */
export function editsOptions( type: string ): boolean {
	return ( OPTION_EDITOR_TYPES as readonly string[] ).includes( type );
}

/**
 * How the correct answer is marked for a type.
 *
 * @param type Question type id.
 * @return `single` (one correct option), `multiple`, or `none` (reorder and matching mark answers by
 *         position or pair).
 */
export function correctMode( type: string ): 'single' | 'multiple' | 'none' {
	if ( 'single-choice' === type || 'true-false' === type ) {
		return 'single';
	}
	return 'multiple-choice' === type ? 'multiple' : 'none';
}

/**
 * Translated label of a difficulty.
 *
 * @param value Stored difficulty.
 * @return The label; the value itself when unknown.
 */
export function difficultyLabel( value: string ): string {
	switch ( value ) {
		case 'easy':
			return __( 'Easy', 'ohmylms' );
		case 'standard':
			return __( 'Standard', 'ohmylms' );
		case 'challenge':
			return __( 'Challenge', 'ohmylms' );
		default:
			return value;
	}
}

/**
 * Choices of the status filter.
 *
 * @return Value/label pairs; `active` first.
 */
export function statusFilters(): { value: StatusFilter; label: string }[] {
	return [
		{ value: 'active', label: __( 'Active', 'ohmylms' ) },
		{ value: 'draft', label: __( 'Not approved', 'ohmylms' ) },
		{ value: 'approved', label: __( 'Approved', 'ohmylms' ) },
		{ value: 'archived', label: __( 'Archived', 'ohmylms' ) },
	];
}

/**
 * Label of a type from the type list.
 *
 * @param types    Registered types.
 * @param id       Type id.
 * @param fallback Label to use when the type is not in the list.
 * @return The label, the fallback, or the id.
 */
export function typeLabel(
	types: readonly QuestionType[],
	id: string,
	fallback = ''
): string {
	return types.find( ( type ) => type.id === id )?.label || fallback || id;
}

/**
 * Describe the version state of a question, such as "v3 · approved".
 *
 * @param item Lifecycle fields.
 * @return Translated text.
 */
export function versionLabel( item: QuestionLifecycle ): string {
	let state: string;
	if ( 'archived' === item.status ) {
		state = __( 'archived', 'ohmylms' );
	} else if ( item.approved_version_id > 0 && ! item.approved_is_current ) {
		state = __( 'newer than approved', 'ohmylms' );
	} else if ( 'approved' === item.status ) {
		state = __( 'approved', 'ohmylms' );
	} else {
		state = __( 'draft', 'ohmylms' );
	}
	return sprintf(
		/* translators: 1: version number, 2: version state such as "approved". */
		__( 'v%1$d · %2$s', 'ohmylms' ),
		item.version,
		state
	);
}

/**
 * Describe how often a question is used, such as "2 quizzes · 14 responses".
 *
 * @param item       Question with usage counts.
 * @param item.usage Usage counts.
 * @return Translated text.
 */
export function usageLabel( item: { usage: QuestionUsage } ): string {
	const { quizzes, responses } = item.usage;
	return [
		sprintf(
			/* translators: %d: number of quizzes. */
			_n( '%d quiz', '%d quizzes', quizzes, 'ohmylms' ),
			quizzes
		),
		sprintf(
			/* translators: %d: number of learner responses. */
			_n( '%d response', '%d responses', responses, 'ohmylms' ),
			responses
		),
	].join( ' · ' );
}

/**
 * Format an RFC 3339 time for display.
 *
 * @param value  Time from the server; may be empty.
 * @param locale BCP 47 locale; the browser default when omitted.
 * @return Localised date and time, or a dash when the value is empty or invalid.
 */
export function formatDateTime( value: string, locale?: string ): string {
	const time = value ? Date.parse( value ) : NaN;
	if ( Number.isNaN( time ) ) {
		return '—';
	}
	return new Intl.DateTimeFormat( locale, {
		dateStyle: 'medium',
		timeStyle: 'short',
	} ).format( time );
}

/**
 * URL of the question bank in the existing admin, where every type can be edited.
 *
 * @param adminUrl Site admin URL ending with a slash.
 * @return Absolute URL.
 */
export function legacyEditorUrl( adminUrl: string ): string {
	return `${ adminUrl }admin.php?page=ohmylms#/content-hub/question-bank`;
}

function option(
	answer: string,
	isCorrect: boolean,
	orderNumber: number
): OptionInput {
	return { answer, is_correct: isCorrect, order_number: orderNumber };
}

/**
 * Body that creates a valid minimal question of a type: one point and, for the option types, two
 * options that satisfy the server's option rules.
 *
 * @param name Question name.
 * @param type Question type id.
 * @return The create payload.
 */
export function createInput( name: string, type: string ): QuestionCreateInput {
	const input: QuestionCreateInput = {
		name: name.trim(),
		settings: { type, score: { enabled: true, value: 1 } },
	};
	switch ( type ) {
		case 'single-choice':
		case 'multiple-choice':
			input.options = [
				option( __( 'Option 1', 'ohmylms' ), true, 0 ),
				option( __( 'Option 2', 'ohmylms' ), false, 1 ),
			];
			break;
		case 'true-false':
			input.options = [
				option( __( 'True', 'ohmylms' ), true, 0 ),
				option( __( 'False', 'ohmylms' ), false, 1 ),
			];
			break;
		case 'reorder':
			input.options = [
				option( __( 'Option 1', 'ohmylms' ), false, 0 ),
				option( __( 'Option 2', 'ohmylms' ), false, 1 ),
			];
			break;
		case 'matching':
			input.options = [
				{
					...option( __( 'Option 1', 'ohmylms' ), false, 0 ),
					matching_data: { label: __( 'Match 1', 'ohmylms' ) },
				},
				{
					...option( __( 'Option 2', 'ohmylms' ), false, 1 ),
					matching_data: { label: __( 'Match 2', 'ohmylms' ) },
				},
			];
			break;
	}
	return input;
}

function pointsFrom( settings: QuestionSettings ): string {
	const score = settings.score;
	if ( ! score || 'object' !== typeof score || Array.isArray( score ) ) {
		return '';
	}
	const { enabled, value } = score as Record< string, unknown >;
	if ( ! enabled ) {
		return '';
	}
	if ( 'number' === typeof value && Number.isFinite( value ) ) {
		return String( value );
	}
	return 'string' === typeof value ? value : '';
}

/**
 * Start state of the editor form.
 *
 * @param question Editor view.
 * @return Form state.
 */
export function formFromQuestion( question: QuestionDetail ): FormState {
	return {
		name: question.name,
		description: question.description,
		points: pointsFrom( question.settings ),
		difficulty: question.bank.difficulty,
		settings: question.settings,
		options: [ ...question.options ]
			.sort( ( a, b ) => a.order_number - b.order_number )
			.map( ( item, index ) => ( {
				key: item.id > 0 ? `option-${ item.id }` : `loaded-${ index }`,
				id: item.id,
				answer: item.answer,
				is_correct: item.is_correct,
				image_id: item.image_id,
				matching: item.matching_data,
			} ) ),
	};
}

let nextKey = 0;

/**
 * A new, empty option for the options editor.
 *
 * @param type Question type id; matching options start with an empty label.
 * @return The option.
 */
export function newOption( type: string ): FormOption {
	nextKey += 1;
	return {
		key: `new-${ nextKey }`,
		id: 0,
		answer: '',
		is_correct: false,
		image_id: 0,
		matching: 'matching' === type ? { label: '' } : null,
	};
}

/**
 * Move an option up or down.
 *
 * @param options Options in order.
 * @param index   Option to move.
 * @param offset  -1 to move up, 1 to move down.
 * @return The reordered options; unchanged when the move is out of range.
 */
export function moveOption(
	options: readonly FormOption[],
	index: number,
	offset: number
): FormOption[] {
	const target = index + offset;
	const next = [ ...options ];
	const moved = next[ index ];
	const other = next[ target ];
	if ( ! moved || ! other ) {
		return next;
	}
	next[ index ] = other;
	next[ target ] = moved;
	return next;
}

/**
 * Mark an option correct or not. With one correct answer, marking an option clears the others.
 *
 * @param options Options in order.
 * @param index   Option to change.
 * @param checked New state.
 * @param type    Question type id.
 * @return The updated options.
 */
export function setCorrect(
	options: readonly FormOption[],
	index: number,
	checked: boolean,
	type: string
): FormOption[] {
	const single = 'single' === correctMode( type );
	return options.map( ( item, position ) => {
		if ( position === index ) {
			return { ...item, is_correct: checked };
		}
		return single && checked ? { ...item, is_correct: false } : item;
	} );
}

/**
 * Whether the form differs from its saved copy.
 *
 * @param current Current form.
 * @param saved   Saved form.
 * @return True when there are unsaved changes.
 */
export function isDirty( current: FormState, saved: FormState ): boolean {
	return JSON.stringify( current ) !== JSON.stringify( saved );
}

/**
 * Whether there are no field errors.
 *
 * @param errors Result of {@link validateForm}.
 * @return True when valid.
 */
export function isValid( errors: FormErrors ): boolean {
	return 0 === Object.keys( errors ).length;
}

function plain( html: string ): string {
	return html.replace( /<[^>]*>/g, '' ).trim();
}

/**
 * Validate the form with the same rules as the server (which stays authoritative).
 *
 * @param form  Form state.
 * @param saved Saved form; a question without points may stay without points.
 * @param type  Question type id; option rules apply to the built-in option types only.
 * @return Errors keyed by field; empty when valid.
 */
export function validateForm(
	form: FormState,
	saved: FormState,
	type: string
): FormErrors {
	const errors: FormErrors = {};
	const name = form.name.trim();
	if ( ! name || Array.from( name ).length > MAX_NAME ) {
		errors.name = sprintf(
			/* translators: %d: maximum name length. */
			__( 'Enter a question of 1 to %d characters.', 'ohmylms' ),
			MAX_NAME
		);
	}
	if ( form.description.length > MAX_DESCRIPTION ) {
		errors.description = sprintf(
			/* translators: %d: maximum length. */
			__( 'Use at most %d characters.', 'ohmylms' ),
			MAX_DESCRIPTION
		);
	}
	const points = form.points.trim();
	if (
		( points || saved.points.trim() ) &&
		! /^\d+(\.\d+)?$/.test( points )
	) {
		errors.points = __( 'Enter points of zero or more.', 'ohmylms' );
	}
	if (
		form.difficulty !== saved.difficulty &&
		! ( DIFFICULTIES as readonly string[] ).includes( form.difficulty )
	) {
		errors.difficulty = __( 'Choose a difficulty.', 'ohmylms' );
	}
	if ( editsOptions( type ) ) {
		validateOptions( form.options, type, errors );
	}
	return errors;
}

function validateOptions(
	options: readonly FormOption[],
	type: string,
	errors: FormErrors
) {
	const filled = options.filter(
		( item ) => plain( item.answer ) || item.image_id > 0
	);
	const correct = options.filter( ( item ) => item.is_correct ).length;
	if ( options.length > MAX_OPTIONS ) {
		errors.options = sprintf(
			/* translators: %d: maximum number of options. */
			__( 'Use at most %d options.', 'ohmylms' ),
			MAX_OPTIONS
		);
	} else if ( filled.length < 2 ) {
		errors.options = __( 'Add at least two answer options.', 'ohmylms' );
	} else if ( 'true-false' === type && 2 !== options.length ) {
		errors.options = __(
			'A true/false question has exactly two options.',
			'ohmylms'
		);
	} else if ( 'single' === correctMode( type ) && 1 !== correct ) {
		errors.options = __( 'Mark exactly one option as correct.', 'ohmylms' );
	} else if ( 'multiple' === correctMode( type ) && correct < 1 ) {
		errors.options = __(
			'Mark at least one option as correct.',
			'ohmylms'
		);
	}
	options.forEach( ( item, index ) => {
		if ( item.answer.length > MAX_ANSWER ) {
			errors[ `options.${ index }.answer` ] = sprintf(
				/* translators: %d: maximum length. */
				__( 'Use at most %d characters.', 'ohmylms' ),
				MAX_ANSWER
			);
		}
		const label = item.matching?.label ?? '';
		if ( 'matching' === type && ! label.trim() ) {
			errors[ `options.${ index }.label` ] = __(
				'Enter the matching answer for this option.',
				'ohmylms'
			);
		} else if ( label.length > MAX_LABEL ) {
			errors[ `options.${ index }.label` ] = sprintf(
				/* translators: %d: maximum length. */
				__( 'Use at most %d characters.', 'ohmylms' ),
				MAX_LABEL
			);
		}
	} );
}

/**
 * Options as the server accepts them; the position becomes the order number.
 *
 * @param options Edited options in order.
 * @return Option payload.
 */
export function optionInputs( options: readonly FormOption[] ): OptionInput[] {
	return options.map( ( item, index ) => {
		const input: OptionInput = {
			answer: item.answer,
			is_correct: item.is_correct,
			order_number: index,
		};
		if ( item.id > 0 ) {
			input.id = item.id;
		}
		if ( item.image_id > 0 ) {
			input.image_id = item.image_id;
		}
		if ( item.matching ) {
			const matching: MatchingData = {};
			if ( undefined !== item.matching.label ) {
				matching.label = item.matching.label;
			}
			if ( item.matching.image_url ) {
				matching.image_url = item.matching.image_url;
			}
			input.matching_data = matching;
		}
		return input;
	} );
}

/**
 * The settings to save: the edited settings with the type stamped and, when points are set, a
 * score of that many points.
 *
 * @param form Form state.
 * @param type Question type id.
 * @return Settings payload.
 */
export function settingsFor( form: FormState, type: string ): QuestionSettings {
	const settings: QuestionSettings = { ...form.settings, type };
	const points = form.points.trim();
	if ( points ) {
		const score = form.settings.score;
		settings.score = {
			...( score && 'object' === typeof score && ! Array.isArray( score )
				? score
				: {} ),
			enabled: true,
			value: Number( points ),
		};
	}
	return settings;
}

/**
 * Convert form state into a partial update. Call after {@link validateForm} reports no errors.
 *
 * @param form         Form state.
 * @param saved        Saved form the edits started from.
 * @param type         Question type id.
 * @param baseModified `modified` of the last read, for the stale-save check.
 * @return Only the changed fields, plus `base_modified`.
 */
export function toInput(
	form: FormState,
	saved: FormState,
	type: string,
	baseModified: string
): QuestionUpdateInput {
	const input: QuestionUpdateInput = { base_modified: baseModified };
	if ( form.name !== saved.name ) {
		input.name = form.name.trim();
	}
	if ( form.description !== saved.description ) {
		input.description = form.description;
	}
	if (
		form.points.trim() !== saved.points.trim() ||
		JSON.stringify( form.settings ) !== JSON.stringify( saved.settings )
	) {
		input.settings = settingsFor( form, type );
	}
	const options = optionInputs( form.options );
	if (
		JSON.stringify( options ) !==
		JSON.stringify( optionInputs( saved.options ) )
	) {
		input.options = options;
	}
	if ( form.difficulty !== saved.difficulty ) {
		input.bank = { difficulty: form.difficulty };
	}
	return input;
}

function detailField( error: ApiError ): string | undefined {
	const details = error.details;
	if ( ! details || 'object' !== typeof details ) {
		return undefined;
	}
	const field = ( details as Record< string, unknown > ).field;
	return 'string' === typeof field ? field : undefined;
}

function invalidParam(
	error: ApiError
): { param: string; message: string } | undefined {
	const details = error.details;
	const params =
		details && 'object' === typeof details
			? ( details as Record< string, unknown > ).params
			: undefined;
	if ( ! params || 'object' !== typeof params ) {
		return undefined;
	}
	const [ entry ] = Object.entries( params as Record< string, unknown > );
	if ( ! entry ) {
		return undefined;
	}
	const [ param, message ] = entry;
	return {
		param,
		message: 'string' === typeof message ? message : error.message,
	};
}

const PARAM_FIELDS: Record< string, string > = {
	name: 'name',
	description: 'description',
	settings: 'settings',
	options: 'options',
	bank: 'difficulty',
};

/**
 * Map a server rejection to the form field it concerns.
 *
 * @param error Error from the API.
 * @return The field and message, or `null` when it is not about one field.
 */
export function serverFieldError(
	error: ApiError
): { path: string; message: string } | null {
	const message = error.message;
	switch ( error.code ) {
		case 'ohmylms_question_invalid_name':
			return { path: 'name', message };
		case 'ohmylms_bank_invalid':
			return { path: 'difficulty', message };
		case 'ohmylms_invalid_question_settings':
			return { path: 'settings', message };
		case 'ohmylms_question_invalid_options':
		case 'ohmylms_question_invalid_media': {
			const field = detailField( error ) ?? '';
			const label = /^options\[(\d+)\]\.matching_data\.label$/.exec(
				field
			);
			if ( label ) {
				return { path: `options.${ label[ 1 ] }.label`, message };
			}
			return field.startsWith( 'options' )
				? { path: 'options', message }
				: null;
		}
		case 'rest_invalid_param': {
			const invalid = invalidParam( error );
			const path = invalid ? PARAM_FIELDS[ invalid.param ] : undefined;
			return invalid && path ? { path, message: invalid.message } : null;
		}
		default:
			return null;
	}
}
