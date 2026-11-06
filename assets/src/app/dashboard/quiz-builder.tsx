import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Alert } from '../../ui/Alert';
import { Badge } from '../../ui/Badge';
import { Button } from '../../ui/Button';
import { Checkbox } from '../../ui/Checkbox';
import { Input } from '../../ui/Input';
import { Select } from '../../ui/Select';
import { Textarea } from '../../ui/Textarea';
import { api } from './api';
import { Disclosure } from './field';

interface Choice {
	id?: number;
	text: string;
	correct: boolean;
}

interface Question {
	id?: number;
	prompt: string;
	type: string;
	choices: Choice[];
	hint: string;
	explanation: string;
	skill: number;
	answer?: number;
}

interface QuizBody {
	id: number;
	title: string;
	canEdit: boolean;
	settings: { pass: number; attempts: number; shuffle: boolean };
	levels: string[];
	questions: Question[];
	skills: Array< { id: number; name: string } >;
	skillIds: number[];
}

function studioType( type: string ) {
	const map: Record< string, string > = {
		'single-choice': 'single_choice',
		'multiple-choice': 'multiple_choice',
		'short-text': 'short_answer',
		'true-false': 'true_false',
		numerical: 'numerical',
	};
	return map[ type ] || type;
}

const TYPES = [
	[ 'single_choice', __( 'Single choice', 'ohmylms' ) ],
	[ 'multiple_choice', __( 'Multiple choice', 'ohmylms' ) ],
	[ 'numerical', __( 'Numerical', 'ohmylms' ) ],
	[ 'short_answer', __( 'Short answer', 'ohmylms' ) ],
	[ 'true_false', __( 'True or false', 'ohmylms' ) ],
] as const;

function blank( type: string ): Question {
	if ( type === 'true_false' ) {
		return {
			prompt: '',
			type,
			choices: [
				{ text: __( 'True', 'ohmylms' ), correct: true },
				{ text: __( 'False', 'ohmylms' ), correct: false },
			],
			hint: '',
			explanation: '',
			skill: 0,
		};
	}
	if ( type === 'numerical' || type === 'short_answer' ) {
		return {
			prompt: '',
			type,
			choices: [ { text: '', correct: true } ],
			hint: '',
			explanation: '',
			skill: 0,
			answer: 0,
		};
	}
	return {
		prompt: '',
		type,
		choices: [
			{ text: '', correct: true },
			{ text: '', correct: false },
		],
		hint: '',
		explanation: '',
		skill: 0,
	};
}

/**
 * Quiz and exam builder. Questions are saved on question posts, not inside the quiz body.
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
	const [ preview, setPreview ] = useState( -1 );
	const [ picked, setPicked ] = useState< Record< number, boolean > >( {} );
	const [ checked, setChecked ] = useState( false );
	const [ settingsOpen, setSettingsOpen ] = useState( false );
	const [ openQ, setOpenQ ] = useState( 0 );

	useEffect( () => {
		void api< { quizzes: Array< { id: number; title: string } > } >(
			'/studio/quizzes'
		).then( ( body ) => setQuizzes( body.quizzes ) );
	}, [] );
	useEffect( () => {
		if ( ! quizId ) {
			setQuiz( null );
			return;
		}
		void api< QuizBody >( `/studio/quizzes/${ quizId }` ).then( setQuiz );
	}, [ quizId, message ] );

	function updateQuestion( index: number, next: Question ) {
		if ( ! quiz ) {
			return;
		}
		const questions = quiz.questions.slice();
		questions[ index ] = next;
		setQuiz( { ...quiz, questions } );
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

	const previewQuestion = preview >= 0 ? quiz?.questions[ preview ] : null;
	const previewCorrect = previewQuestion
		? previewQuestion.choices.every(
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
					onChange={ ( event ) =>
						setQuizId( Number( event.target.value ) )
					}
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
			</header>
			{ quiz && (
				<>
					<ol className="oml-pills is-quiet">
						{ quiz.levels.map( ( level ) => (
							<li key={ level }>{ level }</li>
						) ) }
					</ol>
					{ quiz.canEdit && (
						<Disclosure
							title={ __( 'Settings', 'ohmylms' ) }
							open={ settingsOpen }
							onToggle={ () => setSettingsOpen( ! settingsOpen ) }
						>
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
					{ quiz.questions.map( ( question, index ) => (
						<article
							key={ question.id || index }
							className="oml-dash-card"
						>
							<Button
								variant="tertiary"
								block
								className="oml-q-head"
								aria-expanded={ openQ === index }
								onClick={ () =>
									setOpenQ( openQ === index ? -1 : index )
								}
							>
								<span>
									{ question.prompt ||
										__( 'New question', 'ohmylms' ) }
								</span>
								<Badge>
									{ studioType( question.type ).replace(
										'_',
										' '
									) }
								</Badge>
							</Button>
							{ openQ === index && (
								<>
									<Input
										label={ __( 'Prompt', 'ohmylms' ) }
										value={ question.prompt }
										onChange={ ( event ) =>
											updateQuestion( index, {
												...question,
												prompt: event.target.value,
											} )
										}
									/>
									<Select
										label={ __( 'Type', 'ohmylms' ) }
										value={ studioType( question.type ) }
										onChange={ ( event ) =>
											updateQuestion(
												index,
												blank( event.target.value )
											)
										}
										options={ TYPES.map(
											( [ value, label ] ) => ( {
												value,
												label,
											} )
										) }
									/>
									{ question.choices.map(
										( choice, choiceIndex ) => (
											<div
												key={ choice.id || choiceIndex }
												className="oml-choice"
											>
												<Input
													label={ sprintf(
														/* translators: %d: answer number. */
														__(
															'Answer %d',
															'ohmylms'
														),
														choiceIndex + 1
													) }
													value={ choice.text }
													onChange={ ( event ) => {
														const choices =
															question.choices.slice();
														choices[ choiceIndex ] =
															{
																...choice,
																text: event
																	.target
																	.value,
															};
														updateQuestion( index, {
															...question,
															choices,
														} );
													} }
												/>
												<Checkbox
													label={ __(
														'Correct',
														'ohmylms'
													) }
													checked={ choice.correct }
													onChange={ ( event ) => {
														const choices =
															question.choices.slice();
														choices[ choiceIndex ] =
															{
																...choice,
																correct:
																	event.target
																		.checked,
															};
														updateQuestion( index, {
															...question,
															choices,
														} );
													} }
												/>
											</div>
										)
									) }
									<Input
										label={ __( 'Hint', 'ohmylms' ) }
										value={ question.hint }
										onChange={ ( event ) =>
											updateQuestion( index, {
												...question,
												hint: event.target.value,
											} )
										}
									/>
									<Textarea
										label={ __( 'Explanation', 'ohmylms' ) }
										rows={ 3 }
										value={ question.explanation }
										onChange={ ( event ) =>
											updateQuestion( index, {
												...question,
												explanation: event.target.value,
											} )
										}
									/>
									<Select
										label={ __( 'Skill', 'ohmylms' ) }
										value={ question.skill }
										onChange={ ( event ) =>
											updateQuestion( index, {
												...question,
												skill: Number(
													event.target.value
												),
											} )
										}
									>
										<option value={ 0 }>
											{ __( 'None', 'ohmylms' ) }
										</option>
										{ quiz.skills.map( ( skill ) => (
											<option
												key={ skill.id }
												value={ skill.id }
											>
												{ skill.name }
											</option>
										) ) }
									</Select>
									<Button
										onClick={ () => {
											setPreview( index );
											setChecked( false );
											setPicked( {} );
										} }
									>
										{ __( 'Preview question', 'ohmylms' ) }
									</Button>
								</>
							) }
						</article>
					) ) }
					{ quiz.canEdit && (
						<div className="oml-dash-actions">
							<Button
								variant="tertiary"
								onClick={ () => {
									setOpenQ( quiz.questions.length );
									setQuiz( {
										...quiz,
										questions: quiz.questions.concat( [
											blank( 'single_choice' ),
										] ),
									} );
								} }
							>
								{ __( 'Add question', 'ohmylms' ) }
							</Button>
						</div>
					) }
					{ previewQuestion && (
						<dialog
							className="oml-dash-dialog oml-preview-sheet"
							open
						>
							<h2>{ __( 'Preview', 'ohmylms' ) }</h2>
							<Button onClick={ () => setPreview( -1 ) }>
								{ __( 'Close preview', 'ohmylms' ) }
							</Button>
							<p>{ previewQuestion.prompt }</p>
							{ previewQuestion.choices.map(
								( choice, choiceIndex ) => (
									<Checkbox
										key={ choiceIndex }
										label={
											choice.text ||
											__( 'Answer', 'ohmylms' )
										}
										checked={ Boolean(
											picked[ choiceIndex ]
										) }
										onChange={ ( event ) =>
											setPicked( {
												...picked,
												[ choiceIndex ]:
													event.target.checked,
											} )
										}
									/>
								)
							) }
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
							{ checked && previewQuestion.explanation && (
								<p>{ previewQuestion.explanation }</p>
							) }
						</dialog>
					) }
				</>
			) }
			{ message && (
				<Alert tone={ failed ? 'danger' : 'success' }>
					{ message }
				</Alert>
			) }
		</section>
	);
}
