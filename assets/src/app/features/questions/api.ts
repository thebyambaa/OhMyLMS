import { del, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	invalidResponse,
	parseBanks,
	parseBulkDelete,
	parseDeleteResult,
	parseQuestionDetail,
	parseQuestionSummary,
	parseQuestionTypes,
	parseQuestionVersion,
	parseQuizChoice,
	parseQuizPlacement,
	parseSkillRef,
	positiveId,
	record,
} from './model';
import type {
	QuestionCreateInput,
	QuestionListQuery,
	QuestionSettings,
	QuestionUpdateInput,
	SkillRef,
} from './types';

/** Skills offered per search. */
export const SKILLS_PER_SEARCH = 20;

/** An unsaved question as the learner player preview accepts it. */
export interface PreviewQuestion {
	id: number;
	name: string;
	description: string;
	settings: QuestionSettings;
	questions: {
		id: number;
		answer: string;
		is_correct: boolean;
		order_number: number;
		image_url: string;
		matching_data: { label?: string; image_url?: string } | null;
	}[];
	image_src?: string;
	video_src?: string;
}

function skillList( value: unknown ): SkillRef[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value.map( parseSkillRef );
}

/**
 * Search the skill catalogue by name.
 *
 * @param search Text to search for.
 * @param signal Cancels the request.
 * @return Up to {@link SKILLS_PER_SEARCH} skills, sorted by name.
 */
export async function searchSkills( search: string, signal?: AbortSignal ) {
	return skillList(
		await get< unknown >( '/ohmylms/v1/skills/catalog', {
			signal,
			query: { search: search.trim(), per_page: SKILLS_PER_SEARCH },
		} )
	);
}

/**
 * Read skills by id, for the names of skills a question is mapped to.
 *
 * @param ids    Skill ids, at most 50.
 * @param signal Cancels the request.
 * @return The skills that exist.
 */
export async function getSkills(
	ids: readonly number[],
	signal?: AbortSignal
) {
	if ( ! ids.length ) {
		return [];
	}
	return skillList(
		await get< unknown >( '/ohmylms/v1/skills/catalog', {
			signal,
			query: { include: ids.slice( 0, 50 ), per_page: 50 },
		} )
	);
}

/**
 * List the question banks the user can at least use.
 *
 * @param signal Cancels the request.
 * @return The banks, sorted by name.
 */
export async function listBanks( signal?: AbortSignal ) {
	return parseBanks(
		await get< unknown >( '/ohmylms/v1/question-banks', { signal } )
	);
}

/**
 * Open an author-only learner preview of an unsaved question in the real quiz player. Nothing is
 * saved and no attempt is started; the preview address expires after 15 minutes.
 *
 * @param postId   A question, quiz or lesson the user may edit (the preview's document).
 * @param question The question as edited.
 * @param signal   Cancels the request.
 * @return The address of the preview document.
 */
export async function previewQuestion(
	postId: number,
	question: PreviewQuestion,
	signal?: AbortSignal
): Promise< string > {
	const result = record(
		await post< unknown >(
			'/ohmylms/v1/question-preview',
			{ post_id: positiveId( postId ), question, seed: 1 },
			{ signal }
		)
	);
	if ( 'string' !== typeof result.url || ! result.url ) {
		throw invalidResponse();
	}
	return result.url;
}

const BASE = '/ohmylms/v1/questions';
const itemPath = ( id: number ) => `${ BASE }/${ positiveId( id ) }`;

/** Rows per page in the question list. */
export const PER_PAGE = 20;

/** Rows per page in the version list. */
export const VERSIONS_PER_PAGE = 20;

/**
 * List questions, most recently modified first. Empty filters are not sent.
 *
 * @param query  Page and filters.
 * @param signal Cancels the request.
 * @return Validated summaries and pagination totals.
 */
export function listQuestions(
	query: QuestionListQuery,
	signal?: AbortSignal
) {
	return getCollection( BASE, parseQuestionSummary, {
		signal,
		query: {
			page: query.page,
			per_page: PER_PAGE,
			search: query.search.trim() || undefined,
			type: query.type || undefined,
			difficulty: query.difficulty || undefined,
			status: query.status,
			orderby: 'modified',
			order: 'desc',
		},
	} );
}

/**
 * Read every registered question type.
 *
 * @param signal Cancels the request.
 * @return Validated types, sorted by label on the server.
 */
export async function listQuestionTypes( signal?: AbortSignal ) {
	return parseQuestionTypes(
		await get< unknown >( `${ BASE }/types`, { signal } )
	);
}

/**
 * Read one question with its settings, options and permissions.
 *
 * @param id     Question id.
 * @param signal Cancels the request.
 * @return The validated editor view.
 */
export async function getQuestion( id: number, signal?: AbortSignal ) {
	return parseQuestionDetail(
		await get< unknown >( itemPath( id ), { signal } )
	);
}

/**
 * Create a question.
 *
 * @param input  Name, settings and options.
 * @param signal Cancels the request.
 * @return The created question.
 */
export async function createQuestion(
	input: QuestionCreateInput,
	signal?: AbortSignal
) {
	return parseQuestionDetail(
		await post< unknown >( BASE, input, { signal } )
	);
}

/**
 * Save changed fields of a question. A stale `base_modified` answers 409 `ohmylms_question_conflict`.
 *
 * @param id     Question id.
 * @param input  Changed fields and `base_modified`.
 * @param signal Cancels the request.
 * @return The saved question.
 */
export async function updateQuestion(
	id: number,
	input: QuestionUpdateInput,
	signal?: AbortSignal
) {
	return parseQuestionDetail(
		await patch< unknown >( itemPath( id ), input, { signal } )
	);
}

/**
 * Archive a question: hidden from the default list and from new use, history kept.
 *
 * @param id     Question id.
 * @param signal Cancels the request.
 * @return The archived question.
 */
export async function archiveQuestion( id: number, signal?: AbortSignal ) {
	return parseQuestionDetail(
		await post< unknown >( `${ itemPath( id ) }/archive`, undefined, {
			signal,
		} )
	);
}

/**
 * Restore an archived question.
 *
 * @param id     Question id.
 * @param signal Cancels the request.
 * @return The restored question.
 */
export async function restoreQuestion( id: number, signal?: AbortSignal ) {
	return parseQuestionDetail(
		await post< unknown >( `${ itemPath( id ) }/restore`, undefined, {
			signal,
		} )
	);
}

/**
 * Approve the current version of a question for shared use and practice. Content saved since the
 * last version is captured as a new version first.
 *
 * @param id     Question id.
 * @param signal Cancels the request.
 * @return The question with its approved version.
 */
export async function approveQuestion( id: number, signal?: AbortSignal ) {
	return parseQuestionDetail(
		await post< unknown >( `${ itemPath( id ) }/approve`, undefined, {
			signal,
		} )
	);
}

/**
 * Copy a question as a new, unapproved question of the current user (same family, new uuid).
 *
 * @param id     Question id.
 * @param signal Cancels the request.
 * @return The copy.
 */
export async function duplicateQuestion( id: number, signal?: AbortSignal ) {
	return parseQuestionDetail(
		await post< unknown >( `${ itemPath( id ) }/duplicate`, undefined, {
			signal,
		} )
	);
}

/**
 * List the saved versions of a question, newest first.
 *
 * @param id     Question id.
 * @param page   Page number, from 1.
 * @param signal Cancels the request.
 * @return Validated versions and totals.
 */
export async function listVersions(
	id: number,
	page: number,
	signal?: AbortSignal
) {
	return getCollection(
		`${ itemPath( id ) }/versions`,
		parseQuestionVersion,
		{
			signal,
			query: { page, per_page: VERSIONS_PER_PAGE },
		}
	);
}

/**
 * Delete a question. One with learner history (or in a published quiz revision) is archived instead;
 * one used in several quizzes is refused with `ohmylms_question_shared`.
 *
 * @param id     Question id.
 * @param signal Cancels the request.
 * @return Whether it was deleted or archived.
 */
export async function deleteQuestion( id: number, signal?: AbortSignal ) {
	return parseDeleteResult(
		await del< unknown >( itemPath( id ), { signal } )
	);
}

/** Error code of a bulk delete refused because some questions cannot be deleted. */
export const DELETE_BLOCKED = 'ohmylms_question_delete_blocked';

/**
 * Delete several questions with the rules of {@link deleteQuestion}, in one transaction. Without
 * `skipBlocked`, one question that cannot be deleted refuses the request ({@link DELETE_BLOCKED}, the
 * blocked ones in the error details); with it, the others are deleted and the blocked ones reported.
 *
 * @param ids         Question ids, at most 100.
 * @param skipBlocked Delete the others when some are blocked.
 * @param signal      Cancels the request.
 * @return Per-question results and the skipped questions.
 */
export async function deleteQuestions(
	ids: readonly number[],
	skipBlocked: boolean,
	signal?: AbortSignal
) {
	return parseBulkDelete(
		await post< unknown >(
			`${ BASE }/delete`,
			{ ids: ids.map( positiveId ), skip_blocked: skipBlocked },
			{ signal }
		)
	);
}

/** Quizzes offered per search when adding a question to a quiz. */
export const QUIZZES_PER_SEARCH = 20;

/**
 * Search quizzes by title, for adding a question to one.
 *
 * @param search Title search.
 * @param signal Cancels the request.
 * @return Quizzes (with whether the user may edit each).
 */
export async function searchQuizzes( search: string, signal?: AbortSignal ) {
	return (
		await getCollection( '/ohmylms/v1/quizzes', parseQuizChoice, {
			signal,
			query: {
				search: search.trim(),
				status: 'all',
				per_page: QUIZZES_PER_SEARCH,
				orderby: 'title',
				order: 'asc',
			},
		} )
	).items;
}

/**
 * Add a question to a quiz under the quiz revision lock: the current revision is read, then the
 * question itself (`copy: false`, shared by reference) or a new copy of it (`copy: true`) is placed at
 * the end. A change made by someone else in between answers 409 `ohmylms_quiz_questions_conflict`.
 *
 * @param quizId     Quiz id.
 * @param questionId Question id.
 * @param copy       Place a copy instead of the question.
 * @param signal     Cancels the request.
 * @return The id placed in the quiz (the copy's when copying).
 */
export async function addToQuiz(
	quizId: number,
	questionId: number,
	copy: boolean,
	signal?: AbortSignal
) {
	const path = `/ohmylms/v1/quizzes/${ positiveId( quizId ) }/questions`;
	const current = parseQuizPlacement(
		await get< unknown >( path, { signal } )
	);
	const result = parseQuizPlacement(
		await post< unknown >(
			copy ? `${ path }/copy` : path,
			{
				question_ids: [ positiveId( questionId ) ],
				revision: current.revision,
			},
			{ signal }
		)
	);
	const placed = copy ? result.copied[ 0 ]?.id : questionId;
	if ( ! placed ) {
		throw invalidResponse();
	}
	return placed;
}
