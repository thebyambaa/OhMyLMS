import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	getResults,
	getResultsLog,
	releaseResults,
	revokeResults,
	saveResultsPolicy,
} from './api';
import {
	RESULTS_MODES,
	formatDate,
	fromLocalInput,
	logActionLabel,
	releaseStateLabel,
	resultsModeLabel,
	skipReasonLabel,
	toLocalInput,
} from './model';
import type { ResultsMode, ResultsView, SkippedAttempt } from './types';

interface ResultsPanelProps {
	examId: number;
	/** The author may change the policy and release (`publish_posts`). */
	canRelease: boolean;
}

function skippedText( skipped: SkippedAttempt[] ): string {
	return skipped
		.map( ( item ) =>
			sprintf(
				/* translators: 1: attempt ID; 2: reason. */
				__( 'attempt %1$d: %2$s', 'ohmylms' ),
				item.id,
				skipReasonLabel( item.reason )
			)
		)
		.join( '; ' );
}

/**
 * Release log, newest first.
 * @param root0
 * @param root0.examId
 */
function ReleaseLog( { examId }: { examId: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => getResultsLog( examId, 1, signal ),
		[ examId ]
	);
	const log = useRequest( load );
	if ( log.loading ) {
		return <p role="status">{ __( 'Loading the log…', 'ohmylms' ) }</p>;
	}
	if ( log.error || ! log.data ) {
		return <p role="alert">{ log.error }</p>;
	}
	if ( ! log.data.items.length ) {
		return <p>{ __( 'Nothing has been logged yet.', 'ohmylms' ) }</p>;
	}
	return (
		<ol aria-label={ __( 'Release log', 'ohmylms' ) }>
			{ log.data.items.map( ( entry ) => (
				<li key={ entry.id }>
					{ formatDate( entry.at ) } —{ ' ' }
					{ logActionLabel( entry.action ) }
					{ entry.attempt_id > 0 &&
						` · ${ sprintf(
							/* translators: %d: attempt ID. */
							__( 'attempt %d', 'ohmylms' ),
							entry.attempt_id
						) }` }
					{ entry.detail && ` · ${ entry.detail }` }
					{ entry.actor.name && ` · ${ entry.actor.name }` }
				</li>
			) ) }
		</ol>
	);
}

/**
 * Results of an exam: when learners see them (immediately, on a date, or when released), the attempts
 * with their release state, single and bulk release with an email to learners, undo before the learner
 * has seen them, and the release log.
 * @param root0
 * @param root0.examId
 * @param root0.canRelease
 */
export default function ResultsPanel( {
	examId,
	canRelease,
}: ResultsPanelProps ) {
	const [ page, setPage ] = useState( 1 );
	const load = useCallback(
		( signal: AbortSignal ) => getResults( examId, page, signal ),
		[ examId, page ]
	);
	const results = useRequest( load );
	const operation = useOperation();
	const [ view, setView ] = useState< ResultsView | null >( null );
	const [ mode, setMode ] = useState< ResultsMode >( 'manual' );
	const [ releaseAt, setReleaseAt ] = useState( '' );
	const [ selected, setSelected ] = useState< number[] >( [] );
	const [ notify, setNotify ] = useState( true );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ showLog, setShowLog ] = useState( false );
	const [ invalid, setInvalid ] = useState< string | null >( null );

	useEffect( () => {
		if ( results.data ) {
			setView( results.data.view );
			setMode( results.data.view.policy.mode );
			setReleaseAt( toLocalInput( results.data.view.policy.release_at ) );
			setSelected( [] );
		}
	}, [ results.data ] );

	if ( ! view ) {
		return (
			<section aria-labelledby="oml-exam-results">
				<h3 id="oml-exam-results">{ __( 'Results', 'ohmylms' ) }</h3>
				{ results.error ? (
					<Notice status="error" isDismissible={ false }>
						{ results.error }{ ' ' }
						<Button variant="link" onClick={ results.retry }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					</Notice>
				) : (
					<p role="status">{ __( 'Loading results…', 'ohmylms' ) }</p>
				) }
			</section>
		);
	}

	const current = view;
	const policyDirty =
		mode !== current.policy.mode ||
		( 'scheduled' === mode &&
			releaseAt !== toLocalInput( current.policy.release_at ) );
	const choosable = current.items.filter(
		( item ) => item.can_release || item.can_revoke
	);
	const chosen = ( flag: 'can_release' | 'can_revoke' ) =>
		current.items
			.filter(
				( item ) => item[ flag ] && selected.includes( item.attempt_id )
			)
			.map( ( item ) => item.attempt_id );

	function refresh( done: string ) {
		setMessage( done );
		results.retry();
	}

	function savePolicy() {
		const date = fromLocalInput( releaseAt );
		if ( 'scheduled' === mode && ! date ) {
			setMessage( null );
			setInvalid(
				__( 'Enter the release date and time (UTC).', 'ohmylms' )
			);
			return;
		}
		setInvalid( null );
		void operation.run(
			( signal ) => saveResultsPolicy( examId, mode, date, signal ),
			() => refresh( __( 'Results policy saved.', 'ohmylms' ) )
		);
	}

	function release( ids: number[] | 'all' ) {
		void operation.run(
			( signal ) => releaseResults( examId, ids, notify, signal ),
			( outcome ) => {
				const parts: string[] = [
					sprintf(
						/* translators: %d: number of attempts. */
						__( 'Released %d attempts.', 'ohmylms' ),
						outcome.released.length
					),
				];
				if ( outcome.notified ) {
					parts.push(
						sprintf(
							/* translators: %d: number of emails. */
							__( 'Emailed %d learners.', 'ohmylms' ),
							outcome.notified
						)
					);
				}
				if ( outcome.skipped.length ) {
					parts.push(
						sprintf(
							/* translators: %s: skipped attempts with reasons. */
							__( 'Not released: %s.', 'ohmylms' ),
							skippedText( outcome.skipped )
						)
					);
				}
				if ( outcome.remaining ) {
					parts.push(
						sprintf(
							/* translators: %d: attempts still pending. */
							__(
								'%d attempts are still pending; release again to continue.',
								'ohmylms'
							),
							outcome.remaining
						)
					);
				}
				refresh( parts.join( ' ' ) );
			}
		);
	}

	function revoke( ids: number[] ) {
		void operation.run(
			( signal ) => revokeResults( examId, ids, signal ),
			( outcome ) =>
				refresh(
					[
						sprintf(
							/* translators: %d: number of attempts. */
							__( 'Undid %d releases.', 'ohmylms' ),
							outcome.revoked.length
						),
						outcome.skipped.length
							? sprintf(
									/* translators: %s: skipped attempts with reasons. */
									__( 'Not undone: %s.', 'ohmylms' ),
									skippedText( outcome.skipped )
								)
							: '',
					]
						.filter( Boolean )
						.join( ' ' )
				)
		);
	}

	const counts = current.counts;
	const toRelease = chosen( 'can_release' );
	const toRevoke = chosen( 'can_revoke' );
	return (
		<section aria-labelledby="oml-exam-results">
			<h3 id="oml-exam-results">{ __( 'Results', 'ohmylms' ) }</h3>
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
			<fieldset disabled={ operation.busy || ! canRelease }>
				<legend>
					{ __( 'When learners see results', 'ohmylms' ) }
				</legend>
				<SelectControl
					label={ __( 'Show results', 'ohmylms' ) }
					value={ mode }
					options={ RESULTS_MODES.map( ( value ) => ( {
						value,
						label: resultsModeLabel( value ),
					} ) ) }
					onChange={ ( next ) => {
						const found = RESULTS_MODES.find(
							( value ) => value === next
						);
						if ( found ) {
							setMode( found );
						}
					} }
				/>
				{ 'scheduled' === mode && (
					<TextControl
						type="datetime-local"
						label={ __( 'Release on (UTC)', 'ohmylms' ) }
						value={ releaseAt }
						aria-invalid={ invalid ? true : undefined }
						onChange={ setReleaseAt }
					/>
				) }
				{ invalid && <p role="alert">{ invalid }</p> }
				<p>
					{ __(
						'Results a learner has already seen stay visible whatever you choose. Written answers are released once they are marked.',
						'ohmylms'
					) }
				</p>
				<Button
					variant="secondary"
					disabled={ operation.busy || ! policyDirty }
					onClick={ savePolicy }
				>
					{ __( 'Save results policy', 'ohmylms' ) }
				</Button>
			</fieldset>
			{ ! canRelease && (
				<p>
					{ __(
						'Only people who can publish may change the policy or release results.',
						'ohmylms'
					) }
				</p>
			) }

			<p role="status">
				{ sprintf(
					/* translators: 1: pending; 2: awaiting marking; 3: released; 4: seen; 5: still writing. */
					__(
						'%1$d pending release · %2$d awaiting marking · %3$d released · %4$d seen · %5$d still writing',
						'ohmylms'
					),
					counts.pending,
					counts.awaiting_marking,
					counts.released + counts.viewed,
					counts.viewed,
					counts.in_progress
				) }
			</p>
			{ current.items.length ? (
				<table className="widefat striped">
					<thead>
						<tr>
							<td>
								<CheckboxControl
									__nextHasNoMarginBottom
									label={ __( 'Select all', 'ohmylms' ) }
									checked={
										choosable.length > 0 &&
										choosable.every( ( item ) =>
											selected.includes( item.attempt_id )
										)
									}
									disabled={
										! canRelease || ! choosable.length
									}
									onChange={ ( checked ) =>
										setSelected(
											checked
												? choosable.map(
														( item ) =>
															item.attempt_id
													)
												: []
										)
									}
								/>
							</td>
							<th scope="col">{ __( 'Learner', 'ohmylms' ) }</th>
							<th scope="col">
								{ __( 'Submitted', 'ohmylms' ) }
							</th>
							<th scope="col">{ __( 'Score', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Results', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Actions', 'ohmylms' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ current.items.map( ( item ) => (
							<tr key={ item.attempt_id }>
								<td>
									{ ( item.can_release ||
										item.can_revoke ) && (
										<CheckboxControl
											__nextHasNoMarginBottom
											label={ sprintf(
												/* translators: %s: learner name. */
												__( 'Select %s', 'ohmylms' ),
												item.learner.name
											) }
											checked={ selected.includes(
												item.attempt_id
											) }
											disabled={ ! canRelease }
											onChange={ ( checked ) =>
												setSelected( ( list ) =>
													checked
														? [
																...list,
																item.attempt_id,
															]
														: list.filter(
																( id ) =>
																	id !==
																	item.attempt_id
															)
												)
											}
										/>
									) }
								</td>
								<td>{ item.learner.name }</td>
								<td>{ formatDate( item.submitted_at ) }</td>
								<td>
									{ null === item.score
										? '—'
										: `${ item.score } / ${ item.max }` }
								</td>
								<td>
									{ releaseStateLabel( item.state ) }
									{ item.released_at &&
										` · ${ formatDate( item.released_at ) }` }
									{ item.notified_at &&
										` · ${ __( 'emailed', 'ohmylms' ) }` }
								</td>
								<td>
									{ item.can_release && (
										<Button
											variant="secondary"
											size="small"
											disabled={
												operation.busy || ! canRelease
											}
											onClick={ () =>
												release( [ item.attempt_id ] )
											}
										>
											{ __( 'Release', 'ohmylms' ) }
										</Button>
									) }
									{ item.can_revoke && (
										<Button
											variant="tertiary"
											size="small"
											isDestructive
											disabled={
												operation.busy || ! canRelease
											}
											onClick={ () =>
												revoke( [ item.attempt_id ] )
											}
										>
											{ __( 'Undo release', 'ohmylms' ) }
										</Button>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) : (
				<p>{ __( 'No one has taken this exam yet.', 'ohmylms' ) }</p>
			) }
			{ results.data && results.data.totalPages > 1 && (
				<p>
					<Button
						variant="tertiary"
						disabled={ page <= 1 || results.loading }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'ohmylms' ) }
					</Button>
					<Button
						variant="tertiary"
						disabled={
							page >= results.data.totalPages || results.loading
						}
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'ohmylms' ) }
					</Button>
				</p>
			) }
			{ canRelease && (
				<p>
					<CheckboxControl
						label={ __(
							'Email learners when their results are released',
							'ohmylms'
						) }
						checked={ notify }
						onChange={ setNotify }
					/>
					<Button
						variant="secondary"
						disabled={ operation.busy || ! toRelease.length }
						onClick={ () => release( toRelease ) }
					>
						{ sprintf(
							/* translators: %d: number of attempts. */
							__( 'Release selected (%d)', 'ohmylms' ),
							toRelease.length
						) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						isDestructive
						disabled={ operation.busy || ! toRevoke.length }
						onClick={ () => revoke( toRevoke ) }
					>
						{ sprintf(
							/* translators: %d: number of attempts. */
							__( 'Undo selected (%d)', 'ohmylms' ),
							toRevoke.length
						) }
					</Button>{ ' ' }
					<Button
						variant="primary"
						disabled={ operation.busy || ! counts.pending }
						isBusy={ operation.busy }
						onClick={ () => release( 'all' ) }
					>
						{ sprintf(
							/* translators: %d: number of attempts. */
							__( 'Release all pending (%d)', 'ohmylms' ),
							counts.pending
						) }
					</Button>
				</p>
			) }
			<p>
				<Button
					variant="link"
					aria-expanded={ showLog }
					onClick={ () => setShowLog( ! showLog ) }
				>
					{ showLog
						? __( 'Hide the release log', 'ohmylms' )
						: __( 'Show the release log', 'ohmylms' ) }
				</Button>
			</p>
			{ showLog && <ReleaseLog examId={ examId } /> }
		</section>
	);
}
