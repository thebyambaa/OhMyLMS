import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import {
	Suspense,
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { ComponentType } from 'react';
import {
	Link,
	useNavigate,
	useParams,
	useSearchParams,
} from 'react-router-dom';
import { useBootstrap } from '../../bootstrap/BootstrapContext';
import { ExtensionBoundary } from '../../extensions/ExtensionBoundary';
import { useExtensions } from '../../extensions/context';
import { useRequest } from '../../hooks/useRequest';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { normalizeApiError } from '../../lib/api';
import {
	approveQuestion,
	archiveQuestion,
	createQuestion,
	duplicateQuestion,
	listQuestionTypes,
	restoreQuestion,
	updateQuestion,
} from './api';
import {
	approvalLabel,
	canApproveCurrent,
	DIFFICULTIES,
	difficultyLabel,
	draftQuestion,
	editsOptions,
	formFromQuestion,
	isDirty,
	isValid,
	legacyEditorUrl,
	MAX_DESCRIPTION,
	MAX_NAME,
	serverFieldError,
	settingsProblem,
	toCreateInput,
	toInput,
	typeLabel,
	usageLabel,
	validateForm,
	versionLabel,
} from './model';
import { errorId, FieldError, OptionsEditor } from './OptionsEditor';
import { useQuestionEditor, type QuestionHandoff } from './useQuestionEditor';
import { Versions } from './Versions';
import type {
	FormErrors,
	FormState,
	QuestionDetail,
	QuestionSettings,
	QuestionType,
} from './types';
import '../../styles/questions.css';

const LIST = '/content-hub/question-bank';

/** Props of a registered `question-editor` extension (public extension API v1). */
interface QuestionEditorProps {
	/** The editor view of the question as last loaded or saved. */
	question: QuestionDetail;
	/** Current settings. */
	value: QuestionSettings;
	/** Merges a patch into the settings; the question type is always kept. */
	onChange: ( patch: QuestionSettings ) => void;
}

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

function BackLink() {
	return (
		<Link to={ LIST }>{ __( 'Back to question bank', 'ohmylms' ) }</Link>
	);
}

/**
 * Single-question editor (`#/content-hub/question-bank/<id>`): name, text, points and difficulty for
 * every type; options for the built-in option types; the registered `question-editor` for the other
 * types (the built-in settings editors, which load lazily, and add-on editors). Saves send only
 * changed fields and are refused when someone else saved first. Leaving with unsaved changes asks
 * first. Approving and duplicating work on the saved question.
 *
 * `#/content-hub/question-bank/new?type=<id>&name=<name>` is an unsaved draft of that type, started
 * from the type's authored defaults; its first save creates the question and replaces the route with
 * the question's own, keeping the editor state the server returned.
 */
export default function QuestionEditorPage() {
	const params = useParams();
	const [ handoff, setHandoff ] = useState< QuestionHandoff | null >( null );
	if ( 'new' === params.id ) {
		return <NewQuestion onHandoff={ setHandoff } />;
	}
	return (
		<SavedQuestion
			key={ params.id }
			route={ params.id }
			handoff={ handoff }
			onHandoff={ setHandoff }
		/>
	);
}

function NewQuestion( {
	onHandoff,
}: {
	onHandoff: ( handoff: QuestionHandoff ) => void;
} ) {
	const [ search ] = useSearchParams();
	const type = search.get( 'type' ) ?? '';
	const name = search.get( 'name' ) ?? '';
	const types = useRequest( listQuestionTypes );
	const draft = useMemo( () => draftQuestion( name, type ), [ name, type ] );
	if ( types.loading ) {
		return (
			<>
				<h2>{ __( 'New question', 'ohmylms' ) }</h2>
				<p role="status">
					{ __( 'Loading question types…', 'ohmylms' ) }
				</p>
			</>
		);
	}
	if ( types.error || ! types.data ) {
		return (
			<>
				<h2>{ __( 'New question', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ types.error ||
						__( 'Question types could not be loaded.', 'ohmylms' ) }
				</Notice>
				<p>
					<Button variant="secondary" onClick={ types.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>{ ' ' }
					<BackLink />
				</p>
			</>
		);
	}
	if ( ! types.data.some( ( item ) => item.id === type ) ) {
		return (
			<>
				<h2>{ __( 'New question', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __(
						'This question type is not available. Start the question again from the question bank.',
						'ohmylms'
					) }
				</Notice>
				<BackLink />
			</>
		);
	}
	return (
		<QuestionWorkspace
			draft
			question={ draft }
			types={ types.data }
			onSaved={ () => undefined }
			reload={ () => undefined }
			onHandoff={ onHandoff }
		/>
	);
}

function SavedQuestion( {
	route,
	handoff,
	onHandoff,
}: {
	route: string | undefined;
	handoff: QuestionHandoff | null;
	onHandoff: ( handoff: QuestionHandoff ) => void;
} ) {
	const id = routeId( route );
	const editor = useQuestionEditor( id, handoff );
	const [ initialMessage ] = useState( () =>
		handoff && handoff.question.id === id ? handoff.message : undefined
	);

	if ( null === id ) {
		return (
			<>
				<h2>{ __( 'Question', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This question link is invalid.', 'ohmylms' ) }
				</Notice>
				<BackLink />
			</>
		);
	}
	if ( editor.loading && ! editor.question ) {
		return (
			<>
				<h2>{ __( 'Question', 'ohmylms' ) }</h2>
				<p role="status">{ __( 'Loading question…', 'ohmylms' ) }</p>
			</>
		);
	}
	if ( editor.error || ! editor.question || ! editor.types ) {
		return (
			<>
				<h2>{ __( 'Question', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ editor.error?.message ??
						__( 'The question could not be loaded.', 'ohmylms' ) }
				</Notice>
				<p>
					{ ( ! editor.error ||
						( 403 !== editor.error.status &&
							404 !== editor.error.status ) ) && (
						<>
							<Button
								variant="secondary"
								onClick={ editor.reload }
							>
								{ __( 'Try again', 'ohmylms' ) }
							</Button>{ ' ' }
						</>
					) }
					<BackLink />
				</p>
			</>
		);
	}
	return (
		<QuestionWorkspace
			question={ editor.question }
			types={ editor.types }
			onSaved={ editor.setQuestion }
			reload={ editor.reload }
			onHandoff={ onHandoff }
			initialMessage={ initialMessage }
		/>
	);
}

function QuestionWorkspace( {
	question,
	types,
	onSaved,
	reload,
	onHandoff,
	initialMessage,
	draft = false,
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
} ) {
	const { adminUrl } = useBootstrap();
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
	const summary = useRef< HTMLDivElement >( null );
	const operation = useRef< AbortController | null >( null );
	const readOnly = ! question.permissions.can_edit;
	const dirty = isDirty( form, saved );
	const guard = useUnsavedChangesGuard( ( draft || dirty ) && ! readOnly );
	const type = question.type;
	const extension = useExtensions( 'question-editor' ).find(
		( entry ) => entry.id === type
	);
	const format = types.find( ( item ) => item.id === type )?.format;
	let area: 'options' | 'full-editor' | 'extension' | 'none' = 'none';
	if ( 'ohmylms-options' === format ) {
		area = editsOptions( type ) ? 'options' : 'full-editor';
	} else if ( extension ) {
		area = 'extension';
	}

	useEffect( () => () => operation.current?.abort(), [] );

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
		const problems = validateForm(
			form,
			saved,
			'options' === area ? type : ''
		);
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
			const body = toCreateInput( form, type );
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
		const input = toInput( form, saved, type, question.modified );
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

	const fullEditor = (
		<a href={ legacyEditorUrl( adminUrl ) }>
			{ __( 'Open the full question editor', 'ohmylms' ) }
		</a>
	);
	const title = saved.name.trim() || __( 'Untitled question', 'ohmylms' );
	const ExtensionEditor = extension?.render as
		ComponentType< QuestionEditorProps > | undefined;

	return (
		<>
			<p>
				<BackLink />
			</p>
			<h2>
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
			</h2>
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
					{ __(
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
					<TextControl
						label={ __( 'Points', 'ohmylms' ) }
						type="number"
						min={ 0 }
						step="any"
						value={ form.points }
						aria-invalid={ invalid( 'points' ) }
						aria-describedby={ described( 'points' ) }
						onChange={ ( next ) => update( 'points', next ) }
					/>
					<FieldError field="points" message={ errors.points } />
					<SelectControl
						label={ __( 'Difficulty', 'ohmylms' ) }
						value={ form.difficulty }
						options={ [
							...( ( DIFFICULTIES as readonly string[] ).includes(
								form.difficulty
							)
								? []
								: [
										{
											value: form.difficulty,
											label: difficultyLabel(
												form.difficulty
											),
										},
									] ),
							...DIFFICULTIES.map( ( value ) => ( {
								value,
								label: difficultyLabel( value ),
							} ) ),
						] }
						aria-invalid={ invalid( 'difficulty' ) }
						aria-describedby={ described( 'difficulty' ) }
						onChange={ ( next ) => update( 'difficulty', next ) }
					/>
					<FieldError
						field="difficulty"
						message={ errors.difficulty }
					/>
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
				{ 'full-editor' === area && (
					<Notice status="info" isDismissible={ false }>
						{ __(
							'The answers of this question type are edited in the full editor.',
							'ohmylms'
						) }{ ' ' }
						{ fullEditor }
					</Notice>
				) }
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
							'This question type does not have an editor in the new admin yet.',
							'ohmylms'
						) }{ ' ' }
						{ fullEditor }
					</Notice>
				) }
				<FieldError field="settings" message={ errors.settings } />

				{ ! readOnly && (
					<Button
						type="submit"
						variant="primary"
						disabled={ busy || ( ! draft && ! dirty ) }
						isBusy={ busy }
					>
						{ __( 'Save question', 'ohmylms' ) }
					</Button>
				) }
			</form>

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
					</section>

					<Versions key={ versionsKey } id={ question.id } />
				</>
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
