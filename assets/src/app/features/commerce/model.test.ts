import { coupon, counts, detail, flatCoupon, order } from './fixtures';
import {
	EMPTY_COUPON_FORM,
	EMPTY_COUPON_QUERY,
	EMPTY_ORDER_QUERY,
	allowedStatuses,
	buildCouponQuery,
	buildOrderQuery,
	couponToForm,
	discountLabel,
	formatMoney,
	orderPath,
	parseCoupon,
	parseDeletedId,
	parseNote,
	parseOrderDetail,
	parseOrderId,
	parseOrderSummary,
	parseStatusCounts,
	revokesAccess,
	statusLabel,
	toCouponPayload,
	toInputDate,
	validateCouponForm,
	validateDateRange,
	validateNote,
} from './model';
import type { CouponForm } from './types';

const validForm: CouponForm = {
	...EMPTY_COUPON_FORM,
	code: 'SPRING',
	amount: '15',
};

describe( 'response parsers', () => {
	it( 'accepts valid rows and drops unknown properties', () => {
		expect(
			parseOrderSummary( { ...order, _links: {}, extra: 1 } )
		).toEqual( order );
		expect( parseOrderDetail( detail ) ).toEqual( detail );
		expect( parseCoupon( coupon ) ).toEqual( coupon );
		expect( parseCoupon( flatCoupon ) ).toEqual( flatCoupon );
		expect( parseStatusCounts( counts ) ).toEqual( counts );
	} );

	it.each( [
		[ 'unknown status', { ...order, status: 'paid' } ],
		[ 'numeric money', { ...order, total: 49.5 } ],
		[ 'malformed money', { ...order, total: '$49.50' } ],
		[ 'missing id', { ...order, id: 0 } ],
		[ 'float id', { ...order, id: 1.5 } ],
		[ 'not an object', 'order' ],
		[ 'array', [ order ] ],
	] )( 'rejects an order with %s', ( _label, value ) => {
		expect( () => parseOrderSummary( value ) ).toThrow(
			'The server returned invalid commerce data.'
		);
	} );

	it( 'rejects incomplete details and coupons', () => {
		expect( () =>
			parseOrderDetail( { ...detail, line_items: 'none' } )
		).toThrow();
		expect( () =>
			parseOrderDetail( { ...detail, allowed_statuses: [ 'refunded!' ] } )
		).toThrow();
		expect( () =>
			parseCoupon( { ...coupon, discount_type: 'fixed_cart' } )
		).toThrow();
		expect( () => parseCoupon( { ...coupon, usage_limit: -1 } ) ).toThrow();
		expect( () =>
			parseCoupon( { ...coupon, individual_use: 'no' } )
		).toThrow();
		expect( () => parseStatusCounts( { pending: 1 } ) ).toThrow();
		expect( () => parseNote( { id: 1, content: 5 } ) ).toThrow();
	} );

	it( 'reads the id of a deleted record', () => {
		expect( parseDeletedId( { deleted: true, previous: { id: 9 } } ) ).toBe(
			9
		);
		expect( () =>
			parseDeletedId( { deleted: false, previous: { id: 9 } } )
		).toThrow();
		expect( () => parseDeletedId( { deleted: true } ) ).toThrow();
	} );
} );

describe( 'queries', () => {
	it( 'omits blank order filters', () => {
		expect( buildOrderQuery( EMPTY_ORDER_QUERY ) ).toEqual( {
			page: 1,
			per_page: 20,
			orderby: 'date',
			order: 'desc',
		} );
		expect(
			buildOrderQuery( {
				...EMPTY_ORDER_QUERY,
				page: 3,
				search: ' ada ',
				status: 'pending',
				paymentMethod: 'stripe',
				after: '2026-01-01',
				before: '2026-02-01',
			} )
		).toEqual( {
			page: 3,
			per_page: 20,
			orderby: 'date',
			order: 'desc',
			search: 'ada',
			status: 'pending',
			payment_method: 'stripe',
			after: '2026-01-01',
			before: '2026-02-01',
		} );
	} );

	it( 'builds the coupon query', () => {
		expect( buildCouponQuery( EMPTY_COUPON_QUERY ) ).toEqual( {
			page: 1,
			per_page: 20,
			orderby: 'date',
			order: 'desc',
		} );
		expect(
			buildCouponQuery( {
				...EMPTY_COUPON_QUERY,
				search: 'x',
				status: 'draft',
			} )
		).toMatchObject( { search: 'x', status: 'draft' } );
	} );

	it( 'validates the date range', () => {
		expect( validateDateRange( '2026-01-02', '2026-01-01' ) ).toMatch(
			'must not be after'
		);
		expect( validateDateRange( '2026-01-01', '2026-01-01' ) ).toBeNull();
		expect( validateDateRange( '', '2026-01-01' ) ).toBeNull();
	} );
} );

describe( 'order helpers', () => {
	it( 'parses route ids strictly', () => {
		expect( parseOrderId( '311' ) ).toBe( 311 );
		for ( const bad of [
			'0',
			'-1',
			'01',
			'1.5',
			'1e3',
			'abc',
			'',
			null,
			undefined,
		] ) {
			expect( parseOrderId( bad ) ).toBeNull();
		}
		expect( orderPath( 5 ) ).toBe( '/orders/5' );
	} );

	it( 'follows the server for status changes', () => {
		expect( allowedStatuses( detail ) ).toEqual( [ 'cancelled' ] );
		expect(
			allowedStatuses( {
				...detail,
				allowed_statuses: [ 'completed', 'cancelled' ],
			} )
		).toEqual( [ 'cancelled' ] );
		expect(
			allowedStatuses( {
				...detail,
				status: 'refunded',
				allowed_statuses: [],
			} )
		).toEqual( [] );
		expect( revokesAccess( 'cancelled' ) ).toBe( true );
		expect( revokesAccess( 'completed' ) ).toBe( false );
	} );

	it( 'labels statuses', () => {
		expect( statusLabel( 'on-hold' ) ).toBe( 'On hold' );
		expect( statusLabel( 'custom' ) ).toBe( 'custom' );
	} );

	it( 'validates notes', () => {
		expect( validateNote( '   ' ) ).toBe( 'Write a note first.' );
		expect( validateNote( 'a'.repeat( 2001 ) ) ).toBe(
			'The note is too long.'
		);
		expect( validateNote( 'ok' ) ).toBeNull();
	} );
} );

describe( 'money', () => {
	it( 'formats with the order currency and keeps the decimals of the string', () => {
		expect( formatMoney( '49.50', 'USD' ) ).toContain( '49.50' );
		expect( formatMoney( '49.50', 'USD' ) ).toContain( '$' );
		expect( formatMoney( '1234', 'JPY' ) ).toMatch( /1,234/ );
		expect( formatMoney( '5.000', 'USD' ) ).toContain( '5.000' );
		expect( formatMoney( '-5.00', 'EUR' ) ).toMatch( /5\.00/ );
	} );

	it( 'falls back for unknown currency codes and bad numbers', () => {
		expect( formatMoney( '5.00', 'not a currency' ) ).toBe(
			'5.00 not a currency'
		);
		expect( formatMoney( 'abc', 'USD' ) ).toBe( 'abc' );
	} );

	it( 'describes coupon discounts', () => {
		expect( discountLabel( coupon ) ).toBe( '15%' );
		expect( discountLabel( flatCoupon ) ).toContain( '10.00' );
	} );
} );

describe( 'coupon form', () => {
	it( 'accepts a minimal valid form', () => {
		expect( validateCouponForm( validForm ) ).toEqual( {} );
	} );

	it.each( [
		[ 'code', { code: '  ' }, 'code' ],
		[ 'code', { code: 'x'.repeat( 51 ) }, 'code' ],
		[ 'amount', { amount: '' }, 'amount' ],
		[ 'amount', { amount: '0' }, 'amount' ],
		[ 'amount', { amount: '-5' }, 'amount' ],
		[ 'amount', { amount: '1e3' }, 'amount' ],
		[ 'amount', { amount: '100.01' }, 'amount' ],
		[
			'date_expires',
			{
				date_start: '2030-02-01T00:00',
				date_expires: '2030-01-01T00:00',
			},
			'date_expires',
		],
		[ 'usage_limit', { usage_limit: '-1' }, 'usage_limit' ],
		[ 'usage_limit', { usage_limit: '1.5' }, 'usage_limit' ],
		[
			'usage_limit_per_user',
			{ usage_limit: '2', usage_limit_per_user: '3' },
			'usage_limit_per_user',
		],
		[
			'course_ids',
			{ course_id_type: 'selected_course' as const },
			'course_ids',
		],
	] )( 'flags %s', ( _label, change, field ) => {
		expect(
			Object.keys( validateCouponForm( { ...validForm, ...change } ) )
		).toContain( field );
	} );

	it( 'allows 100 percent, flat amounts above 100 and equal limits', () => {
		expect( validateCouponForm( { ...validForm, amount: '100' } ) ).toEqual(
			{}
		);
		expect(
			validateCouponForm( {
				...validForm,
				discount_type: 'flat-rate',
				amount: '250.50',
			} )
		).toEqual( {} );
		expect(
			validateCouponForm( {
				...validForm,
				usage_limit: '2',
				usage_limit_per_user: '2',
			} )
		).toEqual( {} );
	} );

	it( 'builds the payload', () => {
		expect(
			toCouponPayload( {
				...validForm,
				code: ' SPRING ',
				date_expires: '2030-01-01T10:30',
				usage_limit: '5',
				course_id_type: 'selected_course',
				course_ids: [ 40 ],
			} )
		).toEqual( {
			code: 'SPRING',
			title: '',
			description: '',
			status: 'publish',
			discount_type: 'percent',
			amount: '15',
			date_start: null,
			date_expires: '2030-01-01T10:30:00',
			usage_limit: 5,
			usage_limit_per_user: null,
			individual_use: false,
			course_id_type: 'selected_course',
			course_ids: [ 40 ],
		} );
		expect(
			toCouponPayload( { ...validForm, course_ids: [ 40 ] } ).course_ids
		).toEqual( [] );
	} );

	it( 'round-trips a coupon through the form', () => {
		expect( couponToForm( coupon ) ).toMatchObject( {
			code: 'SPRING15',
			amount: '15.00',
			date_start: '',
			date_expires: '2026-12-31T23:59',
			usage_limit: '100',
			usage_limit_per_user: '',
		} );
		expect( toInputDate( null ) ).toBe( '' );
	} );
} );
