/**
 * @typedef {Object} TabGroup
 * @property {string}  id         Group id.
 * @property {string}  name       Visible name.
 * @property {string}  background Background color, `#rrggbb`.
 * @property {string}  text       Text color, `#rrggbb`.
 * @property {boolean} collapsed  Whether the group hides its tabs.
 */

/**
 * @typedef {Object} TabLayout
 * @property {string[]}               order       Tab ids in display order.
 * @property {TabGroup[]}             groups      Tab groups.
 * @property {Record<string, string>} assignments Group id by tab id.
 */

/**
 * One ungrouped tab or one group with its tabs.
 * @typedef {Object} TabBlock
 * @property {string}   id      `tab:<id>` or `group:<id>`.
 * @property {TabGroup} [group] The group, absent for an ungrouped tab.
 * @property {string[]} tabs    Tab ids.
 */

/**
 * @param {string} value    Stored color.
 * @param {string} fallback Default color.
 * @return {string} The color when it is `#rrggbb`, else the fallback.
 */
const color = ( value, fallback ) =>
	/^#[0-9a-f]{6}$/i.test( value || '' ) ? value : fallback;

/**
 * Recover saved layouts safely when tabs are added, removed or a stored value is damaged.
 * @param {unknown}  saved Stored layout, any shape.
 * @param {string[]} ids   Tab ids.
 * @return {TabLayout} Valid layout.
 */
export function normalizeLayout( saved, ids ) {
	const source =
		/** @type {{ order?: unknown, groups?: unknown, assignments?: Record<string, unknown> }} */ (
			saved && typeof saved === 'object' ? saved : {}
		);
	const order = [
		...new Set( [
			...( Array.isArray( source.order ) ? source.order : [] ),
			...ids,
		] ),
	].filter( ( id ) => ids.includes( id ) );
	/** @type {TabGroup[]} */
	const groups = [];
	for ( const group of Array.isArray( source.groups ) ? source.groups : [] ) {
		if (
			! group ||
			typeof group.id !== 'string' ||
			! /^[a-z0-9_-]{1,80}$/i.test( group.id ) ||
			groups.some( ( item ) => item.id === group.id )
		) {
			continue;
		}
		groups.push( {
			id: group.id,
			name: ( typeof group.name === 'string'
				? group.name
				: 'Group'
			).slice( 0, 80 ),
			background: color( group.background, '#ede7f6' ),
			text: color( group.text, '#45278b' ),
			collapsed: Boolean( group.collapsed ),
		} );
	}
	/** @type {Record<string, string>} */
	const assignments = {};
	for ( const id of order ) {
		const group = source.assignments?.[ id ];
		if ( groups.some( ( item ) => item.id === group ) ) {
			assignments[ id ] = /** @type {string} */ ( group );
		}
	}
	// A group is one contiguous block, just like browser tab groups.
	/** @type {string[]} */
	const contiguous = [];
	const seen = new Set();
	for ( const id of order ) {
		const group = assignments[ id ];
		if ( ! group ) {
			contiguous.push( id );
		} else if ( ! seen.has( group ) ) {
			seen.add( group );
			contiguous.push(
				...order.filter( ( tab ) => assignments[ tab ] === group )
			);
		}
	}
	return { order: contiguous, groups, assignments };
}

/**
 * @param {TabLayout} layout Layout.
 * @return {TabBlock[]} Ungrouped tabs and groups in display order.
 */
export function layoutBlocks( layout ) {
	/** @type {TabBlock[]} */
	const blocks = [];
	const seen = new Set();
	for ( const id of layout.order ) {
		const group = layout.groups.find(
			( item ) => item.id === layout.assignments[ id ]
		);
		if ( ! group ) {
			blocks.push( { id: `tab:${ id }`, tabs: [ id ] } );
		} else if ( ! seen.has( group.id ) ) {
			seen.add( group.id );
			blocks.push( {
				id: `group:${ group.id }`,
				group,
				tabs: layout.order.filter(
					( tab ) => layout.assignments[ tab ] === group.id
				),
			} );
		}
	}
	return blocks;
}

/**
 * Drop a tab beside another tab, onto a group label, or outside all groups.
 * @param {TabLayout}   layout  Layout.
 * @param {string}      id      Tab id.
 * @param {string|null} target  Tab id, `group:<id>`, or null for outside all groups.
 * @param {boolean}     [after] Drop after the target tab.
 * @return {TabLayout} New layout.
 */
export function moveTab( layout, id, target, after = false ) {
	if ( ! layout.order.includes( id ) || id === target ) {
		return layout;
	}
	const assignments = { ...layout.assignments };
	const groupTarget = target?.startsWith( 'group:' )
		? target.slice( 6 )
		: null;
	const group = groupTarget || layout.assignments[ String( target ) ];
	if ( group && layout.groups.some( ( item ) => item.id === group ) ) {
		assignments[ id ] = group;
	} else {
		delete assignments[ id ];
	}
	const order = layout.order.filter( ( item ) => item !== id );
	let index = order.length;
	if ( groupTarget ) {
		const members = order.filter(
			( tab ) => assignments[ tab ] === groupTarget
		);
		if ( members.length ) {
			index =
				order.indexOf( /** @type {string} */ ( members.at( -1 ) ) ) + 1;
		}
	} else if ( target !== null && order.includes( target ) ) {
		index = order.indexOf( target ) + ( after ? 1 : 0 );
	}
	order.splice( index, 0, id );
	return normalizeLayout( { ...layout, order, assignments }, layout.order );
}

/**
 * Move a whole group or ungrouped tab, preserving all tabs inside the group.
 * @param {TabLayout}   layout  Layout.
 * @param {string}      source  Block id to move.
 * @param {string|null} target  Block id to drop on, or null for the end.
 * @param {boolean}     [after] Drop after the target block.
 * @return {TabLayout} New layout.
 */
export function moveBlock( layout, source, target, after = false ) {
	const blocks = layoutBlocks( layout );
	const block = blocks.find( ( item ) => item.id === source );
	if ( ! block || source === target ) {
		return layout;
	}
	const remaining = blocks.filter( ( item ) => item.id !== source );
	const index = remaining.findIndex( ( item ) => item.id === target );
	remaining.splice(
		index < 0 ? remaining.length : index + ( after ? 1 : 0 ),
		0,
		block
	);
	return { ...layout, order: remaining.flatMap( ( item ) => item.tabs ) };
}

/**
 * @param {TabLayout} layout Layout.
 * @param {string}    id     Tab id.
 * @param {string}    group  Group id, '' for no group.
 * @return {TabLayout} New layout.
 */
export function assignTab( layout, id, group ) {
	const assignments = { ...layout.assignments };
	delete assignments[ id ];
	const ungrouped = normalizeLayout(
		{ ...layout, assignments },
		layout.order
	);
	return group ? moveTab( ungrouped, id, `group:${ group }` ) : ungrouped;
}
