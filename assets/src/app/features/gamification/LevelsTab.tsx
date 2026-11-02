import { Button, Notice, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ListTable } from '../../../ui/ListTable';
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
					<ListTable
						caption={ __( 'Levels', 'ohmylms' ) }
						rows={ resource.items }
						rowKey={ ( level ) => level.slug }
						fixed={ false }
						emptyMessage={ __(
							'No levels yet. Add the first level, for example “Beginner” at 0 points.',
							'ohmylms'
						) }
						columns={ [
							{
								id: 'level',
								label: __( 'Level', 'ohmylms' ),
								primary: true,
								render: ( level ) => (
									<strong>
										<span
											className="ohmylms-level-chip"
											style={ {
												background: level.color,
												color: level.text_color,
											} }
										>
											{ level.name }
										</span>
									</strong>
								),
							},
							{
								id: 'points',
								label: __( 'Points needed', 'ohmylms' ),
								className: 'num',
								render: ( level ) => level.points,
							},
							{
								id: 'learners',
								label: __( 'Learners', 'ohmylms' ),
								className: 'num',
								render: ( level ) => level.earned_count,
							},
						] }
						rowActions={ ( level ) => [
							{
								key: 'edit',
								label: __( 'Edit', 'ohmylms' ),
								ariaLabel: sprintf(
									/* translators: %s: level name. */
									__( 'Edit %s', 'ohmylms' ),
									level.name
								),
								onClick: () =>
									setDialog( { kind: 'edit', level } ),
							},
							{
								key: 'delete',
								label: __( 'Delete', 'ohmylms' ),
								destructive: true,
								ariaLabel: sprintf(
									/* translators: %s: level name. */
									__( 'Delete %s', 'ohmylms' ),
									level.name
								),
								onClick: () =>
									setDialog( { kind: 'delete', level } ),
							},
						] }
					/>
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
