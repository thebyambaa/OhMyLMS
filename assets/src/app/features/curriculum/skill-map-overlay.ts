import { __, sprintf } from '@wordpress/i18n';
import { get } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';
import type { SkillMap } from './skill-map';

/** The heatmap's level names, highest first (`Dashboard\Widgets\MasteryLevels`). */
export const OVERLAY_LEVELS = [
	'mastered',
	'proficient',
	'familiar',
	'attempted',
	'not-started',
] as const;
export type OverlayLevel = ( typeof OVERLAY_LEVELS )[ number ];
export type LevelCounts = Record< OverlayLevel, number >;

export interface OverlayLegendEntry {
	id: OverlayLevel;
	label: string;
	/** Lowest percentage of the level; null for Attempted and Not started. */
	min: number | null;
}
export interface OverlaySkill {
	id: number;
	/** The learner's percentage, or the group's average (learners without evidence count as 0). */
	percent: number;
	level: OverlayLevel;
	/** Learners with evidence on the skill. */
	assessed: number;
	/** Learners at each level. */
	distribution: LevelCounts;
}
export interface OverlayTarget {
	/** `learner:<id>`, `class:<id>` or `school:<id>`. */
	key: string;
	type: 'learner' | 'class' | 'school';
	id: number;
	label: string;
	/** School of a class, '' otherwise. */
	school: string;
	learners: number;
	/** Learners read (a large school is bounded). */
	counted: number;
}
export interface SkillOverlay {
	subject: string;
	target: OverlayTarget;
	/** Skills with evidence; any other skill of the map is Not started. */
	skills: OverlaySkill[];
	/** Skills of the map at each level. */
	counts: LevelCounts;
	total: number;
	levels: OverlayLegendEntry[];
	links: {
		transcript: string | null;
		report: string | null;
		gradebook: string | null;
	};
	limits: { learners: number };
}
export interface OverlayGroup {
	key: string;
	type: 'class' | 'school';
	label: string;
	school: string;
}
export interface OverlayTargets {
	groups: OverlayGroup[];
	groupsTotal: number;
	learners: { id: number; name: string }[];
	limit: number;
	minSearch: number;
}

const BASE = '/ohmylms/v1/skill-map/overlay';
const TARGET = /^(learner|class|school):[1-9]\d{0,18}$/;
const LINKS = {
	transcript: /^\/content-hub\/gradebook\/student\?[\w%=&.-]*$/,
	report: /^\/students\/\d+\/report$/,
	gradebook: /^\/content-hub\/gradebook\/mastery\?[\w%=&.-]*$/,
};

/**
 * Whether a value names something to lay over the map.
 * @param value Candidate from the address.
 */
export function isTarget( value: string ): boolean {
	return TARGET.test( value );
}

function count( value: unknown ) {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}
function percentage( value: unknown ) {
	const number = count( value );
	return number <= 100 ? number : invalid();
}
function list( value: unknown ): unknown[] {
	return Array.isArray( value ) ? value : invalid();
}
function level( value: unknown ): OverlayLevel {
	return OVERLAY_LEVELS.find( ( option ) => option === value ) ?? invalid();
}
function counts( value: unknown ): LevelCounts {
	const row = record( value );
	return Object.fromEntries(
		OVERLAY_LEVELS.map( ( id ) => [ id, count( row[ id ] ) ] )
	) as LevelCounts;
}
function link( value: unknown, pattern: RegExp ): string | null {
	if ( null === value ) {
		return null;
	}
	const route = text( value );
	return pattern.test( route ) ? route : invalid();
}

export function parseOverlay( value: unknown ): SkillOverlay {
	const row = record( value );
	const target = record( row.target );
	const type = text( target.type );
	if ( 'learner' !== type && 'class' !== type && 'school' !== type ) {
		return invalid();
	}
	const key = text( target.key );
	if ( ! isTarget( key ) ) {
		return invalid();
	}
	const links = record( row.links );
	return {
		subject: text( row.subject ),
		target: {
			key,
			type,
			id: positiveId( target.id ),
			label: text( target.label ),
			school: text( target.school ),
			learners: count( target.learners ),
			counted: count( target.counted ),
		},
		skills: list( row.skills ).map( ( item ) => {
			const skill = record( item );
			return {
				id: positiveId( skill.id ),
				percent: percentage( skill.percent ),
				level: level( skill.level ),
				assessed: count( skill.assessed ),
				distribution: counts( skill.distribution ),
			};
		} ),
		counts: counts( row.counts ),
		total: count( row.total ),
		levels: list( row.levels ).map( ( item ) => {
			const entry = record( item );
			return {
				id: level( entry.id ),
				label: text( entry.label ),
				min: null === entry.min ? null : percentage( entry.min ),
			};
		} ),
		links: {
			transcript: link( links.transcript, LINKS.transcript ),
			report: link( links.report, LINKS.report ),
			gradebook: link( links.gradebook, LINKS.gradebook ),
		},
		limits: { learners: count( record( row.limits ).learners ) },
	};
}

export function parseTargets( value: unknown ): OverlayTargets {
	const row = record( value );
	return {
		groups: list( row.groups ).map( ( item ) => {
			const group = record( item );
			const key = text( group.key );
			const type = text( group.type );
			if (
				( 'class' !== type && 'school' !== type ) ||
				! key.startsWith( `${ type }:` ) ||
				! isTarget( key )
			) {
				return invalid();
			}
			return {
				key,
				type,
				label: text( group.label ),
				school: text( group.school ),
			};
		} ),
		groupsTotal: count( row.groups_total ),
		learners: list( row.learners ).map( ( item ) => {
			const learner = record( item );
			return {
				id: positiveId( learner.id ),
				name: text( learner.name ),
			};
		} ),
		limit: count( row.limit ),
		minSearch: count( row.min_search ),
	};
}

export async function readOverlay(
	subject: string,
	target: string,
	signal?: AbortSignal
) {
	return parseOverlay(
		await get< unknown >( BASE, { signal, query: { subject, target } } )
	);
}

export async function readTargets( search: string, signal?: AbortSignal ) {
	return parseTargets(
		await get< unknown >( `${ BASE }/targets`, {
			signal,
			query: { search },
		} )
	);
}

/**
 * A skill's figures; Not started (every counted learner without evidence) when the server listed none.
 * @param overlay Overlay.
 * @param id      Skill.
 */
export function skillLevel( overlay: SkillOverlay, id: number ): OverlaySkill {
	return (
		overlay.skills.find( ( skill ) => skill.id === id ) ?? {
			id,
			percent: 0,
			level: 'not-started',
			assessed: 0,
			distribution: {
				mastered: 0,
				proficient: 0,
				familiar: 0,
				attempted: 0,
				'not-started': overlay.target.counted,
			},
		}
	);
}

/**
 * Per domain of the map, how many of its placed skills there are and how many are Proficient or Mastered.
 * @param map     Skill map.
 * @param overlay Overlay.
 */
export function domainSummary( map: SkillMap, overlay: SkillOverlay ) {
	const summary = new Map< string, { total: number; secure: number } >();
	for ( const skill of map.skills ) {
		if ( ! skill.domain || skill.grade_from < 1 ) {
			continue;
		}
		const row = summary.get( skill.domain ) ?? { total: 0, secure: 0 };
		const { level: reached } = skillLevel( overlay, skill.id );
		summary.set( skill.domain, {
			total: row.total + 1,
			secure:
				row.secure +
				( 'mastered' === reached || 'proficient' === reached ? 1 : 0 ),
		} );
	}
	return summary;
}

/**
 * Range of a legend entry: "95% and above", "80–94%", "below 50%", "no evidence yet".
 * @param levels Legend, highest first.
 * @param index  Entry.
 */
export function levelRange(
	levels: readonly OverlayLegendEntry[],
	index: number
): string {
	const entry = levels[ index ];
	if ( ! entry ) {
		return '';
	}
	if ( 'not-started' === entry.id ) {
		return __( 'no evidence yet', 'ohmylms' );
	}
	if ( null === entry.min ) {
		const familiar = levels.find( ( row ) => 'familiar' === row.id );
		return familiar?.min
			? /* translators: %d: percentage. */
				sprintf( __( 'below %d%%', 'ohmylms' ), familiar.min )
			: '';
	}
	const above = levels[ index - 1 ];
	if ( ! above || null === above.min ) {
		/* translators: %d: percentage. */
		return sprintf( __( '%d%% and above', 'ohmylms' ), entry.min );
	}
	/* translators: 1: lowest percentage, 2: highest percentage. */
	return sprintf( __( '%1$d–%2$d%%', 'ohmylms' ), entry.min, above.min - 1 );
}
