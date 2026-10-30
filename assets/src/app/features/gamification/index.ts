import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/**
 * Gamification at `#/gamification`; the tab is the `tab` query parameter (`points`, `levels`,
 * `badges`, `streaks`, `xp`, `leaderboard`), so the registry needs a single entry.
 */
export const gamification: FeatureDefinition = {
	id: 'gamification',
	title: __( 'Gamification', 'ohmylms' ),
	path: '/gamification',
	extensionRoute: '/gamification/:tab',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-gamification" */ './GamificationPage'
			)
	),
};
