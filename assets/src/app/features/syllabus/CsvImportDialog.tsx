import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	guessRoles,
	type CsvRow,
	type ParsedCsv,
} from '../../../features/curriculum/csv.mjs';
import { useOperation } from '../../hooks/useOperation';
import { importRows } from './api';
import { mappedRows, MAX_BYTES, readCsv } from './csv';
import type { ImportReport, SyllabusOutline } from './model';

const roleOptions = [
	{ value: '', label: __( 'Ignore column', 'ohmylms' ) },
	{ value: 'content', label: __( 'Topic path', 'ohmylms' ) },
	{ value: 'content_code', label: __( 'Topic code', 'ohmylms' ) },
	{ value: 'group', label: __( 'Skill group', 'ohmylms' ) },
	{ value: 'group_code', label: __( 'Group code', 'ohmylms' ) },
	{ value: 'skill', label: __( 'Skill', 'ohmylms' ) },
	{ value: 'skill_code', label: __( 'Skill code', 'ohmylms' ) },
	{ value: 'description', label: __( 'Notes', 'ohmylms' ) },
	{ value: 'category', label: __( 'Category', 'ohmylms' ) },
];
export function CsvImportDialog( {
	id,
	onClose,
	onApplied,
}: {
	id: number;
	onClose: () => void;
	onApplied: ( outline: SyllabusOutline ) => void;
} ) {
	const [ source, setSource ] = useState( '' );
	const [ parsed, setParsed ] = useState< ParsedCsv | null >( null );
	const [ roles, setRoles ] = useState< string[] >( [] );
	const [ header, setHeader ] = useState( true );
	const [ fillDown, setFillDown ] = useState( true );
	const [ report, setReport ] = useState< ImportReport | null >( null );
	const [ reviewed, setReviewed ] = useState< CsvRow[] | null >( null );
	const [ localError, setLocalError ] = useState( '' );
	const [ discard, setDiscard ] = useState( false );
	const operation = useOperation();
	const readVersion = useRef( 0 );
	useEffect(
		() => () => {
			readVersion.current++;
		},
		[]
	);
	function invalidate() {
		setReport( null );
		setReviewed( null );
		setLocalError( '' );
		operation.clearError();
	}
	function read( value: string ) {
		invalidate();
		setSource( value );
		setParsed( null );
		try {
			const result = readCsv( value );
			setParsed( result );
			setRoles( guessRoles( result.records[ 0 ] ?? [] ) );
		} catch ( error ) {
			setLocalError(
				error instanceof Error ? error.message : String( error )
			);
		}
	}
	async function file( selected?: File ) {
		if ( ! selected ) {
			return;
		}
		const version = ++readVersion.current;
		invalidate();
		setParsed( null );
		setSource( '' );
		if ( selected.size > MAX_BYTES ) {
			setLocalError(
				__( 'Choose a CSV file of at most 5 MB.', 'ohmylms' )
			);
			return;
		}
		try {
			const value = new TextDecoder( 'utf-8', { fatal: true } ).decode(
				await selected.arrayBuffer()
			);
			if ( version === readVersion.current ) {
				read( value );
			}
		} catch {
			if ( version === readVersion.current ) {
				setLocalError(
					__( 'The file could not be read as UTF-8.', 'ohmylms' )
				);
			}
		}
	}
	async function review() {
		if ( ! parsed ) {
			return;
		}
		let rows: CsvRow[];
		try {
			rows = mappedRows( parsed, roles, header, fillDown );
		} catch ( error ) {
			setLocalError(
				error instanceof Error ? error.message : String( error )
			);
			return;
		}
		await operation.run(
			( signal ) => importRows( id, rows, true, signal ),
			( result ) => {
				setReport( result.report );
				setReviewed( result.report.valid ? rows : null );
			}
		);
	}
	function close() {
		if ( operation.busy ) {
			return;
		}
		if ( source ) {
			setDiscard( true );
		} else {
			onClose();
		}
	}
	return (
		<Modal
			title={ __( 'Import syllabus CSV', 'ohmylms' ) }
			onRequestClose={ close }
		>
			<p>
				{ __(
					'Choose or paste UTF-8 CSV, map its columns, then review the server plan. Nothing is imported until you apply the reviewed rows.',
					'ohmylms'
				) }
			</p>
			{ ( localError || operation.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ localError || operation.error }
				</Notice>
			) }
			<fieldset disabled={ operation.busy }>
				<legend>
					{ __( 'CSV source and column mapping', 'ohmylms' ) }
				</legend>
				<label htmlFor="syllabus-csv-file">
					{ __( 'CSV file', 'ohmylms' ) }
				</label>
				<input
					id="syllabus-csv-file"
					type="file"
					accept=".csv,.tsv,text/csv,text/tab-separated-values"
					onChange={ ( event ) =>
						void file( event.target.files?.[ 0 ] )
					}
				/>
				<TextareaControl
					label={ __( 'CSV text', 'ohmylms' ) }
					value={ source }
					onChange={ ( value ) => {
						readVersion.current++;
						invalidate();
						setSource( value );
						setParsed( null );
					} }
				/>
				<Button onClick={ () => read( source ) } disabled={ ! source }>
					{ __( 'Read columns', 'ohmylms' ) }
				</Button>
				{ parsed && (
					<>
						<CheckboxControl
							label={ __(
								'First row contains headers',
								'ohmylms'
							) }
							checked={ header }
							onChange={ ( value ) => {
								invalidate();
								setHeader( value );
							} }
						/>
						<CheckboxControl
							label={ __(
								'Fill down topic and group cells',
								'ohmylms'
							) }
							checked={ fillDown }
							onChange={ ( value ) => {
								invalidate();
								setFillDown( value );
							} }
						/>
						{ roles.map( ( role, index ) => (
							<SelectControl
								key={ index }
								label={
									/* translators: %d: CSV column position. */ sprintf(
										__( 'Column %d', 'ohmylms' ),
										index + 1
									)
								}
								help={ (
									parsed.records[ 0 ]?.[ index ] ?? ''
								).slice( 0, 100 ) }
								value={ role }
								options={ roleOptions }
								onChange={ ( value ) => {
									invalidate();
									setRoles(
										roles.map( ( existing, position ) =>
											position === index
												? value
												: existing
										)
									);
								} }
							/>
						) ) }
						<p>
							{
								/* translators: %d: number of parsed CSV records. */ sprintf(
									__( '%d records read.', 'ohmylms' ),
									parsed.records.length
								)
							}
						</p>
						<Button
							variant="secondary"
							onClick={ () => void review() }
						>
							{ __( 'Review import', 'ohmylms' ) }
						</Button>
					</>
				) }
			</fieldset>
			{ report && (
				<section aria-label={ __( 'Import review', 'ohmylms' ) }>
					<h2>
						{ report.valid
							? __( 'Ready to import', 'ohmylms' )
							: __( 'Correct the import errors', 'ohmylms' ) }
					</h2>
					<ul>
						{ report.counts.map( ( count ) => (
							<li key={ count.label }>
								{ count.label }: { count.create }{ ' ' }
								{ __( 'new', 'ohmylms' ) }, { count.update }{ ' ' }
								{ __( 'updates', 'ohmylms' ) }
							</li>
						) ) }
					</ul>
					<ul>
						{ [ ...report.errors, ...report.warnings ].map(
							( issue, index ) => (
								<li key={ index }>
									{ issue.line }: { issue.message }
								</li>
							)
						) }
					</ul>
					<Button
						variant="primary"
						disabled={
							operation.busy || ! report.valid || ! reviewed
						}
						isBusy={ operation.busy }
						onClick={ () =>
							reviewed &&
							void operation.run(
								( signal ) =>
									importRows(
										id,
										reviewed,
										false,
										signal,
										report.reviewRevision
									),
								( result ) => {
									setReport( result.report );
									if ( result.outline ) {
										onApplied( result.outline );
										onClose();
									} else {
										setReviewed( null );
									}
								}
							)
						}
					>
						{ __( 'Apply reviewed import', 'ohmylms' ) }
					</Button>
				</section>
			) }
			{ discard && (
				<div role="alert">
					<p>
						{ __(
							'Close and discard this import review?',
							'ohmylms'
						) }
					</p>
					<Button onClick={ () => setDiscard( false ) }>
						{ __( 'Keep reviewing', 'ohmylms' ) }
					</Button>
					<Button onClick={ onClose }>
						{ __( 'Discard import review', 'ohmylms' ) }
					</Button>
				</div>
			) }
		</Modal>
	);
}
