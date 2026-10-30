import { queryString as query, request } from '../rest.mjs';

const base = '/ohmylms/v1';

export const loadCatalog = ( courseId, fetch = request ) =>
	fetch( { path: `${ base }/content-hub/catalog/${ courseId }` } );
/**
 * Search lessons, quizzes, assignments or skills to attach, leaving out what the course already has.
 * @param params
 * @param fetch
 */
export const searchTargets = ( params, fetch = request ) =>
	fetch( { path: `${ base }/content-hub/targets${ query( params ) }` } );
/**
 * A new draft quiz with its questions, made from an OhMyLMS quiz file (or the older CSV layout).
 * With `dryRun` nothing is created: the response says what the file holds, or the request fails
 * with every problem in `error.data.errors`.
 * @param name    Quiz name; empty keeps the name in the file.
 * @param content File text.
 * @param fetch
 * @param options `dryRun`, and `copyMedia` (default true).
 */
export const importQuiz = ( name, content, fetch = request, options = {} ) =>
	fetch( {
		path: `${ base }/quiz/import/`,
		method: 'POST',
		data: {
			name,
			content,
			...( options.dryRun ? { dry_run: true } : {} ),
			...( options.copyMedia === false ? { copy_media: false } : {} ),
		},
	} );
/**
 * A quiz and all of its questions as an OhMyLMS quiz file: filename, mime, content, summary and
 * warnings about unfinished questions.
 * @param id
 * @param fetch
 */
export const exportQuiz = ( id, fetch = request ) =>
	fetch( { path: `${ base }/quiz/${ id }/export` } );
export const editPath = ( type, id ) =>
	`/${ { lesson: 'lesson-edit', quiz: 'quiz-edit', assignment: 'assignment-edit' }[ type ] }/${ id }`;

/**
 * Question rows as CSV or JSON, either for a quiz or for chosen questions. The file imports back
 * through the Question Bank Import button.
 * @param {Object}   root0
 * @param {number}   root0.quizId
 * @param {number[]} root0.ids
 * @param {string}   root0.format
 * @param {string}   root0.label
 * @param            fetch
 */
export const exportQuestionRows = (
	{ quizId = 0, ids = [], format = 'json', label = '' },
	fetch = request
) => {
	const params = new URLSearchParams( { format } );
	if ( quizId ) {
		params.set( 'quiz_id', quizId );
	} else {
		params.set( 'ids', ids.join( ',' ) );
	}
	if ( label ) {
		params.set( 'label', label );
	}
	return fetch( { path: `${ base }/question-bank/export?${ params }` } );
};
