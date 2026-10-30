import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useBootstrap } from '../../bootstrap/BootstrapContext';
import { normalizeApiError } from '../../lib/api';
import { updateQuiz } from './api';
import {
	formFromQuiz,
	isDirty,
	isValid,
	serverFieldError,
	toInput,
	validateForm,
} from './model';
import { useQuizEditor } from './useQuizEditor';
import { useUnsavedChangesGuard } from './useUnsavedChangesGuard';
import type { QuizForm, QuizFormErrors } from './types';

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

function FieldError( { id, message }: { id: string; message?: string } ) {
	return message ? (
		<p id={ id } role="alert">
			{ message }
		</p>
	) : null;
}

/**
 * Quiz settings editor (`#/quizzes/<id>`): title, status, introduction, time limit, passing score,
 * attempts, randomisation, answer review, layout, feedback and availability. Questions are edited in
 * the question screens. Edits survive failed saves, a quiz changed by someone else is reported before it
 * is overwritten, and leaving with unsaved changes asks first.
 */
export default function QuizEditorPage() {
	const { adminUrl } = useBootstrap();
	const params = useParams();
	const id = routeId( params.id );
	const editor = useQuizEditor( id );
	const [ form, setForm ] = useState< QuizForm | null >( null );
	const [ saved, setSaved ] = useState< QuizForm | null >( null );
	const [ errors, setErrors ] = useState< QuizFormErrors >( {} );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ failure, setFailure ] = useState< string | null >( null );
	const [ conflict, setConflict ] = useState( false );
	const summary = useRef< HTMLDivElement >( null );
	const operation = useRef< AbortController | null >( null );
	const dirty = Boolean( form && saved && isDirty( form, saved ) );
	const guard = useUnsavedChangesGuard( dirty );
	const quiz = editor.quiz;

	useEffect( () => () => operation.current?.abort(), [] );

	useEffect( () => {
		if ( quiz ) {
			const next = formFromQuiz( quiz );
			setForm( next );
			setSaved( next );
			setErrors( {} );
			setConflict( false );
		}
	}, [ quiz ] );

	if ( null === id ) {
		return (
			<>
				<h2>{ __( 'Quiz settings', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This quiz link is invalid.', 'ohmylms' ) }
				</Notice>
				<Link to="/quizzes">
					{ __( 'Back to quizzes', 'ohmylms' ) }
				</Link>
			</>
		);
	}
	if ( editor.loading && ! form ) {
		return (
			<>
				<h2>{ __( 'Quiz settings', 'ohmylms' ) }</h2>
				<p role="status">{ __( 'Loading quiz…', 'ohmylms' ) }</p>
			</>
		);
	}
	if ( editor.error || ! form || ! saved || ! quiz ) {
		return (
			<>
				<h2>{ __( 'Quiz settings', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ editor.error?.message ??
						__( 'The quiz could not be loaded.', 'ohmylms' ) }
				</Notice>
				<p>
					{ ( ! editor.error ||
						( 403 !== editor.error.status &&
							401 !== editor.error.status &&
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
					<Link to="/quizzes">
						{ __( 'Back to quizzes', 'ohmylms' ) }
					</Link>
				</p>
			</>
		);
	}

	const update = < K extends keyof QuizForm >(
		key: K,
		value: QuizForm[ K ]
	) => {
		setForm( ( current ) =>
			current ? { ...current, [ key ]: value } : current
		);
		setMessage( null );
	};
	const invalid = ( key: keyof QuizForm ) =>
		errors[ key ] ? true : undefined;
	const described = ( key: keyof QuizForm ) =>
		errors[ key ] ? `oml-quiz-${ key }-error` : undefined;
	const readOnly = ! quiz.can_edit || 'trash' === quiz.status;

	async function save() {
		if ( ! form || ! quiz || operation.current || readOnly ) {
			return;
		}
		const problems = validateForm( form );
		setErrors( problems );
		setMessage( null );
		setFailure( null );
		setConflict( false );
		if ( ! isValid( problems ) ) {
			setFailure(
				__( 'Fix the highlighted fields before saving.', 'ohmylms' )
			);
			summary.current?.focus();
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		try {
			const result = await updateQuiz(
				quiz.id,
				toInput( form, quiz.updated_at ),
				controller.signal
			);
			if ( controller.signal.aborted ) {
				return;
			}
			editor.setQuiz( result );
			setMessage( __( 'Quiz saved.', 'ohmylms' ) );
		} catch ( problem ) {
			if ( ! controller.signal.aborted ) {
				const error = normalizeApiError( problem );
				const mapped = serverFieldError( error );
				if ( mapped ) {
					setErrors( { [ mapped.field ]: mapped.message } );
				}
				setConflict( 'ohmylms_quiz_conflict' === error.code );
				setFailure( error.message );
				summary.current?.focus();
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				setBusy( false );
				operation.current = null;
			}
		}
	}

	return (
		<>
			<p>
				<Link to="/quizzes">
					{ __( 'Back to quizzes', 'ohmylms' ) }
				</Link>
			</p>
			<h2>
				{ sprintf(
					/* translators: %s: quiz title. */
					__( 'Quiz settings: %s', 'ohmylms' ),
					saved.title
				) }
			</h2>
			<p>
				{ quiz.can_grade && (
					<>
						<Link to={ `/quizzes/${ quiz.id }/attempts` }>
							{ sprintf(
								/* translators: %d: number of attempts. */
								__( 'Attempts (%d)', 'ohmylms' ),
								quiz.attempt_count
							) }
						</Link>{ ' ' }
					</>
				) }
				<a
					href={ `${ adminUrl }admin.php?page=ohmylms#/quiz-edit/${ quiz.id }` }
				>
					{ __( 'Edit questions', 'ohmylms' ) }
				</a>
				{ quiz.native_editor_url && (
					<>
						{ ' ' }
						<a href={ quiz.native_editor_url }>
							{ __( 'Open in the block editor', 'ohmylms' ) }
						</a>
					</>
				) }
			</p>
			{ 'trash' === quiz.status && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This quiz is in the trash. Restore it from the quiz list to edit it.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ dirty && (
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
				{ failure && (
					<Notice status="error" isDismissible={ false }>
						{ failure }
						{ conflict && (
							<>
								{ ' ' }
								<Button
									variant="link"
									onClick={ () => {
										setForm( null );
										editor.reload();
									} }
								>
									{ __( 'Reload the quiz', 'ohmylms' ) }
								</Button>
							</>
						) }
					</Notice>
				) }
			</div>
			<form
				aria-label={ __( 'Quiz settings', 'ohmylms' ) }
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					void save();
				} }
			>
				<fieldset disabled={ busy || readOnly }>
					<legend>{ __( 'Details', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Title', 'ohmylms' ) }
						value={ form.title }
						maxLength={ 200 }
						required
						aria-invalid={ invalid( 'title' ) }
						aria-describedby={ described( 'title' ) }
						onChange={ ( next ) => update( 'title', next ) }
					/>
					<FieldError
						id="oml-quiz-title-error"
						message={ errors.title }
					/>
					<SelectControl
						label={ __( 'Status', 'ohmylms' ) }
						value={ form.status }
						options={ [
							{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
							{
								value: 'publish',
								label: __( 'Published', 'ohmylms' ),
							},
						] }
						onChange={ ( next ) =>
							update(
								'status',
								'publish' === next ? 'publish' : 'draft'
							)
						}
					/>
					<TextareaControl
						label={ __( 'Introduction', 'ohmylms' ) }
						value={ form.description }
						rows={ 4 }
						onChange={ ( next ) => update( 'description', next ) }
					/>
				</fieldset>

				<fieldset disabled={ busy || readOnly }>
					<legend>{ __( 'Attempts and scoring', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Time limit (minutes)', 'ohmylms' ) }
						value={ form.time_limit_minutes }
						inputMode="numeric"
						help={ __( '0 means no time limit.', 'ohmylms' ) }
						aria-invalid={ invalid( 'time_limit_minutes' ) }
						aria-describedby={ described( 'time_limit_minutes' ) }
						onChange={ ( next ) =>
							update( 'time_limit_minutes', next )
						}
					/>
					<FieldError
						id="oml-quiz-time_limit_minutes-error"
						message={ errors.time_limit_minutes }
					/>
					<TextControl
						label={ __( 'Passing score', 'ohmylms' ) }
						value={ form.passing_score }
						inputMode="decimal"
						help={ sprintf(
							/* translators: %s: total marks of the quiz. */
							__(
								'Marks a learner needs to pass, 0 to 100 (0 means no pass mark). The quiz is worth %s marks in total.',
								'ohmylms'
							),
							String( quiz.total_marks )
						) }
						aria-invalid={ invalid( 'passing_score' ) }
						aria-describedby={ described( 'passing_score' ) }
						onChange={ ( next ) => update( 'passing_score', next ) }
					/>
					<FieldError
						id="oml-quiz-passing_score-error"
						message={ errors.passing_score }
					/>
					<TextControl
						label={ __( 'Attempts allowed', 'ohmylms' ) }
						value={ form.attempts_allowed }
						inputMode="numeric"
						help={ __(
							'Attempts each learner may take. 0 uses the default of one attempt.',
							'ohmylms'
						) }
						aria-invalid={ invalid( 'attempts_allowed' ) }
						aria-describedby={ described( 'attempts_allowed' ) }
						onChange={ ( next ) =>
							update( 'attempts_allowed', next )
						}
					/>
					<FieldError
						id="oml-quiz-attempts_allowed-error"
						message={ errors.attempts_allowed }
					/>
				</fieldset>

				<fieldset disabled={ busy || readOnly }>
					<legend>{ __( 'Delivery', 'ohmylms' ) }</legend>
					<CheckboxControl
						label={ __( 'Randomise question order', 'ohmylms' ) }
						checked={ form.randomize_questions }
						onChange={ ( next ) =>
							update( 'randomize_questions', next )
						}
					/>
					<SelectControl
						label={ __( 'Question layout', 'ohmylms' ) }
						value={ form.layout }
						options={ [
							{
								value: 'one_question_per_page',
								label: __( 'One question per page', 'ohmylms' ),
							},
							{
								value: 'all_questions_in_one_page',
								label: __(
									'All questions on one page',
									'ohmylms'
								),
							},
							{
								value: 'number_of_questions_per_page',
								label: __(
									'A set number of questions per page',
									'ohmylms'
								),
							},
						] }
						onChange={ ( next ) => {
							const found = [
								'one_question_per_page',
								'all_questions_in_one_page',
								'number_of_questions_per_page',
							].find( ( value ) => value === next );
							if ( found ) {
								update(
									'layout',
									found as QuizForm[ 'layout' ]
								);
							}
						} }
					/>
					<TextControl
						label={ __( 'Questions per page', 'ohmylms' ) }
						value={ form.questions_per_page }
						inputMode="numeric"
						disabled={
							'number_of_questions_per_page' !== form.layout
						}
						help={ __(
							'Used when the layout is a set number of questions per page.',
							'ohmylms'
						) }
						aria-invalid={ invalid( 'questions_per_page' ) }
						aria-describedby={ described( 'questions_per_page' ) }
						onChange={ ( next ) =>
							update( 'questions_per_page', next )
						}
					/>
					<FieldError
						id="oml-quiz-questions_per_page-error"
						message={ errors.questions_per_page }
					/>
				</fieldset>

				<fieldset disabled={ busy || readOnly }>
					<legend>
						{ __( 'Feedback and availability', 'ohmylms' ) }
					</legend>
					<SelectControl
						label={ __( 'Release results', 'ohmylms' ) }
						value={ form.feedback_mode }
						options={ [
							{
								value: 'immediate',
								label: __( 'Immediately', 'ohmylms' ),
							},
							{
								value: 'after_close',
								label: __( 'After the quiz closes', 'ohmylms' ),
							},
							{
								value: 'manual',
								label: __( 'When I release them', 'ohmylms' ),
							},
						] }
						onChange={ ( next ) => {
							const found = [
								'immediate',
								'after_close',
								'manual',
							].find( ( value ) => value === next );
							if ( found ) {
								update(
									'feedback_mode',
									found as QuizForm[ 'feedback_mode' ]
								);
							}
						} }
					/>
					<CheckboxControl
						label={ __(
							'Show correct answers on the results page',
							'ohmylms'
						) }
						checked={ form.show_correct_answers }
						onChange={ ( next ) =>
							update( 'show_correct_answers', next )
						}
					/>
					<TextControl
						label={ __( 'Opens', 'ohmylms' ) }
						type="datetime-local"
						value={ form.available_from }
						help={ __(
							'Leave empty to open immediately. Times use your browser time zone.',
							'ohmylms'
						) }
						aria-invalid={ invalid( 'available_from' ) }
						aria-describedby={ described( 'available_from' ) }
						onChange={ ( next ) =>
							update( 'available_from', next )
						}
					/>
					<FieldError
						id="oml-quiz-available_from-error"
						message={ errors.available_from }
					/>
					<TextControl
						label={ __( 'Closes', 'ohmylms' ) }
						type="datetime-local"
						value={ form.available_until }
						help={ __(
							'Leave empty to never close. A learner who starts late still ends at this time.',
							'ohmylms'
						) }
						aria-invalid={ invalid( 'available_until' ) }
						aria-describedby={ described( 'available_until' ) }
						onChange={ ( next ) =>
							update( 'available_until', next )
						}
					/>
					<FieldError
						id="oml-quiz-available_until-error"
						message={ errors.available_until }
					/>
				</fieldset>

				<Button
					type="submit"
					variant="primary"
					disabled={ busy || readOnly }
					isBusy={ busy }
				>
					{ __( 'Save quiz', 'ohmylms' ) }
				</Button>
			</form>

			{ guard.pendingPath && (
				<Modal
					title={ __( 'Discard unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ __(
							'Your changes to this quiz have not been saved.',
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
