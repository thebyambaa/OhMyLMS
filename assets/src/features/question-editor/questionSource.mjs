/**
 * Where a question comes from, as one choice shared by the Question block and the Question Player block:
 * write it here, pick from the question bank, or draw from skills. Pure helpers; the blocks apply the result.
 */
export const DEFAULT_DELIVERIES = [ 'quiz', 'inline', 'practice' ];

/**
 * Deliveries the open quiz's kind allows, or all of them in lessons and pages.
 * @return {string[]} Deliveries.
 */
export function editorDeliveries() {
	const list = window.ohmylmsNativeLesson?.profile?.deliveries;
	return Array.isArray( list ) && list.length ? list : DEFAULT_DELIVERIES;
}

/**
 * Sources offered where the block sits. The standalone bank-question editor only writes; skills need the
 * practice delivery, which an exam does not allow.
 * @param {Object}   root0            Context.
 * @param {string}   root0.postType   Post type being edited.
 * @param {string[]} root0.deliveries Deliveries the kind allows.
 * @return {string[]} Any of write, bank and skills.
 */
export function allowedSources( {
	postType = '',
	deliveries = DEFAULT_DELIVERIES,
} = {} ) {
	if ( postType === 'ohmylms-question' ) {
		return [ 'write' ];
	}
	const sources = [ 'write', 'bank' ];
	if ( deliveries.includes( 'practice' ) ) {
		sources.push( 'skills' );
	}
	return sources;
}

/**
 * What choosing a source does from the Question block (which writes a question here).
 * In a quiz or exam, picking from the bank adds questions as slots; elsewhere it becomes a practice set.
 * @param {string} source         write, bank or skills.
 * @param {Object} root0          Context.
 * @param {string} root0.postType Post type being edited.
 * @return {Object} action: none, pick (open the bank picker) or player (replace with these attributes).
 */
export function fromQuestionBlock( source, { postType = '' } = {} ) {
	if ( source === 'bank' ) {
		return postType === 'ohmylms-quiz'
			? { action: 'pick' }
			: {
					action: 'player',
					attributes: {
						source: 'selected',
						questionIds: [],
						skillIds: [],
					},
				};
	}
	if ( source === 'skills' ) {
		return {
			action: 'player',
			attributes: { source: 'random', questionIds: [], skillIds: [] },
		};
	}
	return { action: 'none' };
}

/**
 * What choosing a source does from the Question Player block.
 * @param {string} source write, bank or skills.
 * @return {Object} action: question (replace with a blank Question block) or set (change the player).
 */
export function fromPlayer( source ) {
	if ( source === 'write' ) {
		return { action: 'question' };
	}
	return {
		action: 'set',
		attributes: { source: source === 'skills' ? 'random' : 'selected' },
	};
}

/**
 * The switch position of a Question Player block.
 * @param {Object} attributes Player attributes.
 * @return {string} bank or skills.
 */
export function playerSource( attributes ) {
	return attributes.source === 'random' ? 'skills' : 'bank';
}
