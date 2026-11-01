import { Button, Modal, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { WidefatTable } from '../../components/WidefatTable';
import { useRequest } from '../../hooks/useRequest';
import {
	formatDateTime,
	getQuestionVersion,
	listVersions,
	statementText,
	type QuestionVersion,
	type QuestionVersionContent,
} from '../questions/public';
import { deliveryLabel, formatMarks, slotName } from './model';
import type { PinChoice, QuizQuestionSlot } from './types';

function stateLabel(
	version: QuestionVersion,
	slot: QuizQuestionSlot
): string {
	const labels: string[] = [];
	if ( version.id === slot.pinned_version_id ) {
		labels.push( __( 'Pinned in this quiz', 'ohmylms' ) );
	} else if ( version.id === slot.delivery.version_id ) {
		labels.push( __( 'Learners get it now', 'ohmylms' ) );
	}
	if ( version.is_current ) {
		labels.push( __( 'Latest', 'ohmylms' ) );
	}
	if ( version.is_approved ) {
		labels.push( __( 'Approved', 'ohmylms' ) );
	}
	return labels.join( ', ' ) || '—';
}

function points( content: QuestionVersionContent ): number {
	const score = content.settings.score;
	const value =
		score && 'object' === typeof score
			? Number( ( score as Record< string, unknown > ).value )
			: 0;
	return Number.isFinite( value ) ? value : 0;
}

function VersionContent( {
	questionId,
	versionId,
}: {
	questionId: number;
	versionId: number;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) =>
			getQuestionVersion( questionId, versionId, signal ),
		[ questionId, versionId ]
	);
	const content = useRequest( load );
	if ( content.loading ) {
		return <p role="status">{ __( 'Loading the version…', 'ohmylms' ) }</p>;
	}
	if ( ! content.data ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ content.error }{ ' ' }
				<Button variant="link" onClick={ content.retry }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	const version = content.data;
	const statement = statementText( version.description || version.name );
	return (
		<section
			aria-label={ sprintf(
				/* translators: %d: version number. */
				__( 'Content of version %d', 'ohmylms' ),
				version.version_no
			) }
		>
			<h4>
				{ sprintf(
					/* translators: %d: version number. */
					__( 'Version %d', 'ohmylms' ),
					version.version_no
				) }
			</h4>
			<p>{ statement || version.name }</p>
			<p>
				{ sprintf(
					/* translators: %s: points. */
					__( 'Points: %s', 'ohmylms' ),
					formatMarks( points( version ) )
				) }
			</p>
			{ version.options.length > 0 && (
				<ul aria-label={ __( 'Answers', 'ohmylms' ) }>
					{ version.options.map( ( option, index ) => (
						<li key={ index }>
							{ statementText( option.answer ) || '—' }
							{ option.is_correct &&
								` ${ __( '(correct)', 'ohmylms' ) }` }
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}

/**
 * The saved versions of a question in a quiz, to read any of them and pin the quiz to it, or to unpin
 * so the quiz follows the latest usable version. Only for questions the user may edit (the version
 * list and content are editor-only). The pin is saved with the revision of the loaded question list.
 *
 * @param props         Component props.
 * @param props.slot    The question in the quiz.
 * @param props.busy    A change is being saved.
 * @param props.error   Message of a failed change.
 * @param props.onPin   Pins a version, or unpins with `null`.
 * @param props.onClose Closes the dialog.
 */
export function VersionPinDialog( {
	slot,
	busy,
	error,
	onPin,
	onClose,
}: {
	slot: QuizQuestionSlot;
	busy: boolean;
	error: string | null;
	onPin: ( choice: PinChoice ) => void;
	onClose: () => void;
} ) {
	const [ page, setPage ] = useState( 1 );
	const [ viewing, setViewing ] = useState< number | null >( null );
	const load = useCallback(
		( signal: AbortSignal ) =>
			listVersions( slot.question_id, page, signal ),
		[ slot.question_id, page ]
	);
	const versions = useRequest( load, { keepData: true } );
	const name = slotName( slot );
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: question name. */
				__( 'Versions of %s', 'ohmylms' ),
				name
			) }
			onRequestClose={ () => ! busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			<p>
				{ sprintf(
					/* translators: %s: which version learners get, such as "Latest version (3)". */
					__( 'Learners get: %s.', 'ohmylms' ),
					deliveryLabel( slot )
				) }{ ' ' }
				{ __(
					'Pin a version to keep this quiz on it when the question is edited later. Published quiz revisions and past attempts keep the versions they were given.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ versions.loading && (
				<p role="status">{ __( 'Loading versions…', 'ohmylms' ) }</p>
			) }
			{ versions.error && (
				<Notice status="error" isDismissible={ false }>
					{ versions.error }{ ' ' }
					<Button variant="link" onClick={ versions.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ versions.data && (
				<WidefatTable caption={ __( 'Versions', 'ohmylms' ) }>
					<thead>
						<tr>
							<th scope="col">{ __( 'Version', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Created', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'By', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'State', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Actions', 'ohmylms' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ versions.data.items.map( ( version ) => {
							const label = sprintf(
								/* translators: %d: version number. */
								__( 'version %d', 'ohmylms' ),
								version.version_no
							);
							const pinned =
								version.id === slot.pinned_version_id;
							return (
								<tr key={ version.id }>
									<th scope="row">
										{ `v${ version.version_no }` }
									</th>
									<td>
										{ formatDateTime( version.created_at ) }
									</td>
									<td>{ version.created_by.name || '—' }</td>
									<td>{ stateLabel( version, slot ) }</td>
									<td>
										<Button
											variant="link"
											aria-expanded={
												viewing === version.id
											}
											aria-label={ sprintf(
												/* translators: %s: version, such as "version 2". */
												__( 'View %s', 'ohmylms' ),
												label
											) }
											onClick={ () =>
												setViewing(
													viewing === version.id
														? null
														: version.id
												)
											}
										>
											{ viewing === version.id
												? __( 'Hide', 'ohmylms' )
												: __( 'View', 'ohmylms' ) }
										</Button>{ ' ' }
										<Button
											variant="secondary"
											size="small"
											disabled={ busy || pinned }
											aria-label={ sprintf(
												/* translators: %s: version, such as "version 2". */
												__( 'Pin %s', 'ohmylms' ),
												label
											) }
											onClick={ () =>
												onPin( version.id )
											}
										>
											{ pinned
												? __( 'Pinned', 'ohmylms' )
												: __( 'Pin', 'ohmylms' ) }
										</Button>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</WidefatTable>
			) }
			{ versions.data && versions.data.totalPages > 1 && (
				<p>
					<Button
						variant="secondary"
						size="small"
						disabled={ page <= 1 || versions.loading }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Newer versions', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						size="small"
						disabled={
							page >= versions.data.totalPages || versions.loading
						}
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Older versions', 'ohmylms' ) }
					</Button>
				</p>
			) }
			{ null !== viewing && (
				<VersionContent
					questionId={ slot.question_id }
					versionId={ viewing }
				/>
			) }
			<p>
				{ slot.pinned_version_id > 0 && (
					<>
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () => onPin( null ) }
						>
							{ __( 'Unpin', 'ohmylms' ) }
						</Button>{ ' ' }
					</>
				) }
				<Button
					variant="tertiary"
					disabled={ busy }
					onClick={ onClose }
				>
					{ __( 'Close', 'ohmylms' ) }
				</Button>
			</p>
		</Modal>
	);
}
