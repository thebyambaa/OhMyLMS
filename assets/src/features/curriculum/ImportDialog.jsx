import {
	createElement,
	useEffect,
	useRef,
	useState,
	useId,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import * as api from './api.mjs';
import {
	ROLES,
	buildRows,
	guessRoles,
	looksLikeHeader,
	mappingProblems,
	parseCsv,
	templateCsv,
} from './csv.mjs';
import { changesNothing, fileName, reportRows } from './syllabus.mjs';
import { download } from './download.mjs';
const MAX_BYTES = 5 * 1024 * 1024;
const SHOWN_PROBLEMS = 12;
const ROLE_LABELS = () =>
	Object.fromEntries(
		ROLES.map( ( role ) => [
			role.id,
			{
				content_code: __( 'Folder path code', 'ohmylms' ),
				content: __( 'Folder path', 'ohmylms' ),
				group_code: __( 'Skill folder code', 'ohmylms' ),
				group: __( 'Skill folder name', 'ohmylms' ),
				skill_code: __( 'Skill code', 'ohmylms' ),
				skill: __( 'Skill', 'ohmylms' ),
				description: __( 'Notes', 'ohmylms' ),
				category: __( 'Skill category', 'ohmylms' ),
			}[ role.id ],
		] )
	);
const ROW_TITLES = () => ( {
	contents: __( 'Contents', 'ohmylms' ),
	groups: __( 'Folders', 'ohmylms' ),
	skills: __( 'Skills', 'ohmylms' ),
} );
function Problems( { items, status } ) {
	if ( ! items.length ) {
		return null;
	}
	const shown = items.slice( 0, SHOWN_PROBLEMS );
	return (
		<Notice
			status={ status }
			isDismissible={ false }
			className="ohmylms-syl-problems"
		>
			<ul>
				{ shown.map( ( entry, index ) => (
					<li key={ `${ entry.line }-${ index }` }>
						{ entry.line
							? sprintf(
									/* translators: %1$d: CSV row number; %2$s: validation error message. */ __(
										'Row %1$d: %2$s',
										'ohmylms'
									),
									entry.line,
									entry.message
								)
							: entry.message }
					</li>
				) ) }
			</ul>
			{ items.length > shown.length && (
				<p>
					{ sprintf(
						/* translators: %d: number of additional results. */ _n(
							'…and %d more.',
							'…and %d more.',
							items.length - shown.length,
							'ohmylms'
						),
						items.length - shown.length
					) }
				</p>
			) }
		</Notice>
	);
}
function ReportTable( { report } ) {
	const titles = ROW_TITLES();
	return (
		<table className="widefat striped ohmylms-syl-report">
			<thead>
				<tr>
					<th scope="col">
						<span className="screen-reader-text">
							{ __( 'What', 'ohmylms' ) }
						</span>
					</th>
					<th scope="col">{ __( 'New', 'ohmylms' ) }</th>
					<th scope="col">{ __( 'Updated', 'ohmylms' ) }</th>
					<th scope="col">{ __( 'Moved', 'ohmylms' ) }</th>
					<th scope="col">{ __( 'Unchanged', 'ohmylms' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ reportRows( report ).map( ( row ) => (
					<tr key={ row.id }>
						<th scope="row">{ titles[ row.id ] }</th>
						<td>{ row.create }</td>
						<td>{ row.update }</td>
						<td>{ row.id === 'skills' ? row.move : '–' }</td>
						<td>{ row.same }</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

/**
 * Import a syllabus from CSV in four steps: choose the file, check which column is which, review what
 * would change, then import. Nothing is saved until the review is clean, and an import never deletes.
 * @param root0
 * @param root0.syllabus
 * @param root0.onClose
 * @param root0.onImported
 */
export function ImportDialog( { syllabus, onClose, onImported } ) {
	const _controlId = useId();
	const [ step, setStep ] = useState( 'pick' );
	const [ table, setTable ] = useState( null );
	const [ name, setName ] = useState( '' );
	const [ header, setHeader ] = useState( true );
	const [ fillDown, setFillDown ] = useState( true );
	const [ roles, setRoles ] = useState( [] );
	const [ report, setReport ] = useState( null );
	const [ rows, setRows ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const labels = ROLE_LABELS();
	// Each step replaces the last one's buttons, which would drop keyboard focus onto the page behind the dialog.
	// Moving focus into the dialog's content keeps Escape, Tab and screen readers inside it.
	const frame = useRef( null );
	useEffect( () => {
		frame.current?.focus();
	}, [ step ] );
	async function choose( event ) {
		const file = event.target.files?.[ 0 ];
		event.target.value = '';
		if ( ! file ) {
			return;
		}
		setError( '' );
		if ( file.size > MAX_BYTES ) {
			setError(
				__(
					'That file is larger than 5 MB. Split it into smaller files.',
					'ohmylms'
				)
			);
			return;
		}
		let text;
		try {
			text = await file.text();
		} catch {
			setError( __( 'The file could not be read.', 'ohmylms' ) );
			return;
		}
		const parsed = parseCsv( text );
		if ( parsed.error ) {
			setError( __( 'That file has no rows.', 'ohmylms' ) );
			return;
		}
		const hasHeader = looksLikeHeader( parsed.records[ 0 ] );
		setTable( parsed );
		setName( file.name );
		setHeader( hasHeader );
		setRoles(
			hasHeader
				? guessRoles( parsed.records[ 0 ] )
				: parsed.records[ 0 ].map( () => '' )
		);
		setStep( 'map' );
	}
	function toggleHeader( value ) {
		setHeader( value );
		setRoles(
			value
				? guessRoles( table.records[ 0 ] )
				: table.records[ 0 ].map( () => '' )
		);
	}
	const setRole = ( column ) => ( value ) =>
		setRoles( ( previous ) =>
			previous.map( ( role, index ) =>
				index === column ? value : role
			)
		);
	const problems = table ? mappingProblems( roles ) : [];
	const dataRows = table ? table.records.slice( header ? 1 : 0 ) : [];
	async function check() {
		setBusy( true );
		setError( '' );
		try {
			const built = buildRows( table.records, roles, {
				header,
				fillDown,
			} );
			setRows( built );
			const response = await api.importRows( syllabus.id, built, true );
			setReport( response.report );
			setStep( 'check' );
		} catch ( cause ) {
			setError(
				cause?.message ||
					__( 'The file could not be checked.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	}
	async function apply() {
		setBusy( true );
		setError( '' );
		try {
			const response = await api.importRows( syllabus.id, rows, false );
			setReport( response.report );
			onImported( response );
			setStep( 'done' );
		} catch ( cause ) {
			setError(
				cause?.message ||
					__( 'The import failed. Nothing was changed.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	}
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: curriculum item name. */ __(
					'Import “%s” from CSV',
					'ohmylms'
				),
				syllabus.name
			) }
			onRequestClose={ onClose }
			className="ohmylms-content-hub-dialog ohmylms-syl-import"
		>
			<div ref={ frame } tabIndex={ -1 } className="ohmylms-syl-frame">
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }

				{ step === 'pick' && (
					<div className="ohmylms-syl-step">
						<p>
							{ __(
								'Choose a CSV file with one skill per row: its content (folder or folder), folder and skill, each with an optional code. You will check which column is which before anything is saved, and an import never deletes anything.',
								'ohmylms'
							) }
						</p>
						<p>
							<label
								className="ohmylms-syl-file"
								htmlFor={ _controlId + '-field-1' }
							>
								<span className="screen-reader-text">
									{ __( 'CSV file', 'ohmylms' ) }
								</span>
								<input
									type="file"
									accept=".csv,.tsv,.txt,text/csv,text/plain"
									onChange={ choose }
									id={ _controlId + '-field-1' }
								/>
							</label>
						</p>
						<p className="ohmylms-ext-muted">
							{ __(
								'Comma, semicolon or tab separated; UTF-8 (Mongolian and other scripts are fine).',
								'ohmylms'
							) }{ ' ' }
							<Button
								variant="link"
								onClick={ () =>
									download(
										templateCsv(),
										fileName( syllabus.name, 'template' )
									)
								}
							>
								{ __( 'Download a template', 'ohmylms' ) }
							</Button>
						</p>
					</div>
				) }

				{ step === 'map' && table && (
					<div className="ohmylms-syl-step">
						<p>
							{ sprintf(
								/* translators: %1$s: CSV filename; %2$d: number of CSV data rows. */ _n(
									'“%1$s” has %2$d data row. Tell us what each column holds.',
									'“%1$s” has %2$d data rows. Tell us what each column holds.',
									dataRows.length,
									'ohmylms'
								),
								name,
								dataRows.length
							) }
						</p>
						{ table.replaced && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Some characters could not be read, so the file may not be saved as UTF-8. In your spreadsheet, save it as “CSV UTF-8”.',
									'ohmylms'
								) }
							</Notice>
						) }
						<div className="ohmylms-syl-options">
							<CheckboxControl
								label={ __(
									'The first row holds column titles',
									'ohmylms'
								) }
								checked={ header }
								onChange={ toggleHeader }
								__nextHasNoMarginBottom
							/>
							<CheckboxControl
								label={ __(
									'An empty content or group cell repeats the one above it',
									'ohmylms'
								) }
								checked={ fillDown }
								onChange={ setFillDown }
								help={ __(
									'For spreadsheets with merged cells.',
									'ohmylms'
								) }
								__nextHasNoMarginBottom
							/>
						</div>
						<div className="ohmylms-ext-table-scroll">
							<table className="widefat ohmylms-syl-map">
								<thead>
									<tr>
										{ table.records[ 0 ].map(
											( cell, column ) => (
												<th key={ column } scope="col">
													<SelectControl
														label={ sprintf(
															/* translators: %1$d: CSV column number; %2$s: column heading. */ __(
																'Column %1$d: %2$s',
																'ohmylms'
															),
															column + 1,
															( header &&
																cell.trim() ) ||
																__(
																	'(no title)',
																	'ohmylms'
																)
														) }
														value={
															roles[ column ] ||
															''
														}
														onChange={ setRole(
															column
														) }
														options={ [
															{
																value: '',
																label: __(
																	'Do not import',
																	'ohmylms'
																),
															},
															...ROLES.map(
																( role ) => ( {
																	value: role.id,
																	label: labels[
																		role.id
																	],
																} )
															),
														] }
														__nextHasNoMarginBottom
													/>
													{ header && (
														<span className="ohmylms-ext-muted">
															{ cell }
														</span>
													) }
												</th>
											)
										) }
									</tr>
								</thead>
								<tbody>
									{ dataRows
										.slice( 0, 5 )
										.map( ( record, index ) => (
											<tr key={ index }>
												{ record.map(
													( cell, column ) => (
														<td
															key={ column }
															className={
																roles[ column ]
																	? ''
																	: 'is-ignored'
															}
														>
															{ cell }
														</td>
													)
												) }
											</tr>
										) ) }
								</tbody>
							</table>
						</div>
						{ problems.includes( 'skill' ) && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Choose which column holds the skill (the learning objective).',
									'ohmylms'
								) }
							</Notice>
						) }
						{ problems.some( ( problem ) =>
							problem.startsWith( 'duplicate:' )
						) && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Each kind of information can be used for only one column.',
									'ohmylms'
								) }
							</Notice>
						) }
						<div className="ohmylms-content-hub-dialog-actions">
							<Button
								variant="secondary"
								onClick={ () => setStep( 'pick' ) }
							>
								{ __( 'Choose another file', 'ohmylms' ) }
							</Button>
							<Button
								variant="primary"
								isBusy={ busy }
								disabled={
									busy ||
									problems.length > 0 ||
									! dataRows.length
								}
								onClick={ check }
							>
								{ __( 'Check the file', 'ohmylms' ) }
							</Button>
						</div>
					</div>
				) }

				{ step === 'check' && report && (
					<div className="ohmylms-syl-step">
						<Problems
							items={ report.errors || [] }
							status="error"
						/>
						<Problems
							items={ report.warnings || [] }
							status="warning"
						/>
						{ ( () => {
							if ( report.valid ) {
								if ( changesNothing( report ) ) {
									return (
										<p>
											{ __(
												'This file matches the syllabus already: there is nothing to import.',
												'ohmylms'
											) }
										</p>
									);
								}
								return (
									<p>
										{ sprintf(
											/* translators: %d: number of CSV rows. */ _n(
												'Ready to import %d row. This is what will change:',
												'Ready to import %d rows. This is what will change:',
												report.rows?.used || 0,
												'ohmylms'
											),
											report.rows?.used || 0
										) }
									</p>
								);
							}
							return (
								<p>
									{ __(
										'Nothing was imported. Fix the rows above in your file, then choose it again. Nothing is saved until every row is accepted.',
										'ohmylms'
									) }
								</p>
							);
						} )() }
						{ report.valid && <ReportTable report={ report } /> }
						{ report.valid && (
							<p className="ohmylms-ext-muted">
								{ __(
									'Skills are added to the skill library under this syllabus. Existing contents, folders and skills are matched by code, or by name when there is no code, and are never deleted.',
									'ohmylms'
								) }
							</p>
						) }
						<div className="ohmylms-content-hub-dialog-actions">
							<Button
								variant="secondary"
								onClick={ () => setStep( 'map' ) }
							>
								{ __( 'Back', 'ohmylms' ) }
							</Button>
							<Button
								variant="primary"
								isBusy={ busy }
								disabled={
									busy ||
									! report.valid ||
									changesNothing( report )
								}
								onClick={ apply }
							>
								{ __( 'Import', 'ohmylms' ) }
							</Button>
						</div>
					</div>
				) }

				{ step === 'done' && report && (
					<div className="ohmylms-syl-step">
						<Notice status="success" isDismissible={ false }>
							{ __( 'Imported.', 'ohmylms' ) }
						</Notice>
						<ReportTable report={ report } />
						<Problems
							items={ report.warnings || [] }
							status="warning"
						/>
						<div className="ohmylms-content-hub-dialog-actions">
							<Button variant="primary" onClick={ onClose }>
								{ __( 'Done', 'ohmylms' ) }
							</Button>
						</div>
					</div>
				) }
				{ busy && step !== 'done' && <Spinner /> }
			</div>
		</Modal>
	);
}
