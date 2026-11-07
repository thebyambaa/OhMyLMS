import { get, put } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

/** A lesson that teaches a skill, with the unit it is placed in. */
export interface TaughtIn {
	id: number;
	title: string;
	unitId: number;
	unit: string;
}

/** How the course handles one skill it touches. */
export interface SkillState {
	name: string;
	code: string;
	status: string;
	teaching: TaughtIn[];
	/** Quizzes the course places that are connected to the skill. */
	assessing: TaughtIn[];
	questions: number;
	questionsNeed: number;
	teachingOk: boolean;
	questionsOk: boolean;
}

export interface PlanGroup {
	id: number;
	name: string;
	skills: number[];
}

/** A curriculum item (a topic) with the skill groups placed under it. */
export interface PlanTopic {
	id: number;
	name: string;
	depth: number;
	groups: PlanGroup[];
}

export interface SkillName {
	name: string;
	code: string;
}

export interface SkillPlan {
	courseId: number;
	curricula: { id: number; name: string }[];
	/** The curriculum shown; 0 when none exists. */
	curriculum: number;
	topics: PlanTopic[];
	names: Record< number, SkillName >;
	skills: Record< number, SkillState >;
	/** Unit ID to the topic IDs it covers. */
	unitTopics: Record< number, number[] >;
}

const whole = ( value: unknown ): number =>
	Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();

const ids = ( value: unknown ): number[] =>
	Array.isArray( value ) ? value.map( positiveId ) : invalid();

/**
 * Read an object keyed by numeric ID; a PHP empty array arrives as `[]`.
 *
 * @param value Decoded JSON.
 * @return Entries with their numeric keys.
 */
function entries( value: unknown ): [ number, unknown ][] {
	if ( Array.isArray( value ) && 0 === value.length ) {
		return [];
	}
	return Object.entries( record( value ) ).map( ( [ key, row ] ) => [
		positiveId( Number( key ) ),
		row,
	] );
}

function taught( value: unknown ): TaughtIn {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		title: text( row.title ),
		unitId: whole( row.unit_id ),
		unit: text( row.unit ),
	};
}

function state( value: unknown ): SkillState {
	const row = record( value );
	return {
		name: text( row.name ),
		code: text( row.code ),
		status: text( row.status ),
		teaching: Array.isArray( row.teaching )
			? row.teaching
					.map( record )
					.filter( ( entry ) => 'lesson' === entry.type )
					.map( taught )
			: invalid(),
		assessing: Array.isArray( row.assessing )
			? row.assessing
					.map( record )
					.filter( ( entry ) => 'quiz' === entry.type )
					.map( taught )
			: [],
		questions: whole( row.questions ),
		questionsNeed: whole( row.questions_need ),
		teachingOk: Boolean( row.teaching_ok ),
		questionsOk: Boolean( row.questions_ok ),
	};
}

/**
 * Read the skills of a course with how it teaches them (an `{ ID: state }` object).
 *
 * @param value Decoded JSON.
 * @return The states by skill ID.
 */
export function parseSkillStates(
	value: unknown
): Record< number, SkillState > {
	return Object.fromEntries(
		entries( value ).map( ( [ id, row ] ) => [ id, state( row ) ] )
	);
}

function parseUnitTopics( value: unknown ): Record< number, number[] > {
	return Object.fromEntries(
		entries( value ).map( ( [ id, row ] ) => [ id, ids( row ) ] )
	);
}

/**
 * Read a skill plan response.
 *
 * @param incoming Decoded JSON.
 * @return The validated plan.
 */
export function parsePlan( incoming: unknown ): SkillPlan {
	const row = record( incoming );
	if ( ! Array.isArray( row.curricula ) || ! Array.isArray( row.topics ) ) {
		return invalid();
	}
	return {
		courseId: positiveId( row.course_id ),
		curricula: row.curricula.map( ( value ) => {
			const item = record( value );
			return { id: positiveId( item.id ), name: text( item.name ) };
		} ),
		curriculum: whole( row.curriculum ),
		topics: row.topics.map( ( value ) => {
			const topic = record( value );
			if ( ! Array.isArray( topic.groups ) ) {
				return invalid();
			}
			return {
				id: positiveId( topic.id ),
				name: text( topic.name ),
				depth: whole( topic.depth ),
				groups: topic.groups.map( ( entry ) => {
					const group = record( entry );
					return {
						id: positiveId( group.id ),
						name: text( group.name ),
						skills: ids( group.skills ),
					};
				} ),
			};
		} ),
		names: Object.fromEntries(
			entries( row.names ).map( ( [ id, entry ] ) => {
				const skill = record( entry );
				return [
					id,
					{ name: text( skill.name ), code: text( skill.code ) },
				];
			} )
		),
		skills: parseSkillStates( row.skills ),
		unitTopics: parseUnitTopics( row.unit_topics ),
	};
}

const base = ( course: number ) =>
	`/ohmylms/v1/courses/${ positiveId( course ) }/skill-plan`;

/**
 * Load a curriculum's topics and skills beside how a course teaches them.
 *
 * @param course     Course ID.
 * @param curriculum Curriculum ID, or 0 for the course's own.
 * @param signal     Cancels the request.
 * @return The plan.
 */
export async function getSkillPlan(
	course: number,
	curriculum: number,
	signal?: AbortSignal
) {
	const result = parsePlan(
		await get< unknown >(
			curriculum > 0
				? `${ base( course ) }?curriculum=${ curriculum }`
				: base( course ),
			{ signal }
		)
	);
	return result.courseId === course ? result : invalid();
}

/**
 * Choose the topics a unit covers.
 *
 * @param course Course ID.
 * @param unit   Saved unit ID.
 * @param topics Topic IDs; empty clears the choice.
 * @param signal Cancels the request.
 * @return Every unit's topics.
 */
export async function setUnitTopics(
	course: number,
	unit: number,
	topics: number[],
	signal?: AbortSignal
) {
	const result = record(
		await put< unknown >(
			`${ base( course ) }/units/${ positiveId( unit ) }`,
			{ topic_ids: topics },
			{ signal }
		)
	);
	return parseUnitTopics( result.unit_topics );
}

/** What a connection can be made to. */
export type ConnectableKind = 'lesson' | 'quiz';

/**
 * Connect or disconnect skills of a lesson or quiz placed in the saved outline.
 *
 * @param course        Course ID.
 * @param kind          `lesson` or `quiz`.
 * @param content       Lesson or quiz ID.
 * @param change        Skill IDs to add and remove.
 * @param change.add
 * @param change.remove
 * @param signal        Cancels the request.
 * @return The item's skills and the updated teaching states.
 */
export async function linkContentSkills(
	course: number,
	kind: ConnectableKind,
	content: number,
	change: { add?: number[]; remove?: number[] },
	signal?: AbortSignal
) {
	const result = record(
		await put< unknown >(
			`${ base( course ) }/${
				'quiz' === kind ? 'quizzes' : 'lessons'
			}/${ positiveId( content ) }`,
			{ add: change.add ?? [], remove: change.remove ?? [] },
			{ signal }
		)
	);
	return {
		contentSkills: ids( result.content_skills ),
		skills: parseSkillStates( result.skills ),
	};
}
