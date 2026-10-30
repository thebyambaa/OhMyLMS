import { ListTable } from '../../../ui/ListTable';
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
import { statusViews } from '../../components/listViews';
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
			{ ! exams.loading && exams.data && (
				<ListTable
					caption={ __( 'Exams', 'ohmylms' ) }
					formLabel={ __( 'Search exams', 'ohmylms' ) }
					rows={ exams.data.items }
					rowKey={ ( exam ) => exam.id }
					emptyMessage={ __( 'No exams found.', 'ohmylms' ) }
					views={ statusViews(
						[
							{ value: 'all', label: __( 'All', 'ohmylms' ) },
							{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
							{
								value: 'publish',
								label: __( 'Published', 'ohmylms' ),
							},
						],
						filters.status,
						exams.data.total,
						( value ) => {
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
						}
					) }
					search={ {
						label: __( 'Search exams', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: query,
						onChange: setQuery,
						onSubmit: () => {
							setFilters( ( current ) => ( {
								...current,
								search: query.trim(),
							} ) );
							setPage( 1 );
						},
					} }
					pagination={ {
						page,
						totalPages: exams.data.totalPages,
						totalItems: exams.data.total,
						itemsLabel: sprintf(
							/* translators: %d: number of exams. */
							_n(
								'%d exam found.',
								'%d exams found.',
								exams.data.total,
								'ohmylms'
							),
							exams.data.total
						),
						onChange: setPage,
					} }
					columns={ [
						{
							id: 'title',
							label: __( 'Title', 'ohmylms' ),
							primary: true,
							render: ( exam ) => (
								<strong>
									<Link
										className="row-title"
										to={ examPath( exam.id ) }
									>
										{ exam.title ||
											__( '(no title)', 'ohmylms' ) }
									</Link>
								</strong>
							),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( exam ) => statusLabel( exam.status ),
						},
						{
							id: 'questions',
							label: __( 'Questions', 'ohmylms' ),
							className: 'num',
							render: ( exam ) => exam.question_count,
						},
						{
							id: 'marks',
							label: __( 'Marks', 'ohmylms' ),
							className: 'num',
							render: ( exam ) => exam.total_marks,
						},
						{
							id: 'duration',
							label: __( 'Duration', 'ohmylms' ),
							render: ( exam ) =>
								sprintf(
									/* translators: %d: minutes. */
									__( '%d min', 'ohmylms' ),
									exam.duration_minutes
								),
						},
						{
							id: 'source',
							label: __( 'Source', 'ohmylms' ),
							render: ( exam ) =>
								'imported' === exam.origin && exam.job_id ? (
									<Link to={ reviewPath( exam.job_id ) }>
										{ __( 'Imported paper', 'ohmylms' ) }
									</Link>
								) : (
									__( 'Written in OhMyLMS', 'ohmylms' )
								),
						},
					] }
					rowActions={ ( exam ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							render: (
								<Link to={ examPath( exam.id ) }>
									{ __( 'Edit', 'ohmylms' ) }
								</Link>
							),
						},
					] }
				/>
			) }

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
				{ ! jobs.loading && jobs.data && (
					<ListTable
						caption={ __( 'Imports', 'ohmylms' ) }
						rows={ jobs.data.items }
						rowKey={ ( job ) => job.id }
						emptyMessage={ __( 'No imports yet.', 'ohmylms' ) }
						pagination={ {
							page: jobPage,
							totalPages: jobs.data.totalPages,
							onChange: setJobPage,
						} }
						columns={ [
							{
								id: 'import',
								label: __( 'Import', 'ohmylms' ),
								primary: true,
								render: ( job ) => (
									<strong>
										<Link
											className="row-title"
											to={ reviewPath( job.id ) }
										>
											{ job.title || job.source.name }
										</Link>
									</strong>
								),
							},
							{
								id: 'state',
								label: __( 'State', 'ohmylms' ),
								render: ( job ) => stateLabel( job.state ),
							},
							{
								id: 'creates',
								label: __( 'Creates', 'ohmylms' ),
								render: ( job ) => targetLabel( job.target ),
							},
							{
								id: 'reviewed',
								label: __( 'Reviewed', 'ohmylms' ),
								render: ( job ) =>
									job.row_count
										? sprintf(
												/* translators: 1: accepted rows; 2: all rows. */
												__( '%1$d of %2$d', 'ohmylms' ),
												job.accepted_count +
													job.rejected_count,
												job.row_count
											)
										: '—',
							},
							{
								id: 'date',
								label: __( 'Updated', 'ohmylms' ),
								render: ( job ) => formatDate( job.updated_at ),
							},
						] }
					/>
				) }
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
