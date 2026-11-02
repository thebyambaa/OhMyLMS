import { get, post } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';
import { parseOutline, parseReport, type CommandName } from './model';
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
