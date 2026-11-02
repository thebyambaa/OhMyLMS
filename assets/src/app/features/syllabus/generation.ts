import { get, post } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';
import { nonnegative } from './model';

const BASE = '/ohmylms/v1/courses/from-syllabus';
/** `CourseObjectives::MAX_TARGETS` and the course title limit of `CourseFromSyllabus`. */
export const MAX_COURSE_SKILLS = 200;
export const MAX_COURSE_TITLE = 200;

export interface SourceChoice {
	id: number;
	name: string;
	code: string;
	version: string;
	item_type: string;
}
export interface SourceSkill {
	term_id: number;
	name: string;
	code: string;
	objective: string;
}
export interface SourceLesson {
	id: number;
	title: string;
	skills: SourceSkill[];
}
export interface SourceTopic {
	id: number;
	name: string;
	depth: number;
	lessons: SourceLesson[];
}
export interface SourcePreview {
	name: string;
	topics: SourceTopic[];
	revision: string;
}
export interface PlannedUnit {
	id: number;
	name: string;
	lessons: SourceLesson[];
}
export interface CreatedCourse {
	id: number;
	name: string;
	units: { id: number; name: string }[];
	objectives: number;
	lessons: number;
}

function list( value: unknown ): unknown[] {
	return Array.isArray( value ) ? value : invalid();
}
export async function readSources(
	signal?: AbortSignal
): Promise< SourceChoice[] > {
	return list( await get< unknown >( BASE, { signal } ) ).map( ( raw ) => {
		const row = record( raw );
		return {
			id: positiveId( row.id ),
			name: text( row.name ),
			code: text( row.code ?? '' ),
			version: text( row.version ?? '' ),
			item_type: text( row.item_type ),
		};
	} );
}
export function parsePreview( value: unknown ): SourcePreview {
	const row = record( value );
	return {
		name: text( row.name ),
		revision: text( row.revision ),
		topics: list( row.topics ).map( ( rawTopic ) => {
			const topic = record( rawTopic );
			return {
				id: positiveId( topic.id ),
				name: text( topic.name ),
				depth: nonnegative( topic.depth ),
				lessons: list( topic.lessons ).map( ( rawLesson ) => {
					const lesson = record( rawLesson );
					return {
						id: positiveId( lesson.id ),
						title: text( lesson.title ),
						skills: list( lesson.skills ).map( ( rawSkill ) => {
							const skill = record( rawSkill );
							return {
								term_id: positiveId( skill.term_id ),
								name: text( skill.name ),
								code: text( skill.code ),
								objective: text( skill.objective ?? '' ),
							};
						} ),
					};
				} ),
			};
		} ),
	};
}
export async function readSource( id: number, signal?: AbortSignal ) {
	return parsePreview(
		await get< unknown >( `${ BASE }/${ id }`, { signal } )
	);
}
export async function createFromSyllabus(
	selection: {
		syllabusId: number;
		title: string;
		skillIds: number[];
		revision: string;
	},
	signal?: AbortSignal
): Promise< CreatedCourse > {
	const row = record(
		await post< unknown >(
			BASE,
			{
				syllabus_id: selection.syllabusId,
				title: selection.title,
				skill_ids: selection.skillIds,
				revision: selection.revision,
			},
			{ signal }
		)
	);
	return {
		id: positiveId( row.id ),
		name: text( row.name ),
		units: list( row.units ).map( ( raw ) => {
			const unit = record( raw );
			return { id: positiveId( unit.id ), name: text( unit.name ) };
		} ),
		objectives: nonnegative( row.objectives ),
		lessons: nonnegative( row.lessons ),
	};
}

/**
 * Every skill of the source, in syllabus order.
 * @param preview
 */
export function sourceSkillIds( preview: SourcePreview ): number[] {
	return preview.topics.flatMap( ( topic ) =>
		topic.lessons.flatMap( ( lesson ) =>
			lesson.skills.map( ( skill ) => skill.term_id )
		)
	);
}

/**
 * The outline `CourseFromSyllabus` generates for a selection: a unit for each topic holding a selected
 * skill (named like the server names it), a draft lesson for each such skill group, linked to its selected skills.
 *
 * @param preview  Source read.
 * @param selected Selected skill IDs.
 * @return Units in syllabus order.
 */
export function planCourse(
	preview: SourcePreview,
	selected: ReadonlySet< number >
): PlannedUnit[] {
	return preview.topics
		.map( ( topic ) => ( {
			id: topic.id,
			name: topic.name.trim(),
			lessons: topic.lessons
				.map( ( lesson ) => ( {
					...lesson,
					skills: lesson.skills.filter( ( skill ) =>
						selected.has( skill.term_id )
					),
				} ) )
				.filter( ( lesson ) => lesson.skills.length > 0 ),
		} ) )
		.filter( ( unit ) => unit.lessons.length > 0 );
}
