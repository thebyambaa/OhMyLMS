import { queryString as query, request } from '../rest.mjs';

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

export const listSkills = ( search = '', fetch = request ) =>
	fetch( { path: `/ohmylms/v1/skills${ query( { search } ) }` } );
export const createSkill = ( data, fetch = request ) =>
	fetch( { path: '/ohmylms/v1/skills', method: 'POST', data } );
/**
 * Create a question directly in the bank (not placed in any quiz).
 * @param payload
 * @param fetch
 */
export const createBankQuestion = ( payload, fetch = request ) =>
	fetch( { path: '/ohmylms/v1/question', method: 'POST', data: payload } );
