/**
 * Save source selection without changing the roadmap's folders or skill order.
 * @param {Object} state   Revision-bearing roadmap snapshot.
 * @param {Array}  sources Selected source IDs.
 * @return {Object} Existing roadmap save contract.
 */
export function roadmapSourceWrite( state, sources ) {
	return {
		name: state.name,
		syllabus_ids: [ ...sources ],
		skill_ids: state.skills.map( ( skill ) => skill.id ),
		folders: state.folders.map( ( folder ) => ( {
			...folder,
			skill_ids: [ ...folder.skill_ids ],
		} ) ),
		expected_revision: state.revision,
	};
}
