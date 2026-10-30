import { request } from '../rest.mjs';

export const loadAssessmentSettings = ( quizId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/quiz/${ quizId }/assessment-settings` } );
export const saveAssessmentSettings = ( quizId, data, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/quiz/${ quizId }/assessment-settings`,
		method: 'PUT',
		data,
	} );
export const loadQuizQuestions = ( quizId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/quiz/${ quizId }/content` } );
export const loadRevisions = ( quizId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/quiz/${ quizId }/revisions` } );
export const loadPools = ( quizId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/quiz/${ quizId }/pools` } );
export const savePools = ( quizId, pools, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/quiz/${ quizId }/pools`,
		method: 'PUT',
		data: { pools },
	} );
