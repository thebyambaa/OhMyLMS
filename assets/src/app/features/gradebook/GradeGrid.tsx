import { useEffect, useRef, useState } from '@wordpress/element';
import type { KeyboardEvent, MouseEvent, ReactNode } from 'react';
import { moveFocus, type GridPosition } from './model';

export interface GridColumn {
	key: string;
	/** Header content. */
	header: ReactNode;
	/** Plain-text name of the column. */
	label: string;
	className?: string;
}

export interface GridCell {
	content: ReactNode;
	/** Accessible name of the cell when its text is not enough (state, computed value). */
	label?: string;
	className?: string;
	/** Enter, Space or a click opens it. */
	activatable?: boolean;
}

export interface GridRow {
	key: string;
	/** Row header (first column). */
	header: ReactNode;
	cells: GridCell[];
	className?: string;
}

/**
 * An accessible data grid (WAI-ARIA grid pattern) in WordPress `widefat` markup: one cell is in the tab
 * order at a time; arrows, Home/End, Ctrl+Home/End and Page Up/Down move between cells; Enter, Space or
 * F2 open an activatable cell (or follow the link or button inside a cell); a click does the same.
 *
 * Row 0 is the header row, column 0 the row headers; `onActivate` receives body row and data column
 * indexes (both from 0).
 *
 * @param props            Props.
 * @param props.label      Accessible name of the grid.
 * @param props.corner     Header of the row-header column.
 * @param props.columns    Data columns.
 * @param props.rows       Body rows.
 * @param props.footer     Optional summary row (navigable like the others).
 * @param props.rowCount   Total rows when the grid shows one page (aria-rowcount).
 * @param props.rowOffset  Rows before this page (aria-rowindex).
 * @param props.onActivate Called for an activatable data cell.
 * @param props.className  Extra table classes.
 */
export function GradeGrid( {
	label,
	corner,
	columns,
	rows,
	footer,
	rowCount,
	rowOffset = 0,
	onActivate,
	className = '',
}: {
	label: string;
	corner: ReactNode;
	columns: readonly GridColumn[];
	rows: readonly GridRow[];
	footer?: GridRow;
	rowCount?: number;
	rowOffset?: number;
	onActivate?: ( row: number, column: number ) => void;
	className?: string;
} ) {
	const table = useRef< HTMLTableElement >( null );
	const [ focus, setFocus ] = useState< GridPosition >( { row: 0, col: 0 } );
	const all = footer ? [ ...rows, footer ] : rows;
	const size = { rows: all.length + 1, cols: columns.length + 1 };
	const current = {
		row: Math.min( focus.row, size.rows - 1 ),
		col: Math.min( focus.col, size.cols - 1 ),
	};
	const moved = useRef( false );

	useEffect( () => {
		if ( ! moved.current ) {
			return;
		}
		moved.current = false;
		table.current
			?.querySelector< HTMLElement >(
				`[data-row="${ current.row }"][data-col="${ current.col }"]`
			)
			?.focus();
	}, [ current.row, current.col ] );

	function cellAt( row: number, col: number ): GridCell | null {
		if ( 0 === row || 0 === col ) {
			return null;
		}
		return all[ row - 1 ]?.cells[ col - 1 ] ?? null;
	}

	function activate( row: number, col: number, element: HTMLElement ) {
		const inner = element.querySelector< HTMLElement >( 'a[href], button' );
		if ( inner ) {
			inner.click();
			return;
		}
		const cell = cellAt( row, col );
		if ( cell?.activatable && row <= rows.length && onActivate ) {
			onActivate( row - 1, col - 1 );
		}
	}

	function onKeyDown( event: KeyboardEvent< HTMLTableElement > ) {
		const target = ( event.target as HTMLElement ).closest< HTMLElement >(
			'[data-row]'
		);
		if ( ! target ) {
			return;
		}
		const position = {
			row: Number( target.dataset.row ),
			col: Number( target.dataset.col ),
		};
		if ( [ 'Enter', ' ', 'F2' ].includes( event.key ) ) {
			if ( target === event.target ) {
				event.preventDefault();
				activate( position.row, position.col, target );
			}
			return;
		}
		const next = moveFocus( position, event, size );
		if ( next ) {
			event.preventDefault();
			moved.current = true;
			setFocus( next );
			if ( next.row === current.row && next.col === current.col ) {
				target.focus();
			}
		}
	}

	function onClick( event: MouseEvent< HTMLTableElement > ) {
		const target = ( event.target as HTMLElement ).closest< HTMLElement >(
			'[data-row]'
		);
		if ( ! target ) {
			return;
		}
		const position = {
			row: Number( target.dataset.row ),
			col: Number( target.dataset.col ),
		};
		setFocus( position );
		if ( ( event.target as HTMLElement ).closest( 'a, button' ) ) {
			return;
		}
		const cell = cellAt( position.row, position.col );
		if ( cell?.activatable && position.row <= rows.length && onActivate ) {
			onActivate( position.row - 1, position.col - 1 );
		}
	}

	const tab = ( row: number, col: number ) =>
		row === current.row && col === current.col ? 0 : -1;

	const renderRow = ( row: GridRow, index: number, isFooter: boolean ) => {
		const rowIndex = index + 1;
		return (
			<tr
				key={ row.key }
				className={ row.className }
				aria-rowindex={
					isFooter
						? ( rowCount ?? rows.length ) + 2
						: rowOffset + rowIndex + 1
				}
			>
				<th
					scope="row"
					role="rowheader"
					className="ohmylms-gradebook-grid__rowheader"
					data-row={ rowIndex }
					data-col={ 0 }
					tabIndex={ tab( rowIndex, 0 ) }
				>
					{ row.header }
				</th>
				{ row.cells.map( ( cell, col ) => (
					<td
						key={ columns[ col ]?.key ?? col }
						role="gridcell"
						className={ [
							columns[ col ]?.className,
							cell.className,
							cell.activatable && 'is-activatable',
						]
							.filter( Boolean )
							.join( ' ' ) }
						data-row={ rowIndex }
						data-col={ col + 1 }
						tabIndex={ tab( rowIndex, col + 1 ) }
						aria-label={ cell.label }
					>
						{ cell.content }
					</td>
				) ) }
			</tr>
		);
	};

	return (
		<table
			ref={ table }
			role="grid"
			aria-label={ label }
			aria-rowcount={
				( rowCount ?? rows.length ) + 1 + ( footer ? 1 : 0 )
			}
			aria-colcount={ size.cols }
			className={ `widefat ohmylms-gradebook-grid ${ className }`.trim() }
			onKeyDown={ onKeyDown }
			onClick={ onClick }
		>
			<thead>
				<tr aria-rowindex={ 1 }>
					<th
						scope="col"
						role="columnheader"
						className="ohmylms-gradebook-grid__corner"
						data-row={ 0 }
						data-col={ 0 }
						tabIndex={ tab( 0, 0 ) }
					>
						{ corner }
					</th>
					{ columns.map( ( column, col ) => (
						<th
							key={ column.key }
							scope="col"
							role="columnheader"
							className={ column.className }
							data-row={ 0 }
							data-col={ col + 1 }
							tabIndex={ tab( 0, col + 1 ) }
						>
							{ column.header }
						</th>
					) ) }
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( row, index ) => renderRow( row, index, false ) ) }
			</tbody>
			{ footer && (
				<tfoot>{ renderRow( footer, rows.length, true ) }</tfoot>
			) }
		</table>
	);
}
