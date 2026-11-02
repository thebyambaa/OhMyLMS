import { queryString as query, request } from '../rest.mjs';

const base = '/ohmylms/v1';

/**
 * @typedef {Object} ImportSummary
 * @property {number}                 questions  Questions in the file.
 * @property {number|string}          points     Total points.
 * @property {number}                 randomized Questions with randomized numbers.
 * @property {Record<string, number>} [types]    Question counts by type slug.
 */

/**
 * @typedef {Object} QuizImport Quiz made from a quiz file, or what a dry run would make.
 * @property {number}        id        Quiz ID.
 * @property {string}        name      Quiz name.
 * @property {boolean}       [renamed] Whether the name was changed to stay unique.
 * @property {ImportSummary} summary   What the file holds.
 * @property {string[]}      warnings  Things worth a second look.
 */

/**
 * @typedef {Object} QuizFile Quiz exported as an OhMyLMS quiz file.
 * @property {string}        filename Suggested file name.
 * @property {string}        mime     Media type.
 * @property {string}        content  File text.
 * @property {ImportSummary} summary  What the file holds.
 * @property {string[]}      warnings Unfinished questions.
 */

/**
 * @typedef {Object} QuestionRowsFile Question rows as a CSV or JSON file.
 * @property {string}   filename Suggested file name.
 * @property {string}   mime     Media type.
 * @property {string}   content  File text.
 * @property {number}   count    Questions exported.
 * @property {string[]} warnings Problems worth a second look.
 */

/**
 * @typedef {Object} TargetRow
 * @property {number} id          Post or term ID.
 * @property {string} title       Display title.
 * @property {string} [code]      Skill code.
 * @property {number} [questions] Questions using the skill.
 */

/**
 * @typedef {Object} TargetPage
 * @property {TargetRow[]} [items] Matches on this page.
 * @property {number}      [pages] Page count.
 */

/**
 * @typedef {Object} Catalog
 * @property {Array<{term_id: number}>} [skills] Skills the course has.
 */

/**
 * @param {number}         courseId
 * @param {typeof request} [fetch]
 * @return {Promise<Catalog>} Course catalog.
 */
export const loadCatalog = ( courseId, fetch = request ) =>
	/** @type {Promise<Catalog>} */ (
		fetch( { path: `${ base }/content-hub/catalog/${ courseId }` } )
	);
/**
 * Search lessons, quizzes, assignments or skills to attach, leaving out what the course already has.
 * @param {Record<string, unknown>} params
 * @param {typeof request}          [fetch]
 * @return {Promise<TargetPage>} One page of matches.
 */
export const searchTargets = ( params, fetch = request ) =>
	/** @type {Promise<TargetPage>} */ (
		fetch( { path: `${ base }/content-hub/targets${ query( params ) }` } )
	);
/**
 * A new draft quiz with its questions, made from an OhMyLMS quiz file (or the older CSV layout).
 * With `dryRun` nothing is created: the response says what the file holds, or the request fails
 * with every problem in `error.data.errors`.
 * @param {string}                                  name      Quiz name; empty keeps the name in the file.
 * @param {string}                                  content   File text.
 * @param {typeof request}                          [fetch]
 * @param {{dryRun?: boolean, copyMedia?: boolean}} [options] `dryRun`, and `copyMedia` (default true).
 * @return {Promise<QuizImport>} The quiz, or what the file would make.
 */
export const importQuiz = ( name, content, fetch = request, options = {} ) =>
	/** @type {Promise<QuizImport>} */ (
		fetch( {
			path: `${ base }/quiz/import/`,
			method: 'POST',
			data: {
				name,
				content,
				...( options.dryRun ? { dry_run: true } : {} ),
				...( options.copyMedia === false ? { copy_media: false } : {} ),
			},
		} )
	);
/**
 * A quiz and all of its questions as an OhMyLMS quiz file: filename, mime, content, summary and
 * warnings about unfinished questions.
 * @param {number}         id
 * @param {typeof request} [fetch]
 * @return {Promise<QuizFile>} The file.
 */
export const exportQuiz = ( id, fetch = request ) =>
	/** @type {Promise<QuizFile>} */ (
		fetch( { path: `${ base }/quiz/${ id }/export` } )
	);
/**
 * @param {'lesson'|'quiz'|'assignment'} type
 * @param {number}                       id
 */
export const editPath = ( type, id ) =>
	`/${ { lesson: 'lesson-edit', quiz: 'quiz-edit', assignment: 'assignment-edit' }[ type ] }/${ id }`;

/**
 * Question rows as CSV or JSON, either for a quiz or for chosen questions. The file imports back
 * through the Question Bank Import button.
 * @param {Object}         root0
 * @param {number}         [root0.quizId]
 * @param {number[]}       [root0.ids]
 * @param {string}         [root0.format]
 * @param {string}         [root0.label]
 * @param {typeof request} [fetch]
 * @return {Promise<QuestionRowsFile>} The file.
 */
export const exportQuestionRows = (
	{ quizId = 0, ids = [], format = 'json', label = '' },
	fetch = request
) =>
	/** @type {Promise<QuestionRowsFile>} */ (
		fetch( {
			path: `${ base }/questions/export`,
			method: 'POST',
			data: {
				format,
				...( quizId
					? { quiz_id: Number( quizId ) }
					: { ids: ids.map( Number ) } ),
				...( label ? { label } : {} ),
			},
		} )
	);
