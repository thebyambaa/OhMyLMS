import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { ApiError, normalizeApiError } from '../../lib/api';
import {
	getExam,
	publishExam,
	saveComposition,
	searchBank,
	updateExam,
} from './api';
import {
	FEEDBACK,
	LIMITS,
	addQuestions,
	compositionMarks,
	compositionProblem,
	formFromExam,
	isFormDirty,
	moveItem,
	reviewPath,
	statusLabel,
	toCompositionInput,
	toSettingsInput,
	validateSettings,
} from './model';
import {
	HEADER_FIELDS,
	type BankQuestion,
	type CompositionItem,
	type ExamDetail,
	type HeaderField,
	type PaperSettings,
	type SettingsErrors,
	type SettingsForm,
} from './types';

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

function headerLabel( field: HeaderField ): string {
	const labels: Record< HeaderField, string > = {
		board: __( 'Exam board', 'ohmylms' ),
		qualification: __( 'Qualification', 'ohmylms' ),
		subject_code: __( 'Subject code', 'ohmylms' ),
		paper: __( 'Paper', 'ohmylms' ),
		variant: __( 'Variant', 'ohmylms' ),
		session: __( 'Session', 'ohmylms' ),
		calculator: __( 'Calculator', 'ohmylms' ),
	};
	return labels[ field ];
}

function feedbackLabel( value: ( typeof FEEDBACK )[ number ] ): string {
	if ( 'immediate' === value ) {
		return __( 'As soon as the learner submits', 'ohmylms' );
	}
	return 'after_close' === value
		? __( 'After the exam closes', 'ohmylms' )
		: __( 'When I release results', 'ohmylms' );
}

/**
 * Pick questions from the bank and add them to the composition.
 * @param root0
 * @param root0.examId
 * @param root0.onAdd
 * @param root0.onClose
 */
function BankPicker( {
	examId,
	onAdd,
	onClose,
}: {
	examId: number;
	onAdd: ( questions: BankQuestion[] ) => void;
	onClose: () => void;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ chosen, setChosen ] = useState< BankQuestion[] >( [] );
	const load = useCallback(
		( signal: AbortSignal ) => searchBank( examId, search, page, signal ),
		[ examId, search, page ]
	);
	const bank = useRequest( load );
	return (
		<Modal
			title={ __( 'Add questions from the bank', 'ohmylms' ) }
			onRequestClose={ onClose }
		>
			<form
				role="search"
				aria-label={ __( 'Search the question bank', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setSearch( query.trim() );
					setPage( 1 );
				} }
			>
				<TextControl
					label={ __( 'Search questions', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ bank.error && (
				<Notice status="error" isDismissible={ false }>
					{ bank.error }
				</Notice>
			) }
			{ bank.loading && (
				<p role="status">{ __( 'Searching…', 'ohmylms' ) }</p>
			) }
			{ bank.data && (
				<ul aria-label={ __( 'Questions', 'ohmylms' ) }>
					{ bank.data.items.map( ( question ) => (
						<li key={ question.id }>
							<CheckboxControl
								label={ sprintf(
									/* translators: 1: question name; 2: marks. */
									__( '%1$s (%2$s marks)', 'ohmylms' ),
									question.name,
									String( question.marks )
								) }
								help={
									question.available
										? undefined
										: __(
												'Not available: it is shared and not approved, or not allowed in exams.',
												'ohmylms'
											)
								}
								disabled={ ! question.available }
								checked={ chosen.some(
									( item ) => item.id === question.id
								) }
								onChange={ ( checked ) =>
									setChosen( ( current ) =>
										checked
											? [ ...current, question ]
											: current.filter(
													( item ) =>
														item.id !== question.id
												)
									)
								}
							/>
						</li>
					) ) }
				</ul>
			) }
			{ bank.data && ! bank.data.items.length && (
				<p>{ __( 'No questions found.', 'ohmylms' ) }</p>
			) }
			{ bank.data && bank.data.totalPages > 1 && (
				<p>
					<Button
						variant="tertiary"
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'ohmylms' ) }
					</Button>
					<Button
						variant="tertiary"
						disabled={ page >= bank.data.totalPages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'ohmylms' ) }
					</Button>
				</p>
			) }
			<Button
				variant="primary"
				disabled={ ! chosen.length }
				onClick={ () => onAdd( chosen ) }
			>
				{ sprintf(
					/* translators: %d: number of questions. */
					__( 'Add %d questions', 'ohmylms' ),
					chosen.length
				) }
			</Button>{ ' ' }
			<Button variant="tertiary" onClick={ onClose }>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}

/**
 * Exam editor (`#/exams/<id>`): paper settings, header and timing, then the composition of sections and
 * bank questions, and publication. Saves of a copy changed elsewhere are refused (409).
 */
export default function ExamEditorPage() {
	const params = useParams();
	const id = routeId( params.id );
	const load = useCallback(
		( signal: AbortSignal ) =>
			null === id
				? Promise.reject( new Error( 'invalid id' ) )
				: getExam( id, signal ),
		[ id ]
	);
	const request = useRequest( load );
	const operation = useOperation();
	const [ exam, setExam ] = useState< ExamDetail | null >( null );
	const [ form, setForm ] = useState< SettingsForm | null >( null );
	const [ saved, setSaved ] = useState< SettingsForm | null >( null );
	const [ items, setItems ] = useState< CompositionItem[] >( [] );
	const [ savedItems, setSavedItems ] = useState< CompositionItem[] >( [] );
	const [ errors, setErrors ] = useState< SettingsErrors >( {} );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ failure, setFailure ] = useState< ApiError | null >( null );
	const [ picking, setPicking ] = useState( false );
	const [ publishing, setPublishing ] = useState( false );
	const [ overlap, setOverlap ] = useState< string[] >( [] );
	const settingsDirty = Boolean(
		form && saved && isFormDirty( form, saved )
	);
	const itemsDirty = JSON.stringify( items ) !== JSON.stringify( savedItems );
	const guard = useUnsavedChangesGuard( settingsDirty || itemsDirty );

	const adopt = useCallback(
		( next: ExamDetail, keep: 'none' | 'settings' | 'items' = 'none' ) => {
			const nextForm = formFromExam( next );
			setExam( next );
			setSaved( nextForm );
			setSavedItems( next.composition );
			if ( 'settings' !== keep ) {
				setForm( nextForm );
			}
			if ( 'items' !== keep ) {
				setItems( next.composition );
			}
			setFailure( null );
		},
		[]
	);

	useEffect( () => {
		if ( request.data ) {
			adopt( request.data );
		}
	}, [ request.data, adopt ] );

	if ( null === id ) {
		return (
			<>
				<h2>{ __( 'Exam', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This exam link is invalid.', 'ohmylms' ) }
				</Notice>
				<Link to="/exams">{ __( 'Back to exams', 'ohmylms' ) }</Link>
			</>
		);
	}
	if ( request.loading && ! exam ) {
		return (
			<>
				<h2>{ __( 'Exam', 'ohmylms' ) }</h2>
				<p role="status">{ __( 'Loading exam…', 'ohmylms' ) }</p>
			</>
		);
	}
	if ( ! exam || ! form || ! saved ) {
		return (
			<>
				<h2>{ __( 'Exam', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ request.error ||
						__( 'The exam could not be loaded.', 'ohmylms' ) }
				</Notice>
				<p>
					<Button variant="secondary" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>{ ' ' }
					<Link to="/exams">
						{ __( 'Back to exams', 'ohmylms' ) }
					</Link>
				</p>
			</>
		);
	}

	const editable = exam.permissions.can_edit;
	const composable = exam.permissions.can_compose;
	const update = < K extends keyof SettingsForm >(
		key: K,
		value: SettingsForm[ K ]
	) => {
		setForm( ( previous ) =>
			previous ? { ...previous, [ key ]: value } : previous
		);
		setMessage( null );
	};
	const setPaper = < K extends keyof PaperSettings >(
		key: K,
		value: PaperSettings[ K ]
	) => update( 'paper', { ...form.paper, [ key ]: value } );
	const setItem = ( index: number, next: CompositionItem ) =>
		setItems( ( current ) =>
			current.map( ( item, at ) => ( at === index ? next : item ) )
		);

	function run(
		work: ( signal: AbortSignal ) => Promise< ExamDetail >,
		done: ( result: ExamDetail ) => void
	) {
		setMessage( null );
		setFailure( null );
		void operation.run( async ( signal ) => {
			try {
				return await work( signal );
			} catch ( problem ) {
				const error = normalizeApiError( problem );
				if ( ! signal.aborted ) {
					setFailure( error );
				}
				throw error;
			}
		}, done );
	}

	function saveSettings() {
		if ( ! form || ! exam ) {
			return;
		}
		const problems = validateSettings( form );
		setErrors( problems );
		if ( Object.keys( problems ).length ) {
			setMessage( null );
			return;
		}
		const input = toSettingsInput( form, exam.revision );
		run(
			( signal ) => updateExam( exam.id, input, signal ),
			( result ) => {
				adopt( result, itemsDirty ? 'items' : 'none' );
				setMessage( __( 'Exam settings saved.', 'ohmylms' ) );
			}
		);
	}

	function saveItems() {
		if ( ! exam ) {
			return;
		}
		const problem = compositionProblem( items );
		if ( problem ) {
			setFailure(
				new ApiError( {
					status: 422,
					code: 'ohmylms_exam_invalid_field',
					message: problem,
				} )
			);
			return;
		}
		run(
			( signal ) =>
				saveComposition(
					exam.id,
					toCompositionInput( items ),
					exam.revision,
					signal
				),
			( result ) => {
				adopt( result, settingsDirty ? 'settings' : 'none' );
				setMessage( __( 'Questions and sections saved.', 'ohmylms' ) );
			}
		);
	}

	function publish( confirmOverlap: boolean ) {
		if ( ! exam ) {
			return;
		}
		setOverlap( [] );
		setMessage( null );
		setFailure( null );
		void operation.run(
			async ( signal ) => {
				try {
					return await publishExam(
						exam.id,
						exam.revision,
						confirmOverlap,
						signal
					);
				} catch ( problem ) {
					const error = normalizeApiError( problem );
					if ( ! signal.aborted ) {
						if ( 'exam_overlap' === error.code ) {
							setOverlap( [ error.message ] );
						} else {
							setFailure( error );
							setPublishing( false );
						}
					}
					throw error;
				}
			},
			( result ) => {
				setPublishing( false );
				adopt( result );
				setMessage( __( 'Exam published.', 'ohmylms' ) );
			}
		);
	}

	const marks = compositionMarks( items );
	return (
		<>
			<p>
				<Link to="/exams">{ __( 'Back to exams', 'ohmylms' ) }</Link>
				{ exam.job_id > 0 && (
					<>
						{ ' ' }
						<Link to={ reviewPath( exam.job_id ) }>
							{ __( 'Imported paper review', 'ohmylms' ) }
						</Link>
					</>
				) }
			</p>
			<h2>
				{ sprintf(
					/* translators: %s: exam title. */
					__( 'Edit exam: %s', 'ohmylms' ),
					exam.title
				) }
			</h2>
			<p>
				{ statusLabel( exam.status ) } ·{ ' ' }
				{ sprintf(
					/* translators: 1: questions; 2: marks. */
					__( '%1$d questions, %2$s marks', 'ohmylms' ),
					exam.question_count,
					String( exam.total_marks )
				) }
			</p>
			<p>
				{ exam.links.preview && (
					<a
						href={ exam.links.preview }
						target="_blank"
						rel="noreferrer"
					>
						{ __( 'Try exam', 'ohmylms' ) }
					</a>
				) }{ ' ' }
				{ exam.links.paper && (
					<a
						href={ exam.links.paper }
						target="_blank"
						rel="noreferrer"
					>
						{ __( 'Print paper', 'ohmylms' ) }
					</a>
				) }{ ' ' }
				{ exam.links.key && (
					<a href={ exam.links.key } target="_blank" rel="noreferrer">
						{ __( 'Print answer key', 'ohmylms' ) }
					</a>
				) }{ ' ' }
				{ exam.links.editor && editable && (
					<a href={ exam.links.editor }>
						{ __( 'Open the paper editor', 'ohmylms' ) }
					</a>
				) }
			</p>
			{ ! editable && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'This exam is published and can no longer be edited.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ ( settingsDirty || itemsDirty ) && (
				<p role="status">
					{ __( 'You have unsaved changes.', 'ohmylms' ) }
				</p>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ failure && (
				<Notice status="error" isDismissible={ false }>
					{ failure.message }
					{ 409 === failure.status &&
						'ohmylms_exam_conflict' === failure.code && (
							<>
								{ ' ' }
								<Button
									variant="link"
									onClick={ request.retry }
								>
									{ __(
										'Reload the latest version',
										'ohmylms'
									) }
								</Button>
							</>
						) }
				</Notice>
			) }

			<form
				aria-label={ __( 'Exam settings', 'ohmylms' ) }
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					saveSettings();
				} }
			>
				<fieldset disabled={ operation.busy || ! editable }>
					<legend>{ __( 'Details', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Title', 'ohmylms' ) }
						value={ form.title }
						maxLength={ LIMITS.title }
						aria-invalid={ errors.title ? true : undefined }
						onChange={ ( next ) => update( 'title', next ) }
					/>
					{ errors.title && <p role="alert">{ errors.title }</p> }
					<TextareaControl
						label={ __( 'Instructions for learners', 'ohmylms' ) }
						value={ form.instructions }
						rows={ 4 }
						onChange={ ( next ) => update( 'instructions', next ) }
					/>
					{ HEADER_FIELDS.map( ( field ) => (
						<TextControl
							key={ field }
							label={ headerLabel( field ) }
							value={ form.header[ field ] }
							maxLength={ LIMITS.header }
							onChange={ ( next ) =>
								update( 'header', {
									...form.header,
									[ field ]: next,
								} )
							}
						/>
					) ) }
				</fieldset>
				<fieldset disabled={ operation.busy || ! editable }>
					<legend>{ __( 'Timing', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Duration (minutes)', 'ohmylms' ) }
						value={ form.duration_minutes }
						inputMode="numeric"
						aria-invalid={
							errors.duration_minutes ? true : undefined
						}
						onChange={ ( next ) =>
							update( 'duration_minutes', next )
						}
					/>
					{ errors.duration_minutes && (
						<p role="alert">{ errors.duration_minutes }</p>
					) }
					<TextControl
						type="datetime-local"
						label={ __( 'Opens (UTC)', 'ohmylms' ) }
						value={ form.available_from }
						onChange={ ( next ) =>
							update( 'available_from', next )
						}
					/>
					<TextControl
						type="datetime-local"
						label={ __( 'Closes (UTC)', 'ohmylms' ) }
						value={ form.available_until }
						onChange={ ( next ) =>
							update( 'available_until', next )
						}
					/>
					{ errors.timing && <p role="alert">{ errors.timing }</p> }
					<TextControl
						label={ __( 'Grace period (seconds)', 'ohmylms' ) }
						value={ form.grace_seconds }
						inputMode="numeric"
						onChange={ ( next ) => update( 'grace_seconds', next ) }
					/>
					{ errors.grace_seconds && (
						<p role="alert">{ errors.grace_seconds }</p>
					) }
					<SelectControl
						label={ __( 'Show results', 'ohmylms' ) }
						value={ form.feedback_release }
						options={ FEEDBACK.map( ( value ) => ( {
							value,
							label: feedbackLabel( value ),
						} ) ) }
						onChange={ ( next ) => {
							const found = FEEDBACK.find(
								( value ) => value === next
							);
							if ( found ) {
								update( 'feedback_release', found );
							}
						} }
					/>
				</fieldset>
				<fieldset disabled={ operation.busy || ! editable }>
					<legend>{ __( 'Printable paper', 'ohmylms' ) }</legend>
					<SelectControl
						label={ __( 'Paper size', 'ohmylms' ) }
						value={ form.paper.size }
						options={ [
							{ value: 'a4', label: 'A4' },
							{
								value: 'letter',
								label: __( 'Letter', 'ohmylms' ),
							},
						] }
						onChange={ ( next ) =>
							setPaper(
								'size',
								'letter' === next ? 'letter' : 'a4'
							)
						}
					/>
					<SelectControl
						label={ __( 'Font', 'ohmylms' ) }
						value={ form.paper.font }
						options={ [
							{
								value: 'sans',
								label: __( 'Sans serif', 'ohmylms' ),
							},
							{ value: 'serif', label: __( 'Serif', 'ohmylms' ) },
						] }
						onChange={ ( next ) =>
							setPaper(
								'font',
								'serif' === next ? 'serif' : 'sans'
							)
						}
					/>
					<SelectControl
						label={ __( 'Question numbers', 'ohmylms' ) }
						value={ form.paper.numbering }
						options={ [
							{ value: 'number', label: '1, 2, 3' },
							{ value: 'q', label: 'Q1, Q2, Q3' },
						] }
						onChange={ ( next ) =>
							setPaper(
								'numbering',
								'q' === next ? 'q' : 'number'
							)
						}
					/>
					<SelectControl
						label={ __( 'Versions', 'ohmylms' ) }
						value={ String( form.paper.versions ) }
						options={ [ 1, 2, 3, 4 ].map( ( value ) => ( {
							value: String( value ),
							label: String( value ),
						} ) ) }
						onChange={ ( next ) =>
							setPaper(
								'versions',
								Math.min(
									4,
									Math.max( 1, Number( next ) || 1 )
								)
							)
						}
					/>
					<CheckboxControl
						label={ __( 'Show marks', 'ohmylms' ) }
						checked={ form.paper.show_marks }
						onChange={ ( next ) => setPaper( 'show_marks', next ) }
					/>
					<CheckboxControl
						label={ __( 'Page numbers', 'ohmylms' ) }
						checked={ form.paper.page_numbers }
						onChange={ ( next ) =>
							setPaper( 'page_numbers', next )
						}
					/>
				</fieldset>
				{ editable && (
					<Button
						type="submit"
						variant="primary"
						disabled={ operation.busy || ! settingsDirty }
						isBusy={ operation.busy }
					>
						{ __( 'Save settings', 'ohmylms' ) }
					</Button>
				) }
			</form>

			<section aria-labelledby="oml-exam-compose">
				<h3 id="oml-exam-compose">
					{ __( 'Questions and sections', 'ohmylms' ) }
				</h3>
				<p>
					{ sprintf(
						/* translators: %s: total marks. */
						__( 'Total: %s marks', 'ohmylms' ),
						String( marks )
					) }
				</p>
				{ editable && ! composable && (
					<p>
						{ __(
							'The questions of an imported exam come from its reviewed paper. You can add and arrange sections.',
							'ohmylms'
						) }
					</p>
				) }
				{ items.length ? (
					<ol aria-label={ __( 'Composition', 'ohmylms' ) }>
						{ items.map( ( item, index ) => (
							<li
								key={
									'section' === item.kind
										? `s-${ index }`
										: `q-${ item.question_id }`
								}
							>
								{ 'section' === item.kind ? (
									<>
										<TextControl
											label={ __(
												'Section title',
												'ohmylms'
											) }
											value={ item.title }
											maxLength={ LIMITS.title }
											disabled={
												! editable || operation.busy
											}
											onChange={ ( title ) =>
												setItem( index, {
													...item,
													title,
												} )
											}
										/>
										<CheckboxControl
											label={ __(
												'Start on a new page',
												'ohmylms'
											) }
											checked={ item.new_page }
											disabled={
												! editable || operation.busy
											}
											onChange={ ( newPage ) =>
												setItem( index, {
													...item,
													new_page: newPage,
												} )
											}
										/>
									</>
								) : (
									<span>
										{ sprintf(
											/* translators: 1: question name; 2: marks. */
											__(
												'%1$s — %2$s marks',
												'ohmylms'
											),
											item.name,
											String( item.marks )
										) }
										{ ! item.approved &&
											` (${ __( 'not approved yet', 'ohmylms' ) })` }
									</span>
								) }
								{ editable &&
									( composable ||
										'section' === item.kind ) && (
										<>
											{ ' ' }
											<Button
												variant="tertiary"
												size="small"
												disabled={
													0 === index ||
													operation.busy
												}
												onClick={ () =>
													setItems(
														moveItem(
															items,
															index,
															-1
														)
													)
												}
												aria-label={ __(
													'Move up',
													'ohmylms'
												) }
											>
												↑
											</Button>
											<Button
												variant="tertiary"
												size="small"
												disabled={
													items.length - 1 ===
														index || operation.busy
												}
												onClick={ () =>
													setItems(
														moveItem(
															items,
															index,
															1
														)
													)
												}
												aria-label={ __(
													'Move down',
													'ohmylms'
												) }
											>
												↓
											</Button>
											<Button
												variant="link"
												isDestructive
												disabled={ operation.busy }
												onClick={ () =>
													setItems(
														items.filter(
															( _, at ) =>
																at !== index
														)
													)
												}
											>
												{ __( 'Remove', 'ohmylms' ) }
											</Button>
										</>
									) }
							</li>
						) ) }
					</ol>
				) : (
					<p>{ __( 'No questions yet.', 'ohmylms' ) }</p>
				) }
				{ editable && (
					<p>
						<Button
							variant="secondary"
							disabled={ operation.busy }
							onClick={ () =>
								setItems( [
									...items,
									{
										kind: 'section',
										title: __( 'New section', 'ohmylms' ),
										new_page: false,
									},
								] )
							}
						>
							{ __( 'Add section', 'ohmylms' ) }
						</Button>{ ' ' }
						{ composable && (
							<Button
								variant="secondary"
								disabled={ operation.busy }
								onClick={ () => setPicking( true ) }
							>
								{ __( 'Add questions', 'ohmylms' ) }
							</Button>
						) }{ ' ' }
						<Button
							variant="primary"
							disabled={ operation.busy || ! itemsDirty }
							isBusy={ operation.busy }
							onClick={ saveItems }
						>
							{ __( 'Save questions and sections', 'ohmylms' ) }
						</Button>
					</p>
				) }
			</section>

			{ editable && (
				<section aria-labelledby="oml-exam-publish">
					<h3 id="oml-exam-publish">
						{ __( 'Publish', 'ohmylms' ) }
					</h3>
					{ exam.problems.length > 0 && (
						<ul>
							{ exam.problems.map( ( problem ) => (
								<li key={ problem }>{ problem }</li>
							) ) }
						</ul>
					) }
					{ exam.permissions.can_publish && (
						<Button
							variant="primary"
							disabled={
								operation.busy ||
								settingsDirty ||
								itemsDirty ||
								exam.problems.length > 0
							}
							onClick={ () => setPublishing( true ) }
						>
							{ __( 'Publish exam', 'ohmylms' ) }
						</Button>
					) }
				</section>
			) }

			{ picking && (
				<BankPicker
					examId={ exam.id }
					onClose={ () => setPicking( false ) }
					onAdd={ ( questions ) => {
						setItems( addQuestions( items, questions ) );
						setPicking( false );
					} }
				/>
			) }
			{ publishing && (
				<Modal
					title={ __( 'Publish exam', 'ohmylms' ) }
					onRequestClose={ () =>
						! operation.busy && setPublishing( false )
					}
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ __(
							'Publishing approves the current version of every question and makes them exam-only. Published exams cannot be edited.',
							'ohmylms'
						) }
					</p>
					{ exam.warnings.concat( overlap ).map( ( warning ) => (
						<Notice
							key={ warning }
							status="warning"
							isDismissible={ false }
						>
							{ warning }
						</Notice>
					) ) }
					<Button
						variant="primary"
						disabled={ operation.busy }
						isBusy={ operation.busy }
						onClick={ () =>
							publish(
								exam.warnings.length > 0 || overlap.length > 0
							)
						}
					>
						{ exam.warnings.length || overlap.length
							? __( 'Publish anyway', 'ohmylms' )
							: __( 'Publish', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setPublishing( false ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Discard unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ __(
							'Your changes to this exam have not been saved.',
							'ohmylms'
						) }
					</p>
					<Button variant="primary" onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						isDestructive
						onClick={ guard.leave }
					>
						{ __( 'Discard changes', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
