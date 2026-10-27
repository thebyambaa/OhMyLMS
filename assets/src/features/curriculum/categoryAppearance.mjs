/**
 * Category appearance is defined once on syllabus home and reused for every skill.
 * @param skill
 * @param settings
 * @param group
 */
export function categoryAppearance( skill, settings, group ) {
	const categories = settings?.categories || [];
	const category =
		skill.category ||
		( ! skill.category_assigned &&
			categories.find(
				( label ) =>
					label.toLowerCase() ===
					String( group?.name || '' )
						.trim()
						.toLowerCase()
			) ) ||
		'';
	const style = settings?.category_styles?.[ category ] || {};
	return {
		category,
		icon: style.icon || 'awards',
		color: style.color || '#6e42d3',
	};
}
