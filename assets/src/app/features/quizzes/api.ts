import { del, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	parseAttempt,
	parseAttemptDetail,
	parseCourseChoice,
	parseDeleted,
	parseGradeResult,
	parseQuiz,
	parseQuizDetail,
	positiveId,
} from './model';
import type { AttemptFilters, GradeInput, QuizInput } from './types';

const BASE = '/ohmylms/v1/quizzes';
const itemPath = ( id: number ) => `${ BASE }/${ positiveId( id ) }`;
const attemptPath = ( quizId: number, attemptId: number ) =>
	`${ itemPath( quizId ) }/attempts/${ positiveId( attemptId ) }`;

/** Rows per page in the quiz list. */
export const PER_PAGE = 20;

/** Rows per page in the attempt list. */
export const ATTEMPTS_PER_PAGE = 20;

/** Courses per page in the course filter. */
export const COURSES_PER_PAGE = 10;

/**
 * List quizzes the user may edit or grade, newest first.
 *
 * @param page   Page number, from 1.
 * @param search Title search; empty for all.
 * @param status `all`, `draft`, `publish` or `trash`.
 * @param course Course id to filter by, or 0 for all.
 * @param signal Cancels the request.
 * @return Validated quizzes and pagination totals.
 */
export function listQuizzes(
	page: number,
	search: string,
	status: string,
	course: number,
	signal?: AbortSignal
) {
	return getCollection( BASE, parseQuiz, {
		signal,
		query: {
			page,
			per_page: PER_PAGE,
			search,
			status,
			course: course > 0 ? course : undefined,
			orderby: 'created_at',
			order: 'desc',
		},
	} );
}

/**
 * Search the courses the list can be filtered by.
 *
 * @param search Title search.
 * @param page   Page number, from 1.
 * @param signal Cancels the request.
 * @return Validated courses and totals.
 */
export function listCourses(
	search: string,
	page: number,
	signal?: AbortSignal
) {
	return getCollection( `${ BASE }/courses`, parseCourseChoice, {
		signal,
		query: { search, page, per_page: COURSES_PER_PAGE },
	} );
}

/**
 * Read one quiz with description and settings.
 *
 * @param id     Quiz id.
 * @param signal Cancels the request.
 * @return The validated quiz.
 */
export async function getQuiz( id: number, signal?: AbortSignal ) {
	return parseQuizDetail(
		await get< unknown >( itemPath( id ), {
			signal,
			query: { context: 'edit' },
		} )
	);
}

/**
 * Create a draft quiz with only a title.
 *
 * @param title  Quiz title.
 * @param signal Cancels the request.
 * @return The created quiz.
 */
export async function createQuiz( title: string, signal?: AbortSignal ) {
	return parseQuizDetail(
		await post< unknown >( BASE, { title: title.trim() }, { signal } )
	);
}

/**
 * Save the title, status, description and settings of a quiz.
 *
 * @param id     Quiz id.
 * @param input  Validated payload.
 * @param signal Cancels the request.
 * @return The saved quiz as the server stored it.
 */
export async function updateQuiz(
	id: number,
	input: QuizInput,
	signal?: AbortSignal
) {
	return parseQuizDetail(
		await patch< unknown >( itemPath( id ), input, { signal } )
	);
}

/**
 * Move a quiz to the trash.
 *
 * @param id     Quiz id.
 * @param signal Cancels the request.
 */
export async function trashQuiz( id: number, signal?: AbortSignal ) {
	parseDeleted( await del< unknown >( itemPath( id ), { signal } ) );
}

/**
 * Restore a trashed quiz as a draft.
 *
 * @param id     Quiz id.
 * @param signal Cancels the request.
 * @return The restored quiz.
 */
export async function restoreQuiz( id: number, signal?: AbortSignal ) {
	return parseQuizDetail(
		await patch< unknown >(
			itemPath( id ),
			{ status: 'draft' },
			{ signal }
		)
	);
}

/**
 * Delete a quiz for good. The server refuses while the quiz has attempts.
 *
 * @param id     Quiz id.
 * @param signal Cancels the request.
 */
export async function deleteQuiz( id: number, signal?: AbortSignal ) {
	parseDeleted(
		await del< unknown >( itemPath( id ), {
			signal,
			query: { force: true },
		} )
	);
}

/**
 * List the attempts of a quiz.
 *
 * @param quizId  Quiz id.
 * @param page    Page number, from 1.
 * @param filters Status and student search.
 * @param signal  Cancels the request.
 * @return Validated attempts and totals.
 */
export function listAttempts(
	quizId: number,
	page: number,
	filters: AttemptFilters,
	signal?: AbortSignal
) {
	return getCollection( `${ itemPath( quizId ) }/attempts`, parseAttempt, {
		signal,
		query: {
			page,
			per_page: ATTEMPTS_PER_PAGE,
			status: filters.status,
			search: filters.search,
			orderby: 'started_at',
			order: 'desc',
		},
	} );
}

/**
 * Read one attempt with its questions.
 *
 * @param quizId    Quiz id.
 * @param attemptId Attempt id.
 * @param signal    Cancels the request.
 * @return The validated attempt.
 */
export async function getAttempt(
	quizId: number,
	attemptId: number,
	signal?: AbortSignal
) {
	return parseAttemptDetail(
		await get< unknown >( attemptPath( quizId, attemptId ), { signal } )
	);
}

/**
 * Award marks. The server checks every mark against the question maximum and recalculates the total.
 *
 * @param quizId    Quiz id.
 * @param attemptId Attempt id.
 * @param grades    Marks by question.
 * @param reason    Optional note for the grade history.
 * @param signal    Cancels the request.
 * @return The refreshed attempt and the number of grade events written.
 */
export async function gradeAttempt(
	quizId: number,
	attemptId: number,
	grades: GradeInput[],
	reason: string,
	signal?: AbortSignal
) {
	const body: { grades: GradeInput[]; reason?: string } = { grades };
	if ( reason.trim() ) {
		body.reason = reason.trim();
	}
	return parseGradeResult(
		await post< unknown >(
			`${ attemptPath( quizId, attemptId ) }/grades`,
			body,
			{ signal }
		)
	);
}
