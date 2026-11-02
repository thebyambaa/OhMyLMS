import { del, get, post, put } from '../../lib/api';
import type { QuestionCreateInput } from '../questions/public';
import {
	parseQuizQuestionCreated,
	parseQuizQuestions,
	positiveId,
} from './model';
import type { PinChoice, QuizPoolInput, QuizSection } from './types';

const listPath = ( quizId: number ) =>
	`/ohmylms/v1/quizzes/${ positiveId( quizId ) }/questions`;
const slotPath = ( quizId: number, questionId: number ) =>
	`${ listPath( quizId ) }/${ positiveId( questionId ) }`;

/** Error code of a write sent with an outdated `revision`. */
export const QUESTIONS_CONFLICT = 'ohmylms_quiz_questions_conflict';

/**
 * Read the questions of a quiz in order, with marks, versions, pins and random pools.
 *
 * @param quizId Quiz id.
 * @param signal Cancels the request.
 * @return The validated list.
 */
export async function listQuizQuestions(
	quizId: number,
	signal?: AbortSignal
) {
	return parseQuizQuestions(
		await get< unknown >( listPath( quizId ), { signal } )
	);
}

/**
 * Place bank questions at the end of the quiz.
 *
 * @param quizId      Quiz id.
 * @param revision    Revision of the loaded list.
 * @param questionIds Questions to add, at most 50.
 * @param pin         Pin each to the version the quiz would use now.
 * @param signal      Cancels the request.
 * @return The updated list.
 */
export async function addQuizQuestions(
	quizId: number,
	revision: string,
	questionIds: number[],
	pin: boolean,
	signal?: AbortSignal
) {
	return parseQuizQuestions(
		await post< unknown >(
			listPath( quizId ),
			{ question_ids: questionIds, pin, revision },
			{ signal }
		)
	);
}

/**
 * Copy bank questions into the quiz: each copy is a new question of the user, placed at the end.
 *
 * @param quizId      Quiz id.
 * @param revision    Revision of the loaded list.
 * @param questionIds Questions to copy, at most 50.
 * @param signal      Cancels the request.
 * @return The updated list.
 */
export async function copyQuizQuestions(
	quizId: number,
	revision: string,
	questionIds: number[],
	signal?: AbortSignal
) {
	return parseQuizQuestions(
		await post< unknown >(
			`${ listPath( quizId ) }/copy`,
			{ question_ids: questionIds, revision },
			{ signal }
		)
	);
}

/**
 * Create a question (same body as `POST /questions`) and place it at the end of the quiz.
 *
 * @param quizId   Quiz id.
 * @param revision Revision of the loaded list.
 * @param question The new question.
 * @param signal   Cancels the request.
 * @return The updated list and the new question id.
 */
export async function createQuizQuestion(
	quizId: number,
	revision: string,
	question: QuestionCreateInput,
	signal?: AbortSignal
) {
	return parseQuizQuestionCreated(
		await post< unknown >(
			`${ listPath( quizId ) }/new`,
			{ question, revision },
			{ signal }
		)
	);
}

/**
 * Store a new order. The list must name every question of the quiz once.
 *
 * @param quizId      Quiz id.
 * @param revision    Revision of the loaded list.
 * @param questionIds Every question id, in the new order.
 * @param signal      Cancels the request.
 * @return The updated list.
 */
export async function reorderQuizQuestions(
	quizId: number,
	revision: string,
	questionIds: number[],
	signal?: AbortSignal
) {
	return parseQuizQuestions(
		await put< unknown >(
			`${ listPath( quizId ) }/order`,
			{ question_ids: questionIds, revision },
			{ signal }
		)
	);
}

/**
 * Remove a question from the quiz; it stays in the question bank.
 *
 * @param quizId     Quiz id.
 * @param revision   Revision of the loaded list.
 * @param questionId Question to remove.
 * @param signal     Cancels the request.
 * @return The updated list.
 */
export async function removeQuizQuestion(
	quizId: number,
	revision: string,
	questionId: number,
	signal?: AbortSignal
) {
	return parseQuizQuestions(
		await del< unknown >( slotPath( quizId, questionId ), {
			signal,
			query: { revision },
		} )
	);
}

/**
 * Pin a question of the quiz to a version, or unpin it.
 *
 * @param quizId     Quiz id.
 * @param revision   Revision of the loaded list.
 * @param questionId Question.
 * @param choice     `current`, a version id, or `null` to unpin.
 * @param signal     Cancels the request.
 * @return The updated list.
 */
export async function pinQuizQuestion(
	quizId: number,
	revision: string,
	questionId: number,
	choice: PinChoice,
	signal?: AbortSignal
) {
	const body =
		'current' === choice
			? { current: true, revision }
			: { version_id: choice, revision };
	return parseQuizQuestions(
		await put< unknown >( `${ slotPath( quizId, questionId ) }/pin`, body, {
			signal,
		} )
	);
}

/**
 * Set the marks of a question in this quiz, or go back to the question's own points.
 *
 * @param quizId     Quiz id.
 * @param revision   Revision of the loaded list.
 * @param questionId Question.
 * @param marks      Marks (0 to 10000), or `null` to use the question's points.
 * @param signal     Cancels the request.
 * @return The updated list.
 */
export async function setQuizQuestionMarks(
	quizId: number,
	revision: string,
	questionId: number,
	marks: number | null,
	signal?: AbortSignal
) {
	return parseQuizQuestions(
		await put< unknown >(
			`${ slotPath( quizId, questionId ) }/marks`,
			{ marks, revision },
			{ signal }
		)
	);
}

/**
 * Replace the sections of the quiz.
 *
 * @param quizId   Quiz id.
 * @param revision Revision of the loaded list.
 * @param sections Every section in paper order.
 * @param signal   Cancels the request.
 * @return The updated list.
 */
export async function saveQuizSections(
	quizId: number,
	revision: string,
	sections: QuizSection[],
	signal?: AbortSignal
) {
	return parseQuizQuestions(
		await put< unknown >(
			`${ listPath( quizId ) }/sections`,
			{ sections, revision },
			{ signal }
		)
	);
}

/**
 * Replace the random pools of the quiz.
 *
 * @param quizId   Quiz id.
 * @param revision Revision of the loaded list.
 * @param pools    Every pool.
 * @param signal   Cancels the request.
 * @return The updated list.
 */
export async function saveQuizPools(
	quizId: number,
	revision: string,
	pools: QuizPoolInput[],
	signal?: AbortSignal
) {
	return parseQuizQuestions(
		await put< unknown >(
			`${ listPath( quizId ) }/pools`,
			{ pools, revision },
			{ signal }
		)
	);
}
