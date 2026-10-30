import { get } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import { count, invalidResponse, positiveId, record } from './model';

/** Page size of every report table. The server allows at most 50. */
export const REPORT_PER_PAGE = 10;

export type SkillFilter = 'all' | 'review_due' | 'struggling';

/** Body of `GET /students/{id}/report`. */
export interface StudentReportSummary {
	student_id: number;
	courses: { enrolled: number; completed: number; in_progress: number };
	quizzes: {
		attempts: number;
		in_review: number;
		average_percent: number | null;
	};
	assignments: {
		submitted: number;
		graded: number;
		passed: number;
		failed: number;
	};
	skills: { total: number; review_due: number; struggling: number };
}

/** A row of `GET /students/{id}/quiz-attempts`. */
export interface QuizAttemptRow {
	id: number;
	quiz_id: number;
	quiz_title: string;
	course_id: number;
	course_title: string;
	status: string;
	score: number;
	max_marks: number;
	percentage: number | null;
	started_at: string | null;
	submitted_at: string | null;
}

/** A row of `GET /students/{id}/assignment-submissions`. */
export interface SubmissionRow {
	id: number;
	assignment_id: number;
	assignment_title: string;
	course_id: number;
	course_title: string;
	status: 'submitted' | 'graded';
	result: 'passed' | 'failed' | null;
	score: number | null;
	max_score: number;
	attempts: number;
	submitted_at: string | null;
	graded_at: string | null;
}

/** A row of `GET /students/{id}/skills`. */
export interface SkillRow {
	skill_id: number;
	name: string;
	level: string;
	level_label: string;
	review_due: boolean;
	struggling: boolean;
	evidence_count: number;
	recent_accuracy: number;
	last_evidence_at: string | null;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}
function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}
function number( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isFinite( value ) ) {
		throw invalidResponse();
	}
	return value;
}
function nullableNumber( value: unknown ): number | null {
	return null === value ? null : number( value );
}
function flag( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}
/**
 * IDs of deleted courses come back as 0.
 *
 * @param value Decoded JSON.
 * @return The ID, possibly 0.
 */
function anyId( value: unknown ): number {
	return count( value );
}

export function parseSummary( value: unknown ): StudentReportSummary {
	const item = record( value );
	const courses = record( item.courses );
	const quizzes = record( item.quizzes );
	const assignments = record( item.assignments );
	const skills = record( item.skills );
	return {
		student_id: positiveId( item.student_id ),
		courses: {
			enrolled: count( courses.enrolled ),
			completed: count( courses.completed ),
			in_progress: count( courses.in_progress ),
		},
		quizzes: {
			attempts: count( quizzes.attempts ),
			in_review: count( quizzes.in_review ),
			average_percent: nullableNumber( quizzes.average_percent ),
		},
		assignments: {
			submitted: count( assignments.submitted ),
			graded: count( assignments.graded ),
			passed: count( assignments.passed ),
			failed: count( assignments.failed ),
		},
		skills: {
			total: count( skills.total ),
			review_due: count( skills.review_due ),
			struggling: count( skills.struggling ),
		},
	};
}

export function parseQuizAttempt( value: unknown ): QuizAttemptRow {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		quiz_id: anyId( item.quiz_id ),
		quiz_title: text( item.quiz_title ),
		course_id: anyId( item.course_id ),
		course_title: text( item.course_title ),
		status: text( item.status ),
		score: number( item.score ),
		max_marks: number( item.max_marks ),
		percentage: nullableNumber( item.percentage ),
		started_at: nullableText( item.started_at ),
		submitted_at: nullableText( item.submitted_at ),
	};
}

export function parseSubmission( value: unknown ): SubmissionRow {
	const item = record( value );
	const status = item.status;
	const result = item.result;
	if ( 'submitted' !== status && 'graded' !== status ) {
		throw invalidResponse();
	}
	if ( null !== result && 'passed' !== result && 'failed' !== result ) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		assignment_id: anyId( item.assignment_id ),
		assignment_title: text( item.assignment_title ),
		course_id: anyId( item.course_id ),
		course_title: text( item.course_title ),
		status,
		result,
		score: null === item.score ? null : count( item.score ),
		max_score: count( item.max_score ),
		attempts: count( item.attempts ),
		submitted_at: nullableText( item.submitted_at ),
		graded_at: nullableText( item.graded_at ),
	};
}

export function parseSkill( value: unknown ): SkillRow {
	const item = record( value );
	return {
		skill_id: positiveId( item.skill_id ),
		name: text( item.name ),
		level: text( item.level ),
		level_label: text( item.level_label ),
		review_due: flag( item.review_due ),
		struggling: flag( item.struggling ),
		evidence_count: count( item.evidence_count ),
		recent_accuracy: number( item.recent_accuracy ),
		last_evidence_at: nullableText( item.last_evidence_at ),
	};
}

const base = ( id: number ) => `/ohmylms/v1/students/${ positiveId( id ) }`;

export async function getReportSummary( id: number, signal?: AbortSignal ) {
	return parseSummary(
		await get< unknown >( `${ base( id ) }/report`, { signal } )
	);
}
export function listQuizAttempts(
	id: number,
	page: number,
	signal?: AbortSignal
) {
	return getCollection( `${ base( id ) }/quiz-attempts`, parseQuizAttempt, {
		signal,
		query: { page, per_page: REPORT_PER_PAGE },
	} );
}
export function listSubmissions(
	id: number,
	page: number,
	signal?: AbortSignal
) {
	return getCollection(
		`${ base( id ) }/assignment-submissions`,
		parseSubmission,
		{ signal, query: { page, per_page: REPORT_PER_PAGE } }
	);
}
export function listSkills(
	id: number,
	page: number,
	filter: SkillFilter,
	signal?: AbortSignal
) {
	return getCollection( `${ base( id ) }/skills`, parseSkill, {
		signal,
		query: {
			page,
			per_page: REPORT_PER_PAGE,
			...( 'all' === filter ? {} : { filter } ),
		},
	} );
}

/**
 * Route of a student's report.
 *
 * @param id Student ID.
 * @return App route.
 */
export function reportPath( id: number ): string {
	return `/students/${ id }/report`;
}

/**
 * Format a percentage for display.
 *
 * @param value Percentage or null when nothing was scored.
 * @return Formatted text, or an em dash.
 */
export function formatPercent( value: number | null ): string {
	return null === value
		? '—'
		: `${ new Intl.NumberFormat( undefined, {
				maximumFractionDigits: 1,
			} ).format( value ) }%`;
}
