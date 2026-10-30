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
