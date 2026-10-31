import { useEffect, useRef } from '@wordpress/element';
import { useAddressQuery } from '../../components/listQuery';
import type { ApiError } from '../../lib/api';
import {
	getOrder,
	getStatusCounts,
	listCoupons,
	listCourses,
	listNotes,
	listOrders,
} from './api';
import { EMPTY_COUPON_QUERY, EMPTY_ORDER_QUERY } from './model';
import { useRequest } from './useRequest';
import type { CouponQuery, OrderQuery } from './types';

/** Address names of order filters that differ from the query's. */
const ORDER_NAMES = { search: 's', paymentMethod: 'payment_method' };

/**
 * Step back one page when the current page no longer exists (the last row of a page was removed).
 * Each error object is handled once, so the stale error shown while the previous page loads cannot
 * step back a second time.
 *
 * @param error    Error of the list request, if any.
 * @param page     Current page.
 * @param invalid  Server code for a page past the end.
 * @param stepBack Called to move to the previous page.
 */
export function useStepBack(
	error: ApiError | null,
	page: number,
	invalid: string,
	stepBack: () => void
) {
	const handled = useRef< ApiError | null >( null );
	const step = useRef( stepBack );
	step.current = stepBack;
	useEffect( () => {
		if (
			error &&
			error !== handled.current &&
			page > 1 &&
			invalid === error.code
		) {
			handled.current = error;
			step.current();
		}
	}, [ error, page, invalid ] );
}

/**
 * State of the order list: query, paging and filters, kept in the address (`&status=…&s=…&paged=…`),
 * and the request that follows them.
 */
export function useOrders() {
	const address = useAddressQuery( EMPTY_ORDER_QUERY, ORDER_NAMES );
	const { query } = address;
	const request = useRequest(
		( signal ) => listOrders( query, signal ),
		JSON.stringify( query )
	);
	useStepBack( request.error, query.page, 'ohmylms_order_invalid_page', () =>
		address.setPage( Math.max( 1, query.page - 1 ), { replace: true } )
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
		setFilters: ( filters: Partial< OrderQuery > ) =>
			address.setFilters( filters ),
		filtersHref: ( filters: Partial< OrderQuery > ) =>
			address.filtersHref( filters ),
	};
}

/**
 * Order counts per status for the filter.
 *
 * @param refreshKey Change to reload the counts.
 */
export function useStatusCounts( refreshKey: string ) {
	return useRequest( ( signal ) => getStatusCounts( signal ), refreshKey );
}

/**
 * Load one order.
 *
 * @param id Order ID.
 */
export function useOrder( id: number ) {
	return useRequest( ( signal ) => getOrder( id, signal ), String( id ) );
}

/**
 * Load the notes of one order.
 *
 * @param id Order ID.
 */
export function useNotes( id: number ) {
	return useRequest( ( signal ) => listNotes( id, signal ), String( id ) );
}

/** State of the coupon list, kept in the address. */
export function useCoupons() {
	const address = useAddressQuery( EMPTY_COUPON_QUERY, { search: 's' } );
	const { query } = address;
	const request = useRequest(
		( signal ) => listCoupons( query, signal ),
		JSON.stringify( query )
	);
	useStepBack( request.error, query.page, 'ohmylms_coupon_invalid_page', () =>
		address.setPage( Math.max( 1, query.page - 1 ), { replace: true } )
	);
	return {
		...request,
		query,
		setPage: ( page: number ) => address.setPage( page ),
		pageHref: address.pageHref,
		setFilters: ( filters: Partial< CouponQuery > ) =>
			address.setFilters( filters ),
		filtersHref: ( filters: Partial< CouponQuery > ) =>
			address.filtersHref( filters ),
	};
}

/**
 * Course choices for the coupon editor (at most 100, server side search).
 *
 * @param search Title search.
 */
export function useCourseOptions( search: string ) {
	return useRequest( ( signal ) => listCourses( search, signal ), search );
}
