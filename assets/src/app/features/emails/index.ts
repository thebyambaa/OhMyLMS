import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const emails: FeatureDefinition = {
	id: 'emails',
	title: __( 'Emails', 'ohmylms' ),
	path: '/emails',
	capability: 'manage_options',
	component: lazy(
		() =>
			import( /* webpackChunkName: "admin-app-emails" */ './EmailsPage' )
	),
};
