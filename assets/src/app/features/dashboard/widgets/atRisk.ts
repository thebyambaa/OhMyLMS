import { post } from '../../../lib/api';
import { invalidResponse, record } from '../model';
import type { PeriodDays } from '../shell/model';

export type RiskLevel = 'high' | 'watch';
export type RiskRule = 'inactive' | 'mastery' | 'overdue';

export interface RiskReason {
	readonly rule: RiskRule;
	/** Days inactive, mastery points lost or overdue items. */
	readonly value: number;
	/** Reached the rule's severe threshold. */
	readonly severe: boolean;
	/** Translated text, such as "Inactive 9 days". */
	readonly text: string;
}

export interface AtRiskLearner {
	readonly id: number;
	readonly name: string;
	readonly initials: string;
	readonly level: RiskLevel;
	readonly reasons: readonly RiskReason[];
	/** The reasons joined: "Inactive 7 days · Mastery down 15 pts in 2 wks". */
	readonly summary: string;
	/** Student report route, or null when the user cannot open it. */
	readonly report: string | null;
	/** When the current user last nudged the learner (UTC), or null. */
	readonly nudgedAt: string | null;
	/** That date in the site's format. */
	readonly nudgedLabel: string;
	/** False while the current user's 72-hour limit for this learner runs. */
	readonly canNudge: boolean;
}

export interface AtRisk {
	readonly learners: readonly AtRiskLearner[];
	readonly counts: { readonly high: number; readonly watch: number };
	/** Flagged learners in the scope's list (more than `learners` holds when the list is long). */
	readonly total: number;
	readonly rules: Readonly< Record< RiskRule, boolean > >;
	readonly nudge: {
		readonly cap: number;
		readonly remaining: number;
		readonly intervalHours: number;
	};
}

export interface NudgeResult {
	readonly learner: number;
	readonly nudgedAt: string;
	readonly nudgedLabel: string;
	readonly remaining: number;
	readonly guardians: number;
}

const LEVELS: readonly RiskLevel[] = [ 'high', 'watch' ];
const RULES: readonly RiskRule[] = [ 'inactive', 'mastery', 'overdue' ];

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
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

function flag( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function parseReason( value: unknown ): RiskReason {
	const reason = record( value );
	const rule = RULES.find( ( option ) => option === reason.rule );
	if ( ! rule ) {
		throw invalidResponse();
	}
	return {
		rule,
		value: count( reason.value ),
		severe: flag( reason.severe ),
		text: text( reason.text ),
	};
}

function parseLearner( value: unknown ): AtRiskLearner {
	const learner = record( value );
	const level = LEVELS.find( ( option ) => option === learner.level );
	if ( ! level || ! Array.isArray( learner.reasons ) ) {
		throw invalidResponse();
	}
	let report: string | null = null;
	if ( null !== learner.report && undefined !== learner.report ) {
		report = text( learner.report );
		if ( ! /^\/students\/\d+\/report$/.test( report ) ) {
			throw invalidResponse();
		}
	}
	return {
		id: count( learner.id ),
		name: text( learner.name ),
		initials: text( learner.initials ),
		level,
		reasons: learner.reasons.map( parseReason ),
		summary: text( learner.summary ),
		report,
		nudgedAt:
			null === learner.nudged_at || undefined === learner.nudged_at
				? null
				: text( learner.nudged_at ),
		nudgedLabel: text( learner.nudged_label ?? '' ),
		canNudge: flag( learner.can_nudge ),
	};
}

/**
 * Validate the `at-risk` widget data.
 *
 * @param value Decoded `data`.
 * @return The flagged learners.
 */
export function parseAtRisk( value: unknown ): AtRisk {
	const data = record( value );
	if ( ! Array.isArray( data.learners ) ) {
		throw invalidResponse();
	}
	const counts = record( data.counts );
	const rules = record( data.rules );
	const nudge = record( data.nudge );
	return {
		learners: data.learners.map( parseLearner ),
		counts: { high: count( counts.high ), watch: count( counts.watch ) },
		total: count( data.total ),
		rules: {
			inactive: flag( rules.inactive ),
			mastery: flag( rules.mastery ),
			overdue: flag( rules.overdue ),
		},
		nudge: {
			cap: count( nudge.cap ),
			remaining: count( nudge.remaining ),
			intervalHours: count( nudge.interval_hours ),
		},
	};
}

/**
 * Validate the answer of a nudge.
 *
 * @param value Decoded body.
 * @return The nudge.
 */
export function parseNudge( value: unknown ): NudgeResult {
	const data = record( value );
	return {
		learner: count( data.learner ),
		nudgedAt: text( data.nudged_at ),
		nudgedLabel: text( data.nudged_label ),
		remaining: count( data.remaining ),
		guardians: count( data.guardians ),
	};
}

/**
 * Send the nudge email to a flagged learner of the scope (`POST /dashboard/widgets/at-risk/nudges`).
 *
 * @param scope   Scope the learner was listed in.
 * @param period  Period the list was computed for.
 * @param learner Learner id.
 * @return The nudge.
 */
export async function nudgeLearner(
	scope: string,
	period: PeriodDays,
	learner: number
): Promise< NudgeResult > {
	return parseNudge(
		await post< unknown >( '/ohmylms/v1/dashboard/widgets/at-risk/nudges', {
			scope,
			period,
			learner,
		} )
	);
}
