import { __, sprintf } from '@wordpress/i18n';
import type { ApiError } from '../../lib/api';
import { describeFailure, invalidResponse } from './model';
import type {
	EngagementDraftMap,
	EngagementGroup,
	EngagementMap,
	FailureView,
	FieldErrors,
	MasteryDraft,
	MasterySettings,
	ScoreDraft,
	ScoreSettings,
	StreakDraft,
	StreakMilestone,
	StreakSettings,
	XpDraft,
	XpSettings,
} from './types';

/** Limits enforced by the server schema of each group (docs/api/gamification.md). */
export const ENGAGEMENT_LIMITS = {
	maxMilestones: 20,
	maxGoals: 6,
	scoreBands: 4,
} as const;

/* ------------------------------------------------------------------ parsing */

function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}

function list( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function integer( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isInteger( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function decimal( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isFinite( value ) ) {
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

function parseMilestone( value: unknown ): StreakMilestone {
	const item = record( value );
	return {
		days: integer( item.days ),
		badge: text( item.badge ),
		points: integer( item.points ),
	};
}

function parseStreak( raw: unknown ): StreakSettings {
	const item = record( raw );
	return {
		enable: flag( item.enable ),
		lesson: flag( item.lesson ),
		quiz: flag( item.quiz ),
		practice: flag( item.practice ),
		practice_minimum: integer( item.practice_minimum ),
		freezes: flag( item.freezes ),
		initial_freezes: integer( item.initial_freezes ),
		maximum_freezes: integer( item.maximum_freezes ),
		refill_days: integer( item.refill_days ),
		milestones: list( item.milestones ).map( parseMilestone ),
	};
}

function parseXp( raw: unknown ): XpSettings {
	const item = record( raw );
	return {
		enable: flag( item.enable ),
		lesson: integer( item.lesson ),
		perfect: integer( item.perfect ),
		challenge: integer( item.challenge ),
		min_answered: integer( item.min_answered ),
		goals: list( item.goals ).map( integer ),
		default_goal: integer( item.default_goal ),
		goal_bonus: integer( item.goal_bonus ),
		daily_cap: integer( item.daily_cap ),
	};
}

function parseScore( raw: unknown ): ScoreSettings {
	const item = record( raw );
	return {
		enable: flag( item.enable ),
		bands: list( item.bands ).map( ( value ) => {
			const band = record( value );
			return { gain: decimal( band.gain ), loss: decimal( band.loss ) };
		} ),
		hint_gain: decimal( item.hint_gain ),
		unlock: integer( item.unlock ),
		decay_grace_days: integer( item.decay_grace_days ),
		decay_per_week: decimal( item.decay_per_week ),
	};
}

function parseMastery( raw: unknown ): MasterySettings {
	const item = record( raw );
	const surfaces: Record< string, boolean > = {};
	for ( const [ id, counts ] of Object.entries(
		// PHP encodes an empty map as an empty list.
		Array.isArray( item.surfaces ) && ! item.surfaces.length
			? {}
			: record( item.surfaces )
	) ) {
		surfaces[ id ] = flag( counts );
	}
	return {
		policy: text( item.policy ),
		surfaces,
		show_score: flag( item.show_score ),
		ai_insights: flag( item.ai_insights ),
		policies: list( item.policies ).map( ( value ) => {
			const policy = record( value );
			return {
				id: text( policy.id ),
				label: text( policy.label ),
				description: text( policy.description ),
			};
		} ),
		surface_options: list( item.surface_options ).map( ( value ) => {
			const surface = record( value );
			return {
				id: text( surface.id ),
				label: text( surface.label ),
				counts: flag( surface.counts ),
			};
		} ),
		ai_ready: flag( item.ai_ready ),
	};
}

/**
 * Validate a group from `GET /engagement/settings/<group>` (or the `settings` of a save).
 *
 * @param group Group id.
 * @param raw   Decoded JSON.
 * @return The typed group.
 */
export function parseEngagement< G extends EngagementGroup >(
	group: G,
	raw: unknown
): EngagementMap[ G ] {
	switch ( group ) {
		case 'streak':
			return parseStreak( raw ) as EngagementMap[ G ];
		case 'xp':
			return parseXp( raw ) as EngagementMap[ G ];
		case 'score':
			return parseScore( raw ) as EngagementMap[ G ];
		default:
			return parseMastery( raw ) as EngagementMap[ G ];
	}
}

/**
 * Validate the answer to a save: `{ success: true, message, settings }`.
 *
 * @param group Group id.
 * @param raw   Decoded JSON.
 * @return The group as stored.
 */
export function parseEngagementSave< G extends EngagementGroup >(
	group: G,
	raw: unknown
): EngagementMap[ G ] {
	const body = record( raw );
	if ( true !== body.success ) {
		throw invalidResponse();
	}
	return parseEngagement( group, body.settings );
}

/* -------------------------------------------------------------- drafts */

/**
 * Comma or space separated goal text as numbers; `null` when something is not a number.
 *
 * @param value Text such as `10, 20, 30`.
 */
export function parseGoals( value: string ): number[] | null {
	const parts = value.split( /[\s,;]+/ ).filter( Boolean );
	const goals = parts.map( Number );
	return goals.every( ( goal ) => Number.isFinite( goal ) ) ? goals : null;
}

export function toEngagementDraft< G extends EngagementGroup >(
	group: G,
	saved: EngagementMap[ G ]
): EngagementDraftMap[ G ] {
	switch ( group ) {
		case 'streak': {
			const value = saved as StreakSettings;
			return {
				...value,
				milestones: value.milestones.map( ( item ) => ( { ...item } ) ),
			} as EngagementDraftMap[ G ];
		}
		case 'xp': {
			const value = saved as XpSettings;
			return {
				...value,
				goals: value.goals.join( ', ' ),
			} as EngagementDraftMap[ G ];
		}
		case 'score': {
			const value = saved as ScoreSettings;
			return {
				...value,
				bands: value.bands.map( ( band ) => ( { ...band } ) ),
			} as EngagementDraftMap[ G ];
		}
		default: {
			const value = saved as MasterySettings;
			return {
				policy: value.policy,
				surfaces: { ...value.surfaces },
				show_score: value.show_score,
				ai_insights: value.ai_insights,
			} as EngagementDraftMap[ G ];
		}
	}
}

/**
 * JSON body of a save. Every editable field is sent, so the request is complete for old servers
 * too; the server still treats it as a partial update.
 *
 * @param group Group id.
 * @param draft Validated draft.
 * @return Request body.
 */
export function engagementPayload< G extends EngagementGroup >(
	group: G,
	draft: EngagementDraftMap[ G ]
): Record< string, unknown > {
	if ( 'xp' === group ) {
		const value = draft as XpDraft;
		return { ...value, goals: parseGoals( value.goals ) ?? [] };
	}
	if ( 'mastery' === group ) {
		const value = draft as MasteryDraft;
		return { ...value, surfaces: { ...value.surfaces } };
	}
	return { ...( draft as StreakDraft | ScoreDraft ) };
}

/* -------------------------------------------------------------- validation */

function inRange(
	value: number | null,
	min: number,
	max: number,
	whole = true
): boolean {
	return (
		null !== value &&
		Number.isFinite( value ) &&
		( ! whole || Number.isInteger( value ) ) &&
		value >= min &&
		value <= max
	);
}

function wholeMessage( min: number, max: number ): string {
	return sprintf(
		/* translators: 1: smallest allowed number, 2: largest allowed number. */
		__( 'Enter a whole number from %1$d to %2$d.', 'ohmylms' ),
		min,
		max
	);
}

function numberMessage( min: number, max: number ): string {
	return sprintf(
		/* translators: 1: smallest allowed number, 2: largest allowed number. */
		__( 'Enter a number from %1$s to %2$s.', 'ohmylms' ),
		String( min ),
		String( max )
	);
}

function checkWhole(
	errors: FieldErrors,
	key: string,
	value: number | null,
	min: number,
	max: number
) {
	if ( ! inRange( value, min, max ) ) {
		errors[ key ] = wholeMessage( min, max );
	}
}

function validateStreak( draft: StreakDraft ): FieldErrors {
	const errors: FieldErrors = {};
	checkWhole( errors, 'practice_minimum', draft.practice_minimum, 1, 30 );
	checkWhole( errors, 'initial_freezes', draft.initial_freezes, 0, 10 );
	checkWhole( errors, 'maximum_freezes', draft.maximum_freezes, 0, 10 );
	checkWhole( errors, 'refill_days', draft.refill_days, 1, 365 );
	if (
		! errors.initial_freezes &&
		! errors.maximum_freezes &&
		( draft.initial_freezes ?? 0 ) > ( draft.maximum_freezes ?? 0 )
	) {
		errors.initial_freezes = __(
			'A learner cannot start with more freezes than the maximum.',
			'ohmylms'
		);
	}
	if ( draft.milestones.length > ENGAGEMENT_LIMITS.maxMilestones ) {
		errors.milestones = __( 'Use at most 20 milestones.', 'ohmylms' );
	}
	const seen = new Set< number >();
	draft.milestones.forEach( ( milestone, index ) => {
		const key = `milestones.${ index }`;
		if ( ! inRange( milestone.days, 1, 10000 ) ) {
			errors[ `${ key }.days` ] = wholeMessage( 1, 10000 );
		} else if ( seen.has( milestone.days as number ) ) {
			errors[ `${ key }.days` ] = __(
				'Each milestone needs a different number of days.',
				'ohmylms'
			);
		} else {
			seen.add( milestone.days as number );
		}
		checkWhole( errors, `${ key }.points`, milestone.points, 0, 100000 );
	} );
	return errors;
}

function validateXp( draft: XpDraft ): FieldErrors {
	const errors: FieldErrors = {};
	checkWhole( errors, 'lesson', draft.lesson, 0, 1000 );
	checkWhole( errors, 'perfect', draft.perfect, 0, 1000 );
	checkWhole( errors, 'challenge', draft.challenge, 0, 100 );
	checkWhole( errors, 'min_answered', draft.min_answered, 1, 30 );
	checkWhole( errors, 'goal_bonus', draft.goal_bonus, 0, 1000 );
	checkWhole( errors, 'daily_cap', draft.daily_cap, 10, 100000 );
	checkWhole( errors, 'default_goal', draft.default_goal, 1, 10000 );
	const goals = parseGoals( draft.goals );
	if (
		! goals ||
		! goals.length ||
		new Set( goals ).size > ENGAGEMENT_LIMITS.maxGoals ||
		goals.some( ( goal ) => ! inRange( goal, 1, 10000 ) )
	) {
		errors.goals = __(
			'Enter one to six whole numbers from 1 to 10000, separated by commas.',
			'ohmylms'
		);
	} else if (
		! errors.default_goal &&
		! goals.includes( draft.default_goal as number )
	) {
		errors.default_goal = __(
			'The default goal must be one of the goal choices.',
			'ohmylms'
		);
	}
	return errors;
}

function validateScore( draft: ScoreDraft ): FieldErrors {
	const errors: FieldErrors = {};
	if ( draft.bands.length !== ENGAGEMENT_LIMITS.scoreBands ) {
		errors.bands = __( 'The score needs exactly four bands.', 'ohmylms' );
	}
	draft.bands.forEach( ( band, index ) => {
		for ( const key of [ 'gain', 'loss' ] as const ) {
			if ( ! inRange( band[ key ], 0, 50, false ) ) {
				errors[ `bands.${ index }.${ key }` ] = numberMessage( 0, 50 );
			}
		}
	} );
	if ( ! inRange( draft.hint_gain, 0, 1, false ) ) {
		errors.hint_gain = numberMessage( 0, 1 );
	}
	checkWhole( errors, 'unlock', draft.unlock, 50, 100 );
	checkWhole( errors, 'decay_grace_days', draft.decay_grace_days, 0, 365 );
	if ( ! inRange( draft.decay_per_week, 0, 20, false ) ) {
		errors.decay_per_week = numberMessage( 0, 20 );
	}
	return errors;
}

/**
 * Validate a draft with the same rules as the server. The server remains authoritative.
 *
 * @param group Group id.
 * @param draft Draft.
 * @return Messages by field; empty when the draft can be saved.
 */
export function validateEngagement< G extends EngagementGroup >(
	group: G,
	draft: EngagementDraftMap[ G ]
): FieldErrors {
	switch ( group ) {
		case 'streak':
			return validateStreak( draft as StreakDraft );
		case 'xp':
			return validateXp( draft as XpDraft );
		case 'score':
			return validateScore( draft as ScoreDraft );
		default:
			return {};
	}
}

/**
 * Reduce a failed save to what the screen shows. A 422 names its field in `data.field`; schema
 * errors (400) name theirs in `data.params`.
 *
 * @param error Typed API error.
 * @return Message, permission flag, code and per-field messages.
 */
export function describeEngagementFailure( error: ApiError ): FailureView {
	const view = describeFailure( error );
	const details = error.details as Record< string, unknown > | null;
	if (
		details &&
		'object' === typeof details &&
		'string' === typeof details.field
	) {
		view.fieldErrors = {
			...view.fieldErrors,
			[ details.field ]: error.message,
		};
	}
	return view;
}
