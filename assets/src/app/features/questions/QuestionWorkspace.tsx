import {
	Button,
	Modal,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import {
	Suspense,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { ComponentType } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ExtensionBoundary } from '../../extensions/ExtensionBoundary';
import { useExtensions } from '../../extensions/context';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { normalizeApiError } from '../../lib/api';
import { AddToQuizDialog } from './AddToQuizDialog';
import {
	approveQuestion,
	archiveQuestion,
	createQuestion,
	deleteQuestion,
	duplicateQuestion,
	restoreQuestion,
	updateQuestion,
	type PreviewQuestion,
} from './api';
import { AcceptedAnswers, BlankAnswers, ManualAnswer } from './AnswerFields';
import {
	answerArea,
	approvalLabel,
	canApproveCurrent,
	deleteOutcome,
	derivedPoints,
	DIFFICULTIES,
	difficultyLabel,
	formFromQuestion,
	isDirty,
	isValid,
	MAX_DESCRIPTION,
	MAX_NAME,
	MAX_POINTS,
	scoreMode,
	serverFieldError,
	settingsFor,
	settingsProblem,
	toCreateInput,
	toInput,
	typeLabel,
	usageLabel,
	validateForm,
	versionLabel,
} from './model';
import { errorId, FieldError, OptionsEditor } from './OptionsEditor';
import { PlayerPreview } from './PlayerPreview';
import {
	BankFields,
	MediaFields,
	SharedSettingsFields,
	SkillsFields,
} from './QuestionFields';
import type { QuestionHandoff } from './useQuestionEditor';
import { Versions } from './Versions';
import type {
	FormErrors,
	FormState,
	QuestionCreateInput,
	QuestionDetail,
	QuestionSettings,
	QuestionType,
	QuestionUsage,
} from './types';
import '../../styles/questions.css';

function deleteHelp( usage: QuestionUsage ): string {
	const outcome = deleteOutcome( usage );
	if ( 'blocked' === outcome ) {
		return sprintf(
			/* translators: %d: number of quizzes. */
			__(
				'This question is used in %d quizzes, so it cannot be deleted. Remove it from all but one quiz first.',
				'ohmylms'
			),
			usage.quizzes
		);
	}
	if ( 'archive' === outcome ) {
		return __(
			'Learners have answered this question, so deleting it archives it instead: their results keep it, and it leaves its quiz.',
			'ohmylms'
		);
	}
	return usage.quizzes
		? __(
				'Deleting removes the question, its versions and its place in its quiz. This cannot be undone.',
				'ohmylms'
			)
		: __(
				'Deleting removes the question and its versions. This cannot be undone.',
				'ohmylms'
			);
}

/** Route of the question bank list; the editor of a question is `${ LIST }/<id>`. */
export const LIST = '/content-hub/question-bank';

/** Props of a registered `question-editor` extension (public extension API v1). */
interface QuestionEditorProps {
	/** The editor view of the question as last loaded or saved. */
	question: QuestionDetail;
	/** Current settings. */
	value: QuestionSettings;
	/** Merges a patch into the settings; the question type is always kept. */
	onChange: ( patch: QuestionSettings ) => void;
}

/**
 * Where a draft is saved when another screen hosts the editor (for example a quiz that creates a
 * question and places it). The host guards unsaved edits; the editor does not navigate.
 */
export interface DraftTarget {
	/** Creates the question; rejects with an `ApiError` as `POST /questions` would. */
	save: (
		input: QuestionCreateInput,
		signal: AbortSignal
	) => Promise< unknown >;
	/** Called with what `save` resolved to. */
	done: ( result: unknown ) => void;
	/** Leaves the draft without saving. */
	cancel: () => void;
	/** Label of the save button. */
	saveLabel: string;
	/** Reports whether the editor holds unsaved edits (always true for a draft). */
	onDirtyChange?: ( dirty: boolean ) => void;
	/** A post the user may edit (such as the hosting quiz) that the learner preview of the draft uses. */
	previewPostId?: number;
}

/** Link back to the question bank list. */
export function BackLink() {
	return (
		<Link to={ LIST }>{ __( 'Back to question bank', 'ohmylms' ) }</Link>
	);
}

/**
 * The question editor form: name, text and points for every type; the answers (options, expected
 * answers, blanks, or the registered `question-editor` of settings types and add-ons); the shared
 * settings, hint and explanation; media; bank, difficulty, source and secure flag; skills by part; and a
 * learner preview in the real player. Saves send only changed fields and are refused when someone else
 * saved first. Approving and duplicating work on the saved question.
 *
 * @param props
 * @param props.question       Question as loaded or saved (a draft has id 0).
 * @param props.types          Registered question types.
 * @param props.onSaved        Receives the saved question.
 * @param props.reload         Reloads the question from the server.
 * @param props.onHandoff      Opens another question with data the page already has.
 * @param props.initialMessage Success notice to show first.
 * @param props.draft          The question is not saved yet; the first save creates it.
 * @param props.target         Hosted draft: saves through the host instead of the question bank.
 */
export function QuestionWorkspace( {
	question,
	types,
	onSaved,
	reload,
	onHandoff,
	initialMessage,
	draft = false,
	target,
}: {
	question: QuestionDetail;
	types: QuestionType[];
	onSaved: ( question: QuestionDetail ) => void;
	reload: () => void;
	/** Opens another question with data the page already has. */
	onHandoff: ( handoff: QuestionHandoff ) => void;
	initialMessage?: string | undefined;
	/** The question is not saved yet; the first save creates it. */
	draft?: boolean;
	/** Hosted draft: the first save goes to the host, which also guards unsaved edits. */
	target?: DraftTarget | undefined;
} ) {
	const navigate = useNavigate();
	const [ form, setForm ] = useState< FormState >( () =>
		formFromQuestion( question )
	);
	const [ saved, setSaved ] = useState< FormState >( () =>
		formFromQuestion( question )
	);
	const [ errors, setErrors ] = useState< FormErrors >( {} );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState< string | null >(
		initialMessage ?? null
	);
	const [ failure, setFailure ] = useState< string | null >( null );
	const [ conflict, setConflict ] = useState< string | null >( null );
	const [ versionsKey, setVersionsKey ] = useState( 0 );
	const [ adding, setAdding ] = useState( false );
	const [ confirmDelete, setConfirmDelete ] = useState( false );
	const summary = useRef< HTMLDivElement >( null );
	const operation = useRef< AbortController | null >( null );
	const readOnly = ! question.permissions.can_edit;
	const dirty = isDirty( form, saved );
	const hosted = Boolean( target );
	const guard = useUnsavedChangesGuard(
		! hosted && ( draft || dirty ) && ! readOnly
	);
	const reportDirty = target?.onDirtyChange;
	const type = question.type;
	const extension = useExtensions( 'question-editor' ).find(
		( entry ) => entry.id === type
	);
	const area = answerArea( type, Boolean( extension ) );
	const points = scoreMode( type );

	useEffect( () => () => operation.current?.abort(), [] );

	useEffect( () => {
		reportDirty?.( ( draft || dirty ) && ! readOnly );
	}, [ reportDirty, draft, dirty, readOnly ] );

	useEffect( () => {
		const next = formFromQuestion( question );
		setForm( next );
		setSaved( next );
		setErrors( {} );
		setConflict( null );
	}, [ question ] );

	const changeSettings = useCallback(
		( patch: QuestionSettings ) => {
			if (
				! patch ||
				'object' !== typeof patch ||
				Array.isArray( patch )
			) {
				return;
			}
			setForm( ( current ) => ( {
				...current,
				settings: { ...current.settings, ...patch, type },
			} ) );
			setMessage( null );
		},
		[ type ]
	);

	const update = < K extends keyof FormState >(
		key: K,
		value: FormState[ K ]
	) => {
		setForm( ( current ) => ( { ...current, [ key ]: value } ) );
		setMessage( null );
	};
	const invalid = ( key: string ) => ( errors[ key ] ? true : undefined );
	const described = ( key: string ) =>
		errors[ key ] ? errorId( key ) : undefined;

	async function perform< T >(
		work: ( signal: AbortSignal ) => Promise< T >,
		success: ( result: T ) => void
	) {
		if ( operation.current ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setMessage( null );
		setFailure( null );
		setConflict( null );
		try {
			const result = await work( controller.signal );
			if ( ! controller.signal.aborted ) {
				success( result );
			}
		} catch ( problem ) {
			if ( ! controller.signal.aborted ) {
				const error = normalizeApiError( problem );
				if ( 'ohmylms_question_conflict' === error.code ) {
					setConflict( error.message );
				} else {
					const mapped = serverFieldError( error );
					if ( mapped ) {
						setErrors( { [ mapped.path ]: mapped.message } );
					}
					setFailure( error.message );
				}
				summary.current?.focus();
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				setBusy( false );
				operation.current = null;
			}
		}
	}

	function save() {
		if ( readOnly || operation.current ) {
			return;
		}
		const problems = validateForm( form, saved, type, area );
		const settingsError =
			draft ||
			JSON.stringify( form.settings ) !== JSON.stringify( saved.settings )
				? settingsProblem( type, form.settings )
				: null;
		if ( settingsError ) {
			problems.settings = settingsError;
		}
		setErrors( problems );
		setMessage( null );
		setConflict( null );
		if ( ! isValid( problems ) ) {
			setFailure(
				__( 'Fix the highlighted fields before saving.', 'ohmylms' )
			);
			summary.current?.focus();
			return;
		}
		if ( draft ) {
			const body = toCreateInput( form, type, area );
			if ( target ) {
				void perform(
					( signal ) => target.save( body, signal ),
					target.done
				);
				return;
			}
			void perform(
				( signal ) => createQuestion( body, signal ),
				( created ) => {
					onHandoff( {
						question: created,
						types,
						message: __( 'Question created.', 'ohmylms' ),
					} );
					navigate( `${ LIST }/${ created.id }`, { replace: true } );
				}
			);
			return;
		}
		const input = toInput( form, saved, type, question.modified, area );
		void perform(
			( signal ) => updateQuestion( question.id, input, signal ),
			( result ) => {
				onSaved( result );
				setVersionsKey( ( current ) => current + 1 );
				setMessage( __( 'Question saved.', 'ohmylms' ) );
			}
		);
	}

	function changeLifecycle() {
		const archiving = 'archived' !== question.status;
		void perform(
			( signal ) =>
				archiving
					? archiveQuestion( question.id, signal )
					: restoreQuestion( question.id, signal ),
			( result ) => {
				onSaved( result );
				setMessage(
					archiving
						? __( 'Question archived.', 'ohmylms' )
						: __( 'Question restored.', 'ohmylms' )
				);
			}
		);
	}

	function approve() {
		void perform(
			( signal ) => approveQuestion( question.id, signal ),
			( result ) => {
				onSaved( result );
				setVersionsKey( ( current ) => current + 1 );
				setMessage(
					sprintf(
						/* translators: %d: version number. */
						__( 'Version %d approved.', 'ohmylms' ),
						result.version
					)
				);
			}
		);
	}

	function duplicate() {
		void perform(
			( signal ) => duplicateQuestion( question.id, signal ),
			( copy ) => {
				onHandoff( {
					question: copy,
					types,
					message: __(
						'Question duplicated. You are now editing the copy.',
						'ohmylms'
					),
				} );
				navigate( `${ LIST }/${ copy.id }` );
			}
		);
	}

	function remove() {
		void perform(
			( signal ) => deleteQuestion( question.id, signal ),
			( result ) => {
				setConfirmDelete( false );
				if ( result.deleted ) {
					navigate( LIST );
					return;
				}
				setMessage(
					__(
						'Learners have answered this question, so it was archived instead of deleted.',
						'ohmylms'
					)
				);
				reload();
			}
		);
	}

	function previewData(): PreviewQuestion {
		return {
			id: question.id,
			name: form.name,
			description: form.description,
			settings: settingsFor( form, type ),
			questions: form.options.map( ( item, index ) => ( {
				id: item.id > 0 ? item.id : 900000 + index,
				answer: item.answer,
				is_correct: item.is_correct,
				order_number: index + 1,
				image_url: item.image_url,
				matching_data: item.matching,
			} ) ),
			image_src: form.image.url,
			video_src: form.video.url,
		};
	}

	const difficulties = [
		...( ( DIFFICULTIES as readonly string[] ).includes( form.difficulty )
			? []
			: [
					{
						value: form.difficulty,
						label: difficultyLabel( form.difficulty ),
					},
				] ),
		...DIFFICULTIES.map( ( value ) => ( {
			value,
			label: difficultyLabel( value ),
		} ) ),
	];
	const title = saved.name.trim() || __( 'Untitled question', 'ohmylms' );
	const ExtensionEditor = extension?.render as
		ComponentType< QuestionEditorProps > | undefined;
	const Heading = hosted ? 'h3' : 'h2';

	return (
		<>
			{ ! hosted && (
				<p>
					<BackLink />
				</p>
			) }
			<Heading>
				{ draft
					? sprintf(
							/* translators: %s: question name. */
							__( 'New question: %s', 'ohmylms' ),
							title
						)
					: sprintf(
							/* translators: %s: question name. */
							__( 'Edit question: %s', 'ohmylms' ),
							title
						) }
			</Heading>
			<p>
				{ sprintf(
					/* translators: %s: question type label. */
					__( 'Type: %s', 'ohmylms' ),
					typeLabel( types, type )
				) }
				{ ! draft && (
					<>
						{ ' · ' }
						{ versionLabel( question ) }
						{ ' · ' }
						{ sprintf(
							/* translators: %s: usage such as "2 quizzes · 14 responses". */
							__( 'Used in %s', 'ohmylms' ),
							usageLabel( question )
						) }
					</>
				) }
			</p>
			{ draft && (
				<p role="status">
					{ hosted
						? __(
								'This question is not saved yet. Complete it and save to create it in the question bank and add it here.',
								'ohmylms'
							)
						: __(
								'This question is not saved yet. Complete it and save to add it to the question bank.',
								'ohmylms'
							) }
				</p>
			) }
			{ ! draft && dirty && (
				<p role="status">
					{ __( 'You have unsaved changes.', 'ohmylms' ) }
				</p>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			<div ref={ summary } tabIndex={ -1 }>
				{ conflict && (
					<Notice status="error" isDismissible={ false }>
						{ conflict }{ ' ' }
						{ __(
							'Your edits are kept below. Reload to see the saved question; this discards your edits.',
							'ohmylms'
						) }{ ' ' }
						<Button
							variant="secondary"
							onClick={ () => {
								setConflict( null );
								reload();
							} }
						>
							{ __( 'Reload', 'ohmylms' ) }
						</Button>
					</Notice>
				) }
				{ failure && (
					<Notice status="error" isDismissible={ false }>
						{ failure }
					</Notice>
				) }
			</div>
			{ readOnly && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'You can view this question but not change it.',
						'ohmylms'
					) }
				</Notice>
			) }
			<form
				aria-label={ __( 'Question', 'ohmylms' ) }
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					save();
				} }
			>
				<fieldset disabled={ busy || readOnly }>
					<legend>{ __( 'Question details', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Name', 'ohmylms' ) }
						value={ form.name }
						maxLength={ MAX_NAME }
						required
						aria-invalid={ invalid( 'name' ) }
						aria-describedby={ described( 'name' ) }
						onChange={ ( next ) => update( 'name', next ) }
					/>
					<FieldError field="name" message={ errors.name } />
					<TextareaControl
						label={ __( 'Question text', 'ohmylms' ) }
						value={ form.description }
						rows={ 4 }
						maxLength={ MAX_DESCRIPTION }
						aria-invalid={ invalid( 'description' ) }
						aria-describedby={ described( 'description' ) }
						onChange={ ( next ) => update( 'description', next ) }
					/>
					<FieldError
						field="description"
						message={ errors.description }
					/>
					{ 'points' === points && (
						<>
							<TextControl
								label={ __( 'Points', 'ohmylms' ) }
								type="number"
								min={ 0 }
								max={ MAX_POINTS }
								step="any"
								value={ form.points }
								aria-invalid={ invalid( 'points' ) }
								aria-describedby={ described( 'points' ) }
								onChange={ ( next ) =>
									update( 'points', next )
								}
							/>
							<FieldError
								field="points"
								message={ errors.points }
							/>
						</>
					) }
					{ 'derived' === points && (
						<p>
							{ sprintf(
								/* translators: %s: total points. */
								__(
									'Points: %s (the total of the parts’ marks; change the marks of each part below).',
									'ohmylms'
								),
								derivedPoints( form.settings )
							) }
						</p>
					) }
					{ 'unscored' === points && (
						<p>
							{ __(
								'Points: 0. This type is not scored; responses are kept but not graded.',
								'ohmylms'
							) }
						</p>
					) }
				</fieldset>

				{ 'options' === area && (
					<OptionsEditor
						type={ type }
						options={ form.options }
						errors={ errors }
						disabled={ busy || readOnly }
						onChange={ ( next ) => update( 'options', next ) }
					/>
				) }
				{ 'accepted' === area && (
					<AcceptedAnswers
						type={ type }
						options={ form.options }
						errors={ errors }
						disabled={ busy || readOnly }
						onChange={ ( next ) => update( 'options', next ) }
					/>
				) }
				{ 'blanks' === area && (
					<BlankAnswers
						name={ form.name }
						settings={ form.settings }
						options={ form.options }
						errors={ errors }
						disabled={ busy || readOnly }
						onSettings={ changeSettings }
						onOptions={ ( next ) => update( 'options', next ) }
					/>
				) }
				{ 'manual' === area && <ManualAnswer type={ type } /> }
				{ 'extension' === area && extension && ExtensionEditor && (
					<fieldset disabled={ busy || readOnly }>
						<legend>{ extension.label }</legend>
						<ExtensionBoundary
							key={ `${ question.id }:${ extension.id }` }
							id={ extension.id }
						>
							<Suspense
								fallback={
									<p role="status">
										{ __( 'Loading editor…', 'ohmylms' ) }
									</p>
								}
							>
								<ExtensionEditor
									question={ question }
									value={ form.settings }
									onChange={ changeSettings }
								/>
							</Suspense>
						</ExtensionBoundary>
					</fieldset>
				) }
				{ 'none' === area && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'No editor is available for this question type. Activate the add-on that provides it to edit its answers; the other fields can still be changed.',
							'ohmylms'
						) }
					</Notice>
				) }
				<FieldError field="settings" message={ errors.settings } />

				<SharedSettingsFields
					type={ type }
					settings={ form.settings }
					errors={ errors }
					disabled={ busy || readOnly }
					onChange={ changeSettings }
				/>
				<MediaFields
					image={ form.image }
					video={ form.video }
					errors={ errors }
					disabled={ busy || readOnly }
					onImage={ ( next ) => update( 'image', next ) }
					onVideo={ ( next ) => update( 'video', next ) }
				/>
				<BankFields
					bankId={ form.bankId }
					difficulty={ form.difficulty }
					source={ form.source }
					secure={ form.secure }
					familyId={ question.bank.family_id }
					difficulties={ difficulties }
					errors={ errors }
					disabled={ busy || readOnly }
					onChange={ ( patch ) => {
						setForm( ( current ) => ( { ...current, ...patch } ) );
						setMessage( null );
					} }
				/>
				<SkillsFields
					settings={ form.settings }
					skills={ form.skills }
					errors={ errors }
					disabled={ busy || readOnly }
					onChange={ ( next ) => update( 'skills', next ) }
				/>

				{ ! readOnly && (
					<Button
						type="submit"
						variant="primary"
						disabled={ busy || ( ! draft && ! dirty ) }
						isBusy={ busy }
					>
						{ target
							? target.saveLabel
							: __( 'Save question', 'ohmylms' ) }
					</Button>
				) }
				{ target && (
					<>
						{ ' ' }
						<Button
							variant="tertiary"
							disabled={ busy }
							onClick={ target.cancel }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
					</>
				) }
			</form>

			<PlayerPreview
				postId={ draft ? ( target?.previewPostId ?? 0 ) : question.id }
				question={ previewData }
			/>

			{ ! readOnly && ! draft && (
				<section aria-labelledby="oml-question-status">
					<h3 id="oml-question-status">
						{ __( 'Availability', 'ohmylms' ) }
					</h3>
					<p>
						{ 'archived' === question.status
							? __(
									'This question is archived: it is hidden from the question bank and cannot be added to quizzes.',
									'ohmylms'
								)
							: __(
									'Archiving hides the question from the question bank and from new quizzes. Its history is kept.',
									'ohmylms'
								) }
					</p>
					{ dirty && (
						<p>
							{ __(
								'Save or undo your changes first.',
								'ohmylms'
							) }
						</p>
					) }
					<Button
						variant="secondary"
						disabled={ busy || dirty }
						onClick={ changeLifecycle }
					>
						{ 'archived' === question.status
							? __( 'Restore question', 'ohmylms' )
							: __( 'Archive question', 'ohmylms' ) }
					</Button>
				</section>
			) }

			{ ! draft && question.permissions.can_approve && (
				<section aria-labelledby="oml-question-approval">
					<h3 id="oml-question-approval">
						{ __( 'Approval', 'ohmylms' ) }
					</h3>
					<p>{ approvalLabel( question ) }</p>
					{ canApproveCurrent( question ) && (
						<>
							{ dirty && (
								<p>
									{ __(
										'Save or undo your changes first.',
										'ohmylms'
									) }
								</p>
							) }
							<Button
								variant="secondary"
								disabled={ busy || dirty }
								onClick={ approve }
							>
								{ __( 'Approve this version', 'ohmylms' ) }
							</Button>
						</>
					) }
				</section>
			) }

			{ ! draft && (
				<>
					<section aria-labelledby="oml-question-copy">
						<h3 id="oml-question-copy">
							{ __( 'Copy', 'ohmylms' ) }
						</h3>
						<p>
							{ __(
								'Duplicating creates a new draft question with the same content, which you can change without affecting this one.',
								'ohmylms'
							) }
						</p>
						{ dirty && (
							<p>
								{ __(
									'Save or undo your changes first; the copy is made from the saved question.',
									'ohmylms'
								) }
							</p>
						) }
						<Button
							variant="secondary"
							disabled={ busy || dirty }
							onClick={ duplicate }
						>
							{ __( 'Duplicate', 'ohmylms' ) }
						</Button>
						{ ! hosted && 'archived' !== question.status && (
							<>
								{ ' ' }
								<Button
									variant="secondary"
									disabled={ busy || dirty }
									onClick={ () => {
										setMessage( null );
										setAdding( true );
									} }
								>
									{ __( 'Add to a quiz', 'ohmylms' ) }
								</Button>
							</>
						) }
					</section>

					{ ! hosted && question.permissions.can_delete && (
						<section aria-labelledby="oml-question-delete">
							<h3 id="oml-question-delete">
								{ __( 'Delete', 'ohmylms' ) }
							</h3>
							<p>{ deleteHelp( question.usage ) }</p>
							<Button
								variant="secondary"
								isDestructive
								disabled={
									busy ||
									dirty ||
									'blocked' ===
										deleteOutcome( question.usage )
								}
								onClick={ () => setConfirmDelete( true ) }
							>
								{ 'archive' === deleteOutcome( question.usage )
									? __( 'Delete (archive)', 'ohmylms' )
									: __( 'Delete question', 'ohmylms' ) }
							</Button>
						</section>
					) }

					<Versions key={ versionsKey } id={ question.id } />
				</>
			) }

			{ adding && (
				<AddToQuizDialog
					question={ {
						...question,
						quizIds: question.quizzes.map( ( quiz ) => quiz.id ),
					} }
					onClose={ () => setAdding( false ) }
					onAdded={ ( quiz, _placed, copy ) => {
						setAdding( false );
						setMessage(
							copy
								? sprintf(
										/* translators: %s: quiz title. */
										__(
											'A copy was added to %s. This question is unchanged.',
											'ohmylms'
										),
										quiz.title
									)
								: sprintf(
										/* translators: %s: quiz title. */
										__( 'Added to %s.', 'ohmylms' ),
										quiz.title
									)
						);
						reload();
					} }
				/>
			) }

			{ confirmDelete && (
				<Modal
					title={ __( 'Delete question', 'ohmylms' ) }
					onRequestClose={ () => ! busy && setConfirmDelete( false ) }
					shouldCloseOnClickOutside={ false }
				>
					{ failure && (
						<Notice status="error" isDismissible={ false }>
							{ failure }
						</Notice>
					) }
					<p>{ deleteHelp( question.usage ) }</p>
					<Button
						variant="primary"
						isDestructive
						disabled={ busy }
						isBusy={ busy }
						onClick={ remove }
					>
						{ __( 'Delete question', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ () => setConfirmDelete( false ) }
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
							'Your changes to this question have not been saved.',
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
