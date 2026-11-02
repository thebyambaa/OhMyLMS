import { Button, Modal, Notice, RadioControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { normalizeApiError } from '../../lib/api';
import {
	deleteItem,
	readDeletion,
	type ChildStrategy,
	type CurriculumItem,
} from './api';

/**
 * Confirm deleting an item or its branch. Linked courses, skills, banks and exams are never deleted;
 * the server refuses the deletion if anything it would affect changed after this preview.
 *
 * @param props           Dialog configuration.
 * @param props.item      Item to delete.
 * @param props.onClose   Close without deleting.
 * @param props.onDeleted Called after the item is deleted.
 */
export function DeleteItemDialog( {
	item,
	onClose,
	onDeleted,
}: {
	item: CurriculumItem;
	onClose: () => void;
	onDeleted: () => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) => readDeletion( item.id, signal ),
		[ item.id ]
	);
	const preview = useRequest( load );
	const operation = useOperation();
	const [ strategy, setStrategy ] = useState< ChildStrategy >( 'promote' );
	const [ stale, setStale ] = useState( false );
	const info = preview.data?.dependents;
	const branch = !! info?.children && strategy === 'delete';
	const links = ( branch ? info?.links : info?.own_links ) ?? 0;
	const bundles = ( branch ? info?.bundles : info?.own_bundles ) ?? 0;
	const groups = ( branch ? info?.groups : info?.own_groups ) ?? 0;
	function confirm() {
		if ( ! preview.data || ! info ) {
			return;
		}
		const revision = preview.data.revision;
		void operation.run(
			async ( signal ) => {
				try {
					return await deleteItem(
						item.id,
						info.children ? strategy : '',
						revision,
						signal
					);
				} catch ( failure ) {
					if (
						normalizeApiError( failure ).code ===
						'ohmylms_curriculum_stale'
					) {
						setStale( true );
					}
					throw failure;
				}
			},
			() => onDeleted()
		);
	}
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: curriculum item name. */
				__( 'Delete “%s”?', 'ohmylms' ),
				item.name
			) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			{ preview.loading && (
				<p role="status">
					{ __( 'Checking what this deletion affects…', 'ohmylms' ) }
				</p>
			) }
			{ preview.error && (
				<Notice status="error" isDismissible={ false }>
					{ preview.error }{ ' ' }
					<Button variant="secondary" onClick={ preview.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
					{ stale && (
						<>
							{ ' ' }
							<Button
								variant="secondary"
								onClick={ () => {
									setStale( false );
									operation.clearError();
									preview.retry();
								} }
							>
								{ __( 'Review again', 'ohmylms' ) }
							</Button>
						</>
					) }
				</Notice>
			) }
			{ info && (
				<>
					{ info.children > 0 && (
						<RadioControl
							label={ sprintf(
								/* translators: %d: number of child curriculum items. */
								_n(
									'This item has %d child.',
									'This item has %d children.',
									info.children,
									'ohmylms'
								),
								info.children
							) }
							selected={ strategy }
							options={ [
								{
									value: 'promote',
									label: __(
										'Move its children up one level and delete only this item',
										'ohmylms'
									),
								},
								{
									value: 'delete',
									label: sprintf(
										/* translators: %d: number of curriculum items. */
										_n(
											'Delete this item and everything under it (%d item in total)',
											'Delete this item and everything under it (%d items in total)',
											info.descendants + 1,
											'ohmylms'
										),
										info.descendants + 1
									),
								},
							] }
							onChange={ ( value ) =>
								setStrategy( value as ChildStrategy )
							}
						/>
					) }
					<ul>
						<li>
							{ links
								? sprintf(
										/* translators: %d: number of content links. */
										_n(
											'%d link to courses, skills, question banks or exams will be removed. The content itself is not deleted.',
											'%d links to courses, skills, question banks or exams will be removed. The content itself is not deleted.',
											links,
											'ohmylms'
										),
										links
									)
								: __(
										'No content is linked here.',
										'ohmylms'
									) }
						</li>
						{ groups > 0 && (
							<li>
								{ sprintf(
									/* translators: %d: number of skill groups. */
									_n(
										'%d skill group will be deleted. Its skills stay in the skill library.',
										'%d skill groups will be deleted. Their skills stay in the skill library.',
										groups,
										'ohmylms'
									),
									groups
								) }
							</li>
						) }
						{ bundles > 0 && (
							<li>
								{ sprintf(
									/* translators: %d: number of course bundles. */
									_n(
										'It will be removed from %d course bundle.',
										'It will be removed from %d course bundles.',
										bundles,
										'ohmylms'
									),
									bundles
								) }
							</li>
						) }
						{ item.is_syllabus && (
							<li>
								{ __(
									'The syllabus’s course is kept as it is; delete it separately if it is no longer needed.',
									'ohmylms'
								) }
							</li>
						) }
					</ul>
					<Button
						variant="primary"
						isDestructive
						isBusy={ operation.busy }
						disabled={ operation.busy || stale }
						onClick={ confirm }
					>
						{ branch
							? __( 'Delete branch', 'ohmylms' )
							: __( 'Delete item', 'ohmylms' ) }
					</Button>{ ' ' }
				</>
			) }
			<Button
				variant="secondary"
				disabled={ operation.busy }
				onClick={ onClose }
			>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
