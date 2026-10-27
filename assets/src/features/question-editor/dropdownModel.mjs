/**
 * Correct choices in both legacy and version two dropdown contracts.
 * @param {Object} slot Dropdown.
 * @return {Array} Correct choices.
 */
export function dropdownAnswers( slot ) {
	if ( Array.isArray( slot.answers ) ) {
		return slot.answers;
	}
	return slot.answer ? [ slot.answer ] : [];
}

/**
 * Rename a choice without losing its correctness tick.
 * @param {Object} slot  Dropdown.
 * @param {number} index Choice position.
 * @param {string} text  New label.
 * @return {Object} Dropdown patch.
 */
export function renameDropdownChoice( slot, index, text ) {
	const old = slot.choices[ index ];
	const answers = dropdownAnswers( slot ).map( ( answer ) =>
		answer === old ? text : answer
	);
	return {
		choices: slot.choices.map( ( choice, position ) =>
			position === index ? text : choice
		),
		answers,
		answer: answers[ 0 ] || '',
	};
}

/**
 * Upgrade editable dropdown settings and derive their total points.
 * @param {Object} settings Existing settings.
 * @param {Array}  slots    Edited dropdowns.
 * @return {Object} Settings patch.
 */
export function dropdownSettings( settings, slots ) {
	const fallback =
		Number( settings.score?.value ?? 1 ) /
		Math.max( 1, settings.slots?.length || slots.length );
	const next = slots.map( ( slot ) => ( {
		...slot,
		answers: dropdownAnswers( slot ),
		points: Number( slot.points ?? fallback ),
		grading: slot.grading || 'equal',
	} ) );
	return {
		slots: next,
		dropdown_grading_version: 2,
		score: {
			...settings.score,
			enabled: true,
			value: next.reduce( ( total, slot ) => total + slot.points, 0 ),
		},
	};
}
