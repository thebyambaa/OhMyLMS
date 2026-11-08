import { get, post, put, request } from '../../lib/api';
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
	icon: string;
	updated_at: string;
}
export type ItemInput = Pick<
	CurriculumItem,
	| 'name'
	| 'item_type'
	| 'description'
	| 'code'
	| 'version'
	| 'is_syllabus'
	| 'icon'
>;
export interface IconOption {
	id: string;
	label: string;
}
export interface Dependents {
	children: number;
	descendants: number;
	links: number;
	own_links: number;
	groups: number;
	own_groups: number;
}
export type ChildStrategy = '' | 'promote' | 'delete';
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
		icon: row.icon === undefined ? '' : text( row.icon ),
		updated_at: text( row.updated_at ),
	};
}
/** What an item holds, for the counts beside it in the tree. */
export interface ItemCounts {
	children: number;
	courses: number;
	/** Skills in the syllabus groups under the item (a syllabus only). */
	skills: number;
}

const count = ( value: unknown ): number =>
	Number.isSafeInteger( value ) && Number( value ) >= 0 ? Number( value ) : 0;

export async function readWorkspace( signal?: AbortSignal ): Promise< {
	items: CurriculumItem[];
	icons: IconOption[];
	types: string[];
	counts: Record< number, ItemCounts >;
} > {
	const row = record(
		await get< unknown >( `${ BASE }/curriculum/tree`, { signal } )
	);
	if ( ! Array.isArray( row.items ) || ! Array.isArray( row.icons ) ) {
		return invalid();
	}
	const counts: Record< number, ItemCounts > = {};
	for ( const value of row.items ) {
		const raw = record( value );
		const links = record( raw.links ?? {} );
		const syllabus = record( raw.syllabus ?? {} );
		counts[ positiveId( raw.id ) ] = {
			children: count( raw.child_count ),
			courses: count( links.course ),
			skills: count( syllabus.skills ),
		};
	}
	return {
		items: row.items.map( parseItem ),
		icons: row.icons.map( ( value ) => {
			const icon = record( value );
			return { id: text( icon.id ), label: text( icon.label ) };
		} ),
		types: Array.isArray( row.types )
			? row.types.filter(
					( type ): type is string => 'string' === typeof type
				)
			: [],
		counts,
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
export function parseDependents( value: unknown ): Dependents {
	const row = record( value );
	const result: Record< string, number > = {};
	for ( const key of [
		'children',
		'descendants',
		'links',
		'own_links',
		'groups',
		'own_groups',
	] ) {
		if (
			! Number.isSafeInteger( row[ key ] ) ||
			Number( row[ key ] ) < 0
		) {
			return invalid();
		}
		result[ key ] = Number( row[ key ] );
	}
	return result as unknown as Dependents;
}
/**
 * What deleting an item would affect, and the revision the confirmation is valid for.
 * @param id
 * @param signal
 */
export async function readDeletion( id: number, signal?: AbortSignal ) {
	const row = record(
		await get< unknown >( `${ BASE }/curriculum/items/${ id }`, {
			signal,
		} )
	);
	const revision = text( row.delete_revision );
	if ( revision.length !== 64 ) {
		return invalid();
	}
	return {
		item: parseItem( row.item ),
		dependents: parseDependents( row.dependents ),
		revision,
	};
}
export async function deleteItem(
	id: number,
	children: ChildStrategy,
	revision: string,
	signal?: AbortSignal
) {
	const row = record(
		await request< unknown >( {
			path: `${ BASE }/curriculum/items/${ id }`,
			method: 'DELETE',
			query: { children, confirm: true, expected_revision: revision },
			signal,
		} )
	);
	if (
		! Array.isArray( row.deleted ) ||
		! row.deleted.every( ( value ) => Number.isSafeInteger( value ) )
	) {
		return invalid();
	}
	return row.deleted as number[];
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

/** What an item can have linked to it. */
export type LinkType = 'course' | 'skill' | 'bank' | 'quiz';

export interface LinkRow {
	id: number;
	title: string;
	status: string;
	code: string;
	available: boolean;
}

export type ItemLinks = Record< LinkType, LinkRow[] >;

const LINK_TYPES: readonly LinkType[] = [ 'course', 'skill', 'bank', 'quiz' ];

function parseLinks( value: unknown ): ItemLinks {
	const row = record( value );
	const result = {} as ItemLinks;
	for ( const type of LINK_TYPES ) {
		const list = row[ type ];
		if ( ! Array.isArray( list ) ) {
			return invalid();
		}
		result[ type ] = list.map( ( entry ) => {
			const link = record( entry );
			return {
				id: positiveId( link.id ),
				title: text( link.title ),
				status: text( link.status ),
				code: 'string' === typeof link.code ? link.code : '',
				available: Boolean( link.available ),
			};
		} );
	}
	return result;
}

/**
 * Courses, skills, question banks and quizzes linked to one item.
 *
 * @param id     Item ID.
 * @param signal Cancels the request.
 * @return The links by type.
 */
export async function readItemLinks( id: number, signal?: AbortSignal ) {
	const row = record(
		await get< unknown >( `${ BASE }/curriculum/items/${ id }`, { signal } )
	);
	return parseLinks( row.links );
}

/**
 * Content that can be linked, by title.
 *
 * @param type   What to look for.
 * @param search Part of a title or name.
 * @param signal Cancels the request.
 * @return Matching rows.
 */
export async function searchLinkTargets(
	type: LinkType,
	search: string,
	signal?: AbortSignal
): Promise< { id: number; title: string; status: string }[] > {
	const result = await get< unknown >( `${ BASE }/curriculum/link-targets`, {
		query: { type, search },
		signal,
	} );
	if ( ! Array.isArray( result ) ) {
		return invalid();
	}
	return result.map( ( entry ) => {
		const row = record( entry );
		return {
			id: positiveId( row.id ),
			title: text( row.title ),
			status: text( row.status ),
		};
	} );
}

/**
 * Link content to an item.
 *
 * @param id       Item ID.
 * @param type     What is linked.
 * @param objectId The linked course, bank or quiz.
 * @param signal   Cancels the request.
 * @return The item's links.
 */
export async function addItemLink(
	id: number,
	type: LinkType,
	objectId: number,
	signal?: AbortSignal
) {
	const row = record(
		await post< unknown >(
			`${ BASE }/curriculum/items/${ id }/links`,
			{ object_type: type, object_id: objectId },
			{ signal }
		)
	);
	return parseLinks( row.links );
}

/**
 * Take content off an item. The content itself is kept.
 *
 * @param id       Item ID.
 * @param type     What is linked.
 * @param objectId The linked course, bank or quiz.
 * @param signal   Cancels the request.
 * @return The item's links.
 */
export async function removeItemLink(
	id: number,
	type: LinkType,
	objectId: number,
	signal?: AbortSignal
) {
	const row = record(
		await request< unknown >( {
			path: `${ BASE }/curriculum/items/${ id }/links`,
			method: 'DELETE',
			query: { object_type: type, object_id: objectId },
			signal,
		} )
	);
	return parseLinks( row.links );
}
