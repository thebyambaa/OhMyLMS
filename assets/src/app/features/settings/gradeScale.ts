import { __, sprintf } from '@wordpress/i18n';
import { get, patch } from '../../lib/api';
import { changedValues } from './designSettings';
import {
	asArray,
	asBoolean,
	asNumber,
	asRecord,
	asString,
	invalidResponse,
} from './model';
import type { FieldErrors } from './types';

/** Values of `/settings/grade-scale`. */
export interface GradeScaleValues {
	a_min: number;
	b_min: number;
	c_min: number;
	d_min: number;
	missing_as_zero: boolean;
}

export type GradeBoundary = 'a_min' | 'b_min' | 'c_min' | 'd_min';

/** Boundaries from the highest letter down, with the letter each starts. */
export const BOUNDARIES: ReadonlyArray< [ GradeBoundary, string ] > = [
	[ 'a_min', 'A' ],
	[ 'b_min', 'B' ],
	[ 'c_min', 'C' ],
	[ 'd_min', 'D' ],
];

/**
 * Validate a `/settings/grade-scale` response.
 *
 * @param raw Decoded body.
 * @return Values and the names that changed.
 */
export function parseGradeScale( raw: unknown ): {
	values: GradeScaleValues;
	changed: string[];
} {
	const body = asRecord( raw );
	if ( 'grade-scale' !== body.group ) {
		throw invalidResponse();
	}
	const values = asRecord( body.values );
	const parsed = {
		missing_as_zero: asBoolean( values.missing_as_zero ),
	} as GradeScaleValues;
	for ( const [ name ] of BOUNDARIES ) {
		const number = asNumber( values[ name ] );
		if ( ! Number.isInteger( number ) ) {
			throw invalidResponse();
		}
		parsed[ name ] = number;
	}
	return {
		values: parsed,
		changed: asArray( body.changed ).map( asString ),
	};
}

/**
 * Validate a draft like the server: whole numbers 1-100, each boundary below the one above it.
 *
 * @param draft Edited values.
 * @return Field name => message.
 */
export function validateGradeScale( draft: GradeScaleValues ): FieldErrors {
	const errors: FieldErrors = {};
	for ( const [ name ] of BOUNDARIES ) {
		const value = draft[ name ];
		if ( ! Number.isInteger( value ) || value < 1 || value > 100 ) {
			errors[ name ] = __( 'Enter a number from 1 to 100.', 'ohmylms' );
		}
	}
	BOUNDARIES.forEach( ( [ name, letter ], index ) => {
		const previous = BOUNDARIES[ index - 1 ];
		if ( ! previous || errors[ name ] ) {
			return;
		}
		const [ above, upper ] = previous;
		if ( ! errors[ above ] && draft[ name ] >= draft[ above ] ) {
			errors[ name ] = sprintf(
				/* translators: 1: a letter such as B, 2: the letter above it such as A. */
				__( '%1$s must start below %2$s.', 'ohmylms' ),
				letter,
				upper
			);
		}
	} );
	return errors;
}

/**
 * The scale as ranges for a preview: `A 90–100`, …, `F 0–59`.
 *
 * @param values Values.
 * @return Rows of letter and range text.
 */
export function scaleRanges(
	values: GradeScaleValues
): { letter: string; range: string }[] {
	const rows: { letter: string; range: string }[] = [];
	let top = 100;
	for ( const [ name, letter ] of BOUNDARIES ) {
		rows.push( {
			letter,
			range: `${ values[ name ] }–${ top }%`,
		} );
		top = values[ name ] - 1;
	}
	rows.push( { letter: 'F', range: `0–${ Math.max( 0, top ) }%` } );
	return rows;
}

export async function getGradeScale( signal?: AbortSignal ) {
	return parseGradeScale(
		await get< unknown >( '/ohmylms/v1/settings/grade-scale', { signal } )
	);
}

export async function saveGradeScale(
	stored: GradeScaleValues,
	draft: GradeScaleValues,
	signal?: AbortSignal
) {
	return parseGradeScale(
		await patch< unknown >(
			'/ohmylms/v1/settings/grade-scale',
			changedValues( stored, draft ),
			{ signal }
		)
	);
}
