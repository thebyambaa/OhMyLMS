import { __ } from '@wordpress/i18n';
import {
	invalid,
	record,
	positiveId,
	text,
	type Row,
} from '../../lib/authoring-validation';

export interface Course {
	id: number;
	title: string;
	status: string;
}
export interface Unit {
	id: number | string;
	title: string;
	number: string;
	description: string;
}
export interface Activity extends Row {
	id: string;
	type: string;
	content_id: number;
	chapter_id?: number | string;
	name?: string;
	style?: string;
	group_id?: string;
}
export interface Program extends Row {
	items: Activity[];
	groups: Row[];
	outcomes: Row[];
}
export interface Outline {
	course_id: number;
	revision: string;
	units: Unit[];
	program: Program;
}

export function parseCourse( value: unknown ): Course {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		title: text( row.title ),
		status: text( row.status ),
	};
}
export function parseOutline( raw: unknown ): Outline {
	const row = record( raw );
	const program = record( row.program );
	if (
		! Array.isArray( row.units ) ||
		! Array.isArray( program.items ) ||
		( program.groups !== undefined && ! Array.isArray( program.groups ) ) ||
		! Array.isArray( program.outcomes ) ||
		! text( row.structure_revision ?? row.revision )
	) {
		return invalid();
	}
	if (
		! [ 'traditional', 'skill-based', 'blended' ].includes(
			text( program.mode )
		) ||
		typeof program.recognize_prior !== 'boolean' ||
		! Number.isSafeInteger( program.evidence_days ) ||
		Number( program.evidence_days ) < 0
	) {
		return invalid();
	}
	const outcomes = program.outcomes.map( ( value ) => {
		const outcome = record( value );
		positiveId( outcome.term_id );
		if (
			! [ 'proficient', 'mastered' ].includes( text( outcome.target ) ) ||
			typeof outcome.required !== 'boolean' ||
			( outcome.chapter_id !== undefined &&
				( ! Number.isSafeInteger( outcome.chapter_id ) ||
					Number( outcome.chapter_id ) < 0 ) )
		) {
			return invalid();
		}
		return outcome;
	} );
	if (
		new Set( outcomes.map( ( outcome ) => outcome.term_id ) ).size !==
		outcomes.length
	) {
		return invalid();
	}
	const units = row.units
		.map( ( value ) => {
			const unit = record( value );
			if ( ! Number.isSafeInteger( unit.id ) || Number( unit.id ) < 0 ) {
				return invalid();
			}
			return {
				id: Number( unit.id ),
				title: text( unit.title ),
				number: text( unit.number ),
				description: text( unit.description ?? '' ),
			};
		} )
		.filter( ( unit ) => unit.id !== 0 );
	const items = program.items.map( ( value ) => {
		const item = record( value );
		text( item.id );
		positiveId( item.content_id );
		for ( const field of [ 'name', 'style', 'group_id' ] ) {
			if ( item[ field ] !== undefined ) {
				text( item[ field ] );
			}
		}
		if (
			! [ 'lesson', 'quiz', 'assignment', 'practice' ].includes(
				text( item.type )
			) ||
			( item.chapter_id !== undefined &&
				( ! Number.isSafeInteger( item.chapter_id ) ||
					Number( item.chapter_id ) < 0 ) )
		) {
			return invalid();
		}
		return { ...item } as Activity;
	} );
	if (
		new Set( units.map( ( unit ) => unit.id ) ).size !== units.length ||
		new Set( items.map( ( item ) => item.id ) ).size !== items.length
	) {
		return invalid();
	}
	return {
		course_id: positiveId( row.course_id ),
		revision: text( row.structure_revision ?? row.revision ),
		units,
		program: {
			...program,
			items,
			groups: ( ( program.groups ?? [] ) as unknown[] ).map( record ),
			outcomes,
		},
	};
}

/**
 * Transport only the editable outline; preserve every program field from the read snapshot.
 * @param outline
 */
export function savePayload( outline: Outline ) {
	if ( outline.units.some( ( unit ) => ! unit.title.trim() ) ) {
		throw new Error( __( 'Every unit needs a title.', 'ohmylms' ) );
	}
	return {
		revision: outline.revision,
		units: outline.units,
		program: outline.program,
	};
}

/** Assessment styles that end their unit (or the course); moves keep them after other activities. */
export const TERMINAL = [ 'unit-test', 'course-challenge' ];

/**
 * Remove a unit that was never saved. Its activities and skill targets stay in the program, unplaced.
 * @param outline
 * @param unit
 */
export function removeNewUnit( outline: Outline, unit: string ): Outline {
	const detach = < T extends Row >( row: T ): T => {
		if ( row.chapter_id !== unit ) {
			return row;
		}
		const next: Row = { ...row, chapter_id: 0 };
		delete next.group_id;
		return next as T;
	};
	return {
		...outline,
		units: outline.units.filter( ( row ) => row.id !== unit ),
		program: {
			...outline.program,
			items: outline.program.items.map( detach ),
			outcomes: outline.program.outcomes.map( detach ),
			groups: outline.program.groups.filter(
				( group ) => group.chapter_id !== unit
			),
		},
	};
}
