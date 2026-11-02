import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { useOperation } from '../../hooks/useOperation';
import { DELETE_BLOCKED, deleteQuestions } from './api';
import { blockedFromError, deleteOutcome } from './model';
import type { BlockedDelete, BulkDeleteResult, QuestionUsage } from './types';

export interface DeleteCandidate {
	id: number;
	name: string;
	usage: QuestionUsage;
}

function outcomeText( item: DeleteCandidate ): string {
	const outcome = deleteOutcome( item.usage );
	if ( 'blocked' === outcome ) {
		return sprintf(
			/* translators: %d: number of quizzes. */
			__(
				'Not deleted: it is used in %d quizzes. Remove it from all but one quiz first.',
				'ohmylms'
			),
			item.usage.quizzes
		);
	}
	if ( 'archive' === outcome ) {
		return __(
			'Archived instead: learners have answered it, so its history is kept.',
			'ohmylms'
		);
	}
	return item.usage.quizzes
		? __( 'Deleted and removed from its quiz.', 'ohmylms' )
		: __( 'Deleted permanently.', 'ohmylms' );
}

/**
 * Confirm deleting one or more questions. Each question shows what will happen: unused questions are
 * deleted, answered ones archived, and questions in several quizzes are left alone. When the server
 * refuses some questions (for example another editor placed one in a second quiz meanwhile), nothing is
 * changed and the dialog offers to delete the others.
 *
 * @param props
 * @param props.items   Questions to delete.
 * @param props.onClose Closes without deleting.
 * @param props.onDone  Called with the results.
 */
export function DeleteQuestionsDialog( {
	items,
	onClose,
	onDone,
}: {
	items: readonly DeleteCandidate[];
	onClose: () => void;
	onDone: ( result: BulkDeleteResult ) => void;
} ) {
	const operation = useOperation();
	const [ blocked, setBlocked ] = useState< BlockedDelete[] >( [] );
	const [ failure, setFailure ] = useState< string | null >( null );
	const predicted = items.filter(
		( item ) => 'blocked' === deleteOutcome( item.usage )
	);
	const skip = predicted.length > 0 || blocked.length > 0;
	const blockedIds = new Set( [
		...predicted.map( ( item ) => item.id ),
		...blocked.map( ( item ) => item.id ),
	] );
	const remaining = items.filter( ( item ) => ! blockedIds.has( item.id ) );

	function submit() {
		setFailure( null );
		void operation.run(
			async ( signal ) => {
				try {
					return await deleteQuestions(
						items.map( ( item ) => item.id ),
						skip,
						signal
					);
				} catch ( problem ) {
					const error = normalizeApiError( problem );
					if ( DELETE_BLOCKED === error.code ) {
						setBlocked( blockedFromError( error.details ) );
						setFailure( error.message );
						return null;
					}
					throw problem;
				}
			},
			( result ) => {
				if ( result ) {
					onDone( result );
				}
			}
		);
	}

	const name = ( item: DeleteCandidate ) =>
		item.name.trim() || __( 'Untitled question', 'ohmylms' );

	return (
		<Modal
			title={ _n(
				'Delete question',
				'Delete questions',
				items.length,
				'ohmylms'
			) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			{ ( failure || operation.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ failure || operation.error }
				</Notice>
			) }
			<ul>
				{ items.map( ( item ) => {
					const refused = blocked.find(
						( entry ) => entry.id === item.id
					);
					return (
						<li key={ item.id }>
							<strong>{ name( item ) }</strong>
							{ ': ' }
							{ refused
								? sprintf(
										/* translators: %s: reason from the server. */
										__( 'Not deleted: %s', 'ohmylms' ),
										refused.message
									)
								: outcomeText( item ) }
						</li>
					);
				} ) }
			</ul>
			<p>
				{ __(
					'Quizzes that held a deleted question are updated; their open editors will ask to reload.',
					'ohmylms'
				) }
			</p>
			<Button
				variant="primary"
				isDestructive
				disabled={ operation.busy || ! remaining.length }
				isBusy={ operation.busy }
				onClick={ submit }
			>
				{ skip
					? sprintf(
							/* translators: %d: number of questions. */
							_n(
								'Delete the other %d question',
								'Delete the other %d questions',
								remaining.length,
								'ohmylms'
							),
							remaining.length
						)
					: sprintf(
							/* translators: %d: number of questions. */
							_n(
								'Delete %d question',
								'Delete %d questions',
								items.length,
								'ohmylms'
							),
							items.length
						) }
			</Button>{ ' ' }
			<Button
				variant="tertiary"
				disabled={ operation.busy }
				onClick={ onClose }
			>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
