import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Membership plan list at `#/memberships`. */
export const memberships: FeatureDefinition = {
	id: 'memberships',
	title: __( 'Memberships', 'ohmylms' ),
	path: '/memberships',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-memberships" */ './MembershipsPage'
			)
	),
};

/**
 * Plan editor at `#/memberships/<id>`. A separate hidden route entry because the registry does not
 * accept wildcard paths; it shares the chunk with the list.
 */
export const membershipEditor: FeatureDefinition = {
	id: 'memberships-editor',
	title: __( 'Membership plan', 'ohmylms' ),
	path: '/memberships/:id',
	hidden: true,
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-memberships" */ './MembershipEditorPage'
			)
	),
};
