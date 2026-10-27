const request = ( options ) => window.wp.apiFetch( options );
const base = '/ohmylms/v1';
const query = ( params ) => {
	const search = new URLSearchParams();
	for ( const [ key, value ] of Object.entries( params || {} ) ) {
		if ( value !== undefined && value !== null && value !== '' ) {
			search.set( key, String( value ) );
		}
	}
	const text = search.toString();
	return text ? `?${ text }` : '';
};

export const listLessons = ( params, fetch = request ) =>
	fetch( { path: `${ base }/content-hub/lessons${ query( params ) }` } );

export const loadCatalog = ( courseId, fetch = request ) =>
	fetch( { path: `${ base }/content-hub/catalog/${ courseId }` } );
/**
 * Replace the catalog's skills, attachments and/or mode in the draft. Returns the fresh catalog.
 * @param courseId
 * @param data
 * @param fetch
 */
export const saveCatalog = ( courseId, data, fetch = request ) =>
	fetch( {
		path: `${ base }/content-hub/catalog/${ courseId }`,
		method: 'PUT',
		data,
	} );
export const publishCatalog = ( courseId, data = {}, fetch = request ) =>
	fetch( {
		path: `${ base }/content-hub/catalog/${ courseId }/publish`,
		method: 'POST',
		data,
	} );
/**
 * Search lessons, quizzes, assignments or skills to attach, leaving out what the course already has.
 * @param params
 * @param fetch
 */
export const searchTargets = ( params, fetch = request ) =>
	fetch( { path: `${ base }/content-hub/targets${ query( params ) }` } );
export const setLessonSkills = ( lessonId, skillIds, fetch = request ) =>
	fetch( {
		path: `${ base }/content-hub/lessons/${ lessonId }/skills`,
		method: 'PUT',
		data: { skill_ids: skillIds },
	} );
export const duplicateLesson = ( lessonId, fetch = request ) =>
	fetch( {
		path: `${ base }/content-hub/lessons/${ lessonId }/duplicate`,
		method: 'POST',
	} );
export const trashLessons = ( ids, fetch = request ) =>
	fetch( {
		path: `${ base }/content-hub/lessons/trash`,
		method: 'POST',
		data: { ids },
	} );
const contentBase = {
	lesson: 'lessons',
	quiz: 'quiz',
	assignment: 'assignment',
};
/**
 * A new draft lesson, quiz or assignment, created where it is needed and attached straight away.
 * @param type
 * @param name
 * @param fetch
 */
export const createContent = ( type, name, fetch = request ) =>
	fetch( {
		path: `${ base }/${ contentBase[ type ] }`,
		method: 'POST',
		data: { name, status: 'draft' },
	} );
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
