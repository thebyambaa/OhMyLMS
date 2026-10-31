import { queryString as query, request } from '../rest.mjs';

const QUESTIONS = '/ohmylms/v1/questions';

/**
 * A typed question response with the flat fields the native editors read: bank attributes at the
 * top level, `skill_map`, `can_edit`, `can_approve`, and `thumbnail_id` on each option.
 *
 * @param {Object} view Summary or editor view from `ohmylms/v1/questions`.
 * @return {Object} The view with the flat fields added.
 */
export const bankView = ( view ) =>
	view && typeof view === 'object'
		? {
				...view,
				...( view.bank || {} ),
				skill_map: view.skills,
				can_edit: Boolean( view.permissions?.can_edit ),
				can_approve: Boolean( view.permissions?.can_approve ),
				...( Array.isArray( view.options )
					? {
							options: view.options.map( ( option ) => ( {
								...option,
								thumbnail_id: option.image_id || 0,
							} ) ),
						}
					: {} ),
			}
		: view;

/**
 * Answer options in the shape `ohmylms/v1/questions` accepts. Editor-only keys are dropped, and
 * unsaved options (`temp`) lose their client-side ID.
 *
 * @param {Object[]} options Options as the editors hold them.
 * @return {Object[]} Options for a write.
 */
export const optionsPayload = ( options = [] ) =>
	options.map( ( option, index ) => {
		const row = {
			answer: String( option.answer ?? '' ),
			is_correct:
				option.is_correct === true || Number( option.is_correct ) > 0,
			order_number: Number.isInteger( Number( option.order_number ) )
				? Math.max( 0, Number( option.order_number ) )
				: index + 1,
		};
		const id = Number( option.id );
		if ( ! option.temp && Number.isInteger( id ) && id > 0 ) {
			row.id = id;
		}
		const image = Number( option.image_id || option.thumbnail_id );
		if ( Number.isInteger( image ) && image > 0 ) {
			row.image_id = image;
		} else if ( /^https?:\/\//.test( option.image_url || '' ) ) {
			row.image_url = option.image_url;
		}
		const matching = option.matching_data;
		if ( matching && typeof matching === 'object' ) {
			row.matching_data = {
				...( matching.label !== undefined
					? { label: String( matching.label ) }
					: {} ),
				...( /^https?:\/\//.test( matching.image_url || '' )
					? { image_url: matching.image_url }
					: {} ),
			};
		}
		return row;
	} );

/**
 * A question write body: `questions` becomes `options`; fields the typed route does not accept
 * (`status`, `quiz_id`, `id`) are left out.
 *
 * @param {Object} data Fields as the editors build them.
 * @return {Object} Body for `POST|PATCH /questions`.
 */
export const questionPayload = ( data ) => {
	const body = {};
	for ( const key of [
		'name',
		'description',
		'settings',
		'skills',
		'bank',
		'thumbnail_id',
		'video_id',
		'base_modified',
	] ) {
		if ( data[ key ] !== undefined ) {
			body[ key ] = data[ key ];
		}
	}
	const options = data.options || data.questions;
	if ( Array.isArray( options ) ) {
		body.options = optionsPayload( options );
	}
	return body;
};

/**
 * Visible bank questions (summaries without answer keys).
 *
 * @param {Object}                               params Filters: search, type, difficulty, status ('' for every usable
 *                                                      question), bank, skill, secure, exclude_quiz, page, per_page.
 * @param {(options: Object) => Promise<Object>} fetch  Transport.
 * @return {Promise<{items: Object[]}>} Rows.
 */
export const searchBank = ( params, fetch = request ) =>
	Promise.resolve(
		fetch( {
			path: `${ QUESTIONS }${ query( {
				...params,
				status: params?.status || 'active',
				secure:
					params?.secure === undefined || params?.secure === ''
						? undefined
						: Boolean( Number( params.secure ) ),
			} ) }`,
		} )
	).then( ( rows ) => ( {
		items: Array.isArray( rows ) ? rows.map( bankView ) : [],
	} ) );

export const loadBankQuestion = ( id, fetch = request ) =>
	Promise.resolve( fetch( { path: `${ QUESTIONS }/${ id }` } ) ).then(
		bankView
	);
export const loadVersions = ( id, fetch = request ) =>
	fetch( { path: `${ QUESTIONS }/${ id }/versions?per_page=100` } );
export const loadVersion = ( id, versionId, fetch = request ) =>
	fetch( { path: `${ QUESTIONS }/${ id }/versions/${ versionId }` } );
export const approveQuestion = ( id, versionId, fetch = request ) =>
	Promise.resolve(
		fetch( {
			path: `${ QUESTIONS }/${ id }/approve`,
			method: 'POST',
			data: versionId ? { version_id: Number( versionId ) } : {},
		} )
	).then( bankView );
export const duplicateQuestion = ( id, fetch = request ) =>
	Promise.resolve(
		fetch( { path: `${ QUESTIONS }/${ id }/duplicate`, method: 'POST' } )
	).then( bankView );
/**
 * Partial update; `data.base_modified` is the `modified` of the last read.
 *
 * @param {number}                               id    Question ID.
 * @param {Object}                               data  Fields to change.
 * @param {(options: Object) => Promise<Object>} fetch Transport.
 * @return {Promise<Object>} Editor view.
 */
export const saveQuestionAttributes = ( id, data, fetch = request ) =>
	Promise.resolve(
		fetch( {
			path: `${ QUESTIONS }/${ id }`,
			method: 'PATCH',
			data: questionPayload( data ),
		} )
	).then( bankView );

export const listBanks = ( fetch = request ) =>
	fetch( { path: '/ohmylms/v1/question-banks' } );

/**
 * Pin a question of a quiz to a version (`0` unpins), checked against the quiz's current revision.
 *
 * @param {number}                               quizId     Quiz ID.
 * @param {number}                               questionId Question ID.
 * @param {number}                               versionId  Version ID, or 0 to unpin.
 * @param {(options: Object) => Promise<Object>} fetch      Transport.
 * @return {Promise<Object>} The quiz's questions.
 */
export const pinQuestion = async (
	quizId,
	questionId,
	versionId,
	fetch = request
) => {
	const current = await fetch( {
		path: `/ohmylms/v1/quizzes/${ quizId }/questions`,
	} );
	return fetch( {
		path: `/ohmylms/v1/quizzes/${ quizId }/questions/${ questionId }/pin`,
		method: 'PUT',
		data: {
			version_id: versionId ? Number( versionId ) : null,
			revision: current?.revision,
		},
	} );
};

export const listSkills = ( search = '', fetch = request ) =>
	fetch( { path: `/ohmylms/v1/skills${ query( { search } ) }` } );
export const createSkill = ( data, fetch = request ) =>
	fetch( { path: '/ohmylms/v1/skills', method: 'POST', data } );
/**
 * Create a question directly in the bank (not placed in any quiz).
 *
 * @param {Object}                               payload Fields as the editors build them.
 * @param {(options: Object) => Promise<Object>} fetch   Transport.
 * @return {Promise<Object>} Editor view of the new question.
 */
export const createBankQuestion = ( payload, fetch = request ) =>
	Promise.resolve(
		fetch( {
			path: QUESTIONS,
			method: 'POST',
			data: questionPayload( payload ),
		} )
	).then( bankView );
