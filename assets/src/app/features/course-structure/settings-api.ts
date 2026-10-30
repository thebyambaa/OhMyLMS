import { get, put } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

export interface CourseSettings {
	course_id: number;
	revision: string;
	title: string;
	description: string;
	author_id: number;
	image_id: number;
	status: string;
	price_type: string;
	regular_price: string;
	sale_price: string;
	access_type: string;
	has_capacity: boolean;
	capacity: number;
}

export function parseSettings( value: unknown ): CourseSettings {
	const row = record( value );
	for ( const key of [ 'image_id', 'capacity' ] ) {
		if (
			! Number.isSafeInteger( row[ key ] ) ||
			Number( row[ key ] ) < 0
		) {
			return invalid();
		}
	}
	if ( typeof row.has_capacity !== 'boolean' || ! text( row.revision ) ) {
		return invalid();
	}
	return {
		course_id: positiveId( row.course_id ),
		revision: text( row.revision ),
		title: text( row.title ),
		description: text( row.description ),
		author_id: positiveId( row.author_id ),
		image_id: Number( row.image_id ),
		status: text( row.status ),
		price_type: text( row.price_type ),
		regular_price: text( row.regular_price ),
		sale_price: text( row.sale_price ),
		access_type: text( row.access_type ),
		has_capacity: row.has_capacity,
		capacity: Number( row.capacity ),
	};
}

const path = ( id: number ) => `/ohmylms/v1/authoring/courses/${ id }/settings`;

export async function readSettings( id: number, signal?: AbortSignal ) {
	const result = parseSettings(
		await get< unknown >( path( id ), { signal } )
	);
	return result.course_id === id ? result : invalid();
}

export async function saveSettings(
	draft: CourseSettings,
	password: string,
	signal: AbortSignal
) {
	const { course_id: courseId, ...data } = draft;
	const result = parseSettings(
		await put< unknown >(
			path( courseId ),
			{ ...data, ...( password ? { password } : {} ) },
			{ signal }
		)
	);
	return result.course_id === courseId ? result : invalid();
}
