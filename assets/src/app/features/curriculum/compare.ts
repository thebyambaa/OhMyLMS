import { __ } from '@wordpress/i18n';
import { ApiError, get, post, put } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
	type Row,
} from '../../lib/authoring-validation';

const BASE = '/ohmylms/v1/syllabus-comparison';

/** Most sources in a comparison group or roadmap. */
export const MAX_SOURCES = 10;

/** Most steps in a roadmap. */
export const MAX_ROADMAP_SKILLS = 200;

/** Shared skills per page (fixed by the server). */
export const SHARED_PER_PAGE = 30;

export interface CompareSource {
	id: number;
	name: string;
	code: string;
	version: string;
	/** Folder names from the root down. */
	path: string[];
	item_type: 'syllabus' | 'roadmap';
}

/** Selectors and the subject => source groups with their revision. */
export interface ComparisonGroups {
	items: CompareSource[];
	selected: Record< string, number[] >;
	revision: string;
}

export interface SharedSkillSummary {
	id: number;
	name: string;
	description: string;
	topic: string;
	subject: string;
	mapped_from: MappedObjective[];
	revision: string;
}

export interface MappedObjective {
	id: number;
	specific_id: number;
	name: string;
	relation: string;
}

export interface SharedSkillInput {
	name: string;
	description: string;
	topic: string;
	subject: string;
}

export interface RoadmapPreview {
	skills: { id: number; name: string }[];
	sources: { id: number; name: string; version: string }[];
	unmapped: number;
}

export interface RoadmapState {
	id: number;
	name: string;
	syllabus_ids: number[];
	skills: { id: number; name: string; folder_id: number }[];
	revision: string;
}

export interface AuditSubject {
	subject: string;
	shared: {
		id: number;
		name: string;
		level: string;
		evidence: number;
	}[];
	syllabuses: {
		id: number;
		name: string;
		equivalent: number;
		related_only: number;
		unmapped: number;
		unassessed: number;
		developing: number;
	}[];
}

function count( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}

function list( value: unknown ): unknown[] {
	return Array.isArray( value ) ? value : invalid();
}

function idList( value: unknown ): number[] {
	return list( value ).map( positiveId );
}

function optionalText( value: unknown ): string {
	return undefined === value || null === value ? '' : text( value );
}

function revision( value: unknown, length: number ): string {
	const found = text( value );
	return new RegExp( `^[a-f0-9]{${ length }}$` ).test( found )
		? found
		: invalid();
}

/**
 * Parse the comparison groups and selectors.
 *
 * @param value Parsed JSON.
 * @return Groups.
 */
export function parseGroups( value: unknown ): ComparisonGroups {
	const body = record( value );
	const selected: Record< string, number[] > = {};
	const raw = body.selected;
	if ( ! Array.isArray( raw ) || raw.length ) {
		for ( const [ subject, ids ] of Object.entries( record( raw ) ) ) {
			selected[ subject ] = idList( ids );
		}
	}
	return {
		items: list( body.items ).map( ( entry ) => {
			const row = record( entry );
			return {
				id: positiveId( row.id ),
				name: text( row.name ),
				code: optionalText( row.code ),
				version: optionalText( row.version ),
				path:
					undefined === row.path ? [] : list( row.path ).map( text ),
				item_type: 'roadmap' === row.item_type ? 'roadmap' : 'syllabus',
			};
		} ),
		selected,
		revision: revision( body.revision, 16 ),
	};
}

function parseMapped( value: unknown ): MappedObjective {
	const row = record( value );
	const specific = row.specific ? record( row.specific ) : null;
	return {
		id: positiveId( row.id ),
		specific_id: positiveId( row.specific_id ),
		name: specific ? optionalText( specific.name ) : '',
		relation: text( row.relation ),
	};
}

/**
 * Parse a shared skill.
 *
 * @param value Parsed JSON.
 * @return Skill.
 */
export function parseSharedSkill( value: unknown ): SharedSkillSummary {
	const row = record( value );
	const mappings = record( row.mappings );
	return {
		id: positiveId( row.id ),
		name: text( row.name ),
		description: optionalText( row.description ),
		topic: optionalText( row.topic ),
		subject: optionalText( row.subject ),
		mapped_from: list( mappings.mapped_from ).map( parseMapped ),
		revision: revision( row.revision, 64 ),
	};
}

/**
 * Parse a roadmap editor state.
 *
 * @param value Parsed JSON.
 * @return State.
 */
export function parseRoadmap( value: unknown ): RoadmapState {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		name: text( row.name ),
		syllabus_ids: idList( row.syllabus_ids ),
		skills: list( row.skills ).map( ( entry ) => {
			const skill = record( entry );
			return {
				id: positiveId( skill.id ),
				name: text( skill.name ),
				folder_id: count( skill.folder_id ),
			};
		} ),
		revision: revision( row.revision, 64 ),
	};
}

/**
 * Parse a roadmap preview.
 *
 * @param value Parsed JSON.
 * @return Preview.
 */
export function parsePreview( value: unknown ): RoadmapPreview {
	const row = record( value );
	return {
		skills: list( row.skills ).map( ( entry ) => {
			const skill = record( entry );
			return { id: positiveId( skill.id ), name: text( skill.name ) };
		} ),
		sources: list( row.sources ).map( ( entry ) => {
			const source = record( entry );
			return {
				id: positiveId( source.id ),
				name: text( source.name ),
				version: optionalText( source.version ),
			};
		} ),
		unmapped: count( row.unmapped ),
	};
}

/**
 * Parse a learner audit into per-subject summaries.
 *
 * @param value Parsed JSON (subject => progress).
 * @return Subjects.
 */
export function parseAudit( value: unknown ): AuditSubject[] {
	if ( Array.isArray( value ) && ! value.length ) {
		return [];
	}
	return Object.entries( record( value ) ).map( ( [ subject, raw ] ) => {
		const data = record( raw );
		return {
			subject,
			shared: list( data.shared ).map( ( entry ) => {
				const skill = record( entry );
				const shared = record( skill.shared );
				return {
					id: positiveId( shared.id ),
					name: text( shared.name ),
					level: optionalText( skill.level_label ),
					evidence: count( skill.evidence_count ?? 0 ),
				};
			} ),
			syllabuses: list( data.syllabuses ).map( ( entry ) => {
				const syllabus = record( entry );
				const head = record( syllabus.syllabus );
				const coverage = record( syllabus.coverage );
				const audit: Row = record( syllabus.audit );
				return {
					id: positiveId( head.id ),
					name: text( head.name ),
					equivalent: count( coverage.equivalent ),
					related_only: count( coverage.related_only ),
					unmapped: count( coverage.unmapped ),
					unassessed: list( audit.unassessed ).length,
					developing: list( audit.developing ).length,
				};
			} ),
		};
	} );
}

/**
 * Whether an error is a stale write of this screen.
 *
 * @param error Thrown value.
 * @return True for the comparison, shared skill and roadmap revision conflicts.
 */
export function isStale( error: unknown ): boolean {
	return (
		error instanceof ApiError &&
		409 === error.status &&
		[
			'ohmylms_comparison_stale',
			'ohmylms_shared_stale',
			'ohmylms_roadmap_stale',
			'ohmylms_roadmap_sources_changed',
		].includes( error.code )
	);
}

/**
 * Check a shared skill form.
 *
 * @param input Form values.
 * @return A message, or null when valid.
 */
export function sharedSkillProblem( input: SharedSkillInput ): string | null {
	const name = input.name.trim();
	if ( ! name || Array.from( name ).length > 190 ) {
		return __( 'Enter a name of 1 to 190 characters.', 'ohmylms' );
	}
	if (
		input.description.length > 2000 ||
		input.topic.trim().length > 100 ||
		input.subject.trim().length > 100
	) {
		return __(
			'Use at most 2000 characters for the scope and 100 for the topic and subject.',
			'ohmylms'
		);
	}
	return null;
}

/**
 * Move one entry of a list up or down.
 *
 * @param items Entries.
 * @param index Entry to move.
 * @param step  -1 (up) or 1 (down).
 * @return A new list.
 */
export function moved< T >( items: readonly T[], index: number, step: number ) {
	const target = index + step;
	if ( target < 0 || target >= items.length ) {
		return [ ...items ];
	}
	const next = [ ...items ];
	const [ entry ] = next.splice( index, 1 );
	next.splice( target, 0, entry as T );
	return next;
}

/**
 * Read the selectors and comparison groups.
 *
 * @param signal Cancels the request.
 * @return Groups.
 */
export async function readGroups( signal?: AbortSignal ) {
	return parseGroups(
		await get< unknown >( `${ BASE }/syllabuses`, { signal } )
	);
}

/**
 * Save one subject's sources; an empty list removes the group.
 *
 * @param subject  Subject.
 * @param ids      Source syllabus IDs.
 * @param expected Revision the form was loaded from.
 * @param signal   Cancels the request.
 * @return Groups after saving.
 */
export async function saveGroup(
	subject: string,
	ids: number[],
	expected: string,
	signal?: AbortSignal
) {
	return parseGroups(
		await put< unknown >(
			`${ BASE }/configuration`,
			{
				subject: subject.trim(),
				syllabus_ids: ids,
				expected_revision: expected,
			},
			{ signal }
		)
	);
}

/**
 * Search shared skills.
 *
 * @param search  Name search.
 * @param subject Subject filter, or ''.
 * @param page    Page, from 1.
 * @param signal  Cancels the request.
 * @return Skills and total.
 */
export async function listSharedSkills(
	search: string,
	subject: string,
	page: number,
	signal?: AbortSignal
) {
	const body = record(
		await get< unknown >( `${ BASE }/shared-skills`, {
			signal,
			query: {
				search: search || undefined,
				subject: subject || undefined,
				page,
			},
		} )
	);
	return {
		items: list( body.items ).map( parseSharedSkill ),
		total: count( body.total ),
	};
}

/**
 * Read one shared skill with its mapped objectives.
 *
 * @param id     Skill ID.
 * @param signal Cancels the request.
 * @return Skill.
 */
export async function readSharedSkill( id: number, signal?: AbortSignal ) {
	return parseSharedSkill(
		await get< unknown >( `${ BASE }/shared-skills/${ positiveId( id ) }`, {
			signal,
		} )
	);
}

/**
 * Create (id 0) or update a shared skill against its revision.
 *
 * @param id       Skill ID, or 0.
 * @param input    Fields.
 * @param expected Revision for updates.
 * @param signal   Cancels the request.
 * @return Saved skill.
 */
export async function saveSharedSkill(
	id: number,
	input: SharedSkillInput,
	expected: string | null,
	signal?: AbortSignal
) {
	return parseSharedSkill(
		await post< unknown >(
			`${ BASE }/shared-skills/save`,
			{
				id,
				name: input.name.trim(),
				description: input.description,
				topic: input.topic.trim(),
				subject: input.subject.trim(),
				...( id && expected ? { expected_revision: expected } : {} ),
			},
			{ signal }
		)
	);
}

/**
 * Preview the shared skills a set of sources maps onto.
 *
 * @param ids    Source syllabus IDs.
 * @param signal Cancels the request.
 * @return Preview.
 */
export async function previewRoadmap( ids: number[], signal?: AbortSignal ) {
	return parsePreview(
		await post< unknown >(
			`${ BASE }/roadmaps/preview`,
			{ syllabus_ids: ids },
			{ signal }
		)
	);
}

/**
 * Create a draft roadmap from sources (all previewed shared skills, in source order).
 *
 * @param name   Roadmap name.
 * @param ids    Source syllabus IDs.
 * @param signal Cancels the request.
 * @return The new roadmap's ID.
 */
export async function createRoadmap(
	name: string,
	ids: number[],
	signal?: AbortSignal
) {
	const body = record(
		await post< unknown >(
			`${ BASE }/roadmaps`,
			{ name: name.trim(), syllabus_ids: ids },
			{ signal }
		)
	);
	return positiveId( record( body.syllabus ).id );
}

/**
 * Read a roadmap's editable state.
 *
 * @param id     Roadmap ID.
 * @param signal Cancels the request.
 * @return State.
 */
export async function readRoadmap( id: number, signal?: AbortSignal ) {
	return parseRoadmap(
		await get< unknown >( `${ BASE }/roadmaps/${ positiveId( id ) }`, {
			signal,
		} )
	);
}

/**
 * Save a roadmap's name, sources and step order (folders are kept; new steps join the first folder).
 *
 * @param id       Roadmap ID.
 * @param input    Name, source IDs and ordered skill IDs.
 * @param expected Revision the editor was loaded from.
 * @param signal   Cancels the request.
 * @return Saved state.
 */
export async function saveRoadmap(
	id: number,
	input: RoadmapInput,
	expected: string,
	signal?: AbortSignal
) {
	return parseRoadmap(
		await put< unknown >(
			`${ BASE }/roadmaps/${ positiveId( id ) }`,
			{
				name: input.name.trim(),
				syllabus_ids: input.syllabus_ids,
				skill_ids: input.skill_ids,
				expected_revision: expected,
			},
			{ signal }
		)
	);
}

export interface RoadmapInput {
	name: string;
	syllabus_ids: number[];
	skill_ids: number[];
}

/**
 * Read one learner's subject audit.
 *
 * @param studentId Learner user ID.
 * @param signal    Cancels the request.
 * @return Subjects.
 */
export async function readAudit( studentId: number, signal?: AbortSignal ) {
	return parseAudit(
		await get< unknown >( `${ BASE }/audit/${ positiveId( studentId ) }`, {
			signal,
		} )
	);
}
