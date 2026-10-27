const request = ( options ) => window.wp.apiFetch( options );
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

export const searchBank = ( params, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/question-bank${ query( params ) }` } );
export const loadBankQuestion = ( id, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/question-bank/${ id }` } );
export const loadVersion = ( versionId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/question-bank/versions/${ versionId }` } );
export const approveQuestion = ( id, versionId, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/question-bank/${ id }/approve`,
		method: 'POST',
		data: versionId ? { version_id: versionId } : {},
	} );
export const archiveQuestion = ( id, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/question-bank/${ id }/archive`,
		method: 'POST',
	} );
export const restoreQuestion = ( id, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/question-bank/${ id }/restore`,
		method: 'POST',
	} );
export const duplicateQuestion = ( id, quizId, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/question-bank/${ id }/duplicate`,
		method: 'POST',
		data: quizId ? { quiz_id: quizId } : {},
	} );
export const saveQuestionAttributes = ( id, data, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/question/${ id }`, method: 'PUT', data } );

export const listBanks = ( fetch = request ) =>
	fetch( { path: '/ohmylms/v1/question-banks' } );
export const createBank = ( data, fetch = request ) =>
	fetch( { path: '/ohmylms/v1/question-banks', method: 'POST', data } );
export const listGrants = ( bankId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/question-banks/${ bankId }/grants` } );
export const grantBank = ( bankId, data, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/question-banks/${ bankId }/grants`,
		method: 'POST',
		data,
	} );
export const revokeBank = ( bankId, data, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/question-banks/${ bankId }/grants`,
		method: 'DELETE',
		data,
	} );

export const addQuestionsToQuiz = (
	quizId,
	questionIds,
	pin = false,
	fetch = request
) =>
	fetch( {
		path: `/ohmylms/v1/quiz/${ quizId }/questions`,
		method: 'POST',
		data: { question_ids: questionIds, pin },
	} );
export const pinQuestion = ( quizId, questionId, versionId, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/quiz/${ quizId }/questions/${ questionId }/pin`,
		method: 'PUT',
		data: { version_id: versionId || 0 },
	} );
export const publishRevision = ( quizId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/quiz/${ quizId }/revisions`, method: 'POST' } );
export const listRevisions = ( quizId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/quiz/${ quizId }/revisions` } );

export const listSkills = ( search = '', fetch = request ) =>
	fetch( { path: `/ohmylms/v1/skills${ query( { search } ) }` } );
export const createSkill = ( data, fetch = request ) =>
	fetch( { path: '/ohmylms/v1/skills', method: 'POST', data } );
export const updateSkill = ( id, data, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/skills/${ id }`, method: 'PUT', data } );
export const deleteSkill = ( id, force = false, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/skills/${ id }${ force ? '?force=1' : '' }`,
		method: 'DELETE',
	} );
/**
 * Replace the lessons ('lessons') or courses ('courses') linked to a skill.
 * @param id
 * @param kind
 * @param ids
 * @param fetch
 */
export const linkSkillPosts = ( id, kind, ids, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/skills/${ id }/${ kind }`,
		method: 'PUT',
		data: { [ kind === 'courses' ? 'course_ids' : 'lesson_ids' ]: ids },
	} );
export const saveSkillMap = ( questionId, skillMap, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/question/${ questionId }/skills`,
		method: 'PUT',
		data: { skill_map: skillMap },
	} );
/**
 * Lessons or courses the author may link: by search text, or titles for known IDs.
 * @param type
 * @param search
 * @param fetch
 */
export const searchLinkTargets = ( type, search, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/skills/link-targets${ query( { type, search } ) }`,
	} );
export const linkTargetTitles = ( type, ids, fetch = request ) =>
	ids.length
		? fetch( {
				path: `/ohmylms/v1/skills/link-targets${ query( { type, include: ids.join( ',' ) } ) }`,
			} )
		: Promise.resolve( [] );
/**
 * Create a question directly in the bank (not placed in any quiz).
 * @param payload
 * @param fetch
 */
export const createBankQuestion = ( payload, fetch = request ) =>
	fetch( { path: '/ohmylms/v1/question', method: 'POST', data: payload } );
