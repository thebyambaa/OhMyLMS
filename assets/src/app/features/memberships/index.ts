import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Membership plan list at `#/memberships`, with the Cohorts and Automations panels (`?panel=`). */
export const memberships: FeatureDefinition = {
	id: 'memberships',
	title: __( 'Memberships', 'ohmylms' ),
	path: '/memberships',
	group: 'commerce',
	tab: 'memberships',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-memberships" */ './MembershipsRoute'
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
	group: 'commerce',
	tab: 'memberships',
	hidden: true,
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-memberships" */ './MembershipEditorPage'
			)
	),
};
