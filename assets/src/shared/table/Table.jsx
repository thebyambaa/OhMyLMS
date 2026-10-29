import { CheckboxControl } from '@wordpress/components';
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import {
	LOADING_ROWS,
	cellContent,
	collectKeys,
	isAllSelected,
	nextSort,
	rowClassName,
	selectedRows,
	sortRows,
	toggleKey,
} from './model.mjs';

const checkboxStyle = { borderColor: '#C8D2E9', borderRadius: '4px' };
const wrapperStyle = { position: 'relative' };
const loadingStyle = { minHeight: '540px' };
const skeletonStyle = { position: 'absolute', top: '50px' };
const emptyStyle = { minHeight: '230px' };

/**
 * Selection checkbox with the admin checkbox styling.
 *
 * @param {Object} props CheckboxControl props.
 * @return {Element} The checkbox.
 */
function SelectionCheckbox( props ) {
	return (
		<CheckboxControl
			__nextHasNoMarginBottom
			className="ohmylms-checkbox-control"
			style={ checkboxStyle }
			{ ...props }
		/>
	);
}

/**
 * Loading placeholder: the markup the shared skeleton renders when active
 * with ten lines, drawn here so the table has no dependency on it.
 *
 * @return {Element} The placeholder.
 */
function LoadingPlaceholder() {
	return (
		<div style={ loadingStyle }>
			<div
				className="ohmylms-skeleton ohmylms-skeleton-active"
				style={ skeletonStyle }
			>
				<div className="ohmylms-skeleton-content">
					<div className="ohmylms-skeleton-title" />
					<div className="ohmylms-skeleton-paragraph">
						{ Array.from( { length: LOADING_ROWS } ).map(
							( unused, index ) => (
								<div
									key={ index }
									className="ohmylms-skeleton-line"
								/>
							)
						) }
					</div>
				</div>
			</div>
		</div>
	);
}

/**
 * Admin data table with optional sorting and row selection.
 *
 * Columns are `{ key, title, render?, sorter?, width? }`. A column with a
 * `sorter( a, b )` function sorts the rows when its header is clicked.
 * `rowSelection` is `{ selectedRowKeys?, onChange( keys, rows ) }`; when it is
 * given a checkbox column is shown. Other props such as `pagination`,
 * `scroll`, `style` and `onChange` are accepted for compatibility and ignored.
 *
 * @param {Object}   props                   Component props.
 * @param {Array}    props.columns           Column definitions.
 * @param {Array}    props.dataSource        Data rows.
 * @param {string}   props.rowKey            Field that identifies a row.
 * @param {Object}   [props.rowSelection]    Row selection configuration.
 * @param {boolean}  [props.loading]         Show the loading placeholder.
 * @param {Object}   [props.locale]          `{ emptyText }` shown when there are no rows.
 * @param {string}   [props.className]       Extra class on the wrapper.
 * @param {Function} [props.onMouseEnterOnRow] Called with `( row, index )` on row hover.
 * @param {Function} [props.onMouseLeaveOnRow] Called with `( row, index )` when hover ends.
 * @param {Function} [props.onRow]           Returns extra props for each row element.
 * @return {Element} The table.
 */
export function Table( {
	columns,
	dataSource,
	rowKey,
	rowSelection,
	loading,
	locale,
	className = '',
	onMouseEnterOnRow,
	onMouseLeaveOnRow,
	onRow,
} ) {
	const [ sort, setSort ] = useState( null );
	const [ selectedKeys, setSelectedKeys ] = useState(
		rowSelection?.selectedRowKeys || []
	);
	const [ allSelected, setAllSelected ] = useState( false );
	const rows = sortRows( dataSource, columns, sort );
	const count = dataSource.length;
	const countRef = useRef( count );
	countRef.current = count;

	// Controlled selection: re-sync whenever the caller passes a new object.
	useEffect( () => {
		if ( rowSelection?.selectedRowKeys ) {
			setSelectedKeys( rowSelection.selectedRowKeys );
			setAllSelected(
				isAllSelected( rowSelection.selectedRowKeys, countRef.current )
			);
		}
	}, [ rowSelection ] );

	const toggleAll = ( checked ) => {
		if ( checked ) {
			const keys = collectKeys( dataSource, rowKey );
			setSelectedKeys( keys );
			rowSelection.onChange?.( keys, dataSource );
			setAllSelected( true );
		} else {
			setSelectedKeys( [] );
			setAllSelected( false );
			rowSelection.onChange?.( [], [] );
		}
	};

	const toggleRow = ( key ) => {
		const keys = toggleKey( selectedKeys, key );
		setSelectedKeys( keys );
		setAllSelected( keys.length === count );
		if ( 'function' === typeof rowSelection?.onChange ) {
			rowSelection.onChange(
				keys,
				selectedRows( dataSource, rowKey, keys )
			);
		}
	};

	let body;
	if ( loading ) {
		body = <LoadingPlaceholder />;
	} else if ( rows.length > 0 ) {
		body = rows.map( ( row, index ) => (
			<tr
				key={ String( row[ rowKey ] ) + index }
				className={ rowClassName( index, count ) }
				onMouseEnter={ () => {
					if ( onMouseEnterOnRow ) {
						onMouseEnterOnRow( row, index );
					}
				} }
				onMouseLeave={ () => {
					if ( onMouseLeaveOnRow ) {
						onMouseLeaveOnRow( row, index );
					}
				} }
				{ ...( 'function' === typeof onRow
					? onRow( row, index )
					: {} ) }
			>
				{ rowSelection && (
					<td className="ohmylms-td ohmylms-td-select">
						<SelectionCheckbox
							checked={ selectedKeys.includes( row[ rowKey ] ) }
							onChange={ () => toggleRow( row[ rowKey ] ) }
						/>
					</td>
				) }
				{ columns.map( ( column, columnIndex ) => (
					<td
						key={ column.key + columnIndex }
						className={ `ohmylms-td ohmylms-td-${ column.key }` }
						style={ { width: column.width ? column.width : '' } }
					>
						{ cellContent( column, row ) }
					</td>
				) ) }
			</tr>
		) );
	} else {
		body = (
			<tr>
				<td colSpan={ columns.length + 1 } style={ emptyStyle }>
					{ locale?.emptyText }
				</td>
			</tr>
		);
	}

	return (
		<div
			className={ `ohmylms-table-wrapper ${ className }` }
			style={ wrapperStyle }
		>
			<table className="ohmylms-table">
				<thead className="ohmylms-table-thead">
					<tr className="ohmylms-table-header-row">
						{ rowSelection && (
							<th className="ohmylms-th ohmylms-th-select">
								<SelectionCheckbox
									checked={ allSelected }
									onChange={ toggleAll }
									disabled={ loading || 0 === rows.length }
								/>
							</th>
						) }
						{ columns.map( ( column, index ) => (
							<th
								key={ column.key + index }
								className={ `ohmylms-th ohmylms-th-${ column.key }` }
								onClick={ () =>
									column.sorter &&
									setSort( nextSort( sort, column.key ) )
								}
							>
								{ column.title }
							</th>
						) ) }
					</tr>
				</thead>
				<tbody className="ohmylms-table-tbody">{ body }</tbody>
			</table>
		</div>
	);
}
