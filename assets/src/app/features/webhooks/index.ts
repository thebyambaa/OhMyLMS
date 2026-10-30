import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const webhooks: FeatureDefinition = {
	id: 'webhooks',
	title: __( 'Webhooks', 'ohmylms' ),
	path: '/webhooks',
	group: 'settings',
	tab: 'webhooks',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-webhooks" */ './WebhooksPage'
			)
	),
};
