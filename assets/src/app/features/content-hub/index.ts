import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export { listSkills } from './api';

/**
 * Content Hub overview at `#/content-hub`: every content area with its counts and recent changes.
 * Replaces the legacy hub frame, so extensions see its route.
 */
export const contentHubOverview: FeatureDefinition = {
	id: 'content-hub-overview',
	title: __( 'Content Hub', 'ohmylms' ),
	path: '/content-hub',
	group: 'content',
	tab: 'courses',
	section: 'overview',
	extensionRoute: '/content-hub',
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-content-hub-overview" */ './OverviewPage'
			)
	),
};

export const contentHub: FeatureDefinition = {
	id: 'content-hub',
	title: __( 'Skills', 'ohmylms' ),
	path: '/content-hub/skills',
	group: 'content',
	tab: 'skills',
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-content-hub" */ './SkillsPage'
			)
	),
};
