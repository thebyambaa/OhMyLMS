import { Button, Notice, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
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
					{ 0 === resource.items.length ? (
						<p>
							{ __(
								'No badges yet. Add a badge and choose when learners earn it.',
								'ohmylms'
							) }
						</p>
					) : (
						<table className="widefat striped">
							<caption className="screen-reader-text">
								{ __( 'Badges', 'ohmylms' ) }
							</caption>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Badge', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Awarded when', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Learners', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Actions', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ resource.items.map( ( badge ) => (
									<tr key={ badge.slug }>
										<th scope="row">
											{ badge.image_url && (
												<img
													src={ badge.image_url }
													alt=""
													width={ 32 }
													height={ 32 }
												/>
											) }{ ' ' }
											<span
												style={ { color: badge.color } }
											>
												{ badge.name }
											</span>
										</th>
										<td>
											{ 'streak' === badge.award_source
												? __(
														'By a learning streak milestone',
														'ohmylms'
													)
												: badge.criteria
														.map( ( criterion ) =>
															describeCriterion(
																criterion,
																options?.metrics ??
																	[]
															)
														)
														.join( '; ' ) }
										</td>
										<td>{ badge.earned_count }</td>
										<td>
											<Button
												variant="link"
												onClick={ () =>
													setDialog( {
														kind: 'edit',
														badge,
													} )
												}
												aria-label={ sprintf(
													/* translators: %s: badge name. */
													__( 'Edit %s', 'ohmylms' ),
													badge.name
												) }
											>
												{ __( 'Edit', 'ohmylms' ) }
											</Button>{ ' ' }
											<Button
												variant="link"
												isDestructive
												onClick={ () =>
													setDialog( {
														kind: 'delete',
														badge,
													} )
												}
												aria-label={ sprintf(
													/* translators: %s: badge name. */
													__(
														'Delete %s',
														'ohmylms'
													),
													badge.name
												) }
											>
												{ __( 'Delete', 'ohmylms' ) }
											</Button>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
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
