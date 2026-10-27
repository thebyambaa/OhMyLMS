/**
 * Change choice selection mode without resetting authored options or media.
 *
 * @param {Object}  question Current question.
 * @param {boolean} multiple Allow several correct answers.
 * @return {Object} Question patch.
 */
export function choiceModePatch( question, multiple ) {
	const settings = question.settings || {};
	let keptCorrect = false;
	return {
		settings: {
			...settings,
			type: multiple ? 'multiple-choice' : 'single-choice',
			partial_credit: multiple
				? Boolean( settings.partial_credit )
				: false,
		},
		questions: ( question.questions || [] ).map( ( option ) => {
			const correct =
				option.is_correct === true || Number( option.is_correct ) === 1;
			const selected = multiple ? correct : correct && ! keptCorrect;
			keptCorrect = keptCorrect || selected;
			return { ...option, is_correct: selected };
		} ),
	};
}

/**
 * Keep the choice workspace mounted when its selection mode changes.
 *
 * @param {string|number} id   Question identity.
 * @param {string}        type Saved response type.
 * @return {string} React workspace key.
 */
export function questionWorkspaceKey( id, type ) {
	const family = [ 'single-choice', 'multiple-choice' ].includes( type )
		? 'choice'
		: type;
	return `${ id }:${ family }`;
}
