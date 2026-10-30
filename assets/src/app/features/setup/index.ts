import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/**
 * First-run setup at `#/setup`; the step is the `step` query parameter (`#/setup?step=pages`), so the
 * registry needs a single entry. It stays in the navigation: after completion it shows a done state
 * with a way to run the setup again.
 */
export const setup: FeatureDefinition = {
	id: 'setup',
	title: __( 'Setup', 'ohmylms' ),
	path: '/setup',
	capability: 'manage_options',
	component: lazy(
		() => import( /* webpackChunkName: "admin-app-setup" */ './SetupPage' )
	),
};
