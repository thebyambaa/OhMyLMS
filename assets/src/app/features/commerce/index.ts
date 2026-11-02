import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Order list at `#/orders`. */
export const orders: FeatureDefinition = {
	id: 'orders',
	title: __( 'Orders', 'ohmylms' ),
	path: '/orders',
	group: 'commerce',
	tab: 'orders',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-commerce" */ './OrdersPage'
			)
	),
};

/** Order details at `#/orders/<id>`; a separate route entry that shares the chunk with the list. */
export const orderDetail: FeatureDefinition = {
	id: 'order-detail',
	title: __( 'Order', 'ohmylms' ),
	path: '/orders/:id',
	group: 'commerce',
	tab: 'orders',
	extensionRoute: '/order-edit/:id',
	hidden: true,
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-commerce" */ './OrderDetailPage'
			)
	),
};

/** Commerce › Cohorts at `#/cohorts` (`cohort=<id>` opens one); formerly `/memberships?panel=cohorts`. */
export const cohorts: FeatureDefinition = {
	id: 'cohorts',
	title: __( 'Cohorts', 'ohmylms' ),
	path: '/cohorts',
	group: 'commerce',
	tab: 'cohorts',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-cohorts" */ './cohorts/CohortsPanel'
			)
	),
};

/** Commerce › Automations at `#/automations` (`rule=`, `show=log`); formerly `/memberships?panel=automations`. */
export const automations: FeatureDefinition = {
	id: 'automations',
	title: __( 'Automations', 'ohmylms' ),
	path: '/automations',
	group: 'commerce',
	tab: 'automations',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-automations" */ './automations/AutomationsPanel'
			)
	),
};

/** Coupon list and editor at `#/coupons`. */
export const coupons: FeatureDefinition = {
	id: 'coupons',
	title: __( 'Coupons', 'ohmylms' ),
	path: '/coupons',
	group: 'commerce',
	tab: 'coupons',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-commerce" */ './CouponsPage'
			)
	),
};
