import { __ } from '@wordpress/i18n';
import { ApiError, get, post, put } from '../../lib/api';
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
	input: SkillInput & { objective?: string },
	id: number | null,
	signal?: AbortSignal
): Promise< number > {
	const objective = input.objective?.trim() ?? '';
	const value = {
		name: input.name.trim(),
		code: input.code.trim(),
		description: input.description,
		parent: input.parent,
		prerequisites: [ ...input.prerequisites ],
		...( objective ? { objective } : {} ),
	};
	if (
		! value.name ||
		value.name.length > 200 ||
		value.code.length > 40 ||
		value.description.length > 10000 ||
		value.prerequisites.length > 100 ||
		objective.length > 2000
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

export type Locale = 'en' | 'mn';
export const LOCALES: readonly Locale[] = [ 'en', 'mn' ];
export interface TranslationText {
	name: string;
	objective: string;
	description: string;
}
export interface SkillDraft extends SkillInput {
	objective: string;
	translations: Record< Locale, TranslationText >;
	public_practice: boolean;
}
export interface SkillDetail {
	skill: SkillDraft & {
		id: number;
		archived: boolean;
		effective_objective: string;
	};
	revision: string;
	resources: { lessons: number; courses: number; questions: number };
	canPublishPractice: boolean;
}
function count( value: unknown ) {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalidResponse();
}
function translation( value: unknown ): TranslationText {
	if ( value === undefined ) {
		return { name: '', objective: '', description: '' };
	}
	if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
		return invalidResponse();
	}
	const row = value as Record< string, unknown >;
	const field = ( key: string ) => {
		if ( row[ key ] === undefined ) {
			return '';
		}
		return typeof row[ key ] === 'string'
			? ( row[ key ] as string )
			: invalidResponse();
	};
	return {
		name: field( 'name' ),
		objective: field( 'objective' ),
		description: field( 'description' ),
	};
}
export function parseDetail( value: unknown ): SkillDetail {
	if ( ! value || typeof value !== 'object' ) {
		return invalidResponse();
	}
	const row = value as Record< string, unknown >;
	const skill = row.skill as Record< string, unknown > | undefined;
	const resources = row.resources as Record< string, unknown > | undefined;
	const permissions = row.permissions as
		Record< string, unknown > | undefined;
	if (
		! skill ||
		typeof skill !== 'object' ||
		typeof row.revision !== 'string' ||
		row.revision.length !== 64 ||
		! resources ||
		typeof resources !== 'object' ||
		! permissions ||
		typeof permissions.public_practice !== 'boolean' ||
		typeof skill.objective !== 'string' ||
		typeof skill.effective_objective !== 'string' ||
		typeof skill.public_practice !== 'boolean' ||
		! skill.translations ||
		typeof skill.translations !== 'object' ||
		Array.isArray( skill.translations )
	) {
		return invalidResponse();
	}
	const translations = skill.translations as Record< string, unknown >;
	return {
		skill: {
			...parseSummary( skill ),
			objective: skill.objective,
			effective_objective: skill.effective_objective,
			public_practice: skill.public_practice,
			translations: {
				en: translation( translations.en ),
				mn: translation( translations.mn ),
			},
		},
		revision: row.revision,
		resources: {
			lessons: count( resources.lessons ),
			courses: count( resources.courses ),
			questions: count( resources.questions ),
		},
		canPublishPractice: permissions.public_practice,
	};
}
export async function readSkill( id: number, signal?: AbortSignal ) {
	return parseDetail(
		await get< unknown >( `/ohmylms/v1/authoring/skills/${ id }`, {
			signal,
		} )
	);
}
export function draftOf( detail: SkillDetail ): SkillDraft {
	const { skill } = detail;
	return {
		name: skill.name,
		code: skill.code,
		description: skill.description,
		parent: skill.parent,
		prerequisites: [ ...skill.prerequisites ],
		objective: skill.objective,
		public_practice: skill.public_practice,
		translations: {
			en: { ...skill.translations.en },
			mn: { ...skill.translations.mn },
		},
	};
}
const same = ( a: unknown, b: unknown ) =>
	JSON.stringify( a ) === JSON.stringify( b );
/**
 * Only fields that differ from what was read are sent; translated names and objectives cannot be cleared.
 * @param draft
 * @param baseline
 */
export function skillPatch( draft: SkillDraft, baseline: SkillDraft ) {
	const patch: Record< string, unknown > = {};
	for ( const key of [
		'name',
		'code',
		'description',
		'parent',
		'prerequisites',
		'objective',
		'public_practice',
	] as const ) {
		if ( ! same( draft[ key ], baseline[ key ] ) ) {
			patch[ key ] =
				typeof draft[ key ] === 'string'
					? ( draft[ key ] as string ).trim()
					: draft[ key ];
		}
	}
	const translations: Partial<
		Record< Locale, Partial< TranslationText > >
	> = {};
	for ( const locale of LOCALES ) {
		for ( const field of [ 'name', 'objective', 'description' ] as const ) {
			const next = draft.translations[ locale ][ field ].trim();
			if ( next === baseline.translations[ locale ][ field ].trim() ) {
				continue;
			}
			if ( ! next && field !== 'description' ) {
				throw new Error(
					__(
						'A translated name or objective cannot be cleared. Enter replacement text instead.',
						'ohmylms'
					)
				);
			}
			translations[ locale ] = {
				...translations[ locale ],
				[ field ]: next,
			};
		}
	}
	if ( Object.keys( translations ).length ) {
		patch.translations = translations;
	}
	if ( typeof patch.name === 'string' && ! patch.name ) {
		throw new Error( __( 'A skill name is required.', 'ohmylms' ) );
	}
	return patch;
}
/**
 * Keep the author's edits and take the server's value for every field they did not touch.
 * @param draft
 * @param baseline
 * @param fresh
 */
export function rebase(
	draft: SkillDraft,
	baseline: SkillDraft,
	fresh: SkillDraft
): SkillDraft {
	const merged = { ...fresh, translations: { ...fresh.translations } };
	for ( const key of Object.keys( draft ) as ( keyof SkillDraft )[] ) {
		if (
			key !== 'translations' &&
			! same( draft[ key ], baseline[ key ] )
		) {
			( merged as Record< string, unknown > )[ key ] = draft[ key ];
		}
	}
	for ( const locale of LOCALES ) {
		const text = { ...fresh.translations[ locale ] };
		for ( const field of [ 'name', 'objective', 'description' ] as const ) {
			if (
				draft.translations[ locale ][ field ] !==
				baseline.translations[ locale ][ field ]
			) {
				text[ field ] = draft.translations[ locale ][ field ];
			}
		}
		merged.translations[ locale ] = text;
	}
	return merged;
}
export async function updateSkill(
	id: number,
	draft: SkillDraft,
	baseline: SkillDraft,
	revision: string,
	signal?: AbortSignal
) {
	const patch = skillPatch( draft, baseline );
	return parseDetail(
		await put< unknown >(
			`/ohmylms/v1/authoring/skills/${ id }`,
			{ ...patch, expected_revision: revision },
			{ signal }
		)
	);
}
/**
 * A provider draft for review; nothing is saved.
 * @param id
 * @param locale
 * @param source
 * @param signal
 */
export async function suggestTranslation(
	id: number,
	locale: Locale,
	source: TranslationText,
	signal?: AbortSignal
): Promise< TranslationText > {
	const value = await post< unknown >(
		`${ BASE }/${ id }/translate`,
		{
			locale,
			source: {
				name: source.name,
				description: source.description,
				...( source.objective ? { objective: source.objective } : {} ),
			},
		},
		{ signal }
	);
	const result = translation( value );
	return result.name ? result : invalidResponse();
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
