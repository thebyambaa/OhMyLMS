/**
 * Keep grid identities and answer keys stable while labels and ordering change.
 *
 * @param {Object} settings Grid settings.
 * @param {string} list     Rows or columns.
 * @param {string} id       Item ID.
 * @param {string} label    New label.
 * @return {Object} Settings patch.
 */
export function renameGridItem( settings, list, id, label ) {
	return {
		[ list ]: settings[ list ].map( ( item ) =>
			item.id === id ? { ...item, label } : item
		),
	};
}

export function addGridItem( settings, list ) {
	const items = settings[ list ] || [];
	const prefix = list === 'rows' ? 'r' : 'c';
	let number = 1;
	while ( items.some( ( item ) => item.id === prefix + number ) ) {
		number++;
	}
	return { [ list ]: [ ...items, { id: prefix + number, label: '' } ] };
}

export function removeGridItem( settings, list, id ) {
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
		[ list ]: settings[ list ].filter( ( item ) => item.id !== id ),
		key,
	};
}

export function moveGridItem( settings, list, id, target ) {
	const items = [ ...settings[ list ] ];
	const index = items.findIndex( ( item ) => item.id === id );
	if ( index < 0 || target < 0 || target >= items.length ) {
		return {};
	}
	const [ item ] = items.splice( index, 1 );
	items.splice( target, 0, item );
	return { [ list ]: items };
}

export function gridSelectionMode( settings, multiple ) {
	const key = {};
	for ( const [ id, answer ] of Object.entries( settings.key || {} ) ) {
		const answers = Array.isArray( answer ) ? answer : [ answer ];
		key[ id ] = multiple ? answers : answers[ 0 ];
	}
	return { multiple, key };
}
