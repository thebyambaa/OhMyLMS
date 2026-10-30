import { __, _n, sprintf } from '@wordpress/i18n';
import type { QueryParams } from '../../lib/api';
import {
	count,
	formatMoney,
	invalidResponse,
	PER_PAGE,
	positiveId,
} from './model';
import type {
	BillingPeriod,
	SubscriptionAction,
	SubscriptionDetail,
	SubscriptionNote,
	SubscriptionOrder,
	SubscriptionQuery,
	SubscriptionStatus,
	SubscriptionSummary,
} from './types';

export const SUBSCRIPTION_STATUSES: readonly SubscriptionStatus[] = [
	'pending',
	'active',
	'on-hold',
	'pending-cancel',
	'cancelled',
	'expired',
];

export const SUBSCRIPTION_ACTIONS: readonly SubscriptionAction[] = [
	'pause',
	'resume',
	'cancel',
];

const BILLING_PERIODS: readonly BillingPeriod[] = [
	'',
	'day',
	'week',
	'month',
	'year',
];

export const EMPTY_SUBSCRIPTION_QUERY: SubscriptionQuery = {
	page: 1,
	search: '',
	status: 'any',
	orderby: 'date',
	order: 'desc',
};

/**
 * Translated label of a subscription status.
 *
 * @param status Status from the API.
 * @return Label for display.
 */
export function subscriptionStatusLabel( status: string ): string {
	switch ( status ) {
		case 'pending':
			return __( 'Pending', 'ohmylms' );
		case 'active':
			return __( 'Active', 'ohmylms' );
		case 'on-hold':
			return __( 'Paused (on hold)', 'ohmylms' );
		case 'pending-cancel':
			return __( 'Pending cancellation', 'ohmylms' );
		case 'cancelled':
			return __( 'Cancelled', 'ohmylms' );
		case 'expired':
			return __( 'Expired', 'ohmylms' );
		default:
			return status;
	}
}

export function actionLabel( action: SubscriptionAction ): string {
	switch ( action ) {
		case 'pause':
			return __( 'Pause subscription', 'ohmylms' );
		case 'resume':
			return __( 'Resume subscription', 'ohmylms' );
		default:
			return __( 'Cancel subscription', 'ohmylms' );
	}
}

/**
 * What a status change does, shown before the user confirms it.
 *
 * @param action Action to confirm.
 * @return Explanation of the consequences.
 */
export function actionConsequence( action: SubscriptionAction ): string {
	switch ( action ) {
		case 'pause':
			return __(
				'The subscription is put on hold and its scheduled renewal payments stop until it is resumed.',
				'ohmylms'
			);
		case 'resume':
			return __(
				'The subscription becomes active again and its next renewal payment is scheduled.',
				'ohmylms'
			);
		default:
			return __(
				'The subscription is cancelled now and no further payments are taken. This cannot be undone.',
				'ohmylms'
			);
	}
}

/**
 * Billing frequency such as "Every month" or "Every 3 weeks".
 *
 * @param period   Billing period.
 * @param interval Periods between renewals.
 * @return Label, or an empty string for an unknown period.
 */
export function billingLabel(
	period: BillingPeriod,
	interval: number
): string {
	const many = interval > 1;
	switch ( period ) {
		case 'day':
			return many
				? sprintf(
						/* translators: %d: number of days. */
						_n(
							'Every %d day',
							'Every %d days',
							interval,
							'ohmylms'
						),
						interval
					)
				: __( 'Every day', 'ohmylms' );
		case 'week':
			return many
				? sprintf(
						/* translators: %d: number of weeks. */
						_n(
							'Every %d week',
							'Every %d weeks',
							interval,
							'ohmylms'
						),
						interval
					)
				: __( 'Every week', 'ohmylms' );
		case 'month':
			return many
				? sprintf(
						/* translators: %d: number of months. */
						_n(
							'Every %d month',
							'Every %d months',
							interval,
							'ohmylms'
						),
						interval
					)
				: __( 'Every month', 'ohmylms' );
		case 'year':
			return many
				? sprintf(
						/* translators: %d: number of years. */
						_n(
							'Every %d year',
							'Every %d years',
							interval,
							'ohmylms'
						),
						interval
					)
				: __( 'Every year', 'ohmylms' );
		default:
			return '';
	}
}

/**
 * Renewal amount and frequency, for example "$25.00 · Every month".
 *
 * @param item Subscription row or detail.
 * @return Label for display.
 */
export function amountLabel( item: SubscriptionSummary ): string {
	const amount = formatMoney( item.recurring_amount, item.currency );
	const every = billingLabel( item.billing_period, item.billing_interval );
	return every ? `${ amount } · ${ every }` : amount;
}

function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}
function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}
function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}
function money( value: unknown ): string {
	const amount = text( value );
	if ( ! /^-?\d+(\.\d+)?$/.test( amount ) ) {
		throw invalidResponse();
	}
	return amount;
}
function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( option ) => option === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}
function list< T >( value: unknown, parse: ( item: unknown ) => T ): T[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value.map( parse );
}

/**
 * Validate one subscription row from the server. Unknown properties (such as `_links`) are dropped.
 *
 * @param value Decoded JSON.
 * @return The subscription row.
 */
export function parseSubscriptionSummary(
	value: unknown
): SubscriptionSummary {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		status: oneOf( item.status, SUBSCRIPTION_STATUSES ),
		plan_id: count( item.plan_id ),
		plan_name: text( item.plan_name ),
		customer_id: count( item.customer_id ),
		customer_name: text( item.customer_name ),
		start_date: nullableText( item.start_date ),
		next_payment_date: nullableText( item.next_payment_date ),
		end_date: nullableText( item.end_date ),
		billing_period: oneOf( item.billing_period, BILLING_PERIODS ),
		billing_interval: positiveId( item.billing_interval ),
		recurring_amount: money( item.recurring_amount ),
		currency: text( item.currency ),
		allowed_actions: list( item.allowed_actions, ( action ) =>
			oneOf( action, SUBSCRIPTION_ACTIONS )
		),
	};
}

function parseOrder( value: unknown ): SubscriptionOrder {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		relationship: oneOf( item.relationship, [
			'parent',
			'renewal',
		] as const ),
		status: text( item.status ),
		total: money( item.total ),
		currency: text( item.currency ),
		date_created: nullableText( item.date_created ),
	};
}

function parseNote( value: unknown ): SubscriptionNote {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		content: text( item.content ),
		added_by: text( item.added_by ),
		date_created: nullableText( item.date_created ),
	};
}

/**
 * Validate a full subscription from the server.
 *
 * @param value Decoded JSON.
 * @return The subscription.
 */
export function parseSubscriptionDetail( value: unknown ): SubscriptionDetail {
	const item = record( value );
	return {
		...parseSubscriptionSummary( item ),
		customer_email: text( item.customer_email ),
		original_order_id: count( item.original_order_id ),
		payment_method: text( item.payment_method ),
		payment_method_title: text( item.payment_method_title ),
		last_payment_date: nullableText( item.last_payment_date ),
		cancelled_date: nullableText( item.cancelled_date ),
		date_created: nullableText( item.date_created ),
		related_orders: list( item.related_orders, parseOrder ),
		notes: list( item.notes, parseNote ),
	};
}

/**
 * Query parameters for the subscription list. Blank filters are omitted.
 *
 * @param query Current list state.
 * @return Parameters for `GET /subscriptions`.
 */
export function buildSubscriptionQuery(
	query: SubscriptionQuery
): QueryParams {
	const search = query.search.trim();
	return {
		page: query.page,
		per_page: PER_PAGE,
		orderby: query.orderby,
		order: query.order,
		...( search ? { search } : {} ),
		...( 'any' === query.status ? {} : { status: query.status } ),
	};
}

export function subscriptionPath( id: number ): string {
	return `/subscriptions/${ id }`;
}

/**
 * Actions offered for a subscription: those the server allows, in a fixed order.
 *
 * @param subscription Subscription row or detail.
 * @return Allowed actions.
 */
export function availableActions(
	subscription: SubscriptionSummary
): SubscriptionAction[] {
	return SUBSCRIPTION_ACTIONS.filter( ( action ) =>
		subscription.allowed_actions.includes( action )
	);
}
