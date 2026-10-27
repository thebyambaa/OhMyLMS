import { SkillConnections } from './SkillConnections';
/** @jsx createElement */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { Button, Notice, TextareaControl } from '@wordpress/components';
import { __, _x, sprintf } from '@wordpress/i18n';
import * as api from './api.mjs';
import { QuickAdd } from './WorkspaceParts';
import { SpreadsheetActions } from './SpreadsheetActions';
import {
	SHEET_COLUMNS,
	SHEET_IDENTITY,
	sheetFieldColumn,
	editSheetCell,
	pastedSheetRows,
	saveSheetChanges,
	sheetChanges,
	syllabusSheetRows,
} from './spreadsheet.mjs';

/**
 * Spreadsheet view of the saved syllabus, retaining skill and placement identities.
 *
 * @param {Object}                                  props               View properties.
 * @param {Object}                                  props.outline       Saved syllabus outline.
 * @param {(response: Object) => void}              props.onSaved       Refresh the saved workspace.
 * @param {(dirty: boolean, busy: boolean) => void} props.onStateChange Report draft state.
 * @param {Object}                                  props.tree          Indexed syllabus tree.
 * @param {Object}                                  props.actions       Existing syllabus actions.
 * @param {(key: string) => void}                   props.onOpen        Open a row's details.
 * @param {boolean}                                 props.pending       Workspace action in progress.
 * @return {Object} Inline spreadsheet.
 */
export function SyllabusSpreadsheet( {
	outline,
	onSaved,
	onStateChange,
	tree,
	actions,
	onOpen,
	pending = false,
} ) {
	const [ rows, setRows ] = useState( () => syllabusSheetRows( outline ) );
	const showConnections =
		outline.syllabus.item_type === 'roadmap' ||
		rows.some( ( row ) => row.skill?.connections?.length );
	const baseline = useRef( rows );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ pasteOpen, setPasteOpen ] = useState( false );
	const [ paste, setPaste ] = useState( '' );
	const [ adding, setAdding ] = useState( null );
	const previousOutline = useRef( outline );
	const table = useRef( null );
	const sequence = useRef( 0 );
	const dirty = JSON.stringify( rows ) !== JSON.stringify( baseline.current );
	const labels = [
		__( '№', 'ohmylms' ),
		__( 'Unit', 'ohmylms' ),
		_x( 'Chapter', 'syllabus-spreadsheet', 'ohmylms' ),
		__( 'Topic', 'ohmylms' ),
	];
	useEffect( () => {
		if ( ! dirty && ! paste.trim() ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty, paste ] );
	useEffect( () => {
		onStateChange?.( dirty || Boolean( paste.trim() ), busy );
	}, [ dirty, paste, busy, onStateChange ] );
	useEffect( () => {
		if ( previousOutline.current !== outline && ! dirty && ! busy ) {
			const next = syllabusSheetRows( outline );
			previousOutline.current = outline;
			baseline.current = next;
			setRows( next );
		}
	}, [ outline, dirty, busy ] );
	const locked = busy || pending;
	const add = async ( data ) => {
		if ( dirty || paste.trim() || locked ) {
			return false;
		}
		if ( adding.kind === 'folder' ) {
			return actions.addTopic( adding.row.contentId, data, {
				open: false,
			} );
		}
		if ( adding.row.groupId ) {
			return actions.addSkill( adding.row.groupId, data );
		}
		return actions.addSkillInTopic( adding.row.contentId, data );
	};
	const save = async () => {
		setBusy( true );
		setNotice( null );
		let saved = 0;
		try {
			const plan = sheetChanges( baseline.current, rows );
			await saveSheetChanges( {
				id: outline.syllabus.id,
				plan,
				api,
				onSaved: ( change ) => {
					saved++;
					const identity = SHEET_IDENTITY[ change.kind ];
					const index = baseline.current.findIndex(
						( row ) => row[ identity ] === change.id
					);
					const edited = rows.find(
						( row ) => row[ identity ] === change.id
					);
					for ( const field of Object.keys( change.fields ) ) {
						const column = sheetFieldColumn( change.kind, field );
						baseline.current = editSheetCell(
							baseline.current,
							index,
							column,
							edited[ column ]
						);
					}
				},
			} );
			const [ fresh, itemTree ] = await Promise.all( [
				api.loadSyllabus( outline.syllabus.id ),
				api.loadTree(),
			] );
			const next = syllabusSheetRows( fresh );
			baseline.current = next;
			setRows( next );
			onSaved( { ...fresh, items: itemTree.items } );
			setNotice( {
				status: 'success',
				text: __( 'Syllabus spreadsheet saved.', 'ohmylms' ),
			} );
		} catch ( cause ) {
			setNotice( {
				status: 'error',
				text: [
					saved
						? __(
								'Some changes were saved. Your remaining edits are still here; retry saving.',
								'ohmylms'
							)
						: __(
								'Your edits are still here. Correct the problem and retry saving.',
								'ohmylms'
							),
					cause.message,
				].join( ' ' ),
			} );
		} finally {
			setBusy( false );
		}
	};
	const navigate = ( event, row, column ) => {
		if ( ! [ 'Enter', 'ArrowUp', 'ArrowDown' ].includes( event.key ) ) {
			return;
		}
		const next =
			row + ( event.key === 'ArrowUp' || event.shiftKey ? -1 : 1 );
		const target = table.current?.querySelector(
			`[data-row="${ next }"][data-column="${ column }"]`
		);
		if ( target ) {
			event.preventDefault();
			target.focus();
		}
	};
	return (
		<section
			className="ohmylms-syllabus-sheet"
			aria-label={ __( 'Syllabus spreadsheet', 'ohmylms' ) }
		>
			<p>
				{ _x(
					'Edit cells or paste rows from Excel or Google Sheets. Unit → Chapter → Topic follows the syllabus folders and skills.',
					'syllabus-spreadsheet',
					'ohmylms'
				) }
			</p>
			{ notice && (
				<Notice status={ notice.status } isDismissible={ false }>
					{ notice.text }
				</Notice>
			) }
			<div className="ohmylms-sheet-toolbar">
				<Button
					variant="secondary"
					disabled={ locked }
					onClick={ () => {
						const last = rows.at( -1 );
						setRows( [
							...rows,
							{
								key: `new-${ ++sequence.current }`,
								number: last?.number || '',
								unit: last?.unit || '',
								chapter: last?.chapter || '',
								topic: '',
							},
						] );
					} }
				>
					{ __( 'Add row', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={ locked }
					aria-expanded={ pasteOpen }
					onClick={ () => setPasteOpen( ! pasteOpen ) }
				>
					{ __( 'Paste rows', 'ohmylms' ) }
				</Button>
				<span role="status">
					{ dirty || paste.trim()
						? __( 'Unsaved changes', 'ohmylms' )
						: __( 'All changes saved', 'ohmylms' ) }
				</span>
				<Button
					variant="primary"
					disabled={ locked || ! dirty }
					isBusy={ busy }
					onClick={ save }
				>
					{ busy
						? __( 'Saving…', 'ohmylms' )
						: __( 'Save changes', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={ locked || ( ! dirty && ! paste.trim() ) }
					onClick={ () => {
						const next = syllabusSheetRows( outline );
						baseline.current = next;
						setRows( next );
						setPaste( '' );
						setNotice( null );
					} }
				>
					{ __( 'Discard changes', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={ locked || dirty || Boolean( paste.trim() ) }
					onClick={ () =>
						setAdding( {
							kind: 'folder',
							row: { contentId: outline.syllabus.id },
						} )
					}
				>
					{ __( 'Add folder', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={ locked || dirty || Boolean( paste.trim() ) }
					onClick={ () =>
						setAdding( {
							kind: 'skill',
							row: { contentId: outline.syllabus.id },
						} )
					}
				>
					{ __( 'Add skill', 'ohmylms' ) }
				</Button>
			</div>
			{ ( dirty || paste.trim() ) && (
				<p className="description">
					{ __(
						'Save or discard your edits to switch views or use row actions.',
						'ohmylms'
					) }
				</p>
			) }
			{ adding && (
				<QuickAdd
					key={ `${ adding.kind }:${ adding.row.contentId }:${ adding.row.groupId || 0 }` }
					nameLabel={
						adding.kind === 'folder'
							? __( 'Folder name', 'ohmylms' )
							: __( 'Skill name', 'ohmylms' )
					}
					codeLabel={ __( 'Code', 'ohmylms' ) }
					submitLabel={
						adding.kind === 'folder'
							? __( 'Add folder', 'ohmylms' )
							: __( 'Add skill', 'ohmylms' )
					}
					pending={ locked || dirty || Boolean( paste.trim() ) }
					onAdd={ add }
					onCancel={ () => setAdding( null ) }
				/>
			) }
			{ pasteOpen && (
				<div className="ohmylms-sheet-paste">
					<TextareaControl
						label={ _x(
							'Paste №, Unit, Chapter, Topic columns',
							'syllabus-spreadsheet',
							'ohmylms'
						) }
						help={ _x(
							'Include the header if you have one. Blank Unit and Chapter cells continue the values above. Pasted rows are added to this syllabus.',
							'syllabus-spreadsheet',
							'ohmylms'
						) }
						value={ paste }
						onChange={ setPaste }
						disabled={ locked }
						rows={ 5 }
					/>
					<Button
						variant="secondary"
						disabled={ locked || ! paste.trim() }
						onClick={ () => {
							setRows( [ ...rows, ...pastedSheetRows( paste ) ] );
							setPaste( '' );
							setPasteOpen( false );
						} }
					>
						{ __( 'Add pasted rows', 'ohmylms' ) }
					</Button>
				</div>
			) }
			<div className="ohmylms-sheet-scroll">
				<table
					ref={ table }
					className={
						showConnections ? 'has-skill-connections' : undefined
					}
					aria-label={ __( 'Syllabus spreadsheet', 'ohmylms' ) }
				>
					<thead>
						<tr>
							{ labels.map( ( label ) => (
								<th scope="col" key={ label }>
									{ label }
								</th>
							) ) }
							{ showConnections && (
								<>
									<th scope="col">
										{ __( 'Connected skills', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Connection types', 'ohmylms' ) }
									</th>
								</>
							) }
							<th scope="col">{ __( 'Actions', 'ohmylms' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row, index ) => (
							<tr key={ row.key }>
								{ SHEET_COLUMNS.map( ( column, position ) => (
									<td key={ column }>
										<input
											type="text"
											value={ row[ column ] }
											title={ row[ column ] }
											disabled={ locked }
											readOnly={
												( row.root && position < 2 ) ||
												( !! row.contentId &&
													position === 0 )
											}
											aria-label={ sprintf(
												/* translators: %1$s: column label; %2$d: row number. */ __(
													'%1$s, row %2$d',
													'ohmylms'
												),
												labels[ position ],
												index + 1
											) }
											data-row={ index }
											data-column={ position }
											onKeyDown={ ( event ) =>
												navigate(
													event,
													index,
													position
												)
											}
											onChange={ ( event ) =>
												setRows(
													editSheetCell(
														rows,
														index,
														column,
														event.target.value
													)
												)
											}
										/>
									</td>
								) ) }
								{ showConnections && (
									<>
										<td>
											<SkillConnections
												skill={ row.skill }
												part="names"
											/>
										</td>
										<td>
											<SkillConnections
												skill={ row.skill }
												part="types"
											/>
										</td>
									</>
								) }
								<td>
									{ row.contentId && (
										<SpreadsheetActions
											row={ row }
											tree={ tree }
											actions={ actions }
											disabled={
												locked ||
												dirty ||
												Boolean( paste.trim() )
											}
											onOpen={ onOpen }
											onAdd={ ( entry, kind ) =>
												setAdding( {
													row: entry,
													kind,
												} )
											}
										/>
									) }
									{ ! row.contentId && (
										<Button
											icon="no-alt"
											label={ __(
												'Remove new row',
												'ohmylms'
											) }
											disabled={ locked }
											onClick={ () =>
												setRows(
													rows.filter(
														( entry ) =>
															entry.key !==
															row.key
													)
												)
											}
										/>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
			<p className="description">
				{ _x(
					'Tab moves across cells. Enter and ↑/↓ move between rows. Renaming a unit or chapter updates its repeated cells. Nested units use Parent > Child.',
					'syllabus-spreadsheet',
					'ohmylms'
				) }
			</p>
		</section>
	);
}
