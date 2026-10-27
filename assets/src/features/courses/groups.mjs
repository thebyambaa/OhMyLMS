/**
 * Pure helpers for the lesson groups of a course program. Groups live in `program.groups`
 * ([{ id, chapter_id, title }], array order is the order inside a unit) and an item joins one
 * through `group_id`. Every helper returns a new program; unknown program fields are kept.
 */
export const MAX_GROUP_TITLE = 120;

/**
 * Single-line, length-limited title, matching the server rule.
 * @param {unknown} value Raw title.
 * @return {string} Clean title.
 */
export const cleanGroupTitle = ( value ) =>
	String( value ?? '' )
		.replace( /\s+/g, ' ' )
		.trim()
		.slice( 0, MAX_GROUP_TITLE );

/**
 * Groups of one unit in display order.
 * @param {Object} program   Program draft.
 * @param {number} chapterId Unit (chapter) ID.
 * @return {Object[]} Groups.
 */
export const groupsOf = ( program, chapterId ) =>
	( program.groups || [] ).filter(
		( group ) => Number( group.chapter_id ) === Number( chapterId )
	);

const withoutGroups = ( program, groups ) => {
	const { groups: _old, ...rest } = program;
	return groups.length ? { ...rest, groups } : rest;
};

/**
 * Add a group to the end of a unit's groups.
 * @param {Object} program   Program draft.
 * @param {number} chapterId Unit ID.
 * @param {string} title     Group title.
 * @param {string} id        New UUID.
 * @return {Object} Program.
 */
export function addGroup( program, chapterId, title, id ) {
	const clean = cleanGroupTitle( title );
	if ( ! clean || ! chapterId ) {
		return program;
	}
	return {
		...program,
		groups: [
			...( program.groups || [] ),
			{ id, chapter_id: Number( chapterId ), title: clean },
		],
	};
}

/**
 * Rename a group; an empty title is ignored because the server rejects it.
 * @param {Object} program Program draft.
 * @param {string} id      Group ID.
 * @param {string} title   New title (kept as typed, trimmed on save).
 * @return {Object} Program.
 */
export const renameGroup = ( program, id, title ) => ( {
	...program,
	groups: ( program.groups || [] ).map( ( group ) =>
		group.id === id ? { ...group, title: String( title ?? '' ) } : group
	),
} );

/**
 * Delete a group; its items stay in the unit, ungrouped.
 * @param {Object} program Program draft.
 * @param {string} id      Group ID.
 * @return {Object} Program.
 */
export function deleteGroup( program, id ) {
	const groups = ( program.groups || [] ).filter(
		( group ) => group.id !== id
	);
	const release = ( row ) => {
		if ( row.group_id !== id ) {
			return row;
		}
		const { group_id: _removed, ...rest } = row;
		return rest;
	};
	return {
		...withoutGroups( program, groups ),
		items: ( program.items || [] ).map( release ),
		outcomes: ( program.outcomes || [] ).map( release ),
	};
}

/**
 * Move a group one place up or down among the groups of its own unit.
 * @param {Object} program   Program draft.
 * @param {string} id        Group ID.
 * @param {number} direction -1 (up) or 1 (down).
 * @return {Object} Program.
 */
export function moveGroup( program, id, direction ) {
	const all = [ ...( program.groups || [] ) ];
	const index = all.findIndex( ( group ) => group.id === id );
	if ( index < 0 ) {
		return program;
	}
	let other = index + direction;
	while (
		other >= 0 &&
		other < all.length &&
		all[ other ].chapter_id !== all[ index ].chapter_id
	) {
		other += direction;
	}
	if ( other < 0 || other >= all.length ) {
		return program;
	}
	[ all[ index ], all[ other ] ] = [ all[ other ], all[ index ] ];
	return { ...program, groups: all };
}

/**
 * Put an item in a group of its own unit, or take it out (empty group ID).
 * @param {Object} program Program draft.
 * @param {string} itemId  Item placement ID.
 * @param {string} groupId Group ID, or '' for none.
 * @return {Object} Program.
 */
export function assignItem( program, itemId, groupId ) {
	return {
		...program,
		items: program.items.map( ( item ) => {
			if ( item.id !== itemId ) {
				return item;
			}
			const { group_id: _removed, ...rest } = item;
			const group = ( program.groups || [] ).find(
				( entry ) => entry.id === groupId
			);
			return group &&
				Number( group.chapter_id ) === Number( item.chapter_id )
				? { ...rest, group_id: groupId }
				: rest;
		} ),
	};
}

/**
 * Move an item to another unit. A group only holds items of its own unit, so the item leaves it.
 * @param {Object} program   Program draft.
 * @param {string} itemId    Item placement ID.
 * @param {number} chapterId Target unit, 0 for the course.
 * @return {Object} Program.
 */
export const moveItemToUnit = ( program, itemId, chapterId ) => ( {
	...program,
	items: program.items.map( ( item ) => {
		if ( item.id !== itemId ) {
			return item;
		}
		const { group_id: _removed, ...rest } = item;
		return { ...rest, chapter_id: Number( chapterId ) };
	} ),
} );

/**
 * Items of a unit's group (or the unit's ungrouped items when groupId is '').
 * @param {Object} program   Program draft.
 * @param {number} chapterId Unit ID.
 * @param {string} groupId   Group ID, or ''.
 * @return {Object[]} Items in program order.
 */
export const itemsIn = ( program, chapterId, groupId = '' ) =>
	( program.items || [] ).filter(
		( item ) =>
			Number( item.chapter_id ) === Number( chapterId ) &&
			( item.group_id || '' ) === groupId
	);

/**
 * Clean groups for saving: trimmed titles, empty-titled groups reported so the editor can warn.
 * @param {Object} program Program draft.
 * @return {{program: Object, invalid: string[]}} Program with trimmed titles and the IDs of unnamed groups.
 */
export function prepareGroups( program ) {
	const groups = ( program.groups || [] ).map( ( group ) => ( {
		...group,
		title: cleanGroupTitle( group.title ),
	} ) );
	return {
		program: withoutGroups( program, groups ),
		invalid: groups
			.filter( ( group ) => ! group.title )
			.map( ( group ) => group.id ),
	};
}
