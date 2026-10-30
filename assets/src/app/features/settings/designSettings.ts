import { __ } from '@wordpress/i18n';
import { get, patch } from '../../lib/api';
import {
	asArray,
	asBoolean,
	asNumber,
	asRecord,
	asString,
	invalidResponse,
} from './model';
import type { FieldErrors } from './types';

export const ARCHIVE_FILTERS = [
	'keyword',
	'category',
	'tag',
	'difficulty_level',
	'price_type',
] as const;
export type ArchiveFilter = ( typeof ARCHIVE_FILTERS )[ number ];

export const ROW_CRITERIA = [
	'all',
	'best_selling',
	'top_rated',
	'top_reviewed',
	'free',
	'paid',
	'recent',
] as const;
export type RowCriteria = ( typeof ROW_CRITERIA )[ number ];

export const LAYOUT_STYLES = [
	'grid-style1',
	'grid-style2',
	'grid-style3',
	'grid-style4',
] as const;
export const MAX_ROWS = 10;

export interface ArchiveRow {
	heading: string;
	criteria: RowCriteria;
}

/** Values of `/settings/course-archive`. */
export interface ArchiveValues {
	courses_per_page: number;
	layout: 'grid' | 'list';
	layout_style: ( typeof LAYOUT_STYLES )[ number ];
	columns_per_row: number;
	show_filters: boolean;
	filters: ArchiveFilter[];
	show_sorting: boolean;
	show_search: boolean;
	show_category: boolean;
	rows: ArchiveRow[];
}

/** The quiz palette fields of `/settings/quiz-design`, with labels, in display order. */
export function getQuizColorFields(): { name: string; label: string }[] {
	return [
		{ name: 'stage', label: __( 'Question stage', 'ohmylms' ) },
		{ name: 'prompt', label: __( 'Question panel', 'ohmylms' ) },
		{ name: 'canvas', label: __( 'Editor background', 'ohmylms' ) },
		{ name: 'toolbar', label: __( 'Toolbar background', 'ohmylms' ) },
		{ name: 'toolbar_text', label: __( 'Toolbar text', 'ohmylms' ) },
		{ name: 'accent', label: __( 'Buttons and progress', 'ohmylms' ) },
		{ name: 'text', label: __( 'Question and answer text', 'ohmylms' ) },
		{ name: 'answer_1', label: __( 'Answer card 1', 'ohmylms' ) },
		{ name: 'answer_2', label: __( 'Answer card 2', 'ohmylms' ) },
		{ name: 'answer_3', label: __( 'Answer card 3', 'ohmylms' ) },
		{ name: 'answer_4', label: __( 'Answer card 4', 'ohmylms' ) },
		{ name: 'correct', label: __( 'Correct answer control', 'ohmylms' ) },
		{ name: 'player_stage', label: __( 'Player stage', 'ohmylms' ) },
		{
			name: 'player_prompt',
			label: __( 'Player question panel', 'ohmylms' ),
		},
		{ name: 'player_toolbar', label: __( 'Player toolbar', 'ohmylms' ) },
		{
			name: 'player_toolbar_text',
			label: __( 'Player toolbar text', 'ohmylms' ),
		},
		{
			name: 'player_answer_1',
			label: __( 'Player answer card 1', 'ohmylms' ),
		},
		{
			name: 'player_answer_2',
			label: __( 'Player answer card 2', 'ohmylms' ),
		},
		{
			name: 'player_answer_3',
			label: __( 'Player answer card 3', 'ohmylms' ),
		},
		{
			name: 'player_answer_4',
			label: __( 'Player answer card 4', 'ohmylms' ),
		},
	];
}

export type QuizColors = Record< string, string >;

function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( option ) => option === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}

function integer( value: unknown ): number {
	const number = asNumber( value );
	if ( ! Number.isInteger( number ) ) {
		throw invalidResponse();
	}
	return number;
}

function groupValues( raw: unknown, group: string ) {
	const body = asRecord( raw );
	if ( group !== body.group ) {
		throw invalidResponse();
	}
	return {
		values: asRecord( body.values ),
		changed: asArray( body.changed ).map( asString ),
	};
}

export function parseArchive( raw: unknown ): {
	values: ArchiveValues;
	changed: string[];
} {
	const { values, changed } = groupValues( raw, 'course-archive' );
	return {
		changed,
		values: {
			courses_per_page: integer( values.courses_per_page ),
			layout: oneOf( values.layout, [ 'grid', 'list' ] as const ),
			layout_style: oneOf( values.layout_style, LAYOUT_STYLES ),
			columns_per_row: integer( values.columns_per_row ),
			show_filters: asBoolean( values.show_filters ),
			filters: asArray( values.filters ).map( ( item ) =>
				oneOf( item, ARCHIVE_FILTERS )
			),
			show_sorting: asBoolean( values.show_sorting ),
			show_search: asBoolean( values.show_search ),
			show_category: asBoolean( values.show_category ),
			rows: asArray( values.rows ).map( ( item ) => {
				const row = asRecord( item );
				return {
					heading: asString( row.heading ),
					criteria: oneOf( row.criteria, ROW_CRITERIA ),
				};
			} ),
		},
	};
}

export function parseQuizColors( raw: unknown ): {
	values: QuizColors;
	changed: string[];
} {
	const { values, changed } = groupValues( raw, 'quiz-design' );
	return {
		changed,
		values: Object.fromEntries(
			getQuizColorFields().map( ( { name } ) => [
				name,
				asString( values[ name ] ),
			] )
		),
	};
}

function same( left: unknown, right: unknown ): boolean {
	return JSON.stringify( left ) === JSON.stringify( right );
}

/**
 * The fields of a draft that differ from the stored values.
 *
 * @param stored Stored values.
 * @param draft  Edited values.
 * @return Only the changed fields.
 */
export function changedValues< T extends object >(
	stored: T,
	draft: T
): Partial< T > {
	return Object.fromEntries(
		Object.entries( draft ).filter(
			( [ key, value ] ) =>
				! same( value, ( stored as Record< string, unknown > )[ key ] )
		)
	) as Partial< T >;
}

export function isDirty< T extends object >( stored: T, draft: T ): boolean {
	return Object.keys( changedValues( stored, draft ) ).length > 0;
}

export function validateArchive( draft: ArchiveValues ): FieldErrors {
	const errors: FieldErrors = {};
	if (
		! Number.isInteger( draft.courses_per_page ) ||
		draft.courses_per_page < 1 ||
		draft.courses_per_page > 100
	) {
		errors.courses_per_page = __(
			'Enter a number from 1 to 100.',
			'ohmylms'
		);
	}
	if (
		! Number.isInteger( draft.columns_per_row ) ||
		draft.columns_per_row < 1 ||
		draft.columns_per_row > 6
	) {
		errors.columns_per_row = __( 'Enter a number from 1 to 6.', 'ohmylms' );
	}
	if ( draft.show_filters && ! draft.filters.length ) {
		errors.filters = __(
			'Choose at least one filter, or turn the filter panel off.',
			'ohmylms'
		);
	}
	if ( draft.rows.length > MAX_ROWS ) {
		errors.rows = __( 'Use at most 10 rows.', 'ohmylms' );
	}
	draft.rows.forEach( ( row, index ) => {
		if ( ! row.heading.trim() || row.heading.length > 100 ) {
			errors[ `rows.${ index }` ] = __(
				'Enter a heading of up to 100 characters.',
				'ohmylms'
			);
		}
	} );
	return errors;
}

export function validateQuizColors( draft: QuizColors ): FieldErrors {
	const errors: FieldErrors = {};
	for ( const [ name, value ] of Object.entries( draft ) ) {
		if ( ! /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( value ) ) {
			errors[ name ] = __(
				'Enter a hex colour such as #6e42d3.',
				'ohmylms'
			);
		}
	}
	return errors;
}

export async function getArchive( signal?: AbortSignal ) {
	return parseArchive(
		await get< unknown >( '/ohmylms/v1/settings/course-archive', {
			signal,
		} )
	);
}
export async function saveArchive(
	stored: ArchiveValues,
	draft: ArchiveValues,
	signal?: AbortSignal
) {
	return parseArchive(
		await patch< unknown >(
			'/ohmylms/v1/settings/course-archive',
			changedValues( stored, draft ),
			{ signal }
		)
	);
}
export async function getQuizColors( signal?: AbortSignal ) {
	return parseQuizColors(
		await get< unknown >( '/ohmylms/v1/settings/quiz-design', { signal } )
	);
}
export async function saveQuizColors(
	stored: QuizColors,
	draft: QuizColors,
	signal?: AbortSignal
) {
	return parseQuizColors(
		await patch< unknown >(
			'/ohmylms/v1/settings/quiz-design',
			changedValues( stored, draft ),
			{ signal }
		)
	);
}

export function criteriaLabel( criteria: RowCriteria ): string {
	return {
		all: __( 'All courses', 'ohmylms' ),
		best_selling: __( 'Best selling', 'ohmylms' ),
		top_rated: __( 'Top rated', 'ohmylms' ),
		top_reviewed: __( 'Most reviewed', 'ohmylms' ),
		free: __( 'Free courses', 'ohmylms' ),
		paid: __( 'Paid courses', 'ohmylms' ),
		recent: __( 'Newest', 'ohmylms' ),
	}[ criteria ];
}

export function filterLabel( filter: ArchiveFilter ): string {
	return {
		keyword: __( 'Keyword', 'ohmylms' ),
		category: __( 'Category', 'ohmylms' ),
		tag: __( 'Tag', 'ohmylms' ),
		difficulty_level: __( 'Difficulty', 'ohmylms' ),
		price_type: __( 'Price', 'ohmylms' ),
	}[ filter ];
}
