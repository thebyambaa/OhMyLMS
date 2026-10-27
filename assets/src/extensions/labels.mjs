/**
 * Course categories were replaced by the curriculum and course tags by Course Bundles. The recovered
 * admin app still says "Category" and "Tag" in its course list filters, course page toggles and
 * archive-page settings, so those exact strings are relabeled through WordPress's gettext filter
 * (see curriculumLabels.js).
 *
 * Only exact strings of the "ohmylms" text domain listed here are changed. Other tag labels in the app
 * belong to contacts and automation ("Add Tag", "Apply Tags", ...), are different strings, and are
 * left alone.
 */
export const RELABELED = Object.freeze( {
	Categories: 'Curriculum',
	Category: 'Curriculum',
	'Category: ': 'Curriculum: ',
	'Enable course category.': 'Show the curriculum as tabs.',
	Tags: 'Course bundles',
	Tag: 'Course bundle',
	'Tag: ': 'Course bundle: ',
} );

/**
 * The text for a gettext lookup: the replacement (through `translate`) when the original string was
 * relabeled, otherwise the translation WordPress already found.
 * @param translation
 * @param text
 * @param translate
 */
export function relabel(
	translation,
	text,
	translate = ( replacement ) => replacement
) {
	return Object.prototype.hasOwnProperty.call( RELABELED, text )
		? translate( RELABELED[ text ] )
		: translation;
}
