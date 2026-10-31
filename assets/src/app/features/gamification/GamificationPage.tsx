import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Link, useSearchParams } from 'react-router-dom';
import {
	createBadge,
	createLevel,
	deleteBadge,
	deleteLevel,
	listBadges,
	listLevels,
	updateBadge,
	updateLevel,
} from './api';
import { BadgesTab } from './BadgesTab';
import { useOptions, useResource, useSection, type ResourceApi } from './hooks';
import { LeaderboardTab } from './LeaderboardTab';
import { LevelsTab } from './LevelsTab';
import { DEFAULT_LIMITS, tabFromParam } from './model';
import { PointsTab } from './PointsTab';
import { RewardsTab } from './RewardsTab';
import { StreaksTab } from './StreaksTab';
import { useServerNavBadges } from '../../layout/serverNavBadges';
import { useAdminPlace } from '../../routing/place';
import { Tabs, useTabPanelProps, type TabItem } from './Tabs';
import {
	TAB_IDS,
	type Badge,
	type BadgeDraft,
	type Level,
	type LevelDraft,
	type TabId,
} from './types';
import { useEngagement } from './useEngagement';
import { useUnsavedChangesGuard } from './useUnsavedChangesGuard';
import { XpTab } from './XpTab';

const LABELS: Record< TabId, string > = {
	points: __( 'Points', 'ohmylms' ),
	levels: __( 'Levels', 'ohmylms' ),
	badges: __( 'Badges', 'ohmylms' ),
	streaks: __( 'Streaks', 'ohmylms' ),
	xp: __( 'XP and mastery', 'ohmylms' ),
	leaderboard: __( 'Leaderboard', 'ohmylms' ),
	rewards: __( 'Rewards', 'ohmylms' ),
};

const LEVELS_API: ResourceApi< Level, LevelDraft > = {
	list: listLevels,
	create: createLevel,
	update: updateLevel,
	remove: deleteLevel,
};

const BADGES_API: ResourceApi< Badge, BadgeDraft > = {
	list: listBadges,
	create: createBadge,
	update: updateBadge,
	remove: deleteBadge,
};

/**
 * Gamification screen at `#/gamification`. The selected tab is the `tab` query parameter
 * (`#/gamification?tab=badges`), so a tab can be linked and survives a reload. Each tab loads the
 * first time it is opened and keeps its own draft while other tabs are shown.
 */
export default function GamificationPage() {
	const [ params, setParams ] = useSearchParams();
	const place = useAdminPlace();
	const selected = tabFromParam( params.get( 'tab' ) );
	const { options } = useOptions();
	const limits = options?.limits ?? DEFAULT_LIMITS;

	const points = useSection(
		'points',
		'points' === selected || 'rewards' === selected,
		limits
	);
	const levels = useSection( 'levels', 'levels' === selected, limits );
	const badges = useSection( 'badges', 'badges' === selected, limits );
	const board = useSection(
		'leaderboard',
		'leaderboard' === selected,
		limits
	);
	const rewards = useSection( 'rewards', 'rewards' === selected, limits );
	const streak = useEngagement( 'streak', 'streaks' === selected );
	const xp = useEngagement( 'xp', 'xp' === selected );
	const score = useEngagement( 'score', 'xp' === selected );
	const mastery = useEngagement( 'mastery', 'xp' === selected );
	const levelList = useResource( LEVELS_API, 'levels' === selected );
	const badgeList = useResource( BADGES_API, 'badges' === selected );

	const dirty: Record< string, boolean > = {
		points: points.dirty,
		levels: levels.dirty,
		badges: badges.dirty,
		streaks: streak.dirty,
		xp: xp.dirty || score.dirty || mastery.dirty,
		leaderboard: board.dirty,
		rewards: rewards.dirty,
	};
	const guard = useUnsavedChangesGuard(
		Object.values( dirty ).some( Boolean )
	);

	const tabs: TabItem[] = TAB_IDS.map( ( id ) => ( {
		id,
		label: LABELS[ id ],
		badge: dirty[ id ] ? __( '(unsaved)', 'ohmylms' ) : undefined,
	} ) );
	useServerNavBadges(
		'section',
		Object.fromEntries( tabs.map( ( tab ) => [ tab.id, tab.badge ] ) )
	);
	const panelProps = useTabPanelProps();
	const visited: Record< string, boolean > = {
		points: points.loaded || points.loading,
		levels: levels.loaded || levels.loading,
		badges: badges.loaded || badges.loading,
		streaks: streak.loaded || streak.loading,
		xp: xp.loaded || xp.loading,
		leaderboard: board.loaded || board.loading,
		rewards: rewards.loaded || rewards.loading,
	};
	const panels: Record< string, JSX.Element > = {
		points: <PointsTab state={ points } limits={ limits } />,
		levels: (
			<LevelsTab
				section={ levels }
				resource={ levelList }
				limits={ limits }
			/>
		),
		badges: (
			<BadgesTab
				section={ badges }
				resource={ badgeList }
				options={ options }
				limits={ limits }
			/>
		),
		streaks: <StreaksTab state={ streak } />,
		xp: <XpTab xp={ xp } score={ score } mastery={ mastery } />,
		leaderboard: (
			<LeaderboardTab leaderboard={ board } options={ options } />
		),
		rewards: <RewardsTab rewards={ rewards } points={ points } />,
	};

	return (
		<>
			{ options && ! options.integration_enabled && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Gamification is switched off in Add-ons, so nothing below takes effect yet.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ guard.pendingPath && (
				<Notice status="warning" isDismissible={ false }>
					<p>
						{ __(
							'You have unsaved changes. Leave this page and discard them?',
							'ohmylms'
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						onClick={ guard.leave }
					>
						{ __( 'Discard and leave', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button variant="secondary" onClick={ guard.stay }>
						{ __( 'Stay on this page', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			<Tabs
				label={ __( 'Gamification sections', 'ohmylms' ) }
				tabs={ tabs }
				selected={ selected }
				onSelect={ ( id ) =>
					setParams( { tab: id }, { replace: true } )
				}
			/>
			{ TAB_IDS.map( ( id ) => (
				<div key={ id } { ...panelProps( id, selected ) }>
					{ ( id === selected || visited[ id ] ) && panels[ id ] }
				</div>
			) ) }
			{ ! place && (
				<p>
					{ __(
						'Certificates are managed on their own screen:',
						'ohmylms'
					) }{ ' ' }
					<Link to="/certificates">
						{ __( 'Open certificates', 'ohmylms' ) }
					</Link>
				</p>
			) }
		</>
	);
}
