import { get, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import { positiveId } from './model';
import {
	buildSubscriptionQuery,
	parseSubscriptionDetail,
	parseSubscriptionSummary,
} from './subscriptionModel';
import type { SubscriptionAction, SubscriptionQuery } from './types';

const BASE = '/ohmylms/v1/subscriptions';
const subscriptionRoute = ( id: number ) => `${ BASE }/${ positiveId( id ) }`;

export function listSubscriptions(
	query: SubscriptionQuery,
	signal?: AbortSignal
) {
	return getCollection( BASE, parseSubscriptionSummary, {
		signal,
		query: buildSubscriptionQuery( query ),
	} );
}

export async function getSubscription( id: number, signal?: AbortSignal ) {
	return parseSubscriptionDetail(
		await get< unknown >( subscriptionRoute( id ), { signal } )
	);
}

/**
 * Pause, resume or cancel a subscription. Only call this after the user confirmed the change: the
 * request always carries `confirm: true`.
 *
 * @param id     Subscription ID.
 * @param action Status change.
 * @param signal Cancels the request.
 * @return The subscription after the change.
 */
export async function runSubscriptionAction(
	id: number,
	action: SubscriptionAction,
	signal?: AbortSignal
) {
	return parseSubscriptionDetail(
		await post< unknown >(
			`${ subscriptionRoute( id ) }/${ action }`,
			{ confirm: true },
			{ signal }
		)
	);
}
