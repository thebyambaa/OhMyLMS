import { del, get, post, put } from '../../lib/api';
import type { GradebookQuery } from './model';
import type {
	CellDetail,
	CourseGrid,
	GradebookContext,
	GradeItem,
	MasteryView,
	RollupView,
	RosterView,
	SavedView,
	Section,
	Transcript,
	Weights,
} from './types';

const BASE = '/ohmylms/v1/gradebook';

function invalid(): Error {
	return new Error( 'The gradebook sent an unexpected response.' );
}

function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalid();
	}
	return value as Record< string, unknown >;
}

/**
 * Check that a response is an object whose named members are arrays.
 *
 * @param raw    Decoded body.
 * @param arrays Members that must be arrays.
 * @return The body typed as T.
 */
export function expectShape< T >( raw: unknown, arrays: readonly string[] ): T {
	const body = record( raw );
	for ( const name of arrays ) {
		if ( ! Array.isArray( body[ name ] ) ) {
			throw invalid();
		}
	}
	return body as unknown as T;
}

function scoped( scope: string ) {
	return scope ? { scope } : {};
}

export async function getContext( scope: string, signal?: AbortSignal ) {
	return expectShape< GradebookContext >(
		await get< unknown >( `${ BASE }/context`, {
			query: scoped( scope ),
			signal,
		} ),
		[ 'scopes', 'courses', 'classes', 'scale' ]
	);
}

export async function getCourseGrid(
	query: GradebookQuery,
	signal?: AbortSignal,
	exportAll = false
) {
	return expectShape< CourseGrid >(
		await get< unknown >( `${ BASE }/courses/${ query.course }`, {
			query: {
				...scoped( query.scope ),
				audience: query.audience,
				...( query.status ? { status: query.status } : {} ),
				...( query.type ? { type: query.type } : {} ),
				...( query.module ? { module: query.module } : {} ),
				...( query.search ? { search: query.search } : {} ),
				orderby: query.orderby,
				order: query.order,
				page: exportAll ? 1 : query.page,
				per_page: 50,
				...( exportAll ? { export: true } : {} ),
			},
			signal,
		} ),
		[ 'items', 'rows', 'modules', 'categories', 'classes', 'scale' ]
	);
}

export async function getRoster(
	scope: string,
	classId: number,
	signal?: AbortSignal
) {
	return expectShape< RosterView >(
		await get< unknown >( `${ BASE }/classes/${ classId }`, {
			query: scoped( scope ),
			signal,
		} ),
		[ 'courses', 'learners', 'scale' ]
	);
}

export async function findLearners(
	scope: string,
	search: string,
	signal?: AbortSignal
) {
	return expectShape< { learners: { id: number; name: string }[] } >(
		await get< unknown >( `${ BASE }/learners`, {
			query: { ...scoped( scope ), ...( search ? { search } : {} ) },
			signal,
		} ),
		[ 'learners' ]
	).learners;
}

export async function getTranscript(
	scope: string,
	learner: number,
	signal?: AbortSignal
) {
	return expectShape< Transcript >(
		await get< unknown >( `${ BASE }/learners/${ learner }`, {
			query: scoped( scope ),
			signal,
		} ),
		[ 'enrolments', 'scale' ]
	);
}

export async function getMastery(
	query: GradebookQuery,
	signal?: AbortSignal
) {
	return expectShape< MasteryView >(
		await get< unknown >( `${ BASE }/mastery`, {
			query: {
				...scoped( query.scope ),
				course: query.course,
				audience: query.audience,
				...( query.search ? { search: query.search } : {} ),
				page: query.page,
			},
			signal,
		} ),
		[ 'skills', 'learners', 'levels' ]
	);
}

export async function getRollup( scope: string, signal?: AbortSignal ) {
	return expectShape< RollupView >(
		await get< unknown >( `${ BASE }/rollup`, {
			query: scoped( scope ),
			signal,
		} ),
		[ 'nodes' ]
	);
}

function cellPath( course: number, item: number, learner: number ) {
	return `${ BASE }/courses/${ course }/cells/${ item }/${ learner }`;
}

export async function getCell(
	scope: string,
	course: number,
	item: number,
	learner: number,
	signal?: AbortSignal
) {
	return expectShape< CellDetail >(
		await get< unknown >( cellPath( course, item, learner ), {
			query: scoped( scope ),
			signal,
		} ),
		[ 'history' ]
	);
}

export interface CellChange {
	action: 'override' | 'excuse' | 'revert';
	score?: number;
	reason: string;
	expected_version: number;
}

export async function changeCell(
	scope: string,
	course: number,
	item: number,
	learner: number,
	change: CellChange
) {
	return expectShape< CellDetail >(
		await put< unknown >( cellPath( course, item, learner ), {
			...scoped( scope ),
			...change,
		} ),
		[ 'history' ]
	);
}

export async function saveWeights(
	scope: string,
	course: number,
	classId: number,
	weights: Weights,
	expectedVersion: number
) {
	return record(
		await put< unknown >( `${ BASE }/courses/${ course }/weights`, {
			...scoped( scope ),
			class_id: classId,
			weights,
			expected_version: expectedVersion,
		} )
	) as { class_id: number; weights: Weights; version: number };
}

export interface ItemSettings {
	category: string;
	max_marks: number | null;
	audience: GradeItem[ 'audience' ];
	class_ids: number[];
	due_at: string | null;
}

export async function saveItem(
	scope: string,
	course: number,
	item: number,
	settings: ItemSettings
) {
	return record(
		await put< unknown >( `${ BASE }/courses/${ course }/items/${ item }`, {
			...scoped( scope ),
			...settings,
		} )
	) as unknown as GradeItem;
}

export async function listViews( signal?: AbortSignal ) {
	return expectShape< { views: SavedView[] } >(
		await get< unknown >( `${ BASE }/views`, { signal } ),
		[ 'views' ]
	).views;
}

export async function createView(
	name: string,
	section: Section,
	query: string
) {
	return record(
		await post< unknown >( `${ BASE }/views`, { name, section, query } )
	) as unknown as SavedView;
}

export async function deleteView( id: string ) {
	return del< unknown >( `${ BASE }/views/${ encodeURIComponent( id ) }` );
}
