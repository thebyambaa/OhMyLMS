import {
	Button,
	Modal,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { gradeAttempt } from './api';
import {
	LIMITS,
	attemptStatusLabel,
	buildGrades,
	draftsFromQuestions,
	formatDate,
	formatMarks,
	hasGradeChanges,
	previewTotal,
} from './model';
import { useAttemptDetail } from './useAttempts';
import type { AttemptQuestion, Drafts } from './types';

interface Props {
	quizId: number;
	attemptId: number;
	onClose: () => void;
	/** Called after marks were saved so the attempt list can refresh. */
	onGraded: () => void;
}

/**
 * Dialog to review one attempt and award marks per question. Every mark is checked against the
 * question maximum before it is sent, the server checks it again and recalculates the total, and saving
 * twice with the same marks writes nothing new.
 *
 * @param props           Component props.
 * @param props.quizId    Quiz id.
 * @param props.attemptId Attempt id.
 * @param props.onClose   Closes the dialog.
 * @param props.onGraded  Called after a successful save.
 */
export function AttemptReview( {
	quizId,
	attemptId,
	onClose,
	onGraded,
}: Props ) {
	const detail = useAttemptDetail( quizId, attemptId );
	const attempt = detail.attempt;
	const [ drafts, setDrafts ] = useState< Drafts >( {} );
	const [ reason, setReason ] = useState( '' );
	const [ errors, setErrors ] = useState< Record< number, string > >( {} );
	const [ busy, setBusy ] = useState( false );
	const [ failure, setFailure ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	useEffect( () => {
		if ( attempt ) {
			setDrafts( draftsFromQuestions( attempt.questions ) );
			setErrors( {} );
		}
	}, [ attempt ] );

	const gradable = Boolean( attempt && 'in-progress' !== attempt.status );
	const changed = attempt
		? hasGradeChanges( attempt.questions, drafts )
		: false;

	async function save() {
		if ( ! attempt || operation.current ) {
			return;
		}
		const built = buildGrades( attempt.questions, drafts );
		setErrors( built.errors );
		setFailure( null );
		setMessage( null );
		if ( Object.keys( built.errors ).length ) {
			setFailure(
				__( 'Fix the highlighted marks before saving.', 'ohmylms' )
			);
			return;
		}
		if ( ! built.grades.length ) {
			setMessage( __( 'There are no changes to save.', 'ohmylms' ) );
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		try {
			const result = await gradeAttempt(
				quizId,
				attempt.id,
				built.grades,
				reason,
				controller.signal
			);
			if ( controller.signal.aborted ) {
				return;
			}
			detail.setAttempt( result );
			setReason( '' );
			setMessage(
				sprintf(
					/* translators: %d: number of questions whose marks changed. */
					_n(
						'Marks saved. %d question changed.',
						'Marks saved. %d questions changed.',
						result.changed,
						'ohmylms'
					),
					result.changed
				)
			);
			onGraded();
		} catch ( problem ) {
			if ( ! controller.signal.aborted ) {
				setFailure( normalizeApiError( problem ).message );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				setBusy( false );
				operation.current = null;
			}
		}
	}

	function setTotal( question: AttemptQuestion, value: string ) {
		setDrafts( ( current ) => ( {
			...current,
			[ question.question_id ]: {
				total: value,
				parts: current[ question.question_id ]?.parts ?? {},
			},
		} ) );
		setMessage( null );
	}
	function setPart( question: AttemptQuestion, part: string, value: string ) {
		setDrafts( ( current ) => ( {
			...current,
			[ question.question_id ]: {
				total: current[ question.question_id ]?.total ?? '',
				parts: {
					...( current[ question.question_id ]?.parts ?? {} ),
					[ part ]: value,
				},
			},
		} ) );
		setMessage( null );
	}

	return (
		<Modal
			title={
				attempt
					? sprintf(
							/* translators: %s: student name. */
							__( 'Attempt by %s', 'ohmylms' ),
							attempt.student.name
						)
					: __( 'Attempt', 'ohmylms' )
			}
			onRequestClose={ () => {
				if ( ! busy ) {
					onClose();
				}
			} }
			shouldCloseOnClickOutside={ false }
		>
			{ detail.loading && (
				<p role="status">{ __( 'Loading attempt…', 'ohmylms' ) }</p>
			) }
			{ detail.error && (
				<Notice status="error" isDismissible={ false }>
					{ detail.error.message }
				</Notice>
			) }
			{ attempt && (
				<form
					aria-label={ __( 'Grade attempt', 'ohmylms' ) }
					noValidate
					onSubmit={ ( event ) => {
						event.preventDefault();
						void save();
					} }
				>
					<p>
						{ attempt.student.email } ·{ ' ' }
						{ attemptStatusLabel( attempt.status ) } ·{ ' ' }
						{ formatDate(
							attempt.submitted_at ?? attempt.started_at
						) }
					</p>
					<p role="status">
						{ sprintf(
							/* translators: 1: current score, 2: maximum marks. */
							__( 'Score: %1$s of %2$s', 'ohmylms' ),
							formatMarks( attempt.score ),
							formatMarks( attempt.max_marks )
						) }
						{ changed &&
							' ' +
								sprintf(
									/* translators: %s: preview of the new score. */
									__(
										'(will be about %s after saving)',
										'ohmylms'
									),
									formatMarks(
										previewTotal(
											attempt.questions,
											drafts
										)
									)
								) }
						{ null !== attempt.passed &&
							' · ' +
								( attempt.passed
									? __( 'Passed', 'ohmylms' )
									: __( 'Not passed', 'ohmylms' ) ) }
					</p>
					{ ! gradable && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'The student has not submitted this attempt yet, so it cannot be graded.',
								'ohmylms'
							) }
						</Notice>
					) }
					{ gradable && ! attempt.versioned && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'This attempt was taken before versioned grading and can only be viewed.',
								'ohmylms'
							) }
						</Notice>
					) }
					{ message && (
						<Notice
							status="success"
							onRemove={ () => setMessage( null ) }
						>
							{ message }
						</Notice>
					) }
					{ failure && (
						<Notice status="error" isDismissible={ false }>
							{ failure }
						</Notice>
					) }
					<ol>
						{ attempt.questions.map( ( question ) => {
							const draft = drafts[ question.question_id ];
							const fieldId = `oml-grade-${ question.question_id }`;
							const canGrade = gradable && attempt.versioned;
							return (
								<li key={ question.question_id }>
									<h3>{ question.title || question.type }</h3>
									<p>
										{ __( 'Answer:', 'ohmylms' ) }{ ' ' }
										{ question.answer.length
											? question.answer.join( '; ' )
											: __( 'No answer', 'ohmylms' ) }
									</p>
									<p>
										{ 'in-review' === question.status
											? __(
													'Waiting for grading',
													'ohmylms'
												)
											: sprintf(
													/* translators: 1: marks awarded, 2: maximum marks. */
													__(
														'Marked %1$s of %2$s',
														'ohmylms'
													),
													formatMarks(
														question.awarded
													),
													formatMarks(
														question.max_marks
													)
												) }
									</p>
									{ question.parts &&
									question.parts.length > 0 ? (
										<fieldset
											disabled={ busy || ! canGrade }
										>
											<legend>
												{ sprintf(
													/* translators: %s: question title. */
													__(
														'Marks by part for %s',
														'ohmylms'
													),
													question.title
												) }
											</legend>
											{ question.parts.map( ( part ) => (
												<TextControl
													key={ part.id }
													label={ sprintf(
														/* translators: 1: part id, 2: maximum marks. */
														__(
															'Part %1$s (out of %2$s)',
															'ohmylms'
														),
														part.id,
														formatMarks( part.max )
													) }
													value={
														draft?.parts[
															part.id
														] ?? ''
													}
													inputMode="decimal"
													onChange={ ( value ) =>
														setPart(
															question,
															part.id,
															value
														)
													}
												/>
											) ) }
										</fieldset>
									) : (
										<TextControl
											label={ sprintf(
												/* translators: 1: question title, 2: maximum marks. */
												__(
													'Marks for %1$s (out of %2$s)',
													'ohmylms'
												),
												question.title || question.type,
												formatMarks(
													question.max_marks
												)
											) }
											value={ draft?.total ?? '' }
											inputMode="decimal"
											disabled={ busy || ! canGrade }
											aria-invalid={
												errors[ question.question_id ]
													? true
													: undefined
											}
											aria-describedby={
												errors[ question.question_id ]
													? `${ fieldId }-error`
													: undefined
											}
											onChange={ ( value ) =>
												setTotal( question, value )
											}
										/>
									) }
									{ errors[ question.question_id ] && (
										<p
											id={ `${ fieldId }-error` }
											role="alert"
										>
											{ errors[ question.question_id ] }
										</p>
									) }
									{ question.history.length > 0 && (
										<details>
											<summary>
												{ __(
													'Grade history',
													'ohmylms'
												) }
											</summary>
											<ul>
												{ question.history.map(
													( event, index ) => (
														<li
															key={ `${ event.created_at }-${ index }` }
														>
															{ sprintf(
																/* translators: 1: marks, 2: who graded, 3: date. */
																__(
																	'%1$s marks by %2$s on %3$s',
																	'ohmylms'
																),
																formatMarks(
																	event.awarded
																),
																'manual' ===
																	event.grader
																	? event.reviewer_name ||
																			__(
																				'a grader',
																				'ohmylms'
																			)
																	: __(
																			'automatic grading',
																			'ohmylms'
																		),
																formatDate(
																	event.created_at
																)
															) }
															{ event.reason &&
																` — ${ event.reason }` }
														</li>
													)
												) }
											</ul>
										</details>
									) }
								</li>
							);
						} ) }
					</ol>
					{ gradable && attempt.versioned && (
						<TextareaControl
							label={ __( 'Note (optional)', 'ohmylms' ) }
							value={ reason }
							rows={ 2 }
							maxLength={ LIMITS.reason }
							disabled={ busy }
							help={ __(
								'Kept with the grade history of the questions you change.',
								'ohmylms'
							) }
							onChange={ setReason }
						/>
					) }
					{ gradable && attempt.versioned && (
						<Button
							type="submit"
							variant="primary"
							disabled={ busy || ! changed }
							isBusy={ busy }
						>
							{ __( 'Save marks', 'ohmylms' ) }
						</Button>
					) }{ ' ' }
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ onClose }
					>
						{ __( 'Close', 'ohmylms' ) }
					</Button>
				</form>
			) }
		</Modal>
	);
}
