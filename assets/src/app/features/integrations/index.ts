import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const integrations: FeatureDefinition = {
	id: 'integrations',
	title: __( 'Integrations', 'ohmylms' ),
	path: '/integrations',
	group: 'settings',
	tab: 'integrations',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-integrations" */ './IntegrationsPage'
			)
	),
};
