import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/**
 * Core settings at `#/settings`; the tab is the `tab` query parameter (for example
 * `#/settings?tab=tax`), so the registry needs a single entry.
 */
export const settings: FeatureDefinition = {
	id: 'settings',
	title: __( 'Settings', 'ohmylms' ),
	path: '/settings',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-settings" */ './SettingsPage'
			)
	),
};
