// @ts-check
import { request } from '../rest.mjs';

/** @typedef {( options: import('@wordpress/api-fetch').APIFetchOptions ) => Promise<Object>} Fetch */
/** @typedef {{ label: string, value: number|string }} ContentOption A chapter content item for the prerequisite picker. */

/**
 * Save the learner instructions of an exam.
 *
 * @param {number} examId       Exam ID.
 * @param {string} instructions Instructions.
 * @param {Fetch}  [fetch]      Request implementation.
 * @return {Promise<Object>} Saved exam settings.
 */
export const saveExamInstructions = ( examId, instructions, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/exams/${ examId }/settings`,
		method: 'POST',
		data: { instructions },
	} );

/**
 * Publish an exam. Without `confirm` the server refuses with `exam_overlap` when practice uses of its
 * questions would stop working.
 *
 * @param {number}  examId  Exam ID.
 * @param {boolean} confirm Accept the overlap.
 * @param {Fetch}   [fetch] Request implementation.
 * @return {Promise<Object>} Published exam.
 */
export const publishExam = ( examId, confirm, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/exams/${ examId }/publish`,
		method: 'POST',
		data: { confirm_overlap: confirm },
	} );

/**
 * Contents of a chapter matching a search, for the lesson prerequisite picker.
 *
 * @param {number} chapterId Chapter ID.
 * @param {string} term      Search text.
 * @param {Fetch}  [fetch]   Request implementation.
 * @return {Promise<ContentOption[]|{data?: ContentOption[]}>} Matches, as a list or `{ data }`.
 */
export const searchChapterContents = ( chapterId, term, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/chapters/${ chapterId }/search-contents?term=${ encodeURIComponent( term ) }`,
	} );

/**
 * Signed, short-lived "Preview as learner" links of a saved lesson (OhMyLMS\Rest\V1\LessonPreviewController).
 *
 * @param {number} lessonId Lesson ID.
 * @param {Fetch}  [fetch]  Request implementation.
 * @return {Promise<{id: number, url: string, bypass_url: string, expires: number, status: string}>} Links.
 */
export const createLearnerPreview = ( lessonId, fetch = request ) =>
	/** @type {Promise<{id: number, url: string, bypass_url: string, expires: number, status: string}>} */ (
		fetch( {
			path: `/ohmylms/v1/lessons/${ lessonId }/learner-preview`,
			method: 'POST',
		} )
	);

/**
 * Render an unsaved question as the learner sees it.
 *
 * @param {Object}      data    Question, document settings and seed.
 * @param {AbortSignal} signal  Cancels the request.
 * @param {Fetch}       [fetch] Request implementation.
 * @return {Promise<{url: string}>} `{ url }` of the preview document.
 */
export const previewQuestion = ( data, signal, fetch = request ) =>
	/** @type {Promise<{url: string}>} */ (
		fetch( {
			path: '/ohmylms/v1/question-preview',
			method: 'POST',
			signal,
			data,
		} )
	);
