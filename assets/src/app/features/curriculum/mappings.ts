import { get, post, put } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

/**
 * Mappings between syllabus objectives and shared skills, and the comparison of two syllabuses
 * through them, for the syllabus editor (through `public.ts`). The comparison and skill workspace
 * screens read and remove mappings with their own views in `compare.ts` and `workspace.ts`; this
 * module stays separate so neither is bundled into the syllabus chunk, nor this one into theirs.
 */
const BASE = '/ohmylms/v1/syllabus-comparison';

function nonnegative( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}

/**
 * One page of shared skills for a mapping picker.
 *
 * @param search Name search.
 * @param page   1-based page.
 * @param signal Cancellation.
 * @return Skill names and the total.
 */
export async function searchSharedSkills(
	search: string,
	page: number,
	signal?: AbortSignal
) {
	const value = record(
		await get< unknown >( `${ BASE }/shared-skills`, {
			query: { search, page },
			signal,
		} )
	);
	if ( ! Array.isArray( value.items ) ) {
		return invalid();
	}
	return {
		total: nonnegative( value.total ),
		items: value.items.map( ( raw ) => {
			const row = record( raw );
			return { id: positiveId( row.id ), name: text( row.name ) };
		} ),
	};
}

/**
 * Create or change the mapping of a syllabus objective to a shared skill.
 *
 * @param specificId Syllabus objective (skill term).
 * @param sharedId   Shared skill.
 * @param relation   `equivalent`, `broader`, `narrower` or `related`.
 * @param note       Reviewer note.
 * @param revision   Revision the mapping was read at; empty for a new mapping.
 * @param signal     Cancellation.
 */
export async function saveSkillMapping(
	specificId: number,
	sharedId: number,
	relation: string,
	note: string,
	revision: string,
	signal?: AbortSignal
) {
	await put(
		`${ BASE }/mapping`,
		{
			specific_id: specificId,
			shared_id: sharedId,
			relation,
			note,
			expected_revision: revision,
		},
		{ signal }
	);
}

/**
 * Remove one mapping at the revision it was read at.
 *
 * @param specificId Syllabus objective (skill term).
 * @param sharedId   Shared skill.
 * @param revision   Mapping revision.
 * @param signal     Cancellation.
 */
export async function removeSkillMapping(
	specificId: number,
	sharedId: number,
	revision: string,
	signal?: AbortSignal
) {
	await post(
		`${ BASE }/mapping/remove`,
		{
			specific_id: specificId,
			shared_id: sharedId,
			expected_revision: revision,
		},
		{ signal }
	);
}

export interface SyllabusComparison {
	a: { id: number; name: string };
	b: { id: number; name: string };
	overlap: number;
	both: number;
	onlyA: number;
	onlyB: number;
	unmappedA: number;
	unmappedB: number;
	rows: { name: string; a: string[]; b: string[] }[];
}

/**
 * Coverage of two syllabuses through their shared-skill mappings.
 *
 * @param a      First syllabus.
 * @param b      Second syllabus.
 * @param signal Cancellation.
 * @return Counts and the rows per shared skill.
 */
export async function compareSyllabuses(
	a: number,
	b: number,
	signal?: AbortSignal
): Promise< SyllabusComparison > {
	const value = record(
		await get< unknown >( `${ BASE }/compare`, {
			query: { a, b },
			signal,
		} )
	);
	if (
		! Array.isArray( value.rows ) ||
		positiveId( record( value.a ).id ) !== a ||
		positiveId( record( value.b ).id ) !== b ||
		nonnegative( value.overlap ) > 100
	) {
		return invalid();
	}
	const count = ( key: string ) => {
		const list = value[ key ];
		if ( ! Array.isArray( list ) ) {
			return invalid();
		}
		list.forEach( positiveId );
		return list.length;
	};
	const left = record( value.a );
	const right = record( value.b );
	const skills = record( value.skills );
	const names = ( side: Record< string, unknown > ) => {
		if ( ! Array.isArray( side.objectives ) ) {
			return invalid();
		}
		return new Map(
			side.objectives.map( ( raw ) => {
				const row = record( raw );
				return [ positiveId( row.id ), text( row.name ) ];
			} )
		);
	};
	const leftNames = names( left );
	const rightNames = names( right );
	const parseSide = ( raw: unknown, labels: Map< number, string > ) => {
		if ( Array.isArray( raw ) && ! raw.length ) {
			return [];
		}
		return Object.keys( record( raw ) ).map(
			( id ) => labels.get( Number( id ) ) ?? invalid()
		);
	};
	return {
		a: { id: positiveId( left.id ), name: text( left.name ) },
		b: { id: positiveId( right.id ), name: text( right.name ) },
		overlap: nonnegative( value.overlap ),
		both: count( 'both' ),
		onlyA: count( 'only_a' ),
		onlyB: count( 'only_b' ),
		unmappedA: count( 'unmapped_a' ),
		unmappedB: count( 'unmapped_b' ),
		rows: value.rows.map( ( raw ) => {
			const row = record( raw );
			const id = positiveId( row.skill );
			return {
				name: text( record( skills[ String( id ) ] ).name ),
				a: parseSide( row.a, leftNames ),
				b: parseSide( row.b, rightNames ),
			};
		} ),
	};
}
