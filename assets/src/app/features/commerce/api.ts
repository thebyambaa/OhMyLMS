import { del, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	buildCouponQuery,
	buildOrderQuery,
	parseCoupon,
	parseDeletedId,
	parseNote,
	parseNotes,
	parseOrderDetail,
	parseOrderSummary,
	parseStatusCounts,
	positiveId,
} from './model';
import type {
	CouponPayload,
	CouponQuery,
	OrderQuery,
	OrderStatus,
} from './types';

const BASE = '/ohmylms-commerce/v1';
const orderRoute = ( id: number ) => `${ BASE }/orders/${ positiveId( id ) }`;
const couponRoute = ( id: number ) => `${ BASE }/coupons/${ positiveId( id ) }`;

export function listOrders( query: OrderQuery, signal?: AbortSignal ) {
	return getCollection( `${ BASE }/orders`, parseOrderSummary, {
		signal,
		query: buildOrderQuery( query ),
	} );
}
export async function getStatusCounts( signal?: AbortSignal ) {
	return parseStatusCounts(
		await get< unknown >( `${ BASE }/orders/status-counts`, { signal } )
	);
}
export async function getOrder( id: number, signal?: AbortSignal ) {
	return parseOrderDetail(
		await get< unknown >( orderRoute( id ), { signal } )
	);
}
export async function changeOrderStatus(
	id: number,
	status: OrderStatus,
	signal?: AbortSignal
) {
	return parseOrderDetail(
		await patch< unknown >( orderRoute( id ), { status }, { signal } )
	);
}
export async function trashOrder( id: number, signal?: AbortSignal ) {
	return parseDeletedId(
		await del< unknown >( orderRoute( id ), { signal } )
	);
}
export async function listNotes( id: number, signal?: AbortSignal ) {
	return parseNotes(
		await get< unknown >( `${ orderRoute( id ) }/notes`, { signal } )
	);
}
export async function addNote(
	id: number,
	note: string,
	signal?: AbortSignal
) {
	return parseNote(
		await post< unknown >(
			`${ orderRoute( id ) }/notes`,
			{ note },
			{ signal }
		)
	);
}

export function listCoupons( query: CouponQuery, signal?: AbortSignal ) {
	return getCollection( `${ BASE }/coupons`, parseCoupon, {
		signal,
		query: buildCouponQuery( query ),
	} );
}
export async function createCoupon(
	payload: CouponPayload,
	signal?: AbortSignal
) {
	return parseCoupon(
		await post< unknown >( `${ BASE }/coupons`, payload, { signal } )
	);
}
export async function updateCoupon(
	id: number,
	payload: CouponPayload,
	signal?: AbortSignal
) {
	return parseCoupon(
		await patch< unknown >( couponRoute( id ), payload, { signal } )
	);
}
export async function deleteCoupon( id: number, signal?: AbortSignal ) {
	return parseDeletedId(
		await del< unknown >( couponRoute( id ), { signal } )
	);
}

/** Course choices for the coupon editor: the students feature's bounded course list. */
export { listCourses } from '../students/public';
