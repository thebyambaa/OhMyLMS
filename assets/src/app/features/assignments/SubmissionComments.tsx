import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { addComment, deleteComment, listComments } from './api';
import { LIMITS, formatDate } from './model';
import type { SubmissionComment } from './types';

/**
 * Comment thread of one submission on the grading screen. Posting emails the learner; the learner
 * replies from the assignment page. Comments are plain text.
 *
 * @param props              Component props.
 * @param props.assignmentId Assignment ID.
 * @param props.submissionId Submission ID.
 */
export default function SubmissionComments( {
	assignmentId,
	submissionId,
}: {
	assignmentId: number;
	submissionId: number;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) =>
			listComments( assignmentId, submissionId, signal ),
		[ assignmentId, submissionId ]
	);
	const request = useRequest( load );
	const operation = useOperation();
	const [ items, setItems ] = useState< SubmissionComment[] >( [] );
	const [ body, setBody ] = useState( '' );
	const [ message, setMessage ] = useState< string | null >( null );

	useEffect( () => {
		if ( request.data ) {
			setItems( request.data.items );
		}
	}, [ request.data ] );

	const heading = (
		<h3 id="oml-asg-comments">{ __( 'Comments', 'ohmylms' ) }</h3>
	);
	if ( request.loading && ! request.data ) {
		return (
			<section aria-labelledby="oml-asg-comments">
				{ heading }
				<p role="status">{ __( 'Loading comments…', 'ohmylms' ) }</p>
			</section>
		);
	}
	if ( ! request.data ) {
		return (
			<section aria-labelledby="oml-asg-comments">
				{ heading }
				<Notice status="error" isDismissible={ false }>
					{ request.error ||
						__( 'The comments could not be loaded.', 'ohmylms' ) }
				</Notice>
				<Button variant="secondary" onClick={ request.retry }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			</section>
		);
	}
	const enabled = request.data.comments_enabled;

	function post() {
		const text = body.trim();
		setMessage( null );
		if ( ! text || operation.busy ) {
			return;
		}
		void operation.run(
			( signal ) =>
				addComment( assignmentId, submissionId, text, signal ),
			( comment ) => {
				setItems( ( current ) => [ ...current, comment ] );
				setBody( '' );
				setMessage(
					__( 'Comment posted. The learner is emailed.', 'ohmylms' )
				);
			}
		);
	}

	function remove( comment: SubmissionComment ) {
		setMessage( null );
		void operation.run(
			( signal ) =>
				deleteComment( assignmentId, submissionId, comment.id, signal ),
			() => {
				setItems( ( current ) =>
					current.filter( ( item ) => item.id !== comment.id )
				);
				setMessage( __( 'Comment deleted.', 'ohmylms' ) );
			}
		);
	}

	return (
		<section aria-labelledby="oml-asg-comments">
			{ heading }
			{ items.length ? (
				<ol>
					{ items.map( ( comment ) => (
						<li key={ comment.id }>
							<p>
								<strong>{ comment.author.display_name }</strong>
								{ 'instructor' === comment.role
									? ` (${ __( 'instructor', 'ohmylms' ) })`
									: ` (${ __( 'learner', 'ohmylms' ) })` }
								{ ' · ' }
								<time dateTime={ comment.created_at }>
									{ formatDate( comment.created_at ) }
								</time>
							</p>
							<p className="ohmylms-pre-wrap">{ comment.body }</p>
							{ comment.can_delete && (
								<Button
									variant="link"
									isDestructive
									disabled={ operation.busy }
									onClick={ () => remove( comment ) }
								>
									{ sprintf(
										/* translators: %s: comment author. */
										__( 'Delete comment by %s', 'ohmylms' ),
										comment.author.display_name
									) }
								</Button>
							) }
						</li>
					) ) }
				</ol>
			) : (
				<p>{ __( 'No comments yet.', 'ohmylms' ) }</p>
			) }
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
			{ enabled ? (
				<form
					aria-label={ __( 'Add a comment', 'ohmylms' ) }
					noValidate
					onSubmit={ ( event ) => {
						event.preventDefault();
						post();
					} }
				>
					<TextareaControl
						label={ __( 'Comment for the learner', 'ohmylms' ) }
						value={ body }
						rows={ 3 }
						maxLength={ LIMITS.comment }
						onChange={ setBody }
					/>
					<Button
						type="submit"
						variant="secondary"
						disabled={ operation.busy || ! body.trim() }
						isBusy={ operation.busy }
					>
						{ __( 'Post comment', 'ohmylms' ) }
					</Button>
				</form>
			) : (
				<p>
					{ __(
						'Comments are switched off for this assignment. Switch them on in the assignment editor.',
						'ohmylms'
					) }
				</p>
			) }
		</section>
	);
}
