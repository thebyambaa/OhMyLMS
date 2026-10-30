import { get, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	asArray,
	asBoolean,
	asNumber,
	asRecord,
	asString,
	invalidResponse,
} from './model';

export interface MigrationSource {
	id: string;
	title: string;
	description: string;
	available: boolean;
}

export interface SourceCourse {
	id: number;
	title: string;
	status: string;
	/** OhMyLMS course created from it, or null. */
	migrated_to: number | null;
}

export const SOURCE_PER_PAGE = 20;

function positive( value: unknown ): number {
	const id = asNumber( value );
	if ( ! Number.isSafeInteger( id ) || id < 1 ) {
		throw invalidResponse();
	}
	return id;
}

function sourceId( id: string ): string {
	if ( ! /^[A-Za-z][A-Za-z0-9_-]{0,39}$/.test( id ) ) {
		throw invalidResponse();
	}
	return id;
}

export function parseSource( raw: unknown ): MigrationSource {
	const item = asRecord( raw );
	return {
		id: sourceId( asString( item.id ) ),
		title: asString( item.title ),
		description: asString( item.description ),
		available: asBoolean( item.available ),
	};
}

export function parseSourceCourse( raw: unknown ): SourceCourse {
	const item = asRecord( raw );
	return {
		id: positive( item.id ),
		title: asString( item.title ),
		status: asString( item.status ),
		migrated_to:
			null === item.migrated_to ? null : positive( item.migrated_to ),
	};
}

export async function listSources( signal?: AbortSignal ) {
	return asArray(
		await get< unknown >( '/ohmylms/v1/migrations', { signal } )
	).map( parseSource );
}

export function listSourceCourses(
	source: string,
	page: number,
	search: string,
	signal?: AbortSignal
) {
	const term = search.trim();
	return getCollection(
		`/ohmylms/v1/migrations/${ sourceId( source ) }/courses`,
		parseSourceCourse,
		{
			signal,
			query: {
				page,
				per_page: SOURCE_PER_PAGE,
				...( term ? { search: term } : {} ),
			},
		}
	);
}

export async function migrateCourse(
	source: string,
	courseId: number,
	signal?: AbortSignal
): Promise< number > {
	const body = asRecord(
		await post< unknown >(
			`/ohmylms/v1/migrations/${ sourceId( source ) }`,
			{ course_id: courseId },
			{ signal }
		)
	);
	return positive( body.migrated_to );
}
