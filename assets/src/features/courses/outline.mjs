import { lessonSkillIds, linkLessonSkill } from './lessonSkills.mjs';

/**
 * Move one stable row without changing the source list or its identifiers.
 * @param rows
 * @param id
 * @param offset
 */
export function moveOutlineRow( rows, id, offset ) {
	const result = [ ...rows ];
	const from = result.findIndex( ( row ) => row.id === id );
	const to = from + offset;
	if ( from >= 0 && to >= 0 && to < result.length ) {
		[ result[ from ], result[ to ] ] = [ result[ to ], result[ from ] ];
	}
	return result;
}

/**
 * Serialize the displayed unit/lesson order, keeping tests and the challenge terminal.
 * @param program
 * @param units
 */
export function orderedOutlineItems( program, units ) {
	const result = [];
	for ( const unit of [ ...units, { id: 0 } ] ) {
		const items = program.items.filter(
			( item ) => String( item.chapter_id || 0 ) === String( unit.id )
		);
		for ( const group of ( program.groups || [] ).filter(
			( row ) => String( row.chapter_id ) === String( unit.id )
		) ) {
			result.push(
				...items.filter(
					( item ) =>
						item.group_id === group.id &&
						! [ 'unit-test', 'course-challenge' ].includes(
							item.style
						)
				)
			);
		}
		const grouped = new Set( result );
		result.push(
			...items.filter(
				( item ) =>
					! grouped.has( item ) &&
					! [ 'unit-test', 'course-challenge' ].includes( item.style )
			)
		);
		result.push(
			...items.filter( ( item ) =>
				[ 'unit-test', 'course-challenge' ].includes( item.style )
			)
		);
	}
	const included = new Set( result );
	const remainder = program.items.filter(
		( item ) => ! included.has( item )
	);
	return [
		...result.filter( ( item ) => item.style !== 'course-challenge' ),
		...remainder.filter( ( item ) => item.style !== 'course-challenge' ),
		...result.filter( ( item ) => item.style === 'course-challenge' ),
		...remainder.filter( ( item ) => item.style === 'course-challenge' ),
	];
}

/**
 * Remove a lesson container, retaining its activities and skill outcomes in the unit.
 * @param program
 * @param id
 */
export function removeOutlineLesson( program, id ) {
	const ungroup = ( row ) => {
		if ( row.group_id !== id ) {
			return row;
		}
		const { group_id: removed, ...kept } = row;
		return kept;
	};
	return {
		...program,
		groups: ( program.groups || [] ).filter( ( row ) => row.id !== id ),
		items: program.items.map( ungroup ),
		outcomes: program.outcomes.map( ungroup ),
	};
}

/**
 * Intended skill coverage in each lesson, computed from current unsaved placements.
 * Shared resource tags and activities in other lessons do not imply coverage here.
 * @param program
 */
export function outlineLessonReadiness( program ) {
	return ( program.groups || [] ).map( ( lesson ) => {
		const member = ( row ) =>
			row.group_id === lesson.id &&
			String( row.chapter_id ) === String( lesson.chapter_id );
		const items = program.items.filter( member );
		const skills = lessonSkillIds( program, lesson.id );
		const taught = new Set();
		const assessed = new Set();
		for ( const item of items ) {
			const coverage =
				item.type === 'practice'
					? [ item.content_id ]
					: item.skill_ids || [];
			const target = [ 'quiz', 'practice', 'assignment' ].includes(
				item.type
			)
				? assessed
				: taught;
			coverage
				.filter( ( id ) => skills.includes( id ) )
				.forEach( ( id ) => target.add( id ) );
		}
		const missingTeaching = skills.filter( ( id ) => ! taught.has( id ) );
		const missingAssessment = skills.filter(
			( id ) => ! assessed.has( id )
		);
		const drafts = items.filter(
			( item ) => item.type !== 'practice' && item.status !== 'publish'
		);
		return {
			id: lesson.id,
			title: lesson.title,
			items: items.length,
			skills,
			missingTeaching,
			missingAssessment,
			drafts: drafts.length,
			complete:
				!! items.length &&
				!! skills.length &&
				! missingTeaching.length &&
				! missingAssessment.length &&
				! drafts.length,
		};
	} );
}

/**
 * One label for where the outline stands, for the editor header.
 *
 * @param {Object}  state             Editor state.
 * @param {boolean} state.saving      A save or publication is running.
 * @param {boolean} state.dirty       There are local edits that are not saved.
 * @param {boolean} state.unpublished The saved draft differs from the published version.
 * @return {string} One of saving, unsaved, unpublished or published.
 */
export function outlineStatus( { saving, dirty, unpublished } ) {
	if ( saving ) {
		return 'saving';
	}
	if ( dirty ) {
		return 'unsaved';
	}
	return unpublished ? 'unpublished' : 'published';
}

/**
 * Course skill outcomes whose shared skill no longer exists in the library.
 *
 * @param {Object} program Draft program.
 * @param {Object} names   Skill labels keyed by term ID; missing skills have none.
 * @return {number[]} Term IDs.
 */
export function missingOutlineSkills( program, names ) {
	return program.outcomes
		.filter( ( row ) => ! names?.[ row.term_id ] )
		.map( ( row ) => row.term_id );
}

/**
 * Remove skill outcomes with their practice placements and activity coverage links.
 *
 * @param {Object}   program Draft program.
 * @param {number[]} ids     Term IDs to remove.
 * @return {Object} Updated program.
 */
export function removeOutlineSkills( program, ids ) {
	const removed = ( id ) => ids.includes( id );
	return {
		...program,
		groups: ( program.groups || [] ).map( ( group ) => ( {
			...group,
			skill_ids: lessonSkillIds( program, group.id ).filter(
				( id ) => ! removed( id )
			),
		} ) ),
		outcomes: program.outcomes.filter(
			( row ) => ! removed( row.term_id )
		),
		items: program.items
			.filter(
				( item ) =>
					item.type !== 'practice' || ! removed( item.content_id )
			)
			.map( ( item ) =>
				item.skill_ids?.some( removed )
					? {
							...item,
							skill_ids: item.skill_ids.filter(
								( id ) => ! removed( id )
							),
						}
					: item
			),
	};
}

/**
 * Where a skill can be practised from: a lesson's practice is the skill itself, as on Khan Academy.
 * Each lesson can practise the same shared skill; completion still uses one course outcome.
 *
 * @param {Object}        program       Draft program.
 * @param {number}        termId        Skill term ID.
 * @param {Object}        place         Destination.
 * @param {number|string} place.unitId  Unit ID.
 * @param {string}        place.groupId Lesson UUID, or empty for the unit.
 * @return {string} One of new, linked or placed in this lesson.
 */
export function practicePlacement( program, termId, { unitId, groupId } ) {
	const here = ( row ) =>
		String( row.chapter_id || 0 ) === String( unitId ) &&
		( row.group_id || '' ) === ( groupId || '' );
	if (
		program.items.some(
			( item ) =>
				item.type === 'practice' &&
				item.content_id === termId &&
				here( item )
		)
	) {
		return 'placed';
	}
	return lessonSkillIds( program, groupId ).includes( termId ) ||
		program.outcomes.some(
			( row ) => row.term_id === termId && here( row )
		)
		? 'linked'
		: 'new';
}

/**
 * Link a skill to a lesson (or unit) and place its practice in one step.
 *
 * @param {Object}        program       Draft program.
 * @param {Object}        skill         Skill choice.
 * @param {number}        skill.termId  Skill term ID.
 * @param {number|string} skill.unitId  Unit ID.
 * @param {string}        skill.groupId Lesson UUID, or empty for the unit.
 * @param {string}        skill.id      New placement UUID.
 * @return {Object|null} Updated program, or null when practice is already placed in this lesson.
 */
export function addOutlinePractice( program, { termId, unitId, groupId, id } ) {
	if (
		practicePlacement( program, termId, { unitId, groupId } ) === 'placed'
	) {
		return null;
	}
	const next = linkLessonSkill( program, termId, unitId, groupId );
	return {
		...next,
		items: [
			...next.items,
			{
				id,
				content_id: termId,
				type: 'practice',
				style: 'practice',
				required: false,
				chapter_id: unitId,
				...( groupId ? { group_id: groupId } : {} ),
			},
		],
	};
}

/**
 * Counts shown on a unit card.
 *
 * @param {Object}        program Draft program.
 * @param {number|string} unitId  Unit ID.
 * @return {{lessons: number, skills: number, activities: number}} Counts.
 */
export function outlineUnitSummary( program, unitId ) {
	const inUnit = ( row ) =>
		String( row.chapter_id || 0 ) === String( unitId );
	return {
		lessons: program.items.filter(
			( row ) => inUnit( row ) && 'lesson' === row.type
		).length,
		skills: new Set(
			program.outcomes.filter( inUnit ).map( ( row ) => row.term_id )
		).size,
		activities: program.items.filter( inUnit ).length,
	};
}

/**
 * Insert a row before another row, or after the last row matching a container, keeping identities.
 *
 * @param {Object[]}                 rows     Rows without the moved row.
 * @param {Object}                   row      Moved row.
 * @param {string|number}            beforeId Row to insert before, or empty to append to the container.
 * @param {(row: Object) => boolean} inside   Whether a row belongs to the destination container.
 * @return {Object[]} Rows.
 */
function insertOutlineRow( rows, row, beforeId, inside ) {
	const result = [ ...rows ];
	let index = beforeId
		? result.findIndex( ( entry ) => entry.id === beforeId )
		: -1;
	if ( index < 0 ) {
		const last = result.findLastIndex( inside );
		index = last < 0 ? result.length : last + 1;
	}
	result.splice( index, 0, row );
	return result;
}

/**
 * Drop a unit before another unit, or at the end.
 *
 * @param {Object[]}      units    Units in order.
 * @param {number|string} id       Moved unit.
 * @param {number|string} beforeId Unit to insert before, or empty for the end.
 * @return {Object[]} Units.
 */
export function dropOutlineUnit( units, id, beforeId ) {
	const unit = units.find( ( row ) => row.id === id );
	if ( ! unit || id === beforeId ) {
		return units;
	}
	return insertOutlineRow(
		units.filter( ( row ) => row.id !== id ),
		unit,
		beforeId,
		() => true
	);
}

/**
 * Drop a lesson into a unit before another lesson, or at the end of that unit.
 * Its activities and skills move with it.
 *
 * @param {Object}        program         Draft program.
 * @param {string}        id              Moved lesson UUID.
 * @param {Object}        target          Destination.
 * @param {number|string} target.unitId   Destination unit.
 * @param {string}        target.beforeId Lesson to insert before, or empty.
 * @return {Object} Program.
 */
export function dropOutlineLesson( program, id, { unitId, beforeId } ) {
	const groups = program.groups || [];
	const lesson = groups.find( ( row ) => row.id === id );
	if ( ! lesson || id === beforeId ) {
		return program;
	}
	const move = ( row ) =>
		row.group_id === id ? { ...row, chapter_id: unitId } : row;
	return {
		...program,
		groups: insertOutlineRow(
			groups.filter( ( row ) => row.id !== id ),
			{ ...lesson, chapter_id: unitId },
			beforeId,
			( row ) => String( row.chapter_id ) === String( unitId )
		),
		items: program.items.map( move ),
		outcomes: program.outcomes.map( move ),
	};
}

/**
 * Drop an activity into a lesson or directly into a unit, before another activity or at the end.
 * Unit tests and the course challenge keep their fixed places and are not moved.
 *
 * @param {Object}        program         Draft program.
 * @param {string}        id              Moved placement UUID.
 * @param {Object}        target          Destination.
 * @param {number|string} target.unitId   Destination unit.
 * @param {string}        target.groupId  Destination lesson UUID, or empty for the unit.
 * @param {string}        target.beforeId Activity to insert before, or empty.
 * @return {Object} Program.
 */
export function dropOutlineActivity(
	program,
	id,
	{ unitId, groupId, beforeId }
) {
	const item = program.items.find( ( row ) => row.id === id );
	if (
		! item ||
		id === beforeId ||
		[ 'unit-test', 'course-challenge' ].includes( item.style )
	) {
		return program;
	}
	const { group_id: previous, ...rest } = item;
	const moved = {
		...rest,
		chapter_id: unitId,
		...( groupId ? { group_id: groupId } : {} ),
	};
	return {
		...program,
		items: insertOutlineRow(
			program.items.filter( ( row ) => row.id !== id ),
			moved,
			beforeId,
			( row ) =>
				String( row.chapter_id || 0 ) === String( unitId ) &&
				( row.group_id || '' ) === ( groupId || '' ) &&
				! [ 'unit-test', 'course-challenge' ].includes( row.style )
		),
	};
}

/**
 * Put a unit's skills that are not in a lesson into lessons, each skill with its practice, as on
 * Khan Academy where a lesson's exercises are its skills. Missing skills are left for removal.
 *
 * @param {Object}        program        Draft program.
 * @param {Object}        options        Grouping.
 * @param {number|string} options.unitId Unit.
 * @param {Object}        options.names  Skill labels keyed by term ID.
 * @param {string}        options.title  Lesson title when grouping into one lesson; empty for one lesson per skill.
 * @param {() => string}  options.newId  UUID generator.
 * @return {Object} Program.
 */
export function lessonsFromSkills( program, { unitId, names, title, newId } ) {
	const inUnit = ( row ) =>
		String( row.chapter_id || 0 ) === String( unitId ) && ! row.group_id;
	const loose = program.outcomes.filter(
		( row ) => inUnit( row ) && names?.[ row.term_id ]
	);
	if ( ! loose.length ) {
		return program;
	}
	const groups = [ ...( program.groups || [] ) ];
	const lessonOf = {};
	const single = title ? newId() : '';
	if ( single ) {
		groups.push( { id: single, chapter_id: unitId, title } );
	}
	for ( const row of loose ) {
		lessonOf[ row.term_id ] = single || newId();
		if ( ! single ) {
			groups.push( {
				id: lessonOf[ row.term_id ],
				chapter_id: unitId,
				title: names[ row.term_id ],
			} );
		}
	}
	const items = program.items.map( ( item ) =>
		'practice' === item.type &&
		inUnit( item ) &&
		lessonOf[ item.content_id ]
			? { ...item, group_id: lessonOf[ item.content_id ] }
			: item
	);
	for ( const row of loose ) {
		if (
			! items.some(
				( item ) =>
					'practice' === item.type && item.content_id === row.term_id
			)
		) {
			items.push( {
				id: newId(),
				content_id: row.term_id,
				type: 'practice',
				style: 'practice',
				required: false,
				chapter_id: unitId,
				group_id: lessonOf[ row.term_id ],
			} );
		}
	}
	return {
		...program,
		groups,
		items,
		outcomes: program.outcomes.map( ( row ) =>
			lessonOf[ row.term_id ] && inUnit( row )
				? { ...row, group_id: lessonOf[ row.term_id ] }
				: row
		),
	};
}
