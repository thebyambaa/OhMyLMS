import { get, post, put } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';
import {
	nonnegative,
	parseOutline,
	parseReport,
	type CommandName,
} from './model';
import type { CsvRow } from '../../../features/curriculum/csv.mjs';

const BASE = '/ohmylms/v1';
export async function readSyllabus( id: number, signal?: AbortSignal ) {
	const value = parseOutline(
		await get< unknown >( `${ BASE }/authoring/syllabuses/${ id }`, {
			signal,
		} )
	);
	return value.syllabus.id === id ? value : invalid();
}
export async function command(
	id: number,
	operation: CommandName,
	fields: Record< string, unknown >,
	signal?: AbortSignal
) {
	const result = parseOutline(
		await post< unknown >(
			`${ BASE }/authoring/syllabuses/${ id }/commands`,
			{ ...fields, operation },
			{ signal }
		)
	);
	return result.syllabus.id === id ? result : invalid();
}
export async function importRows(
	id: number,
	rows: CsvRow[],
	dryRun: boolean,
	signal?: AbortSignal,
	reviewRevision?: string
) {
	const value = record(
		await post< unknown >(
			`${ BASE }/authoring/syllabuses/${ id }/commands`,
			{
				operation: 'import',
				rows,
				dry_run: dryRun,
				...( dryRun ? {} : { review_revision: reviewRevision ?? '' } ),
			},
			{ signal }
		)
	);
	const report = parseReport( value.report );
	return { report, outline: report.applied ? parseOutline( value ) : null };
}
export interface Mapping {
	shared_id: number;
	name: string;
	relation: string;
	note: string;
	revision: string;
	combined: boolean;
}
export async function readMappings(
	skill: number,
	signal?: AbortSignal
): Promise< Mapping[] > {
	const value = record(
		await get< unknown >( `${ BASE }/skill-mappings`, {
			query: { skill_id: skill },
			signal,
		} )
	);
	if ( ! Array.isArray( value.maps_to ) ) {
		return invalid();
	}
	return value.maps_to.map( ( raw ) => {
		const row = record( raw );
		return {
			shared_id: positiveId( row.shared_id ),
			name: text( record( row.shared ).name ),
			relation: text( row.relation ),
			note: text( row.note ),
			revision: text( row.revision ),
			combined: !! text( row.group_uuid ?? '' ),
		};
	} );
}
export async function sharedSkills(
	search: string,
	page: number,
	signal?: AbortSignal
) {
	const value = record(
		await get< unknown >( `${ BASE }/syllabus-comparison/shared-skills`, {
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
export async function saveMapping(
	skill: number,
	shared: number,
	relation: string,
	note: string,
	revision: string,
	signal?: AbortSignal
) {
	await put(
		`${ BASE }/syllabus-comparison/mapping`,
		{
			specific_id: skill,
			shared_id: shared,
			relation,
			note,
			expected_revision: revision,
		},
		{ signal }
	);
}
export async function removeMapping(
	skill: number,
	mapping: Mapping,
	signal?: AbortSignal
) {
	await post(
		`${ BASE }/syllabus-comparison/mapping/remove`,
		{
			specific_id: skill,
			shared_id: mapping.shared_id,
			expected_revision: mapping.revision,
		},
		{ signal }
	);
}
export interface Comparison {
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
export async function compare(
	a: number,
	b: number,
	signal?: AbortSignal
): Promise< Comparison > {
	const value = record(
		await get< unknown >( `${ BASE }/syllabus-comparison/compare`, {
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
