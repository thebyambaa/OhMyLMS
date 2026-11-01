/** @typedef {import('./editorTypes').Data} Data */

/**
 * Keep grid identities and answer keys stable while labels and ordering change.
 *
 * @param {Data}   settings Grid settings.
 * @param {string} list     Rows or columns.
 * @param {string} id       Item ID.
 * @param {string} label    New label.
 * @return {Data} Settings patch.
 */
export function renameGridItem( settings, list, id, label ) {
	return {
		[ list ]: settings[ list ].map( ( /** @type {GridItem} */ item ) =>
			item.id === id ? { ...item, label } : item
		),
	};
}

/** @typedef {{ id: string, label: string }} GridItem */

/**
 * @param {Data}   settings Grid settings.
 * @param {string} list     Rows or columns.
 * @return {Data} Settings patch.
 */
export function addGridItem( settings, list ) {
	/** @type {GridItem[]} */
	const items = settings[ list ] || [];
	const prefix = list === 'rows' ? 'r' : 'c';
	let number = 1;
	while ( items.some( ( item ) => item.id === prefix + number ) ) {
		number++;
	}
	return { [ list ]: [ ...items, { id: prefix + number, label: '' } ] };
}

/**
 * @param {Data}   settings Grid settings.
 * @param {string} list     Rows or columns.
 * @param {string} id       Item ID.
 * @return {Data} Settings patch.
 */
export function removeGridItem( settings, list, id ) {
	/** @type {Record<string, unknown>} */
	const key = {};
	for ( const [ row, answer ] of Object.entries( settings.key || {} ) ) {
		if ( list === 'rows' && row === id ) {
			continue;
		}
		if ( list === 'columns' ) {
			if ( Array.isArray( answer ) ) {
				const remaining = answer.filter( ( column ) => column !== id );
				if ( remaining.length ) {
					key[ row ] = remaining;
				}
			} else if ( answer !== id ) {
				key[ row ] = answer;
			}
		} else {
			key[ row ] = answer;
		}
	}
	return {
		[ list ]: settings[ list ].filter(
			( /** @type {GridItem} */ item ) => item.id !== id
		),
		key,
	};
}

/**
 * @param {Data}   settings Grid settings.
 * @param {string} list     Rows or columns.
 * @param {string} id       Item ID.
 * @param {number} target   New position.
 * @return {Data} Settings patch.
 */
export function moveGridItem( settings, list, id, target ) {
	/** @type {GridItem[]} */
	const items = [ ...settings[ list ] ];
	const index = items.findIndex( ( item ) => item.id === id );
	if ( index < 0 || target < 0 || target >= items.length ) {
		return {};
	}
	const [ item ] = items.splice( index, 1 );
	items.splice( target, 0, item );
	return { [ list ]: items };
}

/**
 * @param {Data}    settings Grid settings.
 * @param {boolean} multiple Allow several columns per row.
 * @return {Data} Settings patch.
 */
export function gridSelectionMode( settings, multiple ) {
	/** @type {Record<string, unknown>} */
	const key = {};
	for ( const [ id, answer ] of Object.entries( settings.key || {} ) ) {
		const answers = Array.isArray( answer ) ? answer : [ answer ];
		key[ id ] = multiple ? answers : answers[ 0 ];
	}
	return { multiple, key };
}
