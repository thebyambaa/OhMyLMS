import { request } from '../rest.mjs';

const quizQuestions = ( quizId ) => `/ohmylms/v1/quizzes/${ quizId }/questions`;

/**
 * Random pools in the shape the pools editor holds them (`term_id` is the skill).
 *
 * @param {Object} body `QuizQuestions` from `ohmylms/v1/quizzes/{id}/questions`.
 * @return {{pools: Object[], revision: string}} Pools and the revision they were read at.
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

export const loadAssessmentSettings = ( quizId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/quiz/${ quizId }/assessment-settings` } );
export const saveAssessmentSettings = ( quizId, data, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/quiz/${ quizId }/assessment-settings`,
		method: 'PUT',
		data,
	} );
/**
 * The questions placed in a quiz, without answer keys: `{ id, name, marks }` in quiz order.
 *
 * @param {number}                               quizId Quiz ID.
 * @param {(options: Object) => Promise<Object>} fetch  Transport.
 * @return {Promise<Object[]>} Placed questions.
 */
export const loadQuizQuestions = ( quizId, fetch = request ) =>
	Promise.resolve( fetch( { path: quizQuestions( quizId ) } ) ).then(
		( body ) =>
			( body?.questions || [] ).map( ( slot ) => ( {
				id: slot.question_id,
				name: slot.name,
				marks: slot.question_marks,
			} ) )
	);
export const loadRevisions = ( quizId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/quiz/${ quizId }/revisions` } );
export const loadPools = ( quizId, fetch = request ) =>
	Promise.resolve( fetch( { path: quizQuestions( quizId ) } ) ).then(
		poolsFrom
	);
/**
 * Replace the quiz's pools. `revision` is the one the pools were read at; a newer one answers
 * 409 `ohmylms_quiz_questions_conflict`.
 *
 * @param {number}                               quizId   Quiz ID.
 * @param {Object[]}                             pools    Pools as the editor holds them.
 * @param {string}                               revision Revision of the last read.
 * @param {(options: Object) => Promise<Object>} fetch    Transport.
 * @return {Promise<{pools: Object[], revision: string}>} Saved pools.
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
