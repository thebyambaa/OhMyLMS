import { get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	parseQuestionDetail,
	parseQuestionSummary,
	parseQuestionTypes,
	parseQuestionVersion,
	positiveId,
} from './model';
import type {
	QuestionCreateInput,
	QuestionListQuery,
	QuestionUpdateInput,
} from './types';

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
