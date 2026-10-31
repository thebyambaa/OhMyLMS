import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { KeyboardEvent } from 'react';
import { WidefatTable } from '../../components/WidefatTable';
import { ApiError } from '../../lib/api';
import { useOperation } from '../../hooks/useOperation';
import { command, importRows, readSyllabus } from './api';
import type { ImportReport, SyllabusOutline } from './model';
import {
	cellProblem,
	cellRef,
	cellValue,
	duplicateCodes,
	MAX_SHEET_CHANGES,
	moveCell,
	pastedRows,
	planChanges,
	refKey,
	SHEET_COLUMNS,
	sheetRows,
	type SheetColumn,
	type SheetEdits,
} from './sheet';
import type { CsvRow } from '../../../features/curriculum/csv.mjs';
import './views.css';

function columnLabel( column: SheetColumn ) {
	return {
		path: __( 'Folder', 'ohmylms' ),
		unit: __( 'Unit', 'ohmylms' ),
		unitCode: __( 'Unit code', 'ohmylms' ),
		group: __( 'Skill group', 'ohmylms' ),
		groupCode: __( 'Group code', 'ohmylms' ),
		skillCode: __( 'Skill code', 'ohmylms' ),
		skill: __( 'Skill', 'ohmylms' ),
	}[ column ];
}

interface Conflict {
	kind: string;
	id: number;
	field: string;
	current: string;
}
function conflictsOf( failure: unknown ): Conflict[] {
	if (
		! ( failure instanceof ApiError ) ||
		'ohmylms_syllabus_sheet_stale' !== failure.code
	) {
		return [];
	}
	const details = failure.details as { conflicts?: Conflict[] } | undefined;
	return Array.isArray( details?.conflicts ) ? details.conflicts : [];
}

/**
 * Tabular editing of a syllabus's unit, group and skill names and codes. Arrow keys move between
 * cells; Enter or F2 edits, Enter saves the cell, Escape cancels. Edits are staged and saved as one
 * batch that the server refuses (409) when any edited cell changed since it was loaded. Rows pasted
 * from a spreadsheet are reviewed by the syllabus import before they are added.
 *
 * @param props         Component properties.
 * @param props.outline Saved outline.
 * @param props.onSaved Receives the refreshed outline.
 */
export function SpreadsheetView( {
	outline,
	onSaved,
}: {
	outline: SyllabusOutline;
	onSaved: ( next: SyllabusOutline ) => void;
} ) {
	const [ base, setBase ] = useState( outline );
	const rows = useMemo( () => sheetRows( base ), [ base ] );
	const [ edits, setEdits ] = useState< SheetEdits >( {} );
	const [ active, setActive ] = useState( { row: 0, column: 1 } );
	const [ editing, setEditing ] = useState< string | null >( null );
	const [ conflicts, setConflicts ] = useState< Conflict[] >( [] );
	const [ message, setMessage ] = useState( '' );
	const operation = useOperation();
	const grid = useRef< HTMLDivElement | null >( null );
	const cancelled = useRef( false );
	const changes = useMemo(
		() => planChanges( rows, edits ),
		[ rows, edits ]
	);
	const duplicates = useMemo(
		() => duplicateCodes( rows, edits ),
		[ rows, edits ]
	);
	const invalid = changes.filter( ( change ) =>
		cellProblem( change, change.value )
	);
	const conflictKeys = new Set(
		conflicts.map(
			( entry ) => `${ entry.kind }:${ entry.id }:${ entry.field }`
		)
	);

	function focusCell( row: number, column: number ) {
		setActive( { row, column } );
		grid.current
			?.querySelector< HTMLElement >(
				`[data-cell="${ row }-${ column }"]`
			)
			?.focus();
	}
	function commit( value: string ) {
		const row = rows[ active.row ];
		const column = SHEET_COLUMNS[ active.column ];
		const ref = row && column ? cellRef( row, column ) : null;
		if ( ref ) {
			setEdits( ( current ) => ( {
				...current,
				[ refKey( ref ) ]: value,
			} ) );
		}
		setEditing( null );
	}
	function onCellKey( event: KeyboardEvent< HTMLElement > ) {
		if ( null !== editing ) {
			return;
		}
		const row = rows[ active.row ];
		const column = SHEET_COLUMNS[ active.column ];
		if (
			( 'Enter' === event.key || 'F2' === event.key ) &&
			row &&
			column
		) {
			if ( cellRef( row, column ) ) {
				event.preventDefault();
				setEditing( cellValue( row, column, edits ) );
			}
			return;
		}
		const next = moveCell( active, event.key, {
			rows: rows.length,
			columns: SHEET_COLUMNS.length,
		} );
		if ( next ) {
			event.preventDefault();
			focusCell( next.row, next.column );
		}
	}
	function onEditorKey( event: KeyboardEvent< HTMLInputElement > ) {
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			cancelled.current = true;
			setEditing( null );
			focusCell( active.row, active.column );
		} else if ( 'Enter' === event.key ) {
			event.preventDefault();
			commit( event.currentTarget.value );
			focusCell(
				Math.min( rows.length - 1, active.row + 1 ),
				active.column
			);
		}
	}
	function save() {
		setMessage( '' );
		void operation.run(
			async ( signal ) => {
				try {
					return await command(
						base.syllabus.id,
						'sheet_edit',
						{ changes },
						signal
					);
				} catch ( failure ) {
					setConflicts( conflictsOf( failure ) );
					throw failure;
				}
			},
			( saved ) => {
				setBase( saved );
				setEdits( {} );
				setConflicts( [] );
				setMessage(
					sprintf(
						/* translators: %d: number of cells. */
						__( '%d cells saved.', 'ohmylms' ),
						changes.length
					)
				);
				onSaved( saved );
			}
		);
	}
	async function reloadKeeping() {
		const fresh = await readSyllabus( base.syllabus.id ).catch(
			() => null
		);
		if ( ! fresh ) {
			return;
		}
		setBase( fresh );
		setConflicts( [] );
		operation.clearError();
		setMessage(
			__(
				'Reloaded the latest syllabus. Your edits were kept; review the cells that changed and save again.',
				'ohmylms'
			)
		);
		onSaved( fresh );
	}
	const blocked =
		! changes.length ||
		changes.length > MAX_SHEET_CHANGES ||
		invalid.length > 0 ||
		duplicates.size > 0 ||
		conflicts.length > 0 ||
		operation.busy;
	return (
		<section
			className="ohmylms-sheet"
			aria-label={ __( 'Spreadsheet', 'ohmylms' ) }
		>
			<p id="ohmylms-sheet-help">
				{ __(
					'Arrow keys move between cells. Press Enter or F2 to edit a cell, Enter to keep the change and Escape to cancel. Units, groups and skills keep their identity: renaming one renames it on every row and wherever the skill is used.',
					'ohmylms'
				) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ conflicts.length > 0 ? (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: number of cells. */
						__(
							'%d cells changed elsewhere after you opened the spreadsheet. Nothing was saved.',
							'ohmylms'
						),
						conflicts.length
					) }{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => void reloadKeeping() }
					>
						{ __( 'Reload syllabus and keep my edits', 'ohmylms' ) }
					</Button>
				</Notice>
			) : (
				operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				)
			) }
			{ duplicates.size > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Two skills would share a code. Give each skill its own code.',
						'ohmylms'
					) }
				</Notice>
			) }
			<div className="ohmylms-sheet__scroll" ref={ grid }>
				<WidefatTable
					caption={ sprintf(
						/* translators: %s: syllabus. */
						__( 'Spreadsheet of %s', 'ohmylms' ),
						base.syllabus.name
					) }
					role="grid"
					aria-rowcount={ rows.length + 1 }
					aria-describedby="ohmylms-sheet-help"
				>
					<thead>
						<tr>
							{ SHEET_COLUMNS.map( ( column ) => (
								<th key={ column } scope="col">
									{ columnLabel( column ) }
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row, rowIndex ) => (
							<tr key={ row.key } aria-rowindex={ rowIndex + 2 }>
								{ SHEET_COLUMNS.map(
									( column, columnIndex ) => {
										const ref = cellRef( row, column );
										const value = cellValue(
											row,
											column,
											edits
										);
										const key = ref ? refKey( ref ) : '';
										const changed =
											!! ref &&
											undefined !== edits[ key ] &&
											edits[ key ].trim() !==
												row[ column ].trim();
										const problem = ref
											? ( cellProblem( ref, value ) ??
												( 'skillCode' === column &&
												null !== row.termId &&
												duplicates.has( row.termId )
													? __(
															'This code is used twice.',
															'ohmylms'
														)
													: null ) )
											: null;
										const isActive =
											active.row === rowIndex &&
											active.column === columnIndex;
										const classes = [
											changed && 'is-changed',
											problem && 'is-invalid',
											conflictKeys.has( key ) &&
												'is-conflict',
											! ref && 'is-readonly',
										]
											.filter( Boolean )
											.join( ' ' );
										return (
											<td
												key={ column }
												role="gridcell"
												data-cell={ `${ rowIndex }-${ columnIndex }` }
												tabIndex={ isActive ? 0 : -1 }
												aria-readonly={ ! ref }
												aria-invalid={ !! problem }
												className={
													classes || undefined
												}
												onFocus={ () =>
													setActive( {
														row: rowIndex,
														column: columnIndex,
													} )
												}
												onKeyDown={ onCellKey }
												onDoubleClick={ () => {
													if ( ref ) {
														setActive( {
															row: rowIndex,
															column: columnIndex,
														} );
														setEditing( value );
													}
												} }
											>
												{ isActive &&
												null !== editing ? (
													<input
														// The editor replaces the focused cell and must take focus.
														// eslint-disable-next-line jsx-a11y/no-autofocus
														autoFocus
														type="text"
														aria-label={ sprintf(
															/* translators: 1: column, 2: row number. */
															__(
																'%1$s, row %2$d',
																'ohmylms'
															),
															columnLabel(
																column
															),
															rowIndex + 1
														) }
														defaultValue={ editing }
														onKeyDown={
															onEditorKey
														}
														onBlur={ ( event ) => {
															if (
																cancelled.current
															) {
																cancelled.current = false;
																return;
															}
															commit(
																event
																	.currentTarget
																	.value
															);
														} }
													/>
												) : (
													value || ( ref ? '' : '—' )
												) }
												{ problem && (
													<span className="screen-reader-text">
														{ problem }
													</span>
												) }
											</td>
										);
									}
								) }
							</tr>
						) ) }
					</tbody>
				</WidefatTable>
			</div>
			{ invalid.length > 0 && (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'Fix the highlighted cells before saving: names cannot be empty and codes have length limits.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ changes.length > MAX_SHEET_CHANGES && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: most cells. */
						__( 'Save at most %d cells at a time.', 'ohmylms' ),
						MAX_SHEET_CHANGES
					) }
				</Notice>
			) }
			<p>
				<Button
					variant="primary"
					isBusy={ operation.busy }
					disabled={ blocked }
					onClick={ save }
				>
					{ sprintf(
						/* translators: %d: number of cells. */
						__( 'Save %d changed cells', 'ohmylms' ),
						changes.length
					) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ ! changes.length || operation.busy }
					onClick={ () => {
						setEdits( {} );
						setConflicts( [] );
					} }
				>
					{ __( 'Discard changes', 'ohmylms' ) }
				</Button>
			</p>
			<PasteRows
				syllabus={ base.syllabus.id }
				disabled={ changes.length > 0 || operation.busy }
				onApplied={ ( next ) => {
					setBase( next );
					setEdits( {} );
					onSaved( next );
				} }
			/>
		</section>
	);
}

function PasteRows( {
	syllabus,
	disabled,
	onApplied,
}: {
	syllabus: number;
	disabled: boolean;
	onApplied: ( next: SyllabusOutline ) => void;
} ) {
	const [ text, setText ] = useState( '' );
	const [ reviewed, setReviewed ] = useState< {
		rows: CsvRow[];
		report: ImportReport;
	} | null >( null );
	const [ message, setMessage ] = useState( '' );
	const operation = useOperation();
	function review() {
		const rows = pastedRows( text );
		setMessage( '' );
		void operation.run(
			( signal ) => importRows( syllabus, rows, true, signal ),
			( result ) => setReviewed( { rows, report: result.report } )
		);
	}
	function apply() {
		if ( ! reviewed ) {
			return;
		}
		void operation.run(
			( signal ) =>
				importRows(
					syllabus,
					reviewed.rows,
					false,
					signal,
					reviewed.report.reviewRevision
				),
			( result ) => {
				setReviewed( null );
				setText( '' );
				setMessage( __( 'Pasted rows added.', 'ohmylms' ) );
				if ( result.outline ) {
					onApplied( result.outline );
				}
			}
		);
	}
	return (
		<section aria-labelledby="ohmylms-sheet-paste">
			<h3 id="ohmylms-sheet-paste">{ __( 'Paste rows', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Paste rows copied from a spreadsheet with four columns: Unit (use › or > between folders), Skill group, Skill code and Skill. Empty unit and group cells repeat the row above. The rows are checked first and nothing is added until you apply them.',
					'ohmylms'
				) }
			</p>
			{ disabled && (
				<p>
					{ __(
						'Save or discard the edited cells before pasting rows.',
						'ohmylms'
					) }
				</p>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<TextareaControl
				label={ __( 'Rows to paste', 'ohmylms' ) }
				value={ text }
				rows={ 6 }
				disabled={ disabled || operation.busy }
				onChange={ ( value ) => {
					setText( value );
					setReviewed( null );
				} }
			/>
			<Button
				variant="secondary"
				disabled={ disabled || operation.busy || ! text.trim() }
				onClick={ review }
			>
				{ __( 'Check pasted rows', 'ohmylms' ) }
			</Button>
			{ reviewed && (
				<div aria-live="polite">
					<p>
						{ reviewed.report.counts
							.map( ( count ) =>
								sprintf(
									/* translators: 1: kind, 2: created, 3: updated. */
									__(
										'%1$s: %2$d new, %3$d updated',
										'ohmylms'
									),
									{
										contents: __( 'Units', 'ohmylms' ),
										groups: __( 'Skill groups', 'ohmylms' ),
										skills: __( 'Skills', 'ohmylms' ),
									}[ count.label ] ?? count.label,
									count.create,
									count.update
								)
							)
							.join( '; ' ) }
					</p>
					{ reviewed.report.errors.length > 0 && (
						<Notice status="error" isDismissible={ false }>
							<ul>
								{ reviewed.report.errors.map(
									( issue, index ) => (
										<li key={ index }>
											{ sprintf(
												/* translators: 1: line, 2: problem. */
												__(
													'Row %1$d: %2$s',
													'ohmylms'
												),
												issue.line,
												issue.message
											) }
										</li>
									)
								) }
							</ul>
						</Notice>
					) }
					{ reviewed.report.warnings.length > 0 && (
						<Notice status="warning" isDismissible={ false }>
							<ul>
								{ reviewed.report.warnings.map(
									( issue, index ) => (
										<li key={ index }>
											{ sprintf(
												/* translators: 1: line, 2: problem. */
												__(
													'Row %1$d: %2$s',
													'ohmylms'
												),
												issue.line,
												issue.message
											) }
										</li>
									)
								) }
							</ul>
						</Notice>
					) }
					<Button
						variant="primary"
						disabled={ ! reviewed.report.valid || operation.busy }
						isBusy={ operation.busy }
						onClick={ apply }
					>
						{ __( 'Add pasted rows', 'ohmylms' ) }
					</Button>
				</div>
			) }
		</section>
	);
}
