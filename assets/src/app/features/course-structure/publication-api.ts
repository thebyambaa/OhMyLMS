import { get, post } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

export interface Publication {
	course_id: number;
	revision: string;
	title: string;
	status: string;
	mode: string;
	outcomes: number;
	published_program_id: number;
	readiness: string[];
	units: {
		id: number;
		title: string;
		items: { id: string; type: string; title: string }[];
	}[];
}

export function parsePublication( incoming: unknown ): Publication {
	const row = record( incoming );
	if (
		! text( row.revision ) ||
		! Array.isArray( row.readiness ) ||
		! Array.isArray( row.units )
	) {
		return invalid();
	}
	for ( const field of [ 'outcomes', 'published_program_id' ] ) {
		if (
			! Number.isSafeInteger( row[ field ] ) ||
			Number( row[ field ] ) < 0
		) {
			return invalid();
		}
	}
	return {
		course_id: positiveId( row.course_id ),
		revision: text( row.revision ),
		title: text( row.title ),
		status: text( row.status ),
		mode: text( row.mode ),
		outcomes: Number( row.outcomes ),
		published_program_id: Number( row.published_program_id ),
		readiness: row.readiness.map( text ),
		units: row.units.map( ( value ) => {
			const unit = record( value );
			if (
				! Number.isSafeInteger( unit.id ) ||
				Number( unit.id ) < 0 ||
				! Array.isArray( unit.items )
			) {
				return invalid();
			}
			return {
				id: Number( unit.id ),
				title: text( unit.title ),
				items: unit.items.map( ( raw ) => {
					const item = record( raw );
					return {
						id: text( item.id ),
						type: text( item.type ),
						title: text( item.title ),
					};
				} ),
			};
		} ),
	};
}

const path = ( id: number ) =>
	`/ohmylms/v1/authoring/courses/${ id }/publication`;

export async function previewPublication( id: number, signal: AbortSignal ) {
	const result = parsePublication(
		await get< unknown >( path( id ), { signal } )
	);
	return result.course_id === id ? result : invalid();
}

export async function publishCourse(
	preview: Publication,
	signal: AbortSignal
) {
	const result = parsePublication(
		await post< unknown >(
			path( preview.course_id ),
			{ revision: preview.revision, confirmed: true },
			{ signal }
		)
	);
	return result.course_id === preview.course_id ? result : invalid();
}
