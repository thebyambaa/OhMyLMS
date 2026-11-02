import type { SubscriptionDetail, SubscriptionSummary } from './types';

/** Test fixtures only; production components never import this module. */
export const activeSubscription: SubscriptionSummary = {
	id: 501,
	status: 'active',
	plan_id: 60,
	plan_name: 'Gold membership',
	customer_id: 12,
	customer_name: 'Ada Lovelace',
	start_date: '2026-09-01T10:00:00',
	next_payment_date: '2026-11-01T10:00:00',
	end_date: null,
	billing_period: 'month',
	billing_interval: 1,
	recurring_amount: '25.00',
	currency: 'USD',
	allowed_actions: [ 'pause', 'cancel' ],
};
export const pausedSubscription: SubscriptionSummary = {
	...activeSubscription,
	id: 502,
	status: 'on-hold',
	customer_id: 13,
	customer_name: 'Grace Hopper',
	billing_period: 'week',
	billing_interval: 2,
	allowed_actions: [ 'resume', 'cancel' ],
};
export const subscriptionDetail: SubscriptionDetail = {
	...activeSubscription,
	customer_email: 'ada@example.com',
	original_order_id: 311,
	payment_method: 'offline',
	payment_method_title: 'Offline',
	last_payment_date: '2026-10-01T10:00:00',
	cancelled_date: null,
	date_created: '2026-09-01T10:00:00',
	related_orders: [
		{
			id: 320,
			relationship: 'renewal',
			status: 'completed',
			total: '25.00',
			currency: 'USD',
			date_created: '2026-10-01T10:00:00',
		},
		{
			id: 311,
			relationship: 'parent',
			status: 'completed',
			total: '25.00',
			currency: 'USD',
			date_created: '2026-09-01T10:00:00',
		},
	],
	notes: [
		{
			id: 9,
			content: 'Subscription record created.',
			added_by: 'OhMyLMS System',
			date_created: '2026-09-01T10:00:00',
		},
	],
};
export const cancelledDetail: SubscriptionDetail = {
	...subscriptionDetail,
	status: 'cancelled',
	next_payment_date: null,
	cancelled_date: '2026-10-30T09:00:00',
	allowed_actions: [],
};
