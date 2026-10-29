import { __ } from '@wordpress/i18n';
import { ApiError, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';

export interface SkillSummary {
	id: number;
	name: string;
	code: string;
	description: string;
	parent: number;
	prerequisites: number[];
	archived: boolean;
}
export interface SkillInput {
	name: string;
	code: string;
	description: string;
	parent: number;
	prerequisites: number[];
}
const BASE = '/ohmylms/v1/skills';
export function invalidResponse(): never {
	throw new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid skill data.', 'ohmylms' ),
	} );
}
export function parseSummary( value: unknown ): SkillSummary {
	if ( ! value || typeof value !== 'object' ) {
		return invalidResponse();
	}
	const row = value as Record< string, unknown >;
	if (
		! Number.isSafeInteger( row.id ) ||
		Number( row.id ) < 1 ||
		! Number.isSafeInteger( row.parent ) ||
		Number( row.parent ) < 0 ||
		typeof row.name !== 'string' ||
		typeof row.code !== 'string' ||
		typeof row.description !== 'string' ||
		typeof row.archived !== 'boolean' ||
		! Array.isArray( row.prerequisites ) ||
		! row.prerequisites.every(
			( id ) => Number.isSafeInteger( id ) && id > 0
		)
	) {
		return invalidResponse();
	}
	return {
		id: Number( row.id ),
		name: row.name,
		code: row.code,
		description: row.description,
		parent: Number( row.parent ),
		prerequisites: row.prerequisites as number[],
		archived: row.archived,
	};
}
export function listSkills(
	page: number,
	search: string,
	signal?: AbortSignal,
	include: readonly number[] = []
) {
	return getCollection( `${ BASE }/catalog`, parseSummary, {
		signal,
		query: { page, per_page: include.length ? 50 : 20, search, include },
	} );
}
export async function saveSkill(
	input: SkillInput,
	id: number | null,
	signal?: AbortSignal
): Promise< number > {
	const value = {
		name: input.name.trim(),
		code: input.code.trim(),
		description: input.description,
		parent: input.parent,
		prerequisites: [ ...input.prerequisites ],
	};
	if (
		! value.name ||
		value.name.length > 200 ||
		value.code.length > 40 ||
		value.description.length > 10000 ||
		value.prerequisites.length > 100
	) {
		throw new Error(
			__(
				'Check the skill name, code and description lengths.',
				'ohmylms'
			)
		);
	}
	const result = await ( id === null
		? post< unknown >( BASE, value, { signal } )
		: put< unknown >( `${ BASE }/${ id }`, value, { signal } ) );
	if (
		! result ||
		typeof result !== 'object' ||
		! ( 'id' in result ) ||
		! Number.isSafeInteger( result.id ) ||
		Number( result.id ) < 1
	) {
		return invalidResponse();
	}
	return Number( result.id );
}
export async function changeArchive(
	id: number,
	archived: boolean,
	signal?: AbortSignal
) {
	const result = await post< unknown >(
		`${ BASE }/${ id }/${ archived ? 'archive' : 'restore' }`,
		undefined,
		{ signal }
	);
	if (
		! result ||
		typeof result !== 'object' ||
		! ( 'id' in result ) ||
		result.id !== id ||
		! ( 'archived' in result ) ||
		result.archived !== archived
	) {
		invalidResponse();
	}
}
export async function getSkillSummary( id: number, signal?: AbortSignal ) {
	const result = await listSkills( 1, '', signal, [ id ] );
	const skill = result.items.find( ( item ) => item.id === id );
	if ( ! skill ) {
		return invalidResponse();
	}
	return skill;
}

export type LinkKind = 'lessons' | 'courses';
export interface LinkTarget {
	id: number;
	title: string;
	linked: boolean;
}
export function listTargets(
	id: number,
	kind: LinkKind,
	page: number,
	search: string,
	linkedOnly: boolean,
	signal?: AbortSignal
) {
	return getCollection(
		`${ BASE }/${ id }/relationships/${ kind }`,
		( value: unknown ): LinkTarget => {
			if ( ! value || typeof value !== 'object' ) {
				return invalidResponse();
			}
			const row = value as Record< string, unknown >;
			if (
				! Number.isSafeInteger( row.id ) ||
				Number( row.id ) < 1 ||
				typeof row.title !== 'string' ||
				typeof row.linked !== 'boolean'
			) {
				return invalidResponse();
			}
			return {
				id: Number( row.id ),
				title: row.title,
				linked: row.linked,
			};
		},
		{ signal, query: { page, search, linked_only: linkedOnly } }
	);
}
export async function changeLink(
	id: number,
	kind: LinkKind,
	target: LinkTarget,
	signal?: AbortSignal
) {
	const value = await put< unknown >(
		`${ BASE }/${ id }/relationships/${ kind }/${ target.id }`,
		{ linked: ! target.linked },
		{ signal }
	);
	if (
		! value ||
		typeof value !== 'object' ||
		! ( 'id' in value ) ||
		value.id !== target.id ||
		! ( 'linked' in value ) ||
		value.linked !== ! target.linked
	) {
		invalidResponse();
	}
}
