import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const contentHub: FeatureDefinition = {
	id: 'content-hub',
	title: __( 'Content Hub', 'ohmylms' ),
	path: '/content-hub/skills',
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-content-hub" */ './SkillsPage'
			)
	),
};
