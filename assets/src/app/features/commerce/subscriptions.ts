import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Subscription list at `#/subscriptions`. */
export const subscriptions: FeatureDefinition = {
	id: 'subscriptions',
	title: __( 'Subscriptions', 'ohmylms' ),
	path: '/subscriptions',
	group: 'commerce',
	tab: 'subscriptions',
	extensionRoute: '/subscriptions',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-commerce" */ './SubscriptionsPage'
			)
	),
};

/** Subscription details at `#/subscriptions/<id>`; shares the chunk with the list. */
export const subscriptionDetail: FeatureDefinition = {
	id: 'subscription-detail',
	title: __( 'Subscription', 'ohmylms' ),
	path: '/subscriptions/:id',
	group: 'commerce',
	tab: 'subscriptions',
	extensionRoute: '/subscription-edit/:id',
	hidden: true,
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-commerce" */ './SubscriptionDetailPage'
			)
	),
};
