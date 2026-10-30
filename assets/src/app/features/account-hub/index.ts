import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/**
 * Account Hub at `#/account-hub`; the tab is the `tab` query parameter (`schools`, `classes`,
 * `teachers`, `parents`). Extensions keep seeing the original `/accounthub` screen route.
 */
export const accountHub: FeatureDefinition = {
	id: 'account-hub',
	title: __( 'Account Hub', 'ohmylms' ),
	path: '/account-hub',
	extensionRoute: '/accounthub',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-account-hub" */ './AccountHubPage'
			)
	),
};

/** School editor at `#/account-hub/schools/<id>`, opened from the Schools tab. */
export const accountHubSchool: FeatureDefinition = {
	id: 'account-hub-school',
	title: __( 'School', 'ohmylms' ),
	path: '/account-hub/schools/:id',
	extensionRoute: '/accounthub',
	hidden: true,
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-account-hub" */ './SchoolEditorPage'
			)
	),
};

/** Class editor and roster at `#/account-hub/classes/<id>`. */
export const accountHubClass: FeatureDefinition = {
	id: 'account-hub-class',
	title: __( 'Class', 'ohmylms' ),
	path: '/account-hub/classes/:id',
	extensionRoute: '/accounthub',
	hidden: true,
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-account-hub" */ './ClassEditorPage'
			)
	),
};
