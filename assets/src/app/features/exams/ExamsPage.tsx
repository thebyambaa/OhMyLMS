import { Table } from '../../../ui/Table';
import {
	Button,
	CheckboxControl,
	FormFileUpload,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	checkRows,
	createExam,
	listExams,
	listJobs,
	startImport,
	type ExamFilters,
} from './api';
import {
	LIMITS,
	TARGETS,
	examPath,
	formatDate,
	reviewPath,
	stateLabel,
	statusLabel,
	targetLabel,
} from './model';
import type {
	DryRunReport,
	ImportStart,
	ImportTarget,
	StatusFilter,
} from './types';

type Dialog = 'new' | 'import' | null;

function dialogFrom( action: string | null ): Dialog {
	return 'new' === action || 'import' === action ? action : null;
}

function isRowsFile( file: File | null ): boolean {
	return Boolean( file && /\.(csv|json|txt)$/i.test( file.name ) );
}

/**
 * New blank exam: title and duration, then the editor.
 * @param root0
 * @param root0.onClose
 */
function NewExamDialog( { onClose }: { onClose: () => void } ) {
	const navigate = useNavigate();
	const operation = useOperation();
	const [ title, setTitle ] = useState( '' );
	const [ minutes, setMinutes ] = useState( '60' );
	const [ error, setError ] = useState< string | null >( null );
	function submit() {
		const value = title.trim();
		const duration = /^\d{1,4}$/.test( minutes ) ? Number( minutes ) : 0;
		if ( ! value || Array.from( value ).length > LIMITS.title ) {
			setError(
				sprintf(
					/* translators: %d: maximum title length. */
					__( 'Enter a title of 1 to %d characters.', 'ohmylms' ),
					LIMITS.title
				)
			);
			return;
		}
		if ( duration < 1 || duration > LIMITS.duration ) {
			setError(
				sprintf(
					/* translators: %d: longest duration in minutes. */
					__( 'Enter a duration from 1 to %d minutes.', 'ohmylms' ),
					LIMITS.duration
				)
			);
			return;
		}
		setError( null );
		void operation.run(
			( signal ) => createExam( value, duration, signal ),
			( created ) => navigate( examPath( created.id ) )
		);
	}
	return (
		<Modal
			title={ __( 'New exam', 'ohmylms' ) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			<form
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					submit();
				} }
			>
				{ operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				) }
				{ error && (
					<p id="oml-exam-new-error" role="alert">
						{ error }
					</p>
				) }
				<TextControl
					label={ __( 'Exam title', 'ohmylms' ) }
					value={ title }
					maxLength={ LIMITS.title }
					required
					disabled={ operation.busy }
					onChange={ setTitle }
				/>
				<TextControl
					label={ __( 'Duration (minutes)', 'ohmylms' ) }
					value={ minutes }
					inputMode="numeric"
					disabled={ operation.busy }
					onChange={ setMinutes }
					help={ __(
						'The exam starts as a draft. Add questions and sections in the editor.',
						'ohmylms'
					) }
				/>
				<Button
					type="submit"
					variant="primary"
					disabled={ operation.busy }
					isBusy={ operation.busy }
				>
					{ __( 'Create exam', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ operation.busy }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</form>
		</Modal>
	);
}

/**
 * Import: a PDF paper (reviewed later) or CSV/JSON rows (checked first, then saved).
 * @param root0
 * @param root0.onClose
 */
function ImportDialog( { onClose }: { onClose: () => void } ) {
	const navigate = useNavigate();
	const operation = useOperation();
	const [ file, setFile ] = useState< File | null >( null );
	const [ markScheme, setMarkScheme ] = useState< File | null >( null );
	const [ text, setText ] = useState( '' );
	const [ target, setTarget ] = useState< ImportTarget >( 'exam' );
	const [ name, setName ] = useState( '' );
	const [ skipInvalid, setSkipInvalid ] = useState( false );
	const [ report, setReport ] = useState< DryRunReport | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const pdf = Boolean( file && ! isRowsFile( file ) );
	const input: ImportStart = {
		file,
		markScheme: pdf ? markScheme : null,
		text: file ? undefined : text,
		target,
		name: name.trim() || undefined,
		skipInvalid,
	};
	function validate(): boolean {
		if ( ! file && ! text.trim() ) {
			setError(
				__(
					'Choose a PDF, CSV or JSON file, or paste question rows.',
					'ohmylms'
				)
			);
			return false;
		}
		setError( null );
		return true;
	}
	function check() {
		if ( validate() ) {
			void operation.run(
				( signal ) => checkRows( input, signal ),
				setReport
			);
		}
	}
	function start() {
		if ( validate() ) {
			void operation.run(
				( signal ) => startImport( input, signal ),
				( job ) => navigate( reviewPath( job.id ) )
			);
		}
	}
	return (
		<Modal
			title={ __( 'Import an exam', 'ohmylms' ) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			<form
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					start();
				} }
			>
				{ operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				) }
				{ error && <p role="alert">{ error }</p> }
				<p>
					{ __(
						'A PDF paper is stored privately and extracted for your review. CSV or JSON rows are checked, then saved as question drafts.',
						'ohmylms'
					) }
				</p>
				<FormFileUpload
					accept=".pdf,.csv,.json,application/pdf,text/csv,application/json"
					onChange={ ( event ) => {
						setFile( event.currentTarget.files?.[ 0 ] ?? null );
						setReport( null );
					} }
					render={ ( { openFileDialog } ) => (
						<Button variant="secondary" onClick={ openFileDialog }>
							{ file
								? sprintf(
										/* translators: %s: file name. */
										__( 'Paper: %s', 'ohmylms' ),
										file.name
									)
								: __( 'Choose a file', 'ohmylms' ) }
						</Button>
					) }
				/>
				{ pdf && (
					<FormFileUpload
						accept=".pdf,application/pdf"
						onChange={ ( event ) =>
							setMarkScheme(
								event.currentTarget.files?.[ 0 ] ?? null
							)
						}
						render={ ( { openFileDialog } ) => (
							<Button
								variant="tertiary"
								onClick={ openFileDialog }
							>
								{ markScheme
									? sprintf(
											/* translators: %s: file name. */
											__( 'Mark scheme: %s', 'ohmylms' ),
											markScheme.name
										)
									: __(
											'Add a mark scheme (optional)',
											'ohmylms'
										) }
							</Button>
						) }
					/>
				) }
				{ ! file && (
					<TextareaControl
						label={ __( 'Or paste CSV or JSON rows', 'ohmylms' ) }
						value={ text }
						rows={ 6 }
						onChange={ ( next ) => {
							setText( next );
							setReport( null );
						} }
					/>
				) }
				<SelectControl
					label={ __( 'Create', 'ohmylms' ) }
					value={ target }
					options={ TARGETS.map( ( value ) => ( {
						value,
						label: targetLabel( value ),
					} ) ) }
					onChange={ ( next ) => {
						const found = TARGETS.find(
							( value ) => value === next
						);
						if ( found ) {
							setTarget( found );
						}
					} }
				/>
				<TextControl
					label={ __( 'Name (optional)', 'ohmylms' ) }
					value={ name }
					maxLength={ LIMITS.title }
					onChange={ setName }
				/>
				{ ! pdf && (
					<CheckboxControl
						label={ __( 'Skip rows with errors', 'ohmylms' ) }
						checked={ skipInvalid }
						onChange={ setSkipInvalid }
					/>
				) }
				{ report && (
					<div role="status">
						<p>
							{ sprintf(
								/* translators: 1: rows to create; 2: rows with errors. */
								__(
									'%1$d new or changed rows, %2$d with errors.',
									'ohmylms'
								),
								( report.counts.create ?? 0 ) +
									( report.counts.update ?? 0 ),
								report.counts.error ?? 0
							) }
						</p>
						<ul>
							{ report.rows
								.filter( ( row ) => row.errors.length )
								.map( ( row ) => (
									<li key={ row.row }>
										{ sprintf(
											/* translators: 1: row number; 2: problems. */
											__( 'Row %1$d: %2$s', 'ohmylms' ),
											row.row,
											row.errors.join( ' ' )
										) }
									</li>
								) ) }
						</ul>
					</div>
				) }
				{ ! pdf && (
					<Button
						variant="secondary"
						disabled={ operation.busy }
						onClick={ check }
					>
						{ __( 'Check rows', 'ohmylms' ) }
					</Button>
				) }{ ' ' }
				<Button
					type="submit"
					variant="primary"
					disabled={ operation.busy }
					isBusy={ operation.busy }
				>
					{ pdf
						? __( 'Upload paper', 'ohmylms' )
						: __( 'Import rows', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ operation.busy }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</form>
		</Modal>
	);
}

/**
 * Exams (`#/exams`): the author's exams and imports, with New (`?action=new`) and Import
 * (`?action=import`). Replaces the legacy Content Hub Exams tab.
 */
export default function ExamsPage() {
	const [ params, setParams ] = useSearchParams();
	const dialog = dialogFrom( params.get( 'action' ) );
	const [ page, setPage ] = useState( 1 );
	const [ jobPage, setJobPage ] = useState( 1 );
	const [ filters, setFilters ] = useState< ExamFilters >( {
		search: '',
		status: 'all',
	} );
	const [ query, setQuery ] = useState( '' );
	const load = useCallback(
		( signal: AbortSignal ) => listExams( page, filters, signal ),
		[ page, filters ]
	);
	const exams = useRequest( load );
	const loadJobs = useCallback(
		( signal: AbortSignal ) => listJobs( jobPage, signal ),
		[ jobPage ]
	);
	const jobs = useRequest( loadJobs );
	function open( next: Dialog ) {
		setParams( next ? { action: next } : {}, { replace: true } );
	}
	useEffect( () => {
		if ( params.get( 'action' ) && ! dialog ) {
			setParams( {}, { replace: true } );
		}
	}, [ params, dialog, setParams ] );

	return (
		<>
			<h2>{ __( 'Exams', 'ohmylms' ) }</h2>
			<p>
				<Button variant="primary" onClick={ () => open( 'new' ) }>
					{ __( 'New exam', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button variant="secondary" onClick={ () => open( 'import' ) }>
					{ __( 'Import exam', 'ohmylms' ) }
				</Button>
			</p>
			<form
				role="search"
				aria-label={ __( 'Search exams', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setFilters( ( current ) => ( {
						...current,
						search: query.trim(),
					} ) );
					setPage( 1 );
				} }
			>
				<TextControl
					label={ __( 'Search exams', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<SelectControl
					label={ __( 'Status', 'ohmylms' ) }
					value={ filters.status }
					options={ [
						{ value: 'all', label: __( 'All', 'ohmylms' ) },
						{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
						{
							value: 'publish',
							label: __( 'Published', 'ohmylms' ),
						},
					] }
					onChange={ ( value ) => {
						const status = (
							[ 'all', 'draft', 'publish' ] as StatusFilter[]
						 ).find( ( item ) => item === value );
						if ( status ) {
							setFilters( ( current ) => ( {
								...current,
								status,
							} ) );
							setPage( 1 );
						}
					} }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ exams.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ exams.error && (
				<Notice status="error" isDismissible={ false }>
					{ exams.error }{ ' ' }
					<Button variant="link" onClick={ exams.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ exams.loading && (
				<p role="status">{ __( 'Loading exams…', 'ohmylms' ) }</p>
			) }
			{ ! exams.loading &&
				exams.data &&
				( exams.data.items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: number of exams. */
								_n(
									'%d exam found.',
									'%d exams found.',
									exams.data.total,
									'ohmylms'
								),
								exams.data.total
							) }
						</p>
						<Table caption={ __( 'Exams', 'ohmylms' ) } striped>
							<thead>
								<tr>
									{ [
										__( 'Title', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Questions', 'ohmylms' ),
										__( 'Marks', 'ohmylms' ),
										__( 'Duration', 'ohmylms' ),
										__( 'Source', 'ohmylms' ),
									].map( ( heading ) => (
										<th key={ heading } scope="col">
											{ heading }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ exams.data.items.map( ( exam ) => (
									<tr key={ exam.id }>
										<th scope="row">
											<Link to={ examPath( exam.id ) }>
												{ exam.title ||
													__(
														'(no title)',
														'ohmylms'
													) }
											</Link>
										</th>
										<td>{ statusLabel( exam.status ) }</td>
										<td>{ exam.question_count }</td>
										<td>{ exam.total_marks }</td>
										<td>
											{ sprintf(
												/* translators: %d: minutes. */
												__( '%d min', 'ohmylms' ),
												exam.duration_minutes
											) }
										</td>
										<td>
											{ 'imported' === exam.origin &&
											exam.job_id ? (
												<Link
													to={ reviewPath(
														exam.job_id
													) }
												>
													{ __(
														'Imported paper',
														'ohmylms'
													) }
												</Link>
											) : (
												__(
													'Written in OhMyLMS',
													'ohmylms'
												)
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ page }
							pages={ exams.data.totalPages }
							busy={ exams.loading }
							onChange={ setPage }
						/>
					</>
				) : (
					<p>{ __( 'No exams found.', 'ohmylms' ) }</p>
				) ) }

			<section aria-labelledby="oml-exam-imports">
				<h3 id="oml-exam-imports">{ __( 'Imports', 'ohmylms' ) }</h3>
				{ jobs.error && (
					<Notice status="error" isDismissible={ false }>
						{ jobs.error }{ ' ' }
						<Button variant="link" onClick={ jobs.retry }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					</Notice>
				) }
				{ jobs.loading && (
					<p role="status">{ __( 'Loading imports…', 'ohmylms' ) }</p>
				) }
				{ ! jobs.loading &&
					jobs.data &&
					( jobs.data.items.length ? (
						<>
							<Table
								caption={ __( 'Imports', 'ohmylms' ) }
								striped
							>
								<thead>
									<tr>
										{ [
											__( 'Import', 'ohmylms' ),
											__( 'State', 'ohmylms' ),
											__( 'Creates', 'ohmylms' ),
											__( 'Reviewed', 'ohmylms' ),
											__( 'Updated', 'ohmylms' ),
										].map( ( heading ) => (
											<th key={ heading } scope="col">
												{ heading }
											</th>
										) ) }
									</tr>
								</thead>
								<tbody>
									{ jobs.data.items.map( ( job ) => (
										<tr key={ job.id }>
											<th scope="row">
												<Link
													to={ reviewPath( job.id ) }
												>
													{ job.title ||
														job.source.name }
												</Link>
											</th>
											<td>{ stateLabel( job.state ) }</td>
											<td>
												{ targetLabel( job.target ) }
											</td>
											<td>
												{ job.row_count
													? sprintf(
															/* translators: 1: accepted rows; 2: all rows. */
															__(
																'%1$d of %2$d',
																'ohmylms'
															),
															job.accepted_count +
																job.rejected_count,
															job.row_count
														)
													: '—' }
											</td>
											<td>
												{ formatDate( job.updated_at ) }
											</td>
										</tr>
									) ) }
								</tbody>
							</Table>
							<Pagination
								page={ jobPage }
								pages={ jobs.data.totalPages }
								busy={ jobs.loading }
								onChange={ setJobPage }
							/>
						</>
					) : (
						<p>{ __( 'No imports yet.', 'ohmylms' ) }</p>
					) ) }
			</section>

			{ 'new' === dialog && (
				<NewExamDialog onClose={ () => open( null ) } />
			) }
			{ 'import' === dialog && (
				<ImportDialog onClose={ () => open( null ) } />
			) }
		</>
	);
}
