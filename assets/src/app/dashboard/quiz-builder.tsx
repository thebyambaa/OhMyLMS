import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api } from './api';
import { Disclosure, Field } from './field';

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

	return (
		<section>
			<header className="oml-page-bar">
				<h1 className="oml-dash-title">
					{ __( 'Quizzes & exams', 'ohmylms' ) }
				</h1>
				<Field label={ __( 'Quiz', 'ohmylms' ) }>
					<select
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
					</select>
				</Field>
				{ quiz?.canEdit && (
					<button
						type="button"
						className="oml-dash-btn is-primary"
						onClick={ () => {
							void api( `/studio/quizzes/${ quiz.id }`, {
								method: 'POST',
								body: JSON.stringify( {
									settings: quiz.settings,
									questions: quiz.questions,
									title: quiz.title,
								} ),
							} )
								.then( () =>
									setMessage( __( 'Quiz saved.', 'ohmylms' ) )
								)
								.catch( ( failure: Error ) =>
									setMessage( failure.message )
								);
						} }
					>
						{ __( 'Save quiz', 'ohmylms' ) }
					</button>
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
							<Field label={ __( 'Pass mark %', 'ohmylms' ) }>
								<input
									type="number"
									min={ 0 }
									max={ 100 }
									value={ quiz.settings.pass }
									onChange={ ( event ) =>
										setQuiz( {
											...quiz,
											settings: {
												...quiz.settings,
												pass: Number(
													event.target.value
												),
											},
										} )
									}
								/>
							</Field>
							<Field label={ __( 'Attempts', 'ohmylms' ) }>
								<input
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
							</Field>
							<div className="oml-dash-field">
								<input
									id="oml-quiz-shuffle"
									type="checkbox"
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
								/>{ ' ' }
								<label htmlFor="oml-quiz-shuffle">
									{ __( 'Shuffle questions', 'ohmylms' ) }
								</label>
							</div>
						</Disclosure>
					) }
					{ quiz.questions.map( ( question, index ) => (
						<article
							key={ question.id || index }
							className="oml-dash-card"
						>
							<button
								type="button"
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
								<span className="oml-badge">
									{ studioType( question.type ).replace(
										'_',
										' '
									) }
								</span>
							</button>
							{ openQ === index && (
								<>
									<Field label={ __( 'Prompt', 'ohmylms' ) }>
										<input
											value={ question.prompt }
											onChange={ ( event ) =>
												updateQuestion( index, {
													...question,
													prompt: event.target.value,
												} )
											}
										/>
									</Field>
									<Field label={ __( 'Type', 'ohmylms' ) }>
										<select
											value={ studioType(
												question.type
											) }
											onChange={ ( event ) =>
												updateQuestion(
													index,
													blank( event.target.value )
												)
											}
										>
											{ TYPES.map(
												( [ value, label ] ) => (
													<option
														key={ value }
														value={ value }
													>
														{ label }
													</option>
												)
											) }
										</select>
									</Field>
									{ question.choices.map(
										( choice, choiceIndex ) => (
											<label
												key={ choice.id || choiceIndex }
												className="oml-dash-field oml-choice"
												htmlFor={ `oml-choice-${ index }-${ choiceIndex }` }
											>
												<span className="screen-reader-text">
													{ __(
														'Answer',
														'ohmylms'
													) }
												</span>
												<input
													id={ `oml-choice-${ index }-${ choiceIndex }-mark` }
													type="checkbox"
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
												<input
													id={ `oml-choice-${ index }-${ choiceIndex }` }
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
											</label>
										)
									) }
									<Field label={ __( 'Hint', 'ohmylms' ) }>
										<input
											value={ question.hint }
											onChange={ ( event ) =>
												updateQuestion( index, {
													...question,
													hint: event.target.value,
												} )
											}
										/>
									</Field>
									<Field
										label={ __( 'Explanation', 'ohmylms' ) }
									>
										<input
											value={ question.explanation }
											onChange={ ( event ) =>
												updateQuestion( index, {
													...question,
													explanation:
														event.target.value,
												} )
											}
										/>
									</Field>
									<Field label={ __( 'Skill', 'ohmylms' ) }>
										<select
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
										</select>
									</Field>
									<button
										type="button"
										className="oml-dash-btn"
										onClick={ () => {
											setPreview( index );
											setChecked( false );
											setPicked( {} );
										} }
									>
										{ __( 'Preview', 'ohmylms' ) }
									</button>
								</>
							) }
						</article>
					) ) }
					{ quiz.canEdit && (
						<div className="oml-dash-actions">
							<button
								type="button"
								className="oml-dash-btn is-ghost"
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
							</button>
						</div>
					) }
					{ preview >= 0 && quiz.questions[ preview ] && (
						<dialog
							className="oml-dash-dialog oml-preview-sheet"
							open
						>
							<h2>{ __( 'Preview', 'ohmylms' ) }</h2>
							<button
								type="button"
								className="oml-dash-btn"
								onClick={ () => setPreview( -1 ) }
							>
								{ __( 'Close', 'ohmylms' ) }
							</button>
							<p>{ quiz.questions[ preview ].prompt }</p>
							{ quiz.questions[ preview ].choices.map(
								( choice, choiceIndex ) => (
									<label
										key={ choiceIndex }
										className="oml-dash-field"
										htmlFor={ `oml-preview-${ choiceIndex }` }
									>
										<input
											id={ `oml-preview-${ choiceIndex }` }
											type="checkbox"
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
										/>{ ' ' }
										{ choice.text ||
											__( 'Answer', 'ohmylms' ) }
									</label>
								)
							) }
							<button
								type="button"
								className="oml-dash-btn is-primary"
								onClick={ () => setChecked( true ) }
							>
								{ __( 'Check', 'ohmylms' ) }
							</button>
							{ checked && (
								<p role="status">
									{ quiz.questions[ preview ].choices.every(
										( choice, choiceIndex ) =>
											Boolean( picked[ choiceIndex ] ) ===
											choice.correct
									)
										? __( 'Correct.', 'ohmylms' )
										: __( 'Not yet.', 'ohmylms' ) }
								</p>
							) }
							{ checked &&
								quiz.questions[ preview ].explanation && (
									<p>
										{
											quiz.questions[ preview ]
												.explanation
										}
									</p>
								) }
						</dialog>
					) }
				</>
			) }
			{ message && <p role="status">{ message }</p> }
		</section>
	);
}
