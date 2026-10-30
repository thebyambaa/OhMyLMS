import { __ } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type { QueryParams } from '../../lib/api';
import type {
	Billing,
	Coupon,
	CouponField,
	CouponForm,
	CouponLine,
	CouponPayload,
	CouponQuery,
	CourseOption,
	LineItem,
	OrderDetail,
	OrderNote,
	OrderQuery,
	OrderStatus,
	OrderSummary,
	RefundRecord,
	RelatedOrder,
	StatusCounts,
} from './types';

export const PER_PAGE = 20;
export const COURSE_OPTION_LIMIT = 100;
export const NOTE_MAX_LENGTH = 2000;
export const CODE_MAX_LENGTH = 50;

export const ORDER_STATUSES: readonly OrderStatus[] = [
	'pending',
	'processing',
	'on-hold',
	'completed',
	'cancelled',
	'failed',
	'refunded',
];

/** Gateways shipped with the plugin; the server filters by any gateway id. */
export const PAYMENT_METHODS: readonly string[] = [
	'offline',
	'stripe',
	'qpay',
];

export const EMPTY_ORDER_QUERY: OrderQuery = {
	page: 1,
	search: '',
	status: 'any',
	paymentMethod: '',
	after: '',
	before: '',
	orderby: 'date',
	order: 'desc',
};

export const EMPTY_COUPON_QUERY: CouponQuery = {
	page: 1,
	search: '',
	status: 'any',
	orderby: 'date',
	order: 'desc',
};

export const EMPTY_COUPON_FORM: CouponForm = {
	code: '',
	title: '',
	description: '',
	status: 'publish',
	discount_type: 'percent',
	amount: '',
	date_start: '',
	date_expires: '',
	usage_limit: '',
	usage_limit_per_user: '',
	individual_use: false,
	course_id_type: 'all',
	course_ids: [],
};

/**
 * Translated label of an order status.
 *
 * @param status Status from the API.
 * @return Label for display.
 */
export function statusLabel( status: string ): string {
	switch ( status ) {
		case 'pending':
			return __( 'Pending payment', 'ohmylms' );
		case 'processing':
			return __( 'Processing', 'ohmylms' );
		case 'on-hold':
			return __( 'On hold', 'ohmylms' );
		case 'completed':
			return __( 'Completed', 'ohmylms' );
		case 'cancelled':
			return __( 'Cancelled', 'ohmylms' );
		case 'failed':
			return __( 'Failed', 'ohmylms' );
		case 'refunded':
			return __( 'Refunded', 'ohmylms' );
		default:
			return status;
	}
}

export function paymentLabel( id: string ): string {
	switch ( id ) {
		case 'offline':
			return __( 'Offline', 'ohmylms' );
		case 'stripe':
			return __( 'Stripe', 'ohmylms' );
		case 'qpay':
			return __( 'QPay', 'ohmylms' );
		default:
			return id;
	}
}

export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid commerce data.', 'ohmylms' ),
	} );
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
function flag( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}
export function count( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}
function nullableCount( value: unknown ): number | null {
	return null === value ? null : count( value );
}
export function positiveId( value: unknown ): number {
	const id = count( value );
	if ( id < 1 ) {
		throw invalidResponse();
	}
	return id;
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
 * Validate one order row from the server. Unknown properties (such as `_links`) are dropped.
 *
 * @param value Decoded JSON.
 * @return The order row.
 */
export function parseOrderSummary( value: unknown ): OrderSummary {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		number: text( item.number ),
		status: oneOf( item.status, ORDER_STATUSES ),
		currency: text( item.currency ),
		total: money( item.total ),
		date_created: nullableText( item.date_created ),
		payment_method: text( item.payment_method ),
		payment_method_title: text( item.payment_method_title ),
		student_id: count( item.student_id ),
		student_name: text( item.student_name ),
	};
}

function parseBilling( value: unknown ): Billing {
	const item = record( value );
	return {
		first_name: text( item.first_name ),
		last_name: text( item.last_name ),
		email: text( item.email ),
		phone: text( item.phone ),
		address: text( item.address ),
		city: text( item.city ),
		state: text( item.state ),
		postcode: text( item.postcode ),
		country: text( item.country ),
		vat_number: text( item.vat_number ),
	};
}
function parseLineItem( value: unknown ): LineItem {
	const item = record( value );
	return {
		id: count( item.id ),
		course_id: count( item.course_id ),
		name: text( item.name ),
		quantity: count( item.quantity ),
		subtotal: money( item.subtotal ),
		total: money( item.total ),
	};
}
function parseCouponLine( value: unknown ): CouponLine {
	const item = record( value );
	return { code: text( item.code ), discount: money( item.discount ) };
}
function parseRefund( value: unknown ): RefundRecord {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		amount: money( item.amount ),
		reason: text( item.reason ),
		date_created: nullableText( item.date_created ),
	};
}
function parseRelated( value: unknown ): RelatedOrder {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		type: oneOf( item.type, [ 'order', 'subscription' ] as const ),
		relationship: text( item.relationship ),
		status: text( item.status ),
		total: money( item.total ),
		date_created: nullableText( item.date_created ),
	};
}

/**
 * Validate a full order from the server.
 *
 * @param value Decoded JSON.
 * @return The order.
 */
export function parseOrderDetail( value: unknown ): OrderDetail {
	const item = record( value );
	return {
		...parseOrderSummary( item ),
		parent_id: count( item.parent_id ),
		subtotal: money( item.subtotal ),
		discount_total: money( item.discount_total ),
		tax_amount: money( item.tax_amount ),
		date_paid: nullableText( item.date_paid ),
		date_completed: nullableText( item.date_completed ),
		transaction_id: text( item.transaction_id ),
		billing: parseBilling( item.billing ),
		line_items: list( item.line_items, parseLineItem ),
		coupon_lines: list( item.coupon_lines, parseCouponLine ),
		refunds: list( item.refunds, parseRefund ),
		total_refunded: money( item.total_refunded ),
		remaining_refundable: money( item.remaining_refundable ),
		related_orders: list( item.related_orders, parseRelated ),
		allowed_statuses: list( item.allowed_statuses, ( status ) =>
			oneOf( status, ORDER_STATUSES )
		),
	};
}

export function parseNote( value: unknown ): OrderNote {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		content: text( item.content ),
		added_by: text( item.added_by ),
		date_created: text( item.date_created ),
	};
}

export function parseNotes( value: unknown ): OrderNote[] {
	return list( value, parseNote );
}

export function parseStatusCounts( value: unknown ): StatusCounts {
	const item = record( value );
	const counts = {} as StatusCounts;
	for ( const status of ORDER_STATUSES ) {
		counts[ status ] = count( item[ status ] );
	}
	return counts;
}

export function parseCoupon( value: unknown ): Coupon {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		code: text( item.code ),
		title: text( item.title ),
		description: text( item.description ),
		status: oneOf( item.status, [ 'publish', 'draft' ] as const ),
		discount_type: oneOf( item.discount_type, [
			'percent',
			'flat-rate',
		] as const ),
		amount: money( item.amount ),
		date_start: nullableText( item.date_start ),
		date_expires: nullableText( item.date_expires ),
		usage_count: count( item.usage_count ),
		usage_limit: nullableCount( item.usage_limit ),
		usage_limit_per_user: nullableCount( item.usage_limit_per_user ),
		individual_use: flag( item.individual_use ),
		course_id_type: oneOf( item.course_id_type, [
			'all',
			'selected_course',
		] as const ),
		course_ids: list( item.course_ids, positiveId ),
		date_created: text( item.date_created ),
		currency: text( item.currency ),
	};
}

/**
 * Validate the body of a delete response.
 *
 * @param value Decoded JSON.
 * @return The id of what was deleted.
 */
export function parseDeletedId( value: unknown ): number {
	const item = record( value );
	if ( true !== item.deleted ) {
		throw invalidResponse();
	}
	return positiveId( record( item.previous ).id );
}

export function parseCourseOption( value: unknown ): CourseOption {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		status: text( item.status ),
	};
}

/**
 * Query parameters for the order list. Blank filters are omitted.
 *
 * @param query Current list state.
 * @return Parameters for `GET /orders`.
 */
export function buildOrderQuery( query: OrderQuery ): QueryParams {
	const search = query.search.trim();
	return {
		page: query.page,
		per_page: PER_PAGE,
		orderby: query.orderby,
		order: query.order,
		...( search ? { search } : {} ),
		...( 'any' === query.status ? {} : { status: query.status } ),
		...( query.paymentMethod
			? { payment_method: query.paymentMethod }
			: {} ),
		...( query.after ? { after: query.after } : {} ),
		...( query.before ? { before: query.before } : {} ),
	};
}

export function buildCouponQuery( query: CouponQuery ): QueryParams {
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

/**
 * Check the date filter before it is sent.
 *
 * @param after  First day, `YYYY-MM-DD` or empty.
 * @param before Last day, `YYYY-MM-DD` or empty.
 * @return An error message, or null when the range is usable.
 */
export function validateDateRange(
	after: string,
	before: string
): string | null {
	return after && before && after > before
		? __( 'The start date must not be after the end date.', 'ohmylms' )
		: null;
}

/**
 * Parse an order ID from a route parameter.
 *
 * @param value Raw parameter.
 * @return A positive integer, or null when the value is absent or not a plain positive integer.
 */
export function parseOrderId(
	value: string | null | undefined
): number | null {
	if ( ! value || ! /^[1-9]\d{0,14}$/.test( value ) ) {
		return null;
	}
	return Number( value );
}

export function orderPath( id: number ): string {
	return `/orders/${ id }`;
}

/**
 * Format a decimal money string in the order currency. The string is only converted for display;
 * the server stays the source of truth for every amount.
 *
 * @param amount   Decimal string such as `49.50`.
 * @param currency ISO 4217 code of the order.
 * @return Locale formatted amount, or `amount currency` for an unknown code.
 */
export function formatMoney( amount: string, currency: string ): string {
	const value = Number( amount );
	if ( ! Number.isFinite( value ) ) {
		return amount;
	}
	const decimals = ( amount.split( '.' )[ 1 ] ?? '' ).length;
	try {
		return new Intl.NumberFormat( undefined, {
			style: 'currency',
			currency,
			minimumFractionDigits: decimals,
			maximumFractionDigits: decimals,
		} ).format( value );
	} catch {
		return `${ amount } ${ currency }`;
	}
}

/**
 * Format an ISO site-time date for display.
 *
 * @param value ISO string from the API, or null.
 * @return A localised date and time, or an empty string.
 */
export function formatDate( value: string | null ): string {
	if ( ! value ) {
		return '';
	}
	const date = new Date( value );
	return Number.isNaN( date.getTime() )
		? ''
		: new Intl.DateTimeFormat( undefined, {
				dateStyle: 'medium',
				timeStyle: 'short',
			} ).format( date );
}

/**
 * Statuses the order can move to, as decided by the server and limited to known statuses.
 *
 * @param order Order detail.
 * @return Allowed target statuses.
 */
export function allowedStatuses( order: OrderDetail ): OrderStatus[] {
	return order.allowed_statuses.filter(
		( status ) => status !== order.status
	);
}

/**
 * Whether the status change removes the access of the student.
 *
 * @param status Target status.
 * @return True for cancellation.
 */
export function revokesAccess( status: OrderStatus ): boolean {
	return 'cancelled' === status;
}

/**
 * Validate the note text.
 *
 * @param value Text typed by the user.
 * @return An error message, or null.
 */
export function validateNote( value: string ): string | null {
	if ( ! value.trim() ) {
		return __( 'Write a note first.', 'ohmylms' );
	}
	return value.length > NOTE_MAX_LENGTH
		? __( 'The note is too long.', 'ohmylms' )
		: null;
}

/**
 * Convert an API date (`YYYY-MM-DDTHH:mm:ss`) to a `datetime-local` value.
 *
 * @param value API date or null.
 * @return Value for the input, or an empty string.
 */
export function toInputDate( value: string | null ): string {
	return value ? value.slice( 0, 16 ) : '';
}

function toApiDate( value: string ): string | null {
	return value ? `${ value }:00` : null;
}

export function couponToForm( coupon: Coupon ): CouponForm {
	return {
		code: coupon.code,
		title: coupon.title,
		description: coupon.description,
		status: coupon.status,
		discount_type: coupon.discount_type,
		amount: coupon.amount,
		date_start: toInputDate( coupon.date_start ),
		date_expires: toInputDate( coupon.date_expires ),
		usage_limit:
			null === coupon.usage_limit ? '' : String( coupon.usage_limit ),
		usage_limit_per_user:
			null === coupon.usage_limit_per_user
				? ''
				: String( coupon.usage_limit_per_user ),
		individual_use: coupon.individual_use,
		course_id_type: coupon.course_id_type,
		course_ids: [ ...coupon.course_ids ],
	};
}

function limitValue( value: string ): number | null {
	return value.trim() ? Number( value.trim() ) : null;
}
function validLimit( value: string ): boolean {
	return ! value.trim() || /^\d{1,9}$/.test( value.trim() );
}

/**
 * Validate the coupon form with the same rules as the server. The server remains authoritative.
 *
 * @param form Form state.
 * @return Messages by field; empty when the form can be submitted.
 */
export function validateCouponForm(
	form: CouponForm
): Partial< Record< CouponField, string > > {
	const errors: Partial< Record< CouponField, string > > = {};
	const code = form.code.trim();
	if ( ! code ) {
		errors.code = __( 'Enter a coupon code.', 'ohmylms' );
	} else if ( code.length > CODE_MAX_LENGTH ) {
		errors.code = __(
			'The code can have at most 50 characters.',
			'ohmylms'
		);
	}

	const amount = form.amount.trim();
	if ( ! /^\d+(\.\d+)?$/.test( amount ) || Number( amount ) <= 0 ) {
		errors.amount = __( 'Enter a discount greater than zero.', 'ohmylms' );
	} else if ( 'percent' === form.discount_type && Number( amount ) > 100 ) {
		errors.amount = __( 'A percentage cannot exceed 100.', 'ohmylms' );
	}

	if (
		form.date_start &&
		form.date_expires &&
		form.date_expires <= form.date_start
	) {
		errors.date_expires = __(
			'The expiry must be after the start.',
			'ohmylms'
		);
	}

	if ( ! validLimit( form.usage_limit ) ) {
		errors.usage_limit = __(
			'Enter a whole number or leave blank.',
			'ohmylms'
		);
	}
	if ( ! validLimit( form.usage_limit_per_user ) ) {
		errors.usage_limit_per_user = __(
			'Enter a whole number or leave blank.',
			'ohmylms'
		);
	} else if (
		! errors.usage_limit &&
		Number( form.usage_limit ) > 0 &&
		Number( form.usage_limit_per_user ) > Number( form.usage_limit )
	) {
		errors.usage_limit_per_user = __(
			'The per-customer limit cannot exceed the total limit.',
			'ohmylms'
		);
	}

	if (
		'selected_course' === form.course_id_type &&
		! form.course_ids.length
	) {
		errors.course_ids = __( 'Choose at least one course.', 'ohmylms' );
	}
	return errors;
}

/**
 * Build the request body of a coupon form.
 *
 * @param form Validated form state.
 * @return JSON payload.
 */
export function toCouponPayload( form: CouponForm ): CouponPayload {
	return {
		code: form.code.trim(),
		title: form.title.trim(),
		description: form.description.trim(),
		status: form.status,
		discount_type: form.discount_type,
		amount: form.amount.trim(),
		date_start: toApiDate( form.date_start ),
		date_expires: toApiDate( form.date_expires ),
		usage_limit: limitValue( form.usage_limit ),
		usage_limit_per_user: limitValue( form.usage_limit_per_user ),
		individual_use: form.individual_use,
		course_id_type: form.course_id_type,
		course_ids:
			'selected_course' === form.course_id_type ? form.course_ids : [],
	};
}

/**
 * One-line description of a coupon discount.
 *
 * @param coupon Coupon.
 * @return For example `15%` or `$10.00`.
 */
export function discountLabel( coupon: Coupon ): string {
	return 'percent' === coupon.discount_type
		? `${ Number( coupon.amount ) }%`
		: formatMoney( coupon.amount, coupon.currency );
}
