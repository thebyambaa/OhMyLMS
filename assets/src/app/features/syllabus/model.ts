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
export interface SyllabusOutline {
	syllabus: { id: number; name: string };
	contents: Content[];
	course: { id: number; title: string; published: boolean } | null;
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
	| 'create_skill'
	| 'edit_skill'
	| 'attach_skill'
	| 'move_skill'
	| 'detach_skill'
	| 'sync_course'
	| 'sheet_edit';
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
		};
	}
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
		},
		contents,
		course,
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
