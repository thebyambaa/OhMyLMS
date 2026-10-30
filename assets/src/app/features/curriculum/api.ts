import { get, post, put } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

export interface CurriculumItem {
	id: number;
	parent_id: number;
	position: number;
	name: string;
	item_type: string;
	description: string;
	code: string;
	version: string;
	is_syllabus: boolean;
	course_id: number;
	updated_at: string;
}
export type ItemInput = Pick<
	CurriculumItem,
	'name' | 'item_type' | 'description' | 'code' | 'version' | 'is_syllabus'
>;
const BASE = '/ohmylms/v1';
export function parseItem( value: unknown ): CurriculumItem {
	const row = record( value );
	for ( const field of [ 'parent_id', 'position', 'course_id' ] ) {
		if (
			! Number.isSafeInteger( row[ field ] ) ||
			Number( row[ field ] ) < 0
		) {
			return invalid();
		}
	}
	if ( typeof row.is_syllabus !== 'boolean' ) {
		return invalid();
	}
	return {
		id: positiveId( row.id ),
		parent_id: Number( row.parent_id ),
		position: Number( row.position ),
		course_id: Number( row.course_id ),
		name: text( row.name ),
		item_type: text( row.item_type ),
		description: text( row.description ),
		code: text( row.code ),
		version: text( row.version ),
		is_syllabus: row.is_syllabus,
		updated_at: text( row.updated_at ),
	};
}
export async function readTree(
	signal?: AbortSignal
): Promise< CurriculumItem[] > {
	const row = record(
		await get< unknown >( `${ BASE }/curriculum/tree`, { signal } )
	);
	return Array.isArray( row.items ) ? row.items.map( parseItem ) : invalid();
}
export async function saveItem(
	input: ItemInput,
	current: CurriculumItem | null,
	parent: number,
	signal?: AbortSignal
) {
	const row = record(
		current
			? await put< unknown >(
					`${ BASE }/curriculum/items/${ current.id }`,
					{ ...input, expected_updated_at: current.updated_at },
					{ signal }
				)
			: await post< unknown >(
					`${ BASE }/curriculum/items`,
					{ ...input, parent_id: parent },
					{ signal }
				)
	);
	return parseItem( row.item );
}
export async function moveItem(
	item: CurriculumItem,
	parent: number,
	position?: number,
	signal?: AbortSignal
) {
	await post(
		`${ BASE }/curriculum/items/${ item.id }/move`,
		{
			parent_id: parent,
			...( position !== undefined ? { position } : {} ),
		},
		{ signal }
	);
}
export async function readPlacement( course: number, signal?: AbortSignal ) {
	const row = record(
		await get< unknown >( `${ BASE }/courses/${ course }/organization`, {
			signal,
		} )
	);
	if ( ! Array.isArray( row.curriculum ) ) {
		return invalid();
	}
	return row.curriculum.map( ( item ) => positiveId( record( item ).id ) );
}
export async function savePlacement(
	course: number,
	ids: number[],
	signal?: AbortSignal
) {
	await put(
		`${ BASE }/courses/${ course }/organization`,
		{ curriculum_ids: ids },
		{ signal }
	);
}

/**
 * Exclude the current branch from parent choices; the server checks cycles and depth again.
 * @param items
 * @param id
 */
export function parentChoices( items: CurriculumItem[], id: number ) {
	const excluded = new Set( [ id ] );
	let changed = true;
	while ( changed ) {
		changed = false;
		for ( const item of items ) {
			if ( excluded.has( item.parent_id ) && ! excluded.has( item.id ) ) {
				excluded.add( item.id );
				changed = true;
			}
		}
	}
	return items.filter( ( item ) => ! excluded.has( item.id ) );
}
export function itemPath(
	items: CurriculumItem[],
	item: CurriculumItem
): string {
	const names = [ item.name ];
	const seen = new Set( [ item.id ] );
	let parent = item.parent_id;
	while ( parent && ! seen.has( parent ) ) {
		seen.add( parent );
		const row = items.find( ( candidate ) => candidate.id === parent );
		if ( ! row ) {
			break;
		}
		names.unshift( row.name );
		parent = row.parent_id;
	}
	return names.join( ' › ' );
}
