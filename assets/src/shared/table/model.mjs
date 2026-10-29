/**
 * Pure state and layout helpers for the admin table.
 *
 * Behaviour mirrors the recovered `ohmylms-table` component: sorting is
 * client-side and driven by a column `sorter( a, b )` function, and row
 * selection is tracked by the value of each row's `rowKey` field.
 */

/** Number of placeholder lines shown while the table is loading. */
export const LOADING_ROWS = 10;

/**
 * Next sort state after a header click: ascending, then descending, then off.
 *
 * @param {?{key: string, order: string}} current Current sort state.
 * @param {string}                        key     Column key that was clicked.
 * @return {?{key: string, order: string}} The next sort state, or null to clear.
 */
export function nextSort( current, key ) {
	if ( current && key === current.key ) {
		return 'asc' === current.order ? { key, order: 'desc' } : null;
	}
	return { key, order: 'asc' };
}

/**
 * Copy of the rows ordered by the active sort. Rows keep their order when
 * there is no sort state or the sorted column has no sorter function.
 *
 * @param {Array}                         rows    Data rows.
 * @param {Array}                         columns Column definitions.
 * @param {?{key: string, order: string}} sort    Active sort state.
 * @return {Array} A new, possibly reordered, array.
 */
export function sortRows( rows, columns, sort ) {
	const sorted = Array.from( rows );
	if ( ! sort ) {
		return sorted;
	}
	const column = columns.find(
		( candidate ) => candidate.key === sort.key && candidate.sorter
	);
	if ( column && 'function' === typeof column.sorter ) {
		sorted.sort( ( a, b ) =>
			'asc' === sort.order
				? column.sorter( a, b )
				: -column.sorter( a, b )
		);
	}
	return sorted;
}

/**
 * Values of the row key field for every row.
 *
 * @param {Array}  rows   Data rows.
 * @param {string} rowKey Field that identifies a row.
 * @return {Array} Row keys in row order.
 */
export function collectKeys( rows, rowKey ) {
	return rows.map( ( row ) => row[ rowKey ] );
}

/**
 * Add the key when absent, remove it when present.
 *
 * @param {Array}   selectedKeys Currently selected keys.
 * @param {unknown} key          Key to toggle.
 * @return {Array} A new array of selected keys.
 */
export function toggleKey( selectedKeys, key ) {
	return selectedKeys.includes( key )
		? selectedKeys.filter( ( candidate ) => candidate !== key )
		: [ ...selectedKeys, key ];
}

/**
 * Rows whose key is selected, in data order.
 *
 * @param {Array}  rows         Data rows.
 * @param {string} rowKey       Field that identifies a row.
 * @param {Array}  selectedKeys Selected keys.
 * @return {Array} Selected rows.
 */
export function selectedRows( rows, rowKey, selectedKeys ) {
	return rows.filter( ( row ) => selectedKeys.includes( row[ rowKey ] ) );
}

/**
 * Whether the header checkbox is ticked: every row is selected, and there is
 * at least one selected key.
 *
 * @param {Array}  selectedKeys Selected keys.
 * @param {number} rowCount     Number of data rows.
 * @return {boolean} True when all rows are selected.
 */
export function isAllSelected( selectedKeys, rowCount ) {
	return 0 !== selectedKeys.length && selectedKeys.length === rowCount;
}

/**
 * Class name of a body row. The last row also gets `ohmylms-tr-last`; the
 * separator space is always emitted, matching the recovered markup.
 *
 * @param {number} index Zero-based row index.
 * @param {number} count Number of rows.
 * @return {string} Class attribute value.
 */
export function rowClassName( index, count ) {
	return `ohmylms-tr ohmylms-tr-${ index } ${
		count === index + 1 ? 'ohmylms-tr-last' : ''
	}`;
}

/**
 * Value rendered inside a body cell.
 *
 * @param {Object} column Column definition.
 * @param {Object} row    Data row.
 * @return {unknown} Rendered content.
 */
export function cellContent( column, row ) {
	return column.render
		? column.render( row[ column.key ], row )
		: row[ column.key ];
}
