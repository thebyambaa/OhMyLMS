import { Button, Notice, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ConfirmDelete } from './ConfirmDelete';
import { ToggleField } from './fields';
import { LevelEditor } from './LevelEditor';
import { SectionFrame } from './SectionFrame';
import type { ResourceState, SectionState } from './hooks';
import type { Level, LevelDraft, Limits } from './types';

type Dialog =
	{ kind: 'edit'; level?: Level } | { kind: 'delete'; level: Level } | null;

/**
 * Levels tab: switch levels on and manage the ladder. Levels are listed from the lowest
 * threshold up; a learner reaches a level when their points reach its threshold.
 *
 * @param props          Component props.
 * @param props.section  Levels on/off section state.
 * @param props.resource Levels list state.
 * @param props.limits   Server limits.
 */
export function LevelsTab( {
	section,
	resource,
	limits,
}: {
	section: SectionState< 'levels' >;
	resource: ResourceState< Level, LevelDraft >;
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
				title={ __( 'Learner levels', 'ohmylms' ) }
				description={ __(
					'Levels recognise progress. A learner reaches a level when their bonus points reach its threshold.',
					'ohmylms'
				) }
				state={ section }
			>
				{ section.draft && (
					<ToggleField
						label={ __( 'Use learner levels', 'ohmylms' ) }
						checked={ section.draft.enabled }
						onChange={ ( enabled ) =>
							section.update( () => ( { enabled } ) )
						}
					/>
				) }
			</SectionFrame>
			<h3>{ __( 'Levels', 'ohmylms' ) }</h3>
			{ resource.loading && (
				<p role="status">
					<Spinner /> { __( 'Loading levels…', 'ohmylms' ) }
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
								resource.items.length >= limits.max_levels
							}
							accessibleWhenDisabled
							onClick={ () => setDialog( { kind: 'edit' } ) }
						>
							{ __( 'Add level', 'ohmylms' ) }
						</Button>
					</p>
					{ 0 === resource.items.length ? (
						<p>
							{ __(
								'No levels yet. Add the first level, for example “Beginner” at 0 points.',
								'ohmylms'
							) }
						</p>
					) : (
						<table className="widefat striped">
							<caption className="screen-reader-text">
								{ __( 'Levels', 'ohmylms' ) }
							</caption>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Level', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Points needed', 'ohmylms' ) }
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
								{ resource.items.map( ( level ) => (
									<tr key={ level.slug }>
										<th scope="row">
											<span
												style={ {
													background: level.color,
													color: level.text_color,
													padding: '2px 8px',
													borderRadius: 4,
												} }
											>
												{ level.name }
											</span>
										</th>
										<td>{ level.points }</td>
										<td>{ level.earned_count }</td>
										<td>
											<Button
												variant="link"
												onClick={ () =>
													setDialog( {
														kind: 'edit',
														level,
													} )
												}
												aria-label={ sprintf(
													/* translators: %s: level name. */
													__( 'Edit %s', 'ohmylms' ),
													level.name
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
														level,
													} )
												}
												aria-label={ sprintf(
													/* translators: %s: level name. */
													__(
														'Delete %s',
														'ohmylms'
													),
													level.name
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
				<LevelEditor
					level={ dialog.level }
					levels={ resource.items }
					limits={ limits }
					working={ resource.working }
					error={ resource.actionError }
					onCancel={ close }
					onSave={ async ( draft ) => {
						const saved = await resource.save(
							dialog.level?.slug ?? null,
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
					title={ __( 'Delete level', 'ohmylms' ) }
					message={ sprintf(
						/* translators: 1: level name; 2: learner count text. */
						__(
							'Delete the level “%1$s”? %2$s Learners keep their points.',
							'ohmylms'
						),
						dialog.level.name,
						sprintf(
							/* translators: %d: number of learners. */
							_n(
								'%d learner has reached it.',
								'%d learners have reached it.',
								dialog.level.earned_count,
								'ohmylms'
							),
							dialog.level.earned_count
						)
					) }
					working={ resource.working }
					error={ resource.actionError }
					onCancel={ close }
					onConfirm={ () => {
						void resource
							.remove( dialog.level.slug )
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
