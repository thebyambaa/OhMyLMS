import { get } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

export type CoverageStatus =
	'covered' | 'needs-teaching' | 'needs-questions' | 'needs-both';

const STATUSES: readonly CoverageStatus[] = [
	'covered',
	'needs-teaching',
	'needs-questions',
	'needs-both',
];

/** A lesson, quiz or assignment a skill is connected to, with the unit it is placed in. */
export interface CoverageContent {
	id: number;
	type: string;
	title: string;
	unitId: number;
	unit: string;
}

export interface CoverageSkill {
	id: number;
	name: string;
	code: string;
	/** The course has this skill as an outcome. */
	outcome: boolean;
	required: boolean;
	/** Target level of the outcome, or '' when the skill is not an outcome. */
	target: string;
	/** The course targets this skill as a syllabus objective. */
	objective: boolean;
	/** A practice activity of the course is on this skill. */
	practice: boolean;
	teaching: CoverageContent[];
	assessing: CoverageContent[];
	questions: number;
	questionsNeed: number;
	families: number;
	familiesNeed: number;
	/** Whether the thresholds come from the skill's target or from the default (proficient). */
	needBasis: 'target' | 'default';
	teachingOk: boolean;
	questionsOk: boolean;
	status: CoverageStatus;
}

export interface CoverageSummary {
	skills: number;
	required: number;
	covered: number;
	needsTeaching: number;
	needsQuestions: number;
	needsBoth: number;
	objectives: number;
	objectivesTaught: number;
	requiredNotReady: number;
}

export interface SkillCoverage {
	courseId: number;
	summary: CoverageSummary;
	skills: CoverageSkill[];
}

const whole = ( value: unknown ): number =>
	Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();

function content( value: unknown ): CoverageContent {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		type: text( row.type ),
		title: text( row.title ),
		unitId: whole( row.unit_id ),
		unit: text( row.unit ),
	};
}

function skill( value: unknown ): CoverageSkill {
	const row = record( value );
	const status = STATUSES.find( ( item ) => item === row.status );
	if (
		! status ||
		! Array.isArray( row.teaching ) ||
		! Array.isArray( row.assessing )
	) {
		return invalid();
	}
	return {
		id: positiveId( row.term_id ),
		name: text( row.name ),
		code: text( row.code ),
		outcome: Boolean( row.outcome ),
		required: Boolean( row.required ),
		target: text( row.target ),
		objective: Boolean( row.objective ),
		practice: Boolean( row.practice ),
		teaching: row.teaching.map( content ),
		assessing: row.assessing.map( content ),
		questions: whole( row.questions ),
		questionsNeed: whole( row.questions_need ),
		families: whole( row.families ),
		familiesNeed: whole( row.families_need ),
		needBasis: 'target' === row.need_basis ? 'target' : 'default',
		teachingOk: Boolean( row.teaching_ok ),
		questionsOk: Boolean( row.questions_ok ),
		status,
	};
}

/**
 * Read a skill coverage response.
 *
 * @param incoming Decoded JSON.
 * @return The validated report.
 */
export function parseCoverage( incoming: unknown ): SkillCoverage {
	const row = record( incoming );
	if ( ! Array.isArray( row.skills ) ) {
		return invalid();
	}
	const summary = record( row.summary );
	return {
		courseId: positiveId( row.course_id ),
		summary: {
			skills: whole( summary.skills ),
			required: whole( summary.required ),
			covered: whole( summary.covered ),
			needsTeaching: whole( summary.needs_teaching ),
			needsQuestions: whole( summary.needs_questions ),
			needsBoth: whole( summary.needs_both ),
			objectives: whole( summary.objectives ),
			objectivesTaught: whole( summary.objectives_taught ),
			requiredNotReady: whole( summary.required_not_ready ),
		},
		skills: row.skills.map( skill ),
	};
}

/**
 * Load the skill coverage of a course's saved draft.
 *
 * @param id     Course ID.
 * @param signal Cancels the request.
 * @return The report for that course.
 */
export async function getSkillCoverage( id: number, signal?: AbortSignal ) {
	const result = parseCoverage(
		await get< unknown >( `/ohmylms/v1/courses/${ id }/skill-coverage`, {
			signal,
		} )
	);
	return result.courseId === id ? result : invalid();
}
