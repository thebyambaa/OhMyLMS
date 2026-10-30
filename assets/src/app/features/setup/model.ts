import { __ } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import {
	CURRENCY_POSITIONS,
	EXPERIENCES,
	NICHES,
	STATUSES,
	STEP_IDS,
	type CurrencyOption,
	type Experience,
	type FailureView,
	type FieldErrors,
	type Niche,
	type PageAction,
	type PageStatus,
	type SetupState,
	type StarterPage,
	type StepId,
	type StepInfo,
	type StepResponse,
	type StepResult,
	type StepStatus,
	type WizardStatus,
} from './types';

/** Limits mirrored from the server schema (`SetupSteps`, see contract.json). */
export const LIMITS = {
	maxTopics: 10,
	maxTopicChars: 60,
	maxDecimals: 8,
} as const;

// ---- Runtime validation of server data ------------------------------------------------------

export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid setup data.', 'ohmylms' ),
	} );
}

type Row = Record< string, unknown >;

function isRow( value: unknown ): value is Row {
	return (
		'object' === typeof value && null !== value && ! Array.isArray( value )
	);
}

function oneOf< T extends string >(
	list: readonly T[],
	value: unknown
): T | undefined {
	return list.find( ( item ) => item === value );
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function whole( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isInteger( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}

function asList( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function parseStep( raw: unknown ): StepInfo {
	if ( ! isRow( raw ) ) {
		throw invalidResponse();
	}
	const id = oneOf( STEP_IDS, raw.id );
	const status = oneOf< StepStatus >(
		[ 'pending', 'done', 'skipped' ],
		raw.status
	);
	if (
		! id ||
		! status ||
		'boolean' !== typeof raw.skippable ||
		'boolean' !== typeof raw.available
	) {
		throw invalidResponse();
	}
	return {
		id,
		status,
		skippable: raw.skippable,
		available: raw.available,
	};
}

function parsePage( raw: unknown ): StarterPage {
	if ( ! isRow( raw ) ) {
		throw invalidResponse();
	}
	const status = oneOf< PageStatus >(
		[ 'ready', 'adoptable', 'missing' ],
		raw.status
	);
	if ( ! status ) {
		throw invalidResponse();
	}
	return {
		key: text( raw.key ),
		title: text( raw.title ),
		slug: text( raw.slug ),
		page_id: whole( raw.page_id ),
		status,
	};
}

/**
 * Validate the state returned by the server.
 *
 * @param  raw Decoded JSON.
 * @return Typed state.
 * @throws {ApiError} `invalid_response` when the shape is wrong.
 */
export function parseState( raw: unknown ): SetupState {
	if (
		! isRow( raw ) ||
		! isRow( raw.answers ) ||
		! isRow( raw.preferences )
	) {
		throw invalidResponse();
	}
	const status: WizardStatus | undefined = oneOf( STATUSES, raw.status );
	const current =
		null === raw.current_step ? null : oneOf( STEP_IDS, raw.current_step );
	const experience =
		null === raw.answers.experience
			? null
			: oneOf( EXPERIENCES, raw.answers.experience );
	const niche =
		null === raw.answers.niche ? null : oneOf( NICHES, raw.answers.niche );
	if (
		! status ||
		undefined === current ||
		undefined === experience ||
		undefined === niche
	) {
		throw invalidResponse();
	}
	return {
		version: whole( raw.version ),
		status,
		currentStep: current,
		steps: asList( raw.steps ).map( parseStep ),
		answers: {
			experience,
			niche,
			topics: asList( raw.answers.topics ).map( text ),
		},
		preferences: {
			currency: text( raw.preferences.currency ),
			currency_pos: text( raw.preferences.currency_pos ),
			price_num_decimals: whole( raw.preferences.price_num_decimals ),
		},
		starterPages: asList( raw.starter_pages ).map( parsePage ),
		startedAt: nullableText( raw.started_at ),
		completedAt: nullableText( raw.completed_at ),
	};
}

function parseResult( raw: unknown ): StepResult {
	if ( ! isRow( raw ) ) {
		throw invalidResponse();
	}
	return {
		changed: asList( raw.changed ).map( text ),
		pages: asList( raw.pages ).map( ( item ): PageAction => {
			if ( ! isRow( item ) ) {
				throw invalidResponse();
			}
			const action = oneOf< PageAction[ 'action' ] >(
				[ 'kept', 'adopted', 'created' ],
				item.action
			);
			if ( ! action ) {
				throw invalidResponse();
			}
			return {
				key: text( item.key ),
				page_id: whole( item.page_id ),
				action,
			};
		} ),
	};
}

export function parseStepResponse( raw: unknown ): StepResponse {
	if ( ! isRow( raw ) ) {
		throw invalidResponse();
	}
	return {
		state: parseState( raw.state ),
		result: parseResult( raw.result ),
	};
}

/**
 * Validate a wizard-level response (`{ state }`).
 *
 * @param raw Decoded JSON.
 * @return Typed state.
 */
export function parseStateResponse( raw: unknown ): SetupState {
	if ( ! isRow( raw ) ) {
		throw invalidResponse();
	}
	return parseState( raw.state );
}

export function parseCurrencies( raw: unknown ): CurrencyOption[] {
	return asList( raw ).map( ( item ) => {
		if ( ! isRow( item ) ) {
			throw invalidResponse();
		}
		return {
			code: text( item.code ),
			name: text( item.name ),
			symbol: text( item.symbol ),
		};
	} );
}

// ---- State machine -------------------------------------------------------------------------

/**
 * Whether a status means the wizard is finished.
 * @param state
 */
export function isCompleted( state: SetupState ): boolean {
	return 'completed' === state.status;
}

/** The last screen after all steps, where the wizard is completed. */
export const FINISH = 'finish';

/** A wizard screen: a server step or the final review. */
export type Screen = StepId | typeof FINISH;

/** Screens in order. */
export const SCREENS: readonly Screen[] = [ ...STEP_IDS, FINISH ];

/**
 * The screen to show: the one in the URL when it exists, otherwise the first unresolved step, and the
 * final screen when everything is resolved.
 *
 * @param param Raw `step` query parameter.
 * @param state Wizard state.
 * @return A screen.
 */
export function resolveStep( param: string | null, state: SetupState ): Screen {
	return (
		SCREENS.find( ( screen ) => screen === param ) ??
		state.currentStep ??
		FINISH
	);
}

export function stepIndex( screen: Screen ): number {
	return SCREENS.indexOf( screen );
}

/**
 * The screen after `screen`, or `null` at the end.
 * @param screen
 */
export function nextStep( screen: Screen ): Screen | null {
	return SCREENS[ stepIndex( screen ) + 1 ] ?? null;
}

/**
 * The screen before `screen`, or `null` at the start.
 * @param screen
 */
export function previousStep( screen: Screen ): Screen | null {
	const index = stepIndex( screen );
	return index > 0 ? ( SCREENS[ index - 1 ] ?? null ) : null;
}

export function stepInfo(
	state: SetupState,
	step: StepId
): StepInfo | undefined {
	return state.steps.find( ( item ) => item.id === step );
}

/**
 * Done or skipped steps and the total.
 * @param state
 */
export function progressOf( state: SetupState ): {
	resolved: number;
	total: number;
} {
	return {
		resolved: state.steps.filter( ( step ) => 'pending' !== step.status )
			.length,
		total: state.steps.length,
	};
}

/**
 * Whether the server will accept completion (every step done or skipped).
 * @param state
 */
export function canComplete( state: SetupState ): boolean {
	return (
		state.steps.length > 0 &&
		state.steps.every( ( step ) => 'pending' !== step.status )
	);
}

/**
 * Ids of the steps still pending.
 * @param state
 */
export function pendingSteps( state: SetupState ): StepId[] {
	return state.steps
		.filter( ( step ) => 'pending' === step.status )
		.map( ( step ) => step.id );
}

// ---- Input validation (mirrors the server schema) --------------------------------------------

/**
 * Validate the experience choice.
 *
 * @param value Chosen value.
 * @return Field errors; empty when valid.
 */
export function validateExperience( value: string ): FieldErrors {
	return oneOf( EXPERIENCES, value )
		? {}
		: { experience: __( 'Choose an option.', 'ohmylms' ) };
}

export function isExperience( value: string ): value is Experience {
	return undefined !== oneOf( EXPERIENCES, value );
}

export function isNiche( value: string ): value is Niche {
	return undefined !== oneOf( NICHES, value );
}

/**
 * Validate the preferences form.
 *
 * @param input          Form values.
 * @param input.currency Currency code.
 * @param input.position Symbol position.
 * @param input.decimals Number of decimals as typed.
 * @return Field errors; empty when valid.
 */
export function validatePreferences( input: {
	currency: string;
	position: string;
	decimals: string;
} ): FieldErrors {
	const errors: FieldErrors = {};
	if ( ! /^[A-Z]{3}$/.test( input.currency ) ) {
		errors.currency = __( 'Choose a currency.', 'ohmylms' );
	}
	if ( ! oneOf( CURRENCY_POSITIONS, input.position ) ) {
		errors.currency_pos = __( 'Choose a position.', 'ohmylms' );
	}
	const decimals = Number( input.decimals );
	if (
		'' === input.decimals.trim() ||
		! Number.isInteger( decimals ) ||
		decimals < 0 ||
		decimals > LIMITS.maxDecimals
	) {
		errors.price_num_decimals = __(
			'Enter a whole number from 0 to 8.',
			'ohmylms'
		);
	}
	return errors;
}

/**
 * Split typed topics (one per line or comma separated) into clean, unique names.
 *
 * @param input Raw text.
 * @return Names, in order, without blanks or case-insensitive duplicates.
 */
export function parseTopics( input: string ): string[] {
	const seen = new Set< string >();
	const names: string[] = [];
	for ( const part of input.split( /[\n,]/ ) ) {
		const name = part.trim();
		const key = name.toLowerCase();
		if ( name && ! seen.has( key ) ) {
			seen.add( key );
			names.push( name );
		}
	}
	return names;
}

/**
 * Validate the niche form.
 *
 * @param niche  Chosen niche.
 * @param topics Parsed topics.
 * @return Field errors; empty when valid.
 */
export function validateNiche( niche: string, topics: string[] ): FieldErrors {
	const errors: FieldErrors = {};
	if ( ! isNiche( niche ) ) {
		errors.niche = __( 'Choose a subject area.', 'ohmylms' );
	}
	if ( topics.length > LIMITS.maxTopics ) {
		errors.topics = __( 'Use at most 10 topics.', 'ohmylms' );
	} else if ( topics.some( ( t ) => t.length > LIMITS.maxTopicChars ) ) {
		errors.topics = __(
			'Each topic can have at most 60 characters.',
			'ohmylms'
		);
	}
	return errors;
}

// ---- Failures ------------------------------------------------------------------------------------

function messagesFrom( details: unknown ): FieldErrors {
	if ( ! isRow( details ) || ! isRow( details.params ) ) {
		return {};
	}
	const errors: FieldErrors = {};
	for ( const [ name, message ] of Object.entries( details.params ) ) {
		if ( 'string' === typeof message ) {
			errors[ name ] = message;
		}
	}
	return errors;
}

/**
 * Reduce a failed request to what the screen shows.
 *
 * @param error Typed API error.
 * @return Message, whether it is a permission problem and per-field messages.
 */
export function describeFailure( error: ApiError ): FailureView {
	return {
		message: error.message,
		permission: 401 === error.status || 403 === error.status,
		fieldErrors: messagesFrom( error.details ),
	};
}
