import { useAddressQuery } from '../../components/listQuery';
import { useStepBack } from './hooks';
import { getSubscription, listSubscriptions } from './subscriptionApi';
import { EMPTY_SUBSCRIPTION_QUERY } from './subscriptionModel';
import { useRequest } from './useRequest';
import type { SubscriptionQuery } from './types';

/**
 * State of the subscription list: query, paging and filters, kept in the address, and the request
 * that follows them.
 */
export function useSubscriptions() {
	const address = useAddressQuery( EMPTY_SUBSCRIPTION_QUERY, {
		search: 's',
	} );
	const { query } = address;
	const request = useRequest(
		( signal ) => listSubscriptions( query, signal ),
		JSON.stringify( query )
	);
	useStepBack(
		request.error,
		query.page,
		'ohmylms_subscription_invalid_page',
		() =>
			address.setPage( Math.max( 1, query.page - 1 ), {
				replace: true,
			} )
	);
	return {
		...request,
		query,
		setPage: ( page: number ) => address.setPage( page ),
		pageHref: address.pageHref,
		/**
		 * Replace filters and return to the first page.
		 *
		 * @param filters Filters to change.
		 */
		setFilters: ( filters: Partial< SubscriptionQuery > ) =>
			address.setFilters( filters ),
		filtersHref: ( filters: Partial< SubscriptionQuery > ) =>
			address.filtersHref( filters ),
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
