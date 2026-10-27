/**
 * Resolve lesson skills, including courses saved before lessons had explicit links.
 * @param {Object} program Course program.
 * @param {string} groupId Lesson identifier.
 * @return {number[]} Shared skill identifiers.
 */
export function lessonSkillIds( program, groupId ) {
	const group = program.groups?.find( ( row ) => row.id === groupId );
	if ( ! group ) {
		return [];
	}
	return Array.isArray( group.skill_ids )
		? group.skill_ids
		: program.outcomes
				.filter( ( row ) => row.group_id === groupId )
				.map( ( row ) => row.term_id );
}

/**
 * Change one lesson's links without moving skills or changing completion targets.
 * @param {Object}   program Course program.
 * @param {string}   groupId Lesson identifier.
 * @param {number[]} ids     Skill identifiers.
 * @return {Object} Updated program.
 */
export function setLessonSkills( program, groupId, ids ) {
	const groups = ( program.groups || [] ).map( ( group ) => ( {
		...group,
		skill_ids:
			group.id === groupId
				? [ ...new Set( ids ) ]
				: lessonSkillIds( program, group.id ),
	} ) );
	return {
		...program,
		groups,
		outcomes: program.outcomes.map( ( outcome ) => {
			const linked = groups.filter( ( group ) =>
				group.skill_ids.includes( outcome.term_id )
			);
			const home =
				linked.find( ( group ) => group.id === outcome.group_id ) ||
				linked[ 0 ];
			return home
				? { ...outcome, chapter_id: home.chapter_id, group_id: home.id }
				: { ...outcome, group_id: '' };
		} ),
	};
}

/**
 * Link an existing or new course outcome to a lesson. Mastery is still counted once.
 * @param {Object}        program Course program.
 * @param {number}        termId  Skill identifier.
 * @param {number|string} unitId  Unit identifier.
 * @param {string}        groupId Lesson identifier.
 * @return {Object} Updated program.
 */
export function linkLessonSkill( program, termId, unitId, groupId ) {
	const exists = program.outcomes.some( ( row ) => row.term_id === termId );
	const next = {
		...program,
		outcomes: exists
			? program.outcomes
			: [
					...program.outcomes,
					{
						term_id: termId,
						target: 'proficient',
						required: false,
						chapter_id: unitId,
						group_id: groupId || '',
					},
				],
	};
	return groupId
		? setLessonSkills( next, groupId, [
				...lessonSkillIds( next, groupId ),
				termId,
			] )
		: next;
}

/**
 * Remove a lesson link and its local coverage, retaining shared content and other lessons.
 * @param {Object} program Course program.
 * @param {string} groupId Lesson identifier.
 * @param {number} termId  Skill identifier.
 * @return {Object} Updated program.
 */
export function unlinkLessonSkill( program, groupId, termId ) {
	const next = setLessonSkills(
		program,
		groupId,
		lessonSkillIds( program, groupId ).filter( ( id ) => id !== termId )
	);
	return {
		...next,
		items: next.items
			.filter(
				( item ) =>
					! (
						item.group_id === groupId &&
						item.type === 'practice' &&
						item.content_id === termId
					)
			)
			.map( ( item ) =>
				item.group_id === groupId && item.skill_ids
					? {
							...item,
							skill_ids: item.skill_ids.filter(
								( id ) => id !== termId
							),
						}
					: item
			),
	};
}
