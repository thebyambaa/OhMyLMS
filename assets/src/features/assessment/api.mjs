import { request } from '../rest.mjs';

/** @typedef {( options: import('@wordpress/api-fetch').APIFetchOptions ) => Promise<Object>} Fetch */
/** @typedef {import('./model.mjs').AssessmentSettings} AssessmentSettings */
/** @typedef {''|'easy'|'standard'|'challenge'} Difficulty Pool difficulty, empty for any. */

/**
 * A random pool as the pools editor holds it.
 *
 * @typedef {Object} Pool
 * @property {string}     title          Section title.
 * @property {number}     term_id        Skill ID.
 * @property {number}     count          Questions drawn.
 * @property {number}     marks          Marks each.
 * @property {Difficulty} difficulty     Difficulty.
 * @property {number}     [bank_id]      Bank ID, 0 for any.
 * @property {boolean}    include_secure Draws exam-only questions too.
 */

/**
 * A pool as `ohmylms/v1/quizzes/{id}/questions` reads it.
 *
 * @typedef {Object} QuizQuestionsPool
 * @property {string}        [title]          Section title.
 * @property {{id?: number}} [skill]          Skill.
 * @property {number}        count            Questions drawn.
 * @property {number}        marks            Marks each.
 * @property {Difficulty}    [difficulty]     Difficulty.
 * @property {number}        [bank_id]        Bank ID.
 * @property {boolean}       [include_secure] Draws exam-only questions too.
 */

/**
 * A question placed in the quiz, as `ohmylms/v1/quizzes/{id}/questions` reads it.
 *
 * @typedef {Object} QuizQuestionsSlot
 * @property {number} question_id    Question ID.
 * @property {string} name           Question title.
 * @property {number} question_marks Question marks.
 */

/**
 * `QuizQuestions` from `ohmylms/v1/quizzes/{id}/questions`.
 *
 * @typedef {Object} QuizQuestionsBody
 * @property {QuizQuestionsPool[]} [pools]     Random pools.
 * @property {QuizQuestionsSlot[]} [questions] Placed questions.
 * @property {string}              [revision]  Revision of the placement.
 */

/**
 * A published revision of a quiz.
 *
 * @typedef {Object} QuizRevision
 * @property {number|string} id          Revision row ID.
 * @property {number|string} revision_no Revision number.
 * @property {number|string} total_marks Total marks.
 * @property {number}        attempts    Attempts.
 * @property {string}        created_at  Publication time, UTC.
 * @property {string}        status      `published` or `superseded`.
 */

/** @param {number} quizId Quiz ID. */
const quizQuestions = ( quizId ) => `/ohmylms/v1/quizzes/${ quizId }/questions`;

/**
 * Random pools in the shape the pools editor holds them (`term_id` is the skill).
 *
 * @param {QuizQuestionsBody|null|undefined} body `QuizQuestions` from `ohmylms/v1/quizzes/{id}/questions`.
 * @return {{pools: Pool[], revision: string}} Pools and the revision they were read at.
 */
export const poolsFrom = ( body ) => ( {
	pools: ( body?.pools || [] ).map( ( pool ) => ( {
		title: pool.title || '',
		term_id: Number( pool.skill?.id || 0 ),
		count: pool.count,
		marks: pool.marks,
		difficulty: pool.difficulty || '',
		bank_id: Number( pool.bank_id || 0 ),
		include_secure: pool.include_secure !== false,
	} ) ),
	revision: body?.revision || '',
} );

/**
 * @param {number} quizId  Quiz ID.
 * @param {Fetch}  [fetch] Transport.
 * @return {Promise<{settings: AssessmentSettings}>} Assessment rules of the quiz.
 */
export const loadAssessmentSettings = ( quizId, fetch = request ) =>
	/** @type {Promise<{settings: AssessmentSettings}>} */ (
		fetch( { path: `/ohmylms/v1/quiz/${ quizId }/assessment-settings` } )
	);
/**
 * @param {number}                  quizId  Quiz ID.
 * @param {Record<string, unknown>} data    Rules to change.
 * @param {Fetch}                   [fetch] Transport.
 * @return {Promise<{settings: AssessmentSettings}>} Normalized rules as saved.
 */
export const saveAssessmentSettings = ( quizId, data, fetch = request ) =>
	/** @type {Promise<{settings: AssessmentSettings}>} */ (
		fetch( {
			path: `/ohmylms/v1/quiz/${ quizId }/assessment-settings`,
			method: 'PUT',
			data,
		} )
	);
/**
 * The questions placed in a quiz, without answer keys: `{ id, name, marks }` in quiz order.
 *
 * @param {number}                               quizId Quiz ID.
 * @param {(options: Object) => Promise<Object>} fetch  Transport.
 * @return {Promise<{id: number, name: string, marks: number}[]>} Placed questions.
 */
export const loadQuizQuestions = ( quizId, fetch = request ) =>
	Promise.resolve( fetch( { path: quizQuestions( quizId ) } ) ).then(
		/** @param {QuizQuestionsBody|null|undefined} body Placement. */
		( body ) =>
			( body?.questions || [] ).map( ( slot ) => ( {
				id: slot.question_id,
				name: slot.name,
				marks: slot.question_marks,
			} ) )
	);
/**
 * @param {number} quizId  Quiz ID.
 * @param {Fetch}  [fetch] Transport.
 * @return {Promise<{revisions?: QuizRevision[]}>} Published revisions, newest first.
 */
export const loadRevisions = ( quizId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/quiz/${ quizId }/revisions` } );
/**
 * @param {number} quizId  Quiz ID.
 * @param {Fetch}  [fetch] Transport.
 * @return {Promise<{pools: Pool[], revision: string}>} Pools and the revision they were read at.
 */
export const loadPools = ( quizId, fetch = request ) =>
	Promise.resolve( fetch( { path: quizQuestions( quizId ) } ) ).then(
		poolsFrom
	);
/**
 * Replace the quiz's pools. `revision` is the one the pools were read at; a newer one answers
 * 409 `ohmylms_quiz_questions_conflict`.
 *
 * @param {number}                               quizId   Quiz ID.
 * @param {Pool[]}                               pools    Pools as the editor holds them.
 * @param {string}                               revision Revision of the last read.
 * @param {(options: Object) => Promise<Object>} fetch    Transport.
 * @return {Promise<{pools: Pool[], revision: string}>} Saved pools.
 */
export const savePools = ( quizId, pools, revision, fetch = request ) =>
	Promise.resolve(
		fetch( {
			path: `${ quizQuestions( quizId ) }/pools`,
			method: 'PUT',
			data: {
				pools: pools.map( ( pool ) => ( {
					title: pool.title || '',
					skill_id: Number( pool.term_id || 0 ),
					count: Number( pool.count ),
					marks: Number( pool.marks ),
					difficulty: pool.difficulty || '',
					include_secure: pool.include_secure !== false,
					...( Number( pool.bank_id ) > 0
						? { bank_id: Number( pool.bank_id ) }
						: {} ),
				} ) ),
				revision,
			},
		} )
	).then( poolsFrom );
