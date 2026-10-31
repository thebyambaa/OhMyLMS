import { __, sprintf } from '@wordpress/i18n';
import { invalidResponse, record } from '../model';

/** The five level names, highest first (`Performance\Levels`). */
export const MASTERY_LEVELS = [
	'mastered',
	'proficient',
	'familiar',
	'attempted',
	'not-started',
] as const;
export type MasteryLevel = ( typeof MASTERY_LEVELS )[ number ];

export interface MasteryLegendEntry {
	readonly id: MasteryLevel;
	readonly label: string;
	/** Lowest percentage of the level; null for Attempted and Not started. */
	readonly min: number | null;
}

export interface HeatmapRow {
	/** `class:<id>` or `course:<id>`. */
	readonly key: string;
	readonly id: number;
	readonly label: string;
	/** Learners in the row. */
	readonly learners: number;
}

export interface HeatmapSkill {
	readonly id: number;
	readonly name: string;
	readonly code: string;
	/** Shared subject, '' when none. */
	readonly subject: string;
	/** Learners of the view with evidence on the skill. */
	readonly assessed: number;
}

export interface HeatmapCell {
	/** Index into `rows`. */
	readonly row: number;
	/** Index into `skills`. */
	readonly skill: number;
	readonly percent: number;
	readonly level: MasteryLevel;
	readonly assessed: number;
	readonly below: number;
}

export interface MasteryHeatmap {
	readonly rowType: 'class' | 'course';
	/** Rows of the scope before the bound. */
	readonly rowsTotal: number;
	readonly rows: readonly HeatmapRow[];
	readonly skills: readonly HeatmapSkill[];
	readonly subjects: readonly { name: string; skills: number }[];
	/** Cells with evidence; any other cell is Not started. */
	readonly cells: readonly HeatmapCell[];
	readonly levels: readonly MasteryLegendEntry[];
	readonly limits: { readonly rows: number; readonly skills: number };
	/** Whether learner names link to the student report. */
	readonly canOpenReports: boolean;
}

export interface MasteryLearner {
	readonly id: number;
	readonly name: string;
	readonly percent: number;
	readonly level: MasteryLevel;
	/** Student report route, or null when the user may not open it. */
	readonly route: string | null;
}

/** `GET /dashboard/widgets/mastery-heatmap/learners`. */
export interface MasteryLearners {
	readonly row: { key: string; label: string; learners: number };
	readonly skill: { id: number; name: string };
	/** Learners below Familiar (all of them, not only those listed). */
	readonly below: number;
	/** Learners of the row with evidence on the skill. */
	readonly assessed: number;
	readonly learners: readonly MasteryLearner[];
	readonly limit: number;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function whole( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}

function percentage( value: unknown ): number {
	const number = whole( value );
	if ( number > 100 ) {
		throw invalidResponse();
	}
	return number;
}

function list( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value;
}

/**
 * Name of a level, as `Performance\Levels::label()` gives it.
 *
 * @param level Level.
 * @return Translated name.
 */
export function levelLabel( level: MasteryLevel ): string {
	switch ( level ) {
		case 'mastered':
			return __( 'Mastered', 'ohmylms' );
		case 'proficient':
			return __( 'Proficient', 'ohmylms' );
		case 'familiar':
			return __( 'Familiar', 'ohmylms' );
		case 'attempted':
			return __( 'Attempted', 'ohmylms' );
		default:
			return __( 'Not started', 'ohmylms' );
	}
}

export function masteryLevel( value: unknown ): MasteryLevel {
	const level = MASTERY_LEVELS.find( ( option ) => option === value );
	if ( ! level ) {
		throw invalidResponse();
	}
	return level;
}

const ROW_KEY = /^(class|course):[1-9]\d*$/;

/**
 * Validate the `mastery-heatmap` widget data.
 *
 * @param value Decoded `data`.
 * @return The heatmap.
 */
export function parseMasteryHeatmap( value: unknown ): MasteryHeatmap {
	const data = record( value );
	if ( 'class' !== data.row_type && 'course' !== data.row_type ) {
		throw invalidResponse();
	}
	const rows = list( data.rows ).map( ( item ): HeatmapRow => {
		const row = record( item );
		const key = text( row.key );
		if ( ! ROW_KEY.test( key ) ) {
			throw invalidResponse();
		}
		return {
			key,
			id: whole( row.id ),
			label: text( row.label ),
			learners: whole( row.learners ),
		};
	} );
	const skills = list( data.skills ).map( ( item ): HeatmapSkill => {
		const skill = record( item );
		return {
			id: whole( skill.id ),
			name: text( skill.name ),
			code: text( skill.code ),
			subject: text( skill.subject ),
			assessed: whole( skill.assessed ),
		};
	} );
	const cells = list( data.cells ).map( ( item ): HeatmapCell => {
		const cell = record( item );
		const row = whole( cell.row );
		const skill = whole( cell.skill );
		if ( row >= rows.length || skill >= skills.length ) {
			throw invalidResponse();
		}
		return {
			row,
			skill,
			percent: percentage( cell.percent ),
			level: masteryLevel( cell.level ),
			assessed: whole( cell.assessed ),
			below: whole( cell.below ),
		};
	} );
	const limits = record( data.limits );
	return {
		rowType: data.row_type,
		rowsTotal: whole( data.rows_total ),
		rows,
		skills,
		subjects: list( data.subjects ).map( ( item ) => {
			const subject = record( item );
			return {
				name: text( subject.name ),
				skills: whole( subject.skills ),
			};
		} ),
		cells,
		levels: list( data.levels ).map( ( item ) => {
			const level = record( item );
			return {
				id: masteryLevel( level.id ),
				label: text( level.label ),
				min: null === level.min ? null : percentage( level.min ),
			};
		} ),
		limits: { rows: whole( limits.rows ), skills: whole( limits.skills ) },
		canOpenReports: true === data.can_open_reports,
	};
}

/**
 * Validate the learners of a cell.
 *
 * @param value Decoded response.
 * @return The learners below Familiar.
 */
export function parseMasteryLearners( value: unknown ): MasteryLearners {
	const data = record( value );
	const row = record( data.row );
	const skill = record( data.skill );
	return {
		row: {
			key: text( row.key ),
			label: text( row.label ),
			learners: whole( row.learners ),
		},
		skill: { id: whole( skill.id ), name: text( skill.name ) },
		below: whole( data.below ),
		assessed: whole( data.assessed ),
		learners: list( data.learners ).map( ( item ): MasteryLearner => {
			const learner = record( item );
			let route: string | null = null;
			if ( null !== learner.route && undefined !== learner.route ) {
				route = text( learner.route );
				if ( ! /^\/students\/\d+\/report$/.test( route ) ) {
					throw invalidResponse();
				}
			}
			return {
				id: whole( learner.id ),
				name: text( learner.name ),
				percent: percentage( learner.percent ),
				level: masteryLevel( learner.level ),
				route,
			};
		} ),
		limit: whole( data.limit ),
	};
}

/**
 * The cell of a row and skill (Not started when the server listed none).
 *
 * @param heatmap Heatmap.
 * @param row     Row index.
 * @param skill   Skill index.
 * @return The cell.
 */
export function cellAt(
	heatmap: MasteryHeatmap,
	row: number,
	skill: number
): HeatmapCell {
	return (
		heatmap.cells.find(
			( cell ) => cell.row === row && cell.skill === skill
		) ?? {
			row,
			skill,
			percent: 0,
			level: 'not-started',
			assessed: 0,
			below: 0,
		}
	);
}

/**
 * Range text of a legend entry: "95% and above", "80–94%", "below 50%", "no evidence yet".
 *
 * @param levels Legend, highest first.
 * @param index  Entry.
 * @return Text.
 */
export function levelRange(
	levels: readonly MasteryLegendEntry[],
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
		const familiar = levels.find( ( level ) => 'familiar' === level.id );
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
