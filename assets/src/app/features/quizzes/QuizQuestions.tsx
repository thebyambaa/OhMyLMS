import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import {
	lazy,
	Suspense,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { WidefatTable } from '../../components/WidefatTable';
import { ApiError, normalizeApiError } from '../../lib/api';
import {
	listQuestionTypes,
	MAX_NAME,
	questionEditorPath,
	type QuestionCreateInput,
	type QuestionType,
} from '../questions/public';
import {
	deliveryLabel,
	formatMarks,
	moveQuestion,
	slotName,
	slotWarning,
} from './model';
import { QuestionPicker } from './QuestionPicker';
import { MarksDialog, PoolsDialog, SectionsDialog } from './QuizLayoutDialogs';
import {
	addQuizQuestions,
	copyQuizQuestions,
	createQuizQuestion,
	listQuizQuestions,
	pinQuizQuestion,
	QUESTIONS_CONFLICT,
	removeQuizQuestion,
	reorderQuizQuestions,
	saveQuizPools,
	saveQuizSections,
	setQuizQuestionMarks,
} from './questionsApi';
import type {
	PinChoice,
	QuizPoolInput,
	QuizQuestionCreated,
	QuizQuestions as QuestionList,
	QuizQuestionSlot,
	QuizSection,
} from './types';

const QuestionDraftEditor = lazy(
	() =>
		import(
			/* webpackChunkName: "admin-app-questions" */ '../questions/draft/public'
		)
);

interface Draft {
	name: string;
	type: string;
}

function defaultType( types: readonly QuestionType[] ): string {
	return (
		types.find( ( type ) => 'single-choice' === type.id )?.id ??
		types[ 0 ]?.id ??
		''
	);
}

/**
 * The Questions section of the quiz editor: the questions of the quiz in order with type, marks and the
 * version learners get, the total marks and the random pools. Authors add questions from the question
 * bank, create a question in place (the question bank's draft editor, saved into this quiz), reorder
 * with move buttons, set a question's marks in this quiz, pin or unpin versions, remove questions, and
 * edit the paper's sections and the random pools. Every change is saved at once with the revision of
 * the loaded list; a list changed by someone else is reloaded instead of overwritten.
 *
 * @param props
 * @param props.quizId        Quiz id.
 * @param props.readOnly      The user cannot change the quiz (no edit rights, or in the trash).
 * @param props.onDirtyChange Reports an unsaved question draft; pass a stable function.
 * @param props.onTotalChange Receives the total marks whenever the list loads or changes.
 */
export function QuizQuestions( {
	quizId,
	readOnly,
	onDirtyChange,
	onTotalChange,
}: {
	quizId: number;
	readOnly: boolean;
	onDirtyChange: ( dirty: boolean ) => void;
	onTotalChange: ( total: number ) => void;
} ) {
	const [ data, setData ] = useState< QuestionList | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState< ApiError | null >( null );
	const [ attempt, setAttempt ] = useState( 0 );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ failure, setFailure ] = useState< string | null >( null );
	const [ picking, setPicking ] = useState( false );
	const [ naming, setNaming ] = useState( false );
	const [ name, setName ] = useState( '' );
	const [ type, setType ] = useState( '' );
	const [ nameError, setNameError ] = useState< string | null >( null );
	const [ draft, setDraft ] = useState< Draft | null >( null );
	const [ types, setTypes ] = useState< QuestionType[] | null >( null );
	const [ typesError, setTypesError ] = useState< string | null >( null );
	const [ removing, setRemoving ] = useState< QuizQuestionSlot | null >(
		null
	);
	const [ marking, setMarking ] = useState< QuizQuestionSlot | null >( null );
	const [ layout, setLayout ] = useState< 'sections' | 'pools' | null >(
		null
	);
	const dialog = picking || removing || marking || layout;
	const [ focus, setFocus ] = useState< string[] >( [] );
	const operation = useRef< AbortController | null >( null );
	const revision = useRef( '' );
	const summary = useRef< HTMLDivElement >( null );
	const controls = useRef( new Map< string, HTMLButtonElement >() );
	const reload = useCallback(
		() => setAttempt( ( current ) => current + 1 ),
		[]
	);

	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setLoadError( null );
		listQuizQuestions( quizId, controller.signal ).then(
			( loaded ) => {
				if ( ! controller.signal.aborted ) {
					setData( loaded );
					setLoading( false );
				}
			},
			( problem: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setLoadError( normalizeApiError( problem ) );
					setLoading( false );
				}
			}
		);
		return () => controller.abort();
	}, [ quizId, attempt ] );

	useEffect( () => () => operation.current?.abort(), [] );

	useEffect( () => {
		if ( data ) {
			revision.current = data.revision;
			onTotalChange( data.totals.total_marks );
		}
	}, [ data, onTotalChange ] );

	useEffect( () => {
		if ( ! draft ) {
			onDirtyChange( false );
		}
	}, [ draft, onDirtyChange ] );

	useEffect( () => {
		if ( ! focus.length ) {
			return;
		}
		const target = focus
			.map( ( key ) => controls.current.get( key ) )
			.find( ( element ) => element && ! element.disabled );
		( target ?? summary.current )?.focus();
		setFocus( [] );
	}, [ data, focus ] );

	const needTypes = useCallback( () => {
		if ( types ) {
			return;
		}
		setTypesError( null );
		listQuestionTypes().then( setTypes, ( problem: unknown ) =>
			setTypesError( normalizeApiError( problem ).message )
		);
	}, [ types ] );

	const register =
		( key: string ) => ( element: HTMLButtonElement | null ) => {
			if ( element ) {
				controls.current.set( key, element );
			} else {
				controls.current.delete( key );
			}
		};

	/**
	 * Run one change against the loaded revision. A stale revision reloads the list.
	 *
	 * @param work    Request taking the revision.
	 * @param success Message for the result.
	 * @param after   Controls to focus afterwards, in order of preference.
	 * @return Whether the change was saved.
	 */
	async function change(
		work: ( base: string, signal: AbortSignal ) => Promise< QuestionList >,
		success: ( result: QuestionList ) => string,
		after: string[] = []
	): Promise< boolean > {
		if ( operation.current ) {
			return false;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setMessage( null );
		setFailure( null );
		try {
			const result = await work( revision.current, controller.signal );
			if ( controller.signal.aborted ) {
				return false;
			}
			setData( result );
			setMessage( success( result ) );
			setFocus( after );
			return true;
		} catch ( problem ) {
			if ( controller.signal.aborted ) {
				return false;
			}
			const error = normalizeApiError( problem );
			if ( QUESTIONS_CONFLICT === error.code ) {
				setFailure(
					`${ error.message } ${ __(
						'The list has been reloaded and nothing was changed. Check it and try again.',
						'ohmylms'
					) }`
				);
				reload();
			} else {
				setFailure( error.message );
			}
			summary.current?.focus();
			return false;
		} finally {
			if ( ! controller.signal.aborted ) {
				operation.current = null;
				setBusy( false );
			}
		}
	}

	if ( loadError && ! data ) {
		return (
			<section aria-labelledby="oml-quiz-questions">
				<h3 id="oml-quiz-questions">
					{ __( 'Questions', 'ohmylms' ) }
				</h3>
				<Notice status="error" isDismissible={ false }>
					{ loadError.message }
				</Notice>
				{ 403 !== loadError.status && 401 !== loadError.status && (
					<Button variant="secondary" onClick={ reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				) }
			</section>
		);
	}
	if ( ! data ) {
		return (
			<section aria-labelledby="oml-quiz-questions">
				<h3 id="oml-quiz-questions">
					{ __( 'Questions', 'ohmylms' ) }
				</h3>
				<p role="status">{ __( 'Loading questions…', 'ohmylms' ) }</p>
			</section>
		);
	}

	const slots = data.questions;
	const ids = slots.map( ( slot ) => slot.question_id );
	const locked = readOnly || busy || null !== draft;

	function move( index: number, delta: -1 | 1 ) {
		const slot = slots[ index ];
		const order = moveQuestion( ids, index, delta );
		if ( ! slot || ! order ) {
			return;
		}
		const direction = delta < 0 ? 'up' : 'down';
		const other = delta < 0 ? 'down' : 'up';
		void change(
			( base, signal ) =>
				reorderQuizQuestions( quizId, base, order, signal ),
			() =>
				sprintf(
					/* translators: 1: question name, 2: new position. */
					__( 'Moved %1$s to position %2$d.', 'ohmylms' ),
					slotName( slot ),
					index + delta + 1
				),
			[
				`${ slot.question_id }:${ direction }`,
				`${ slot.question_id }:${ other }`,
			]
		);
	}

	function pin( slot: QuizQuestionSlot, choice: PinChoice ) {
		void change(
			( base, signal ) =>
				pinQuizQuestion(
					quizId,
					base,
					slot.question_id,
					choice,
					signal
				),
			( result ) => {
				const updated = result.questions.find(
					( item ) => item.question_id === slot.question_id
				);
				return null === choice || ! updated
					? sprintf(
							/* translators: %s: question name. */
							__(
								'%s now follows its latest usable version.',
								'ohmylms'
							),
							slotName( slot )
						)
					: sprintf(
							/* translators: 1: question name, 2: version number. */
							__( 'Pinned %1$s to version %2$d.', 'ohmylms' ),
							slotName( slot ),
							updated.pinned_version_no
						);
			},
			[ `${ slot.question_id }:pin` ]
		);
	}

	function remove( slot: QuizQuestionSlot ) {
		void change(
			( base, signal ) =>
				removeQuizQuestion( quizId, base, slot.question_id, signal ),
			() =>
				sprintf(
					/* translators: %s: question name. */
					__( 'Removed %s from the quiz.', 'ohmylms' ),
					slotName( slot )
				)
		).then( ( saved ) => {
			if ( saved ) {
				setRemoving( null );
			}
		} );
	}

	function saveMarks( slot: QuizQuestionSlot, marks: number | null ) {
		void change(
			( base, signal ) =>
				setQuizQuestionMarks(
					quizId,
					base,
					slot.question_id,
					marks,
					signal
				),
			( result ) => {
				const updated = result.questions.find(
					( item ) => item.question_id === slot.question_id
				);
				return sprintf(
					/* translators: 1: question name, 2: marks. */
					__( '%1$s is now worth %2$s in this quiz.', 'ohmylms' ),
					slotName( slot ),
					formatMarks( updated?.marks ?? marks ?? 0 )
				);
			},
			[ `${ slot.question_id }:marks` ]
		).then( ( saved ) => {
			if ( saved ) {
				setMarking( null );
			}
		} );
	}

	function saveSections( sections: QuizSection[] ) {
		void change(
			( base, signal ) =>
				saveQuizSections( quizId, base, sections, signal ),
			() => __( 'Sections saved.', 'ohmylms' )
		).then( ( saved ) => {
			if ( saved ) {
				setLayout( null );
			}
		} );
	}

	function savePools( pools: QuizPoolInput[] ) {
		void change(
			( base, signal ) => saveQuizPools( quizId, base, pools, signal ),
			() => __( 'Random pools saved.', 'ohmylms' )
		).then( ( saved ) => {
			if ( saved ) {
				setLayout( null );
			}
		} );
	}

	function add( questionIds: number[], pinned: boolean, copy: boolean ) {
		void change(
			( base, signal ) =>
				copy
					? copyQuizQuestions( quizId, base, questionIds, signal )
					: addQuizQuestions(
							quizId,
							base,
							questionIds,
							pinned,
							signal
						),
			() =>
				copy
					? sprintf(
							/* translators: %d: number of questions. */
							_n(
								'Added a copy of %d question.',
								'Added copies of %d questions.',
								questionIds.length,
								'ohmylms'
							),
							questionIds.length
						)
					: sprintf(
							/* translators: %d: number of questions. */
							_n(
								'Added %d question.',
								'Added %d questions.',
								questionIds.length,
								'ohmylms'
							),
							questionIds.length
						)
		).then( ( saved ) => {
			if ( saved ) {
				setPicking( false );
			}
		} );
	}

	async function create( input: QuestionCreateInput, signal: AbortSignal ) {
		try {
			return await createQuizQuestion(
				quizId,
				revision.current,
				input,
				signal
			);
		} catch ( problem ) {
			if ( QUESTIONS_CONFLICT === normalizeApiError( problem ).code ) {
				reload();
			}
			throw problem;
		}
	}

	function created( result: QuizQuestionCreated ) {
		const slot = result.questions.find(
			( item ) => item.question_id === result.created_id
		);
		setData( result );
		setDraft( null );
		setFailure( null );
		setMessage(
			sprintf(
				/* translators: %s: question name. */
				__( 'Created %s and added it to the quiz.', 'ohmylms' ),
				slot ? slotName( slot ) : __( 'the question', 'ohmylms' )
			)
		);
		setFocus( [] );
		summary.current?.focus();
	}

	function openPicker() {
		needTypes();
		setFailure( null );
		setMessage( null );
		setPicking( true );
	}

	function openNaming() {
		needTypes();
		setName( '' );
		setType( '' );
		setNameError( null );
		setMessage( null );
		setNaming( true );
	}

	const chosenType = type || defaultType( types ?? [] );

	function startDraft() {
		const value = name.trim();
		if ( ! value || Array.from( value ).length > MAX_NAME ) {
			setNameError(
				sprintf(
					/* translators: %d: maximum name length. */
					__( 'Enter a question of 1 to %d characters.', 'ohmylms' ),
					MAX_NAME
				)
			);
			return;
		}
		setNaming( false );
		setDraft( { name: value, type: chosenType } );
	}

	const totals = data.totals;

	return (
		<section aria-labelledby="oml-quiz-questions">
			<h3 id="oml-quiz-questions">{ __( 'Questions', 'ohmylms' ) }</h3>
			<p>
				{ sprintf(
					/* translators: 1: number of questions, 2: total marks. */
					_n(
						'%1$d question · Total marks: %2$s',
						'%1$d questions · Total marks: %2$s',
						totals.questions,
						'ohmylms'
					),
					totals.questions,
					formatMarks( totals.total_marks )
				) }
				{ totals.pool_questions > 0 && (
					<>
						{ ' ' }
						{ sprintf(
							/* translators: 1: number of random questions, 2: their marks. */
							_n(
								'(includes %1$d random question worth %2$s marks)',
								'(includes %1$d random questions worth %2$s marks)',
								totals.pool_questions,
								'ohmylms'
							),
							totals.pool_questions,
							formatMarks( totals.pool_marks )
						) }
					</>
				) }
			</p>
			{ loading && (
				<p role="status">
					{ __( 'Refreshing questions…', 'ohmylms' ) }
				</p>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			<div ref={ summary } tabIndex={ -1 }>
				{ failure && ! dialog && (
					<Notice status="error" isDismissible={ false }>
						{ failure }
					</Notice>
				) }
				{ loadError && (
					<Notice status="error" isDismissible={ false }>
						{ loadError.message }{ ' ' }
						<Button variant="link" onClick={ reload }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					</Notice>
				) }
			</div>

			{ draft && types ? (
				<Suspense
					fallback={
						<p role="status">
							{ __( 'Loading the question editor…', 'ohmylms' ) }
						</p>
					}
				>
					<QuestionDraftEditor
						name={ draft.name }
						type={ draft.type }
						types={ types }
						save={ create }
						onSaved={ ( result ) =>
							created( result as QuizQuestionCreated )
						}
						onCancel={ () => setDraft( null ) }
						saveLabel={ __( 'Create and add to quiz', 'ohmylms' ) }
						onDirtyChange={ onDirtyChange }
						previewPostId={ quizId }
					/>
				</Suspense>
			) : (
				<>
					{ ! readOnly && (
						<p>
							<Button
								variant="secondary"
								disabled={ locked }
								onClick={ openPicker }
							>
								{ __( 'Add from question bank', 'ohmylms' ) }
							</Button>{ ' ' }
							<Button
								variant="secondary"
								disabled={ locked }
								onClick={ openNaming }
							>
								{ __( 'Create question', 'ohmylms' ) }
							</Button>{ ' ' }
							<Button
								variant="secondary"
								disabled={ locked || ! slots.length }
								onClick={ () => {
									setFailure( null );
									setLayout( 'sections' );
								} }
							>
								{ __( 'Edit sections', 'ohmylms' ) }
							</Button>{ ' ' }
							<Button
								variant="secondary"
								disabled={ locked }
								onClick={ () => {
									setFailure( null );
									setLayout( 'pools' );
								} }
							>
								{ __( 'Edit random pools', 'ohmylms' ) }
							</Button>
						</p>
					) }
					{ slots.length ? (
						<WidefatTable
							caption={ __(
								'Questions in this quiz',
								'ohmylms'
							) }
						>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Position', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Question', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Type', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Marks', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Version', 'ohmylms' ) }
									</th>
									{ ! readOnly && (
										<th scope="col">
											{ __( 'Actions', 'ohmylms' ) }
										</th>
									) }
								</tr>
							</thead>
							<tbody>
								{ slots.map( ( slot, index ) => (
									<SlotRow
										key={ slot.question_id }
										slot={ slot }
										index={ index }
										last={ index === slots.length - 1 }
										readOnly={ readOnly }
										disabled={ locked }
										register={ register }
										onMove={ move }
										onPin={ pin }
										onMarks={ ( item ) => {
											setFailure( null );
											setMarking( item );
										} }
										onRemove={ ( item ) => {
											setFailure( null );
											setRemoving( item );
										} }
									/>
								) ) }
							</tbody>
						</WidefatTable>
					) : (
						<p>
							{ __(
								'This quiz has no questions yet. Add questions from the question bank or create one.',
								'ohmylms'
							) }
						</p>
					) }
				</>
			) }

			{ draft && ! types && (
				<p role="status">
					{ __( 'Loading question types…', 'ohmylms' ) }
				</p>
			) }

			{ data.pools.length > 0 && (
				<section aria-labelledby="oml-quiz-pools">
					<h4 id="oml-quiz-pools">
						{ __( 'Random pools', 'ohmylms' ) }
					</h4>
					<p>
						{ __(
							'Each attempt draws approved questions from these pools after the questions above.',
							'ohmylms'
						) }
					</p>
					<WidefatTable caption={ __( 'Random pools', 'ohmylms' ) }>
						<thead>
							<tr>
								<th scope="col">{ __( 'Pool', 'ohmylms' ) }</th>
								<th scope="col">
									{ __( 'Skill', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Questions drawn', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Marks each', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ data.pools.map( ( pool ) => (
								<tr key={ pool.id }>
									<th scope="row">
										{ pool.title ||
											__( 'Untitled pool', 'ohmylms' ) }
									</th>
									<td>
										{ pool.skill.name ||
											sprintf(
												/* translators: %d: skill id. */
												__( 'Skill #%d', 'ohmylms' ),
												pool.skill.id
											) }
									</td>
									<td>{ pool.count }</td>
									<td>{ formatMarks( pool.marks ) }</td>
								</tr>
							) ) }
						</tbody>
					</WidefatTable>
				</section>
			) }

			{ marking && (
				<MarksDialog
					slot={ marking }
					busy={ busy }
					error={ failure }
					onSave={ ( marks ) => saveMarks( marking, marks ) }
					onClose={ () => {
						setMarking( null );
						setFailure( null );
					} }
				/>
			) }

			{ 'sections' === layout && (
				<SectionsDialog
					sections={ data.sections }
					slots={ slots }
					busy={ busy }
					error={ failure }
					onSave={ saveSections }
					onClose={ () => {
						setLayout( null );
						setFailure( null );
					} }
				/>
			) }

			{ 'pools' === layout && (
				<PoolsDialog
					pools={ data.pools }
					busy={ busy }
					error={ failure }
					onSave={ savePools }
					onClose={ () => {
						setLayout( null );
						setFailure( null );
					} }
				/>
			) }

			{ picking && (
				<QuestionPicker
					inQuiz={ new Set( ids ) }
					types={ types }
					busy={ busy }
					error={ failure }
					onAdd={ add }
					onClose={ () => {
						setPicking( false );
						setFailure( null );
					} }
				/>
			) }

			{ naming && (
				<Modal
					title={ __( 'Create question', 'ohmylms' ) }
					onRequestClose={ () => setNaming( false ) }
					shouldCloseOnClickOutside={ false }
				>
					<form
						noValidate
						onSubmit={ ( event ) => {
							event.preventDefault();
							startDraft();
						} }
					>
						<p>
							{ __(
								'Next you complete the question. Saving creates it in the question bank and adds it to the end of this quiz.',
								'ohmylms'
							) }
						</p>
						<TextControl
							label={ __( 'Name', 'ohmylms' ) }
							value={ name }
							maxLength={ MAX_NAME }
							required
							aria-invalid={ nameError ? true : undefined }
							aria-describedby={
								nameError
									? 'oml-quiz-question-name-error'
									: undefined
							}
							onChange={ setName }
						/>
						{ nameError && (
							<p id="oml-quiz-question-name-error" role="alert">
								{ nameError }
							</p>
						) }
						{ types && types.length ? (
							<SelectControl
								label={ __( 'Type', 'ohmylms' ) }
								value={ chosenType }
								options={ types.map( ( item ) => ( {
									value: item.id,
									label: item.label,
								} ) ) }
								help={ __(
									'The type cannot be changed after the question is created.',
									'ohmylms'
								) }
								onChange={ setType }
							/>
						) : (
							<p role="status">
								{ typesError ??
									__( 'Loading question types…', 'ohmylms' ) }
							</p>
						) }
						<Button
							type="submit"
							variant="primary"
							disabled={ ! chosenType }
						>
							{ __( 'Continue', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							onClick={ () => setNaming( false ) }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
					</form>
				</Modal>
			) }

			{ removing && (
				<Modal
					title={ __( 'Remove question from quiz?', 'ohmylms' ) }
					onRequestClose={ () => setRemoving( null ) }
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ sprintf(
							/* translators: %s: question name. */
							__(
								'%s will be removed from this quiz. It stays in the question bank and in other quizzes, and past attempts keep their results.',
								'ohmylms'
							),
							slotName( removing )
						) }
					</p>
					{ failure && (
						<Notice status="error" isDismissible={ false }>
							{ failure }
						</Notice>
					) }
					<Button
						variant="primary"
						isDestructive
						disabled={ busy }
						isBusy={ busy }
						onClick={ () => remove( removing ) }
					>
						{ __( 'Remove question', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ () => setRemoving( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}

function SlotRow( {
	slot,
	index,
	last,
	readOnly,
	disabled,
	register,
	onMove,
	onPin,
	onMarks,
	onRemove,
}: {
	slot: QuizQuestionSlot;
	index: number;
	last: boolean;
	readOnly: boolean;
	disabled: boolean;
	register: ( key: string ) => ( element: HTMLButtonElement | null ) => void;
	onMove: ( index: number, delta: -1 | 1 ) => void;
	onPin: ( slot: QuizQuestionSlot, choice: PinChoice ) => void;
	onMarks: ( slot: QuizQuestionSlot ) => void;
	onRemove: ( slot: QuizQuestionSlot ) => void;
} ) {
	const name = slotName( slot );
	const warning = slotWarning( slot );
	const pinned = slot.pinned_version_id > 0;
	const pinLabel = pinned
		? sprintf(
				/* translators: %s: question name. */
				__( 'Unpin %s', 'ohmylms' ),
				name
			)
		: sprintf(
				/* translators: %s: question name. */
				__( 'Pin %s to the version used now', 'ohmylms' ),
				name
			);
	return (
		<tr>
			<td>{ slot.position }</td>
			<th scope="row">
				{ name }
				{ warning && (
					<>
						{ ' ' }
						<strong>({ warning })</strong>
					</>
				) }
				{ slot.permissions.can_use && ! slot.permissions.can_edit && (
					<>
						<br />
						<small>
							{ __( 'Shared question (read-only)', 'ohmylms' ) }
						</small>
					</>
				) }
				{ slot.section && (
					<>
						<br />
						<small>
							{ sprintf(
								/* translators: %s: section title. */
								__( 'Section: %s', 'ohmylms' ),
								slot.section
							) }
						</small>
					</>
				) }
			</th>
			<td>{ slot.type_label || slot.type || '—' }</td>
			<td>
				{ formatMarks( slot.marks ) }
				{ null !== slot.marks_override && (
					<>
						<br />
						<small>
							{ sprintf(
								/* translators: %s: points of the question. */
								__(
									'Set in this quiz (question: %s)',
									'ohmylms'
								),
								formatMarks( slot.question_marks )
							) }
						</small>
					</>
				) }
				{ ! readOnly && (
					<>
						<br />
						<Button
							ref={ register( `${ slot.question_id }:marks` ) }
							variant="link"
							disabled={ disabled }
							aria-label={ sprintf(
								/* translators: %s: question name. */
								__( 'Change the marks of %s', 'ohmylms' ),
								name
							) }
							onClick={ () => onMarks( slot ) }
						>
							{ __( 'Change marks', 'ohmylms' ) }
						</Button>
					</>
				) }
			</td>
			<td>{ deliveryLabel( slot ) }</td>
			{ ! readOnly && (
				<td>
					<Button
						ref={ register( `${ slot.question_id }:up` ) }
						variant="secondary"
						size="small"
						disabled={ disabled || 0 === index }
						aria-label={ sprintf(
							/* translators: %s: question name. */
							__( 'Move %s up', 'ohmylms' ),
							name
						) }
						onClick={ () => onMove( index, -1 ) }
					>
						{ __( 'Up', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						ref={ register( `${ slot.question_id }:down` ) }
						variant="secondary"
						size="small"
						disabled={ disabled || last }
						aria-label={ sprintf(
							/* translators: %s: question name. */
							__( 'Move %s down', 'ohmylms' ),
							name
						) }
						onClick={ () => onMove( index, 1 ) }
					>
						{ __( 'Down', 'ohmylms' ) }
					</Button>{ ' ' }
					{ slot.permissions.can_edit && (
						<>
							<Link
								to={ questionEditorPath( slot.question_id ) }
								aria-label={ sprintf(
									/* translators: %s: question name. */
									__( 'Edit %s', 'ohmylms' ),
									name
								) }
							>
								{ __( 'Edit', 'ohmylms' ) }
							</Link>{ ' ' }
						</>
					) }
					{ ( pinned || slot.permissions.can_use ) && (
						<>
							<Button
								ref={ register( `${ slot.question_id }:pin` ) }
								variant="link"
								disabled={ disabled }
								aria-label={ pinLabel }
								onClick={ () =>
									onPin( slot, pinned ? null : 'current' )
								}
							>
								{ pinned
									? __( 'Unpin', 'ohmylms' )
									: __( 'Pin version', 'ohmylms' ) }
							</Button>{ ' ' }
						</>
					) }
					<Button
						variant="link"
						isDestructive
						disabled={ disabled }
						aria-label={ sprintf(
							/* translators: %s: question name. */
							__( 'Remove %s', 'ohmylms' ),
							name
						) }
						onClick={ () => onRemove( slot ) }
					>
						{ __( 'Remove', 'ohmylms' ) }
					</Button>
				</td>
			) }
		</tr>
	);
}
