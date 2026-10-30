import { Button, Notice, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ListTable } from '../../../ui/ListTable';
import { BadgeEditor } from './BadgeEditor';
import { ConfirmDelete } from './ConfirmDelete';
import { ToggleField } from './fields';
import { describeCriterion } from './model';
import { SectionFrame } from './SectionFrame';
import type { ResourceState, SectionState } from './hooks';
import type { Badge, BadgeDraft, GamificationOptions, Limits } from './types';

type Dialog =
	{ kind: 'edit'; badge?: Badge } | { kind: 'delete'; badge: Badge } | null;

/**
 * Badges tab: switch badges on and manage custom badges. Images come from the media library.
 *
 * @param props          Component props.
 * @param props.section  Badges on/off section state.
 * @param props.resource Badges list state.
 * @param props.options  Allow-lists from the server, if loaded.
 * @param props.limits   Server limits.
 */
export function BadgesTab( {
	section,
	resource,
	options,
	limits,
}: {
	section: SectionState< 'badges' >;
	resource: ResourceState< Badge, BadgeDraft >;
	options: GamificationOptions | null;
	limits: Limits;
} ) {
	const [ dialog, setDialog ] = useState< Dialog >( null );
	function close() {
		setDialog( null );
		resource.clearError();
	}
	return (
		<>
			<SectionFrame
				title={ __( 'Achievement badges', 'ohmylms' ) }
				description={ __(
					'Badges celebrate achievements. Each badge is awarded automatically when its conditions are met.',
					'ohmylms'
				) }
				state={ section }
			>
				{ section.draft && (
					<ToggleField
						label={ __( 'Use achievement badges', 'ohmylms' ) }
						checked={ section.draft.enabled }
						onChange={ ( enabled ) =>
							section.update( () => ( { enabled } ) )
						}
					/>
				) }
			</SectionFrame>
			<h3>{ __( 'Badges', 'ohmylms' ) }</h3>
			{ resource.loading && (
				<p role="status">
					<Spinner /> { __( 'Loading badges…', 'ohmylms' ) }
				</p>
			) }
			{ resource.loadError && (
				<Notice status="error" isDismissible={ false }>
					<p>{ resource.loadError.message }</p>
					{ ! resource.loadError.permission && (
						<Button variant="secondary" onClick={ resource.reload }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					) }
				</Notice>
			) }
			{ resource.loaded && ! resource.loadError && (
				<>
					<p>
						<Button
							variant="secondary"
							disabled={
								resource.items.length >= limits.max_badges
							}
							accessibleWhenDisabled
							onClick={ () => setDialog( { kind: 'edit' } ) }
						>
							{ __( 'Add badge', 'ohmylms' ) }
						</Button>
					</p>
					<ListTable
						caption={ __( 'Badges', 'ohmylms' ) }
						rows={ resource.items }
						rowKey={ ( badge ) => badge.slug }
						fixed={ false }
						emptyMessage={ __(
							'No badges yet. Add a badge and choose when learners earn it.',
							'ohmylms'
						) }
						columns={ [
							{
								id: 'badge',
								label: __( 'Badge', 'ohmylms' ),
								primary: true,
								render: ( badge ) => (
									<strong>
										{ badge.image_url && (
											<img
												src={ badge.image_url }
												alt=""
												width={ 32 }
												height={ 32 }
											/>
										) }{ ' ' }
										<span style={ { color: badge.color } }>
											{ badge.name }
										</span>
									</strong>
								),
							},
							{
								id: 'criteria',
								label: __( 'Awarded when', 'ohmylms' ),
								render: ( badge ) =>
									'streak' === badge.award_source
										? __(
												'By a learning streak milestone',
												'ohmylms'
											)
										: badge.criteria
												.map( ( criterion ) =>
													describeCriterion(
														criterion,
														options?.metrics ?? []
													)
												)
												.join( '; ' ),
							},
							{
								id: 'learners',
								label: __( 'Learners', 'ohmylms' ),
								className: 'num',
								render: ( badge ) => badge.earned_count,
							},
						] }
						rowActions={ ( badge ) => [
							{
								key: 'edit',
								label: __( 'Edit', 'ohmylms' ),
								ariaLabel: sprintf(
									/* translators: %s: badge name. */
									__( 'Edit %s', 'ohmylms' ),
									badge.name
								),
								onClick: () =>
									setDialog( { kind: 'edit', badge } ),
							},
							{
								key: 'delete',
								label: __( 'Delete', 'ohmylms' ),
								destructive: true,
								ariaLabel: sprintf(
									/* translators: %s: badge name. */
									__( 'Delete %s', 'ohmylms' ),
									badge.name
								),
								onClick: () =>
									setDialog( { kind: 'delete', badge } ),
							},
						] }
					/>
				</>
			) }
			{ dialog?.kind === 'edit' && (
				<BadgeEditor
					badge={ dialog.badge }
					options={ options }
					limits={ limits }
					working={ resource.working }
					error={ resource.actionError }
					onCancel={ close }
					onSave={ async ( draft ) => {
						const saved = await resource.save(
							dialog.badge?.slug ?? null,
							draft
						);
						if ( saved ) {
							close();
						}
						return saved;
					} }
				/>
			) }
			{ dialog?.kind === 'delete' && (
				<ConfirmDelete
					title={ __( 'Delete badge', 'ohmylms' ) }
					message={ sprintf(
						/* translators: 1: badge name; 2: learner count text. */
						__(
							'Delete the badge “%1$s”? %2$s It will no longer be shown to learners.',
							'ohmylms'
						),
						dialog.badge.name,
						sprintf(
							/* translators: %d: number of learners. */
							_n(
								'%d learner has earned it.',
								'%d learners have earned it.',
								dialog.badge.earned_count,
								'ohmylms'
							),
							dialog.badge.earned_count
						)
					) }
					working={ resource.working }
					error={ resource.actionError }
					onCancel={ close }
					onConfirm={ () => {
						void resource
							.remove( dialog.badge.slug )
							.then( ( ok ) => {
								if ( ok ) {
									close();
								}
							} );
					} }
				/>
			) }
		</>
	);
}
