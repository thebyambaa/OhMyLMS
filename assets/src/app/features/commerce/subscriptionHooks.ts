import { useState } from '@wordpress/element';
import { useStepBack } from './hooks';
import { getSubscription, listSubscriptions } from './subscriptionApi';
import { EMPTY_SUBSCRIPTION_QUERY } from './subscriptionModel';
import { useRequest } from './useRequest';
import type { SubscriptionQuery } from './types';

/** State of the subscription list: query, paging, filters and the request that follows them. */
export function useSubscriptions() {
	const [ query, setQuery ] = useState< SubscriptionQuery >(
		EMPTY_SUBSCRIPTION_QUERY
	);
	const request = useRequest(
		( signal ) => listSubscriptions( query, signal ),
		JSON.stringify( query )
	);
	useStepBack(
		request.error,
		query.page,
		'ohmylms_subscription_invalid_page',
		() =>
			setQuery( ( current ) => ( {
				...current,
				page: Math.max( 1, current.page - 1 ),
			} ) )
	);
	return {
		...request,
		query,
		setPage: ( page: number ) =>
			setQuery( ( current ) => ( { ...current, page } ) ),
		/**
		 * Replace filters and return to the first page.
		 *
		 * @param filters Filters to change.
		 */
		setFilters: ( filters: Partial< SubscriptionQuery > ) =>
			setQuery( ( current ) => ( { ...current, ...filters, page: 1 } ) ),
	};
}

/**
 * Load one subscription.
 *
 * @param id Subscription ID.
 */
export function useSubscription( id: number ) {
	return useRequest(
		( signal ) => getSubscription( id, signal ),
		String( id )
	);
}
