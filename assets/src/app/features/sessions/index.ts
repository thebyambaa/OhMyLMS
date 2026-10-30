import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Live session list at `#/sessions`. */
export const sessions: FeatureDefinition = {
	id: 'sessions',
	title: __( 'Sessions', 'ohmylms' ),
	path: '/sessions',
	extensionRoute: '/sessions',
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-sessions" */ './SessionsPage'
			)
	),
};

/** Session editor at `#/sessions/<id>` (`new` schedules one). Shares the chunk with the list. */
export const sessionEditor: FeatureDefinition = {
	id: 'session-editor',
	title: __( 'Session', 'ohmylms' ),
	path: '/sessions/:id',
	hidden: true,
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-sessions" */ './SessionEditorPage'
			)
	),
};
