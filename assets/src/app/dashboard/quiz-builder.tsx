import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Alert } from '../../ui/Alert';
import { Button } from '../../ui/Button';
import { Checkbox } from '../../ui/Checkbox';
import { Input } from '../../ui/Input';
import { Select } from '../../ui/Select';
import { api } from './api';
import { ConfirmRemove } from './confirm-remove';
import { Disclosure } from './field';
import {
	createQuiz,
	deleteQuestion,
	renameQuiz,
	trashQuiz,
} from './studio-actions';
import { QuizCanvas } from './quiz-canvas';
import { type Question, type QuizBody, blank, isEditable } from './quiz-model';
import { QuizPanel } from './quiz-panel';
import { QuizRail } from './quiz-rail';

/**
 * Quiz and exam builder: a question rail, the question on a canvas, and its settings.
 * Questions are saved on question posts, not inside the quiz body.
 */
export function QuizBuilder() {
	const initial = Number(
		new URLSearchParams( window.location.search ).get( 'quiz' ) || 0
	);
	const [ quizzes, setQuizzes ] = useState<
		Array< { id: number; title: string } >
	>( [] );
	const [ quizId, setQuizId ] = useState( initial );
	const [ quiz, setQuiz ] = useState< QuizBody | null >( null );
	const [ message, setMessage ] = useState( '' );
	const [ failed, setFailed ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ preview, setPreview ] = useState( false );
	const [ picked, setPicked ] = useState< Record< number, boolean > >( {} );
	const [ checked, setChecked ] = useState( false );
	const [ settingsOpen, setSettingsOpen ] = useState( false );
	const [ current, setCurrent ] = useState( 0 );
	const [ creating, setCreating ] = useState( false );
	const [ newTitle, setNewTitle ] = useState( '' );
	const [ quizName, setQuizName ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const [ pending, setPending ] = useState< 'quiz' | 'question' | null >(
		null
	);
	const [ pendingError, setPendingError ] = useState( '' );

	function loadQuizzes() {
		void api< { quizzes: Array< { id: number; title: string } > } >(
			'/studio/quizzes'
		).then( ( body ) => setQuizzes( body.quizzes ) );
	}

	useEffect( loadQuizzes, [] );
	useEffect( () => {
		if ( ! quizId ) {
			setQuiz( null );
			return;
		}
		void api< QuizBody >( `/studio/quizzes/${ quizId }` ).then(
			( body ) => {
				setQuiz( body );
				setQuizName( body.title );
			}
		);
	}, [ quizId, message ] );

	function updateQuestion( index: number, next: Question ) {
		if ( ! quiz ) {
			return;
		}
		const questions = quiz.questions.slice();
		questions[ index ] = next;
		setQuiz( { ...quiz, questions } );
	}

	function moveQuestion( index: number, direction: number ) {
		const target = index + direction;
		if ( ! quiz || target < 0 || target >= quiz.questions.length ) {
			return;
		}
		const questions = quiz.questions.slice();
		const [ moved ] = questions.splice( index, 1 );
		if ( moved ) {
			questions.splice( target, 0, moved );
		}
		setQuiz( { ...quiz, questions } );
		setCurrent( target );
	}

	function addQuestion() {
		if ( ! quiz ) {
			return;
		}
		setQuiz( {
			...quiz,
			questions: quiz.questions.concat( [ blank( 'single_choice' ) ] ),
		} );
		setCurrent( quiz.questions.length );
	}

	function confirmRemove() {
		if ( ! quiz || ! pending ) {
			return;
		}
		const at = Math.min( current, quiz.questions.length - 1 );
		const target = quiz.questions[ at ];
		setBusy( 'remove' );
		setPendingError( '' );
		const request =
			pending === 'quiz'
				? trashQuiz( quiz.id )
				: deleteQuestion( quiz.id, target?.id ?? 0 );
		request
			.then( () => {
				setPending( null );
				setBusy( '' );
				setFailed( false );
				if ( pending === 'quiz' ) {
					setQuizId( 0 );
					loadQuizzes();
					setMessage( __( 'Quiz moved to the trash.', 'ohmylms' ) );
				} else {
					setCurrent( Math.max( 0, at - 1 ) );
					setMessage( __( 'Question removed.', 'ohmylms' ) );
				}
			} )
			.catch( ( failure: Error ) => {
				setPendingError( failure.message );
				setBusy( '' );
			} );
	}

	function removeQuestion() {
		if ( ! quiz ) {
			return;
		}
		const at = Math.min( current, quiz.questions.length - 1 );
		const target = quiz.questions[ at ];
		if ( target && ! target.id ) {
			// Not saved yet: nothing on the server to remove.
			setQuiz( {
				...quiz,
				questions: quiz.questions.filter( ( _, i ) => i !== at ),
			} );
			setCurrent( Math.max( 0, at - 1 ) );
			return;
		}
		setPendingError( '' );
		setPending( 'question' );
	}

	function save() {
		if ( ! quiz ) {
			return;
		}
		setSaving( true );
		void api( `/studio/quizzes/${ quiz.id }`, {
			method: 'POST',
			body: JSON.stringify( {
				settings: quiz.settings,
				questions: quiz.questions,
				title: quiz.title,
			} ),
		} )
			.then( () => {
				setFailed( false );
				setMessage( __( 'Quiz saved.', 'ohmylms' ) );
			} )
			.catch( ( failure: Error ) => {
				// The quiz in state is kept, so nothing the author typed is lost.
				setFailed( true );
				setMessage( failure.message );
			} )
			.finally( () => setSaving( false ) );
	}

	const index = quiz
		? Math.min( current, Math.max( 0, quiz.questions.length - 1 ) )
		: 0;
	const question = quiz?.questions[ index ];
	const locked =
		! quiz?.canEdit || ! question || ! isEditable( question.type );
	const previewCorrect = question
		? question.choices.every(
				( choice, choiceIndex ) =>
					Boolean( picked[ choiceIndex ] ) === choice.correct
			)
		: false;

	return (
		<section>
			<header className="oml-page-bar">
				<h1 className="oml-dash-title">
					{ __( 'Quizzes & exams', 'ohmylms' ) }
				</h1>
				<Select
					label={ __( 'Quiz', 'ohmylms' ) }
					value={ quizId }
					onChange={ ( event ) => {
						setQuizId( Number( event.target.value ) );
						setCurrent( 0 );
					} }
				>
					<option value={ 0 }>
						{ __( 'Choose a quiz', 'ohmylms' ) }
					</option>
					{ quizzes.map( ( item ) => (
						<option key={ item.id } value={ item.id }>
							{ item.title }
						</option>
					) ) }
				</Select>
				{ quiz?.canEdit && (
					<Button
						variant="primary"
						loading={ saving }
						onClick={ save }
					>
						{ __( 'Save quiz', 'ohmylms' ) }
					</Button>
				) }
				<Button
					aria-expanded={ creating }
					onClick={ () => setCreating( ! creating ) }
				>
					{ __( 'New quiz', 'ohmylms' ) }
				</Button>
			</header>
			{ creating && (
				<form
					className="oml-dash-card"
					onSubmit={ ( event ) => {
						event.preventDefault();
						setBusy( 'create' );
						createQuiz( newTitle )
							.then( ( created ) => {
								setNewTitle( '' );
								setCreating( false );
								setBusy( '' );
								loadQuizzes();
								setQuizId( created.id );
								setCurrent( 0 );
								setFailed( false );
								setMessage(
									__( 'Quiz created as a draft.', 'ohmylms' )
								);
							} )
							.catch( ( failure: Error ) => {
								setFailed( true );
								setMessage( failure.message );
								setBusy( '' );
							} );
					} }
				>
					<Input
						label={ __( 'Quiz title', 'ohmylms' ) }
						required
						value={ newTitle }
						onChange={ ( event ) =>
							setNewTitle( event.target.value )
						}
					/>
					<Button
						type="submit"
						variant="primary"
						loading={ busy === 'create' }
					>
						{ __( 'Create quiz', 'ohmylms' ) }
					</Button>
				</form>
			) }
			{ quiz && (
				<>
					<ol className="oml-pills is-quiet">
						{ quiz.levels.map( ( level ) => (
							<li key={ level }>{ level }</li>
						) ) }
					</ol>
					{ quiz.canEdit && (
						<Disclosure
							title={ __( 'Quiz settings', 'ohmylms' ) }
							open={ settingsOpen }
							onToggle={ () => setSettingsOpen( ! settingsOpen ) }
						>
							<div className="oml-inline-form">
								<Input
									label={ __( 'Quiz name', 'ohmylms' ) }
									value={ quizName }
									onChange={ ( event ) =>
										setQuizName( event.target.value )
									}
								/>
								<Button
									loading={ busy === 'rename' }
									disabled={
										! quizName || quizName === quiz.title
									}
									onClick={ () => {
										setBusy( 'rename' );
										renameQuiz( quiz.id, quizName )
											.then( () => {
												loadQuizzes();
												setFailed( false );
												setMessage(
													__(
														'Quiz renamed.',
														'ohmylms'
													)
												);
											} )
											.catch( ( failure: Error ) => {
												setFailed( true );
												setMessage( failure.message );
											} )
											.finally( () => setBusy( '' ) );
									} }
								>
									{ __( 'Rename quiz', 'ohmylms' ) }
								</Button>
								<Button
									variant="tertiary"
									onClick={ () => {
										setPendingError( '' );
										setPending( 'quiz' );
									} }
								>
									{ __( 'Move quiz to trash', 'ohmylms' ) }
								</Button>
							</div>
							<Input
								label={ __( 'Pass mark %', 'ohmylms' ) }
								type="number"
								min={ 0 }
								max={ 100 }
								value={ quiz.settings.pass }
								onChange={ ( event ) =>
									setQuiz( {
										...quiz,
										settings: {
											...quiz.settings,
											pass: Number( event.target.value ),
										},
									} )
								}
							/>
							<Input
								label={ __( 'Attempts', 'ohmylms' ) }
								help={ __( '0 means unlimited.', 'ohmylms' ) }
								type="number"
								min={ 0 }
								value={ quiz.settings.attempts }
								onChange={ ( event ) =>
									setQuiz( {
										...quiz,
										settings: {
											...quiz.settings,
											attempts: Number(
												event.target.value
											),
										},
									} )
								}
							/>
							<Checkbox
								label={ __( 'Shuffle questions', 'ohmylms' ) }
								checked={ quiz.settings.shuffle }
								onChange={ ( event ) =>
									setQuiz( {
										...quiz,
										settings: {
											...quiz.settings,
											shuffle: event.target.checked,
										},
									} )
								}
							/>
						</Disclosure>
					) }
					<div className="oml-qe-wrap">
						<div className="oml-qe">
							<QuizRail
								items={ quiz.questions }
								current={ index }
								canEdit={ quiz.canEdit }
								onSelect={ setCurrent }
								onMove={ moveQuestion }
								onAdd={ addQuestion }
							/>
							{ question ? (
								<>
									{ question.id &&
										! isEditable( question.type ) && (
											<Alert tone="info">
												{ __(
													'This question type is edited in the question editor. It is shown here, and saved unchanged.',
													'ohmylms'
												) }
											</Alert>
										) }
									<QuizCanvas
										question={ question }
										readOnly={ locked }
										onChange={ ( next ) =>
											updateQuestion( index, next )
										}
									/>
									<QuizPanel
										question={ question }
										skills={ quiz.skills }
										readOnly={ locked }
										onChange={ ( next ) =>
											updateQuestion( index, next )
										}
										onRemove={
											quiz.canEdit
												? removeQuestion
												: undefined
										}
										onPreview={ () => {
											setPreview( true );
											setChecked( false );
											setPicked( {} );
										} }
									/>
								</>
							) : (
								<p className="oml-dash-note oml-qe-empty">
									{ __(
										'This quiz has no questions yet. Add the first one.',
										'ohmylms'
									) }
								</p>
							) }
						</div>
					</div>
					{ preview && question && (
						<dialog
							className="oml-dash-dialog oml-preview-sheet"
							open
							aria-label={ __( 'Preview', 'ohmylms' ) }
						>
							<h2>{ __( 'Preview', 'ohmylms' ) }</h2>
							<Button onClick={ () => setPreview( false ) }>
								{ __( 'Close preview', 'ohmylms' ) }
							</Button>
							<p>{ question.prompt }</p>
							{ question.choices.map( ( choice, choiceIndex ) => (
								<Checkbox
									key={ choiceIndex }
									label={
										choice.text || __( 'Answer', 'ohmylms' )
									}
									checked={ Boolean( picked[ choiceIndex ] ) }
									onChange={ ( event ) =>
										setPicked( {
											...picked,
											[ choiceIndex ]:
												event.target.checked,
										} )
									}
								/>
							) ) }
							<Button
								variant="primary"
								onClick={ () => setChecked( true ) }
							>
								{ __( 'Check answer', 'ohmylms' ) }
							</Button>
							{ checked && (
								<Alert
									tone={
										previewCorrect ? 'success' : 'warning'
									}
								>
									{ previewCorrect
										? __( 'Correct.', 'ohmylms' )
										: __( 'Not yet.', 'ohmylms' ) }
								</Alert>
							) }
							{ checked && question.explanation && (
								<p>{ question.explanation }</p>
							) }
						</dialog>
					) }
				</>
			) }
			{ pending && quiz && (
				<ConfirmRemove
					title={
						pending === 'quiz'
							? __( 'Move this quiz to the trash?', 'ohmylms' )
							: __( 'Remove this question?', 'ohmylms' )
					}
					confirm={
						pending === 'quiz'
							? __( 'Move to trash', 'ohmylms' )
							: __( 'Remove question', 'ohmylms' )
					}
					busy={ busy === 'remove' }
					error={ pendingError }
					onConfirm={ confirmRemove }
					onCancel={ () => setPending( null ) }
				>
					{ pending === 'quiz' ? (
						<p>
							{ sprintf(
								/* translators: %s: quiz title. */
								__(
									'“%s” will be moved to the WordPress trash. You can restore it from there. A quiz with student attempts is never deleted permanently.',
									'ohmylms'
								),
								quiz.title
							) }
						</p>
					) : (
						<p>
							{ __(
								'The question will be taken out of this quiz.',
								'ohmylms'
							) }
						</p>
					) }
				</ConfirmRemove>
			) }
			{ message && (
				<Alert tone={ failed ? 'danger' : 'success' }>
					{ message }
				</Alert>
			) }
		</section>
	);
}
