/**
 * Read a language version, with the original as a display fallback.
 * @param {Object} skill    Skill record.
 * @param {string} language Requested language.
 * @param {string} field    Text field.
 * @return {string} Display text.
 */
export function skillText( skill, language, field = 'objective' ) {
	if ( field === 'objective' ) {
		const version = skill.translations?.[ language ];
		return (
			version?.objective ??
			( version?.description || version?.name ) ??
			skill.objective ??
			( skill.description || skill.name || '' )
		);
	}
	return skill.translations?.[ language ]?.[ field ] ?? skill[ field ] ?? '';
}

/**
 * Keep language edits separate from the existing canonical text.
 * @param {string} language Language key.
 * @param {string} field    Text field.
 * @param {string} text     Edited value.
 * @return {Object} Partial update.
 */
export function skillPatch( language, field, text ) {
	return language === 'original'
		? { [ field ]: text }
		: { translations: { [ language ]: { [ field ]: text } } };
}
