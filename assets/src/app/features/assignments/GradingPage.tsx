import {
	Button,
	Modal,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { getSubmission, gradeSubmission } from './api';
import {
	LIMITS,
	formatDate,
	formatSize,
	parseScore,
	submissionLabel,
} from './model';
import type { SubmissionDetail } from './types';

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

/**
 * Grading view (`#/assignments/<id>/submissions/<submissionId>`): the learner's text and files, and a
 * score with feedback. Grading goes through the same pipeline as the legacy screen, so the learner
 * gets the same result, completion and email.
 */
export default function GradingPage() {
	const params = useParams();
	const id = routeId( params.id );
	const submissionId = routeId( params.submissionId );
	const load = useCallback(
		( signal: AbortSignal ) =>
			null === id || null === submissionId
				? Promise.reject( new Error( 'invalid id' ) )
				: getSubmission( id, submissionId, signal ),
		[ id, submissionId ]
	);
	const request = useRequest( load );
	const operation = useOperation();
	const [ current, setCurrent ] = useState< SubmissionDetail | null >( null );
	const [ score, setScore ] = useState( '' );
	const [ feedback, setFeedback ] = useState( '' );
	const [ scoreError, setScoreError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const dirty = Boolean(
		current &&
		( score !== ( null === current.score ? '' : String( current.score ) ) ||
			feedback !== current.feedback )
	);
	const guard = useUnsavedChangesGuard( dirty );

	const adopt = useCallback( ( submission: SubmissionDetail ) => {
		setCurrent( submission );
		setScore( null === submission.score ? '' : String( submission.score ) );
		setFeedback( submission.feedback );
		setScoreError( null );
	}, [] );

	useEffect( () => {
		if ( request.data ) {
			adopt( request.data );
		}
	}, [ request.data, adopt ] );

	const back =
		null === id ? '/assignments' : `/assignments/${ id }/submissions`;
	if ( null === id || null === submissionId ) {
		return (
			<>
				<h2>{ __( 'Grade submission', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This submission link is invalid.', 'ohmylms' ) }
				</Notice>
				<Link to={ back }>{ __( 'Back', 'ohmylms' ) }</Link>
			</>
		);
	}
	if ( request.loading && ! current ) {
		return (
			<>
				<h2>{ __( 'Grade submission', 'ohmylms' ) }</h2>
				<p role="status">{ __( 'Loading submission…', 'ohmylms' ) }</p>
			</>
		);
	}
	if ( ! current ) {
		return (
			<>
				<h2>{ __( 'Grade submission', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ request.error ||
						__( 'The submission could not be loaded.', 'ohmylms' ) }
				</Notice>
				<p>
					<Button variant="secondary" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>{ ' ' }
					<Link to={ back }>
						{ __( 'Back to submissions', 'ohmylms' ) }
					</Link>
				</p>
			</>
		);
	}

	function grade() {
		if ( ! current || null === id || null === submissionId ) {
			return;
		}
		const parsed = parseScore( score, current.max_score );
		setMessage( null );
		if ( 'error' in parsed ) {
			setScoreError( parsed.error );
			return;
		}
		setScoreError( null );
		void operation.run(
			( signal ) =>
				gradeSubmission(
					id,
					submissionId,
					{ score: parsed.score, feedback },
					signal
				),
			( result ) => {
				adopt( result );
				setMessage(
					'passed' === result.result
						? __( 'Grade saved. The learner passed.', 'ohmylms' )
						: __(
								'Grade saved. The learner did not pass.',
								'ohmylms'
							)
				);
			}
		);
	}

	return (
		<>
			<p>
				<Link to={ back }>
					{ __( 'Back to submissions', 'ohmylms' ) }
				</Link>
			</p>
			<h2>
				{ sprintf(
					/* translators: %s: student name. */
					__( 'Submission by %s', 'ohmylms' ),
					current.student.display_name
				) }
			</h2>
			<dl>
				<dt>{ __( 'Course', 'ohmylms' ) }</dt>
				<dd>{ current.course?.title ?? '—' }</dd>
				<dt>{ __( 'Submitted', 'ohmylms' ) }</dt>
				<dd>{ formatDate( current.submitted_at ) }</dd>
				<dt>{ __( 'Status', 'ohmylms' ) }</dt>
				<dd>{ submissionLabel( current ) }</dd>
				{ null !== current.graded_at && (
					<>
						<dt>{ __( 'Graded', 'ohmylms' ) }</dt>
						<dd>{ formatDate( current.graded_at ) }</dd>
					</>
				) }
			</dl>
			<section aria-labelledby="oml-asg-work">
				<h3 id="oml-asg-work">{ __( 'Learner work', 'ohmylms' ) }</h3>
				{ current.content ? (
					<p className="ohmylms-pre-wrap">{ current.content }</p>
				) : (
					<p>{ __( 'No text was submitted.', 'ohmylms' ) }</p>
				) }
				{ current.attachments.length ? (
					<ul>
						{ current.attachments.map( ( file ) => (
							<li key={ file.url }>
								<a href={ file.url } download={ file.name }>
									{ file.name }
								</a>{ ' ' }
								({ formatSize( file.size ) })
							</li>
						) ) }
					</ul>
				) : (
					<p>{ __( 'No file is available.', 'ohmylms' ) }</p>
				) }
			</section>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<form
				aria-label={ __( 'Grade', 'ohmylms' ) }
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					grade();
				} }
			>
				<fieldset disabled={ operation.busy }>
					<legend>{ __( 'Grade', 'ohmylms' ) }</legend>
					<TextControl
						label={ sprintf(
							/* translators: %d: maximum score. */
							__( 'Score (out of %d)', 'ohmylms' ),
							current.max_score
						) }
						help={ sprintf(
							/* translators: %d: passing score. */
							__(
								'The learner passes with %d or more.',
								'ohmylms'
							),
							current.pass_score
						) }
						value={ score }
						inputMode="numeric"
						required
						aria-invalid={ scoreError ? true : undefined }
						aria-describedby={
							scoreError ? 'oml-asg-score-error' : undefined
						}
						onChange={ setScore }
					/>
					{ scoreError && (
						<p id="oml-asg-score-error" role="alert">
							{ scoreError }
						</p>
					) }
					<TextareaControl
						label={ __( 'Feedback for the learner', 'ohmylms' ) }
						value={ feedback }
						rows={ 5 }
						maxLength={ LIMITS.feedback }
						onChange={ setFeedback }
					/>
				</fieldset>
				<Button
					type="submit"
					variant="primary"
					disabled={ operation.busy }
					isBusy={ operation.busy }
				>
					{ 'graded' === current.status
						? __( 'Update grade', 'ohmylms' )
						: __( 'Save grade', 'ohmylms' ) }
				</Button>
			</form>
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Discard unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
					shouldCloseOnClickOutside={ false }
				>
					<p>{ __( 'This grade has not been saved.', 'ohmylms' ) }</p>
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
