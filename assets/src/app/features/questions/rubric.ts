import { __, sprintf } from '@wordpress/i18n';
import type {
	FormErrors,
	QuestionSettings,
	RubricCriterion,
	RubricCriterionForm,
	RubricLevelForm,
} from './types';

/** Rubric limits shared with the server (`OhMyLMS\Assessment\Rubric`). */
export const RUBRIC_LIMITS = {
	criteria: 20,
	levels: 10,
	title: 200,
	description: 2000,
	level: 1000,
	points: 10000,
} as const;

const POINTS = /^\d{1,5}(\.\d{1,4})?$/;
const IDENTIFIER = /^[a-z0-9_-]{1,40}$/;

let nextKey = 0;

function key( prefix: string ): string {
	nextKey += 1;
	return `${ prefix }-${ nextKey }`;
}

function entry( value: unknown ): Record< string, unknown > {
	return value && 'object' === typeof value && ! Array.isArray( value )
		? ( value as Record< string, unknown > )
		: {};
}

function pointsText( value: unknown ): string {
	return 'number' === typeof value && Number.isFinite( value )
		? String( value )
		: '';
}

function textOf( value: unknown ): string {
	return 'string' === typeof value ? value : '';
}

/**
 * The stored rubric as editable rows. Keys depend only on the position, so two reads of the same
 * settings compare equal.
 *
 * @param settings Question settings.
 * @return Criteria in order; empty without a rubric.
 */
export function rubricFromSettings(
	settings: QuestionSettings
): RubricCriterionForm[] {
	const stored = Array.isArray( settings.rubric ) ? settings.rubric : [];
	return stored.map( ( raw, index ) => {
		const item = entry( raw );
		const levels = Array.isArray( item.levels ) ? item.levels : [];
		return {
			key: `criterion-${ index }`,
			id: textOf( item.id ) || `c${ index + 1 }`,
			title: textOf( item.title ),
			description: textOf( item.description ),
			points: pointsText( item.points ),
			levels: levels.map( ( rawLevel, position ) => {
				const level = entry( rawLevel );
				return {
					key: `level-${ index }-${ position }`,
					points: pointsText( level.points ),
					description: textOf( level.description ),
				};
			} ),
		};
	} );
}

/**
 * A new criterion with an ID no other criterion uses.
 *
 * @param rows Current criteria.
 * @return The criterion.
 */
export function newCriterion(
	rows: readonly RubricCriterionForm[]
): RubricCriterionForm {
	const used = new Set( rows.map( ( row ) => row.id ) );
	let number = rows.length + 1;
	while ( used.has( `c${ number }` ) ) {
		number += 1;
	}
	return {
		key: key( 'criterion' ),
		id: `c${ number }`,
		title: '',
		description: '',
		points: '1',
		levels: [],
	};
}

/**
 * A new, empty level.
 *
 * @return The level.
 */
export function newLevel(): RubricLevelForm {
	return { key: key( 'level' ), points: '', description: '' };
}

/**
 * The rubric as the server stores it. Call after {@link validateRubric} reports no errors.
 *
 * @param rows Edited criteria.
 * @return Criteria with numeric points.
 */
export function rubricSettings(
	rows: readonly RubricCriterionForm[]
): RubricCriterion[] {
	return rows.map( ( row ) => ( {
		id: row.id,
		title: row.title.trim(),
		description: row.description.trim(),
		points: Number( row.points ),
		levels: row.levels.map( ( level ) => ( {
			points: Number( level.points ),
			description: level.description.trim(),
		} ) ),
	} ) );
}

/**
 * Total points of the criteria, which become the question's points.
 *
 * @param rows Edited criteria.
 * @return The total, or `null` while a criterion's points are not a valid number.
 */
export function rubricTotal(
	rows: readonly RubricCriterionForm[]
): number | null {
	let total = 0;
	for ( const row of rows ) {
		if ( ! POINTS.test( row.points.trim() ) ) {
			return null;
		}
		total += Number( row.points );
	}
	return Math.round( total * 10000 ) / 10000;
}

/**
 * Check the rubric with the server's rules. Errors are keyed `rubric`, `rubric.<i>.title`,
 * `rubric.<i>.points`, `rubric.<i>.description`, `rubric.<i>.levels.<j>.points` and
 * `rubric.<i>.levels.<j>.description`.
 *
 * @param rows   Edited criteria.
 * @param errors Errors to add to.
 */
export function validateRubric(
	rows: readonly RubricCriterionForm[],
	errors: FormErrors
): void {
	if ( rows.length > RUBRIC_LIMITS.criteria ) {
		errors.rubric = sprintf(
			/* translators: %d: maximum number of criteria. */
			__( 'Use at most %d rubric criteria.', 'ohmylms' ),
			RUBRIC_LIMITS.criteria
		);
		return;
	}
	const ids = new Set< string >();
	rows.forEach( ( row, index ) => {
		const path = `rubric.${ index }`;
		if ( ! IDENTIFIER.test( row.id ) || ids.has( row.id ) ) {
			errors.rubric = __(
				'Two rubric criteria have the same ID. Remove one and add it again.',
				'ohmylms'
			);
		}
		ids.add( row.id );
		const title = row.title.trim();
		if ( ! title || Array.from( title ).length > RUBRIC_LIMITS.title ) {
			errors[ `${ path }.title` ] = sprintf(
				/* translators: %d: maximum length. */
				__( 'Enter a title of 1 to %d characters.', 'ohmylms' ),
				RUBRIC_LIMITS.title
			);
		}
		if (
			Array.from( row.description.trim() ).length >
			RUBRIC_LIMITS.description
		) {
			errors[ `${ path }.description` ] = sprintf(
				/* translators: %d: maximum length. */
				__( 'Use at most %d characters.', 'ohmylms' ),
				RUBRIC_LIMITS.description
			);
		}
		const points = row.points.trim();
		const max = Number( points );
		if (
			! POINTS.test( points ) ||
			max <= 0 ||
			max > RUBRIC_LIMITS.points
		) {
			errors[ `${ path }.points` ] = sprintf(
				/* translators: %d: maximum points. */
				__( 'Enter more than 0 and at most %d points.', 'ohmylms' ),
				RUBRIC_LIMITS.points
			);
		}
		if ( row.levels.length > RUBRIC_LIMITS.levels ) {
			errors.rubric = sprintf(
				/* translators: %d: maximum number of levels. */
				__( 'Use at most %d levels.', 'ohmylms' ),
				RUBRIC_LIMITS.levels
			);
		}
		const seen = new Set< number >();
		row.levels.forEach( ( level, position ) => {
			const levelPath = `${ path }.levels.${ position }`;
			const value = level.points.trim();
			const number = Number( value );
			if (
				! POINTS.test( value ) ||
				( POINTS.test( points ) && number > max ) ||
				seen.has( number )
			) {
				errors[ `${ levelPath }.points` ] = __(
					'Enter different points from 0 up to the criterion’s points.',
					'ohmylms'
				);
			}
			seen.add( number );
			const text = level.description.trim();
			if ( ! text || Array.from( text ).length > RUBRIC_LIMITS.level ) {
				errors[ `${ levelPath }.description` ] = sprintf(
					/* translators: %d: maximum length. */
					__(
						'Describe the level in 1 to %d characters.',
						'ohmylms'
					),
					RUBRIC_LIMITS.level
				);
			}
		} );
	} );
}

/**
 * The form field of a server rubric error (`settings.rubric[1].levels[0].points` and so on).
 *
 * @param field `data.field` of the error.
 * @return The form error key, or `undefined` when it is not about the rubric.
 */
export function rubricErrorPath( field: string ): string | undefined {
	if ( ! field.startsWith( 'settings.rubric' ) ) {
		return undefined;
	}
	const path = field
		.replace( /^settings\./, '' )
		.replace( /\[(\d+)\]/g, '.$1' );
	return /^rubric\.\d+\.(title|description|points)$/.test( path ) ||
		/^rubric\.\d+\.levels\.\d+\.(points|description)$/.test( path )
		? path
		: 'rubric';
}
