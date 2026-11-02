import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

export interface Skill {
	term_id: number;
	name: string;
	code: string;
	description: string;
}
export interface Group {
	id: number;
	item_id: number;
	position: number;
	name: string;
	code: string;
	description: string;
	skills: Skill[];
}
export interface Content {
	id: number;
	parent_id: number;
	name: string;
	code?: string;
	depth: number;
	groups: Group[];
}
export type Target = 'proficient' | 'mastered';
export interface Requirement {
	term_id: number;
	required: boolean;
	target: Target;
}
export interface CategoryStyle {
	icon: string;
	color: string;
}
export interface Profile {
	subject: string;
	grade: string;
	language: string;
	categories: string[];
	category_styles: Record< string, CategoryStyle >;
}
/** The colour `SyllabusSettings::clean` stores for a style sent without one. */
export const DEFAULT_CATEGORY_COLOR = '#6e42d3';
export const HEX_COLOR = /^#[0-9a-f]{6}$/i;
export interface SourceDetails {
	id: number;
	name: string;
	code: string;
	version: string;
	description: string;
	item_type: string;
}
export interface SyllabusOutline {
	syllabus: SourceDetails;
	contents: Content[];
	course: {
		id: number;
		title: string;
		published: boolean;
		mode: string;
	} | null;
	settings: Profile;
	roadmap_sources: { id: number; name: string; version: string }[];
	requirements: Requirement[];
	course_error?: string;
}
export interface Fields {
	name: string;
	code: string;
	description: string;
}
export interface ImportReport {
	reviewRevision: string;
	valid: boolean;
	applied: boolean;
	errors: { line: number; message: string }[];
	warnings: { line: number; message: string }[];
	used: number;
	counts: { label: string; create: number; update: number }[];
}
export type CommandName =
	| 'add_group'
	| 'edit_group'
	| 'move_group'
	| 'delete_group'
	| 'create_skill'
	| 'edit_skill'
	| 'attach_skill'
	| 'move_skill'
	| 'detach_skill'
	| 'edit_source'
	| 'set_requirements'
	| 'sync_course';
export type SendCommand = (
	operation: CommandName,
	fields: Record< string, unknown >
) => Promise< boolean >;
export function nonnegative( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}
export function parseSkill( value: unknown ): Skill {
	const row = record( value );
	return {
		term_id: positiveId( row.term_id ),
		name: text( row.name ),
		code: text( row.code ),
		description: text( row.description ),
	};
}
/**
 * Category styles keyed by category. PHP sends an empty map as `[]`.
 *
 * @param value Decoded `category_styles`.
 */
function parseStyles( value: unknown ): Record< string, CategoryStyle > {
	if ( undefined === value || ( Array.isArray( value ) && ! value.length ) ) {
		return {};
	}
	return Object.fromEntries(
		Object.entries( record( value ) ).map( ( [ category, raw ] ) => {
			const style = record( raw );
			return [
				category,
				{ icon: text( style.icon ), color: text( style.color ) },
			];
		} )
	);
}
export function parseOutline( value: unknown ): SyllabusOutline {
	const row = record( value );
	if ( ! Array.isArray( row.contents ) ) {
		return invalid();
	}
	let course: SyllabusOutline[ 'course' ] = null;
	if ( row.course !== null ) {
		const item = record( row.course );
		if ( typeof item.published !== 'boolean' ) {
			return invalid();
		}
		course = {
			id: positiveId( item.id ),
			title: text( item.title ),
			published: item.published,
			mode: text( item.mode ),
		};
	}
	const settings = record( row.settings );
	if (
		! Array.isArray( settings.categories ) ||
		! Array.isArray( row.requirements )
	) {
		return invalid();
	}
	const requirements = row.requirements.map( ( raw ): Requirement => {
		const requirement = record( raw );
		if (
			typeof requirement.required !== 'boolean' ||
			( requirement.target !== 'proficient' &&
				requirement.target !== 'mastered' )
		) {
			return invalid();
		}
		return {
			term_id: positiveId( requirement.term_id ),
			required: requirement.required,
			target: requirement.target,
		};
	} );
	const rawSources = row.roadmap_sources ?? [];
	const sources = Array.isArray( rawSources )
		? rawSources
		: Object.values( record( rawSources ) );
	const contents = row.contents.map( ( rawContent ) => {
		const content = record( rawContent );
		if ( ! Array.isArray( content.groups ) ) {
			return invalid();
		}
		return {
			id: positiveId( content.id ),
			parent_id: nonnegative( content.parent_id ),
			name: text( content.name ),
			code: undefined === content.code ? '' : text( content.code ),
			depth: nonnegative( content.depth ),
			groups: content.groups.map( ( rawGroup ) => {
				const group = record( rawGroup );
				if ( ! Array.isArray( group.skills ) ) {
					return invalid();
				}
				return {
					id: positiveId( group.id ),
					item_id: positiveId( group.item_id ),
					position: nonnegative( group.position ),
					name: text( group.name ),
					code: text( group.code ),
					description: text( group.description ),
					skills: group.skills.map( parseSkill ),
				};
			} ),
		};
	} );
	const syllabus = record( row.syllabus );
	return {
		syllabus: {
			id: positiveId( syllabus.id ),
			name: text( syllabus.name ),
			code: text( syllabus.code ?? '' ),
			version: text( syllabus.version ?? '' ),
			description: text( syllabus.description ?? '' ),
			item_type: text( syllabus.item_type ),
		},
		contents,
		course,
		settings: {
			subject: text( settings.subject ),
			grade: text( settings.grade ),
			language: text( settings.language ),
			categories: settings.categories.map( ( category ) =>
				text( category )
			),
			category_styles: parseStyles( settings.category_styles ),
		},
		roadmap_sources: sources.map( ( raw ) => {
			const source = record( raw );
			return {
				id: positiveId( source.id ),
				name: text( source.name ),
				version: text( source.version ?? '' ),
			};
		} ),
		requirements,
		...( row.course_error !== undefined
			? { course_error: text( row.course_error ) }
			: {} ),
	};
}
export function parseReport( raw: unknown ): ImportReport {
	const row = record( raw );
	if (
		typeof row.valid !== 'boolean' ||
		typeof row.applied !== 'boolean' ||
		! Array.isArray( row.errors ) ||
		! Array.isArray( row.warnings )
	) {
		return invalid();
	}
	const issues = ( values: unknown[] ) =>
		values.map( ( value ) => {
			const issue = record( value );
			return {
				line: nonnegative( issue.line ),
				message: text( issue.message ),
			};
		} );
	return {
		reviewRevision: text( row.review_revision ),
		valid: row.valid,
		applied: row.applied,
		errors: issues( row.errors ),
		warnings: issues( row.warnings ),
		used: nonnegative( record( row.rows ).used ),
		counts: [ 'contents', 'groups', 'skills' ].map( ( label ) => {
			const count = record( row[ label ] );
			return {
				label,
				create: nonnegative( count.create ),
				update: nonnegative( count.update ),
			};
		} ),
	};
}
