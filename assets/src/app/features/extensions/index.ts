import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Pages registered with `registerAdminPage`, at `/extensions/<id>`. The navigation lists them. */
export const extensionPages: FeatureDefinition = {
	id: 'extension-pages',
	title: __( 'Extensions', 'ohmylms' ),
	path: '/extensions/:id',
	group: 'settings',
	tab: 'ext-:id',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-extension-page" */ './ExtensionPage'
			)
	),
};
