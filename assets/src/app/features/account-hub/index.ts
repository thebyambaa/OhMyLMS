import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/**
 * Account Hub at `#/account-hub`; the tab is the `tab` query parameter (`schools`, `classes`,
 * `teachers`, `parents`, `users`). Extensions keep seeing the original `/accounthub` screen route.
 */
export const accountHub: FeatureDefinition = {
	id: 'account-hub',
	title: __( 'Account Hub', 'ohmylms' ),
	path: '/account-hub',
	group: 'accounts',
	tab: 'schools',
	tabs: [ 'classes', 'teachers', 'parents', 'users' ],
	params: { tab: 'tab' },
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
	group: 'accounts',
	tab: 'schools',
	extensionRoute: '/accounthub',
	hidden: true,
	capability: 'manage_options',
	component: lazy( () =>
		import(
			/* webpackChunkName: "admin-app-account-hub" */ './screens'
		).then( ( screens ) => screens.loadSchoolEditor() )
	),
};

/** School roster intake (invitations, managed students, CSV import) at `#/account-hub/schools/<id>/roster`. */
export const accountHubRoster: FeatureDefinition = {
	id: 'account-hub-roster',
	title: __( 'School roster', 'ohmylms' ),
	path: '/account-hub/schools/:id/roster',
	group: 'accounts',
	tab: 'schools',
	view: 'roster',
	extensionRoute: '/accounthub',
	hidden: true,
	capability: 'manage_options',
	component: lazy( () =>
		import(
			/* webpackChunkName: "admin-app-account-hub" */ './screens'
		).then( ( screens ) => screens.loadSchoolRoster() )
	),
};

/** Class editor and roster at `#/account-hub/classes/<id>`. */
export const accountHubClass: FeatureDefinition = {
	id: 'account-hub-class',
	title: __( 'Class', 'ohmylms' ),
	path: '/account-hub/classes/:id',
	group: 'accounts',
	tab: 'classes',
	extensionRoute: '/accounthub',
	hidden: true,
	capability: 'manage_options',
	component: lazy( () =>
		import(
			/* webpackChunkName: "admin-app-account-hub" */ './screens'
		).then( ( screens ) => screens.loadClassEditor() )
	),
};
