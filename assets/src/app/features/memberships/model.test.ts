import { ApiError } from '../../lib/api';
import { form, member, options, plan, summary } from './fixtures';
import {
	billingLabel,
	formatPrice,
	formFromMembership,
	isCalendarDate,
	isDirty,
	isValid,
	parseCourseRef,
	parseMember,
	parseMembership,
	parseMembershipDetail,
	parseOptions,
	serverFieldError,
	toInput,
	validateForm,
} from './model';
import type { FormState } from './types';

const errorsFor = ( change: Partial< FormState > ) =>
	validateForm( { ...form, ...change }, options );

describe( 'response parsers', () => {
	it( 'accepts valid plans, members, courses and options', () => {
		expect( parseMembership( summary ) ).toEqual( summary );
		expect( parseMembershipDetail( plan ) ).toEqual( plan );
		expect( parseMember( member ) ).toEqual( member );
		expect(
			parseCourseRef( { id: 3, title: 'Algebra', status: 'publish' } )
		).toEqual( { id: 3, title: 'Algebra', status: 'publish' } );
		expect( parseOptions( options ) ).toEqual( options );
	} );
	it( 'accepts null sale fields', () => {
		const bare = {
			...summary,
			sale_price: null,
			sale_start_date: null,
			sale_end_date: null,
		};
		expect( parseMembership( bare ) ).toEqual( bare );
	} );
	it.each( [
		[ 'a non-object', 'plan' ],
		[ 'an array', [] ],
		[ 'null', null ],
		[ 'a string id', { ...summary, id: '7' } ],
		[ 'a zero id', { ...summary, id: 0 } ],
		[ 'an unknown status', { ...summary, status: 'trash' } ],
		[ 'a numeric price', { ...summary, regular_price: 29.5 } ],
		[ 'a negative price', { ...summary, price: '-1.00' } ],
		[ 'a bad date', { ...summary, sale_end_date: '2026-02-30' } ],
		[ 'an unknown period', { ...summary, billing_period: 'decade' } ],
		[ 'a zero interval', { ...summary, billing_interval: 0 } ],
		[ 'a missing flag', { ...summary, all_learning_content: undefined } ],
		[ 'a negative count', { ...summary, member_count: -1 } ],
	] )( 'rejects %s', ( _label, value ) => {
		expect( () => parseMembership( value ) ).toThrow( ApiError );
		expect( () => parseMembership( value ) ).toThrowError(
			expect.objectContaining( { code: 'invalid_response', status: 502 } )
		);
	} );
	it( 'rejects a detail without courses or with malformed ones', () => {
		expect( () =>
			parseMembershipDetail( { ...plan, courses: undefined } )
		).toThrow( ApiError );
		expect( () =>
			parseMembershipDetail( {
				...plan,
				courses: [ { id: 1, title: 'x' } ],
			} )
		).toThrow( ApiError );
		expect( () =>
			parseMembershipDetail( { ...plan, course_ids: [ 1, 'a' ] } )
		).toThrow( ApiError );
	} );
	it( 'rejects options without choices or a numeric price limit', () => {
		expect( () => parseOptions( { ...options, statuses: [] } ) ).toThrow(
			ApiError
		);
		expect( () =>
			parseOptions( {
				...options,
				limits: { ...options.limits, max_price: '9' },
			} )
		).toThrow( ApiError );
	} );
	it( 'rejects an invalid member', () => {
		expect( () => parseMember( { ...member, user_id: 0 } ) ).toThrow(
			ApiError
		);
		expect( () => parseMember( { ...member, end_date: 5 } ) ).toThrow(
			ApiError
		);
	} );
} );

describe( 'isCalendarDate', () => {
	it( 'accepts only real dates', () => {
		expect( isCalendarDate( '2026-11-30' ) ).toBe( true );
		expect( isCalendarDate( '2028-02-29' ) ).toBe( true );
		expect( isCalendarDate( '2026-02-29' ) ).toBe( false );
		expect( isCalendarDate( '2026-13-01' ) ).toBe( false );
		expect( isCalendarDate( '26-1-1' ) ).toBe( false );
		expect( isCalendarDate( '' ) ).toBe( false );
	} );
} );

describe( 'form state', () => {
	it( 'maps a plan to string form values and back', () => {
		const state = formFromMembership( plan );
		expect( state ).toMatchObject( {
			name: 'All access',
			regular_price: '29.50',
			sale_price: '19.00',
			sale_start_date: '2026-11-01',
			billing_interval: '1',
		} );
		expect( toInput( state, 2 ) ).toEqual( {
			name: 'All access',
			status: 'publish',
			description: 'Everything.',
			regular_price: '29.50',
			sale_price: '19.00',
			sale_start_date: '2026-11-01',
			sale_end_date: '2026-11-30',
			sign_up_fee: null,
			billing_period: 'month',
			billing_interval: 1,
			all_learning_content: false,
			course_ids: [ 11, 12 ],
			excluded_course_ids: [],
			curriculum_item_ids: [],
		} );
	} );
	it( 'normalises amounts and clears empty optional fields', () => {
		const input = toInput(
			{
				...form,
				name: '  Padded  ',
				regular_price: ' 5 ',
				sale_price: '',
				sign_up_fee: '2.5',
				sale_start_date: '',
			},
			2
		);
		expect( input.name ).toBe( 'Padded' );
		expect( input.regular_price ).toBe( '5.00' );
		expect( input.sale_price ).toBeNull();
		expect( input.sale_start_date ).toBeNull();
		expect( input.sign_up_fee ).toBe( '2.50' );
	} );
	it( 'keeps curriculum rules it does not edit', () => {
		const input = toInput( { ...form, curriculum_item_ids: [ 4, 9 ] }, 2 );
		expect( input.curriculum_item_ids ).toEqual( [ 4, 9 ] );
	} );
	it( 'detects unsaved changes', () => {
		expect( isDirty( form, { ...form } ) ).toBe( false );
		expect( isDirty( { ...form, name: 'Other' }, form ) ).toBe( true );
	} );
} );

describe( 'validateForm', () => {
	it( 'accepts a valid form', () => {
		expect( isValid( validateForm( form, options ) ) ).toBe( true );
	} );
	it( 'requires a name within the limit', () => {
		expect( errorsFor( { name: '   ' } ).name ).toBeTruthy();
		expect( errorsFor( { name: 'x'.repeat( 201 ) } ).name ).toBeTruthy();
		expect( errorsFor( { name: 'x'.repeat( 200 ) } ).name ).toBeUndefined();
	} );
	it( 'rejects negative, malformed, excessive and over-precise prices', () => {
		for ( const value of [ '-1', 'abc', '', '1e3', '1.234', '1,5' ] ) {
			expect(
				errorsFor( { regular_price: value } ).regular_price
			).toBeTruthy();
		}
		expect(
			errorsFor( { regular_price: '100000000' } ).regular_price
		).toBeTruthy();
		expect(
			errorsFor( { regular_price: '0' } ).regular_price
		).toBeUndefined();
		expect(
			errorsFor( { regular_price: '99999999.99' } ).regular_price
		).toBeUndefined();
		expect( errorsFor( { sign_up_fee: '-3' } ).sign_up_fee ).toBeTruthy();
		expect( errorsFor( { sign_up_fee: '3' } ).sign_up_fee ).toBeUndefined();
	} );
	it( 'follows the currency decimals from the site settings', () => {
		const yen = {
			...options,
			currency: { ...options.currency, decimals: 0 },
		};
		expect(
			validateForm( { ...form, regular_price: '10.5' }, yen )
				.regular_price
		).toBeTruthy();
		expect(
			validateForm( { ...form, regular_price: '10' }, yen ).regular_price
		).toBeUndefined();
	} );
	it( 'keeps the sale price at or below the regular price', () => {
		const sale = {
			sale_start_date: '2026-11-01',
			sale_end_date: '2026-11-30',
		};
		expect(
			errorsFor( { sale_price: '30', ...sale } ).sale_price
		).toBeTruthy();
		expect(
			errorsFor( { sale_price: '29.50', ...sale } ).sale_price
		).toBeUndefined();
		expect(
			errorsFor( { sale_price: '10', ...sale } ).sale_price
		).toBeUndefined();
	} );
	it( 'orders and validates sale dates', () => {
		const base = { sale_price: '10' };
		expect(
			errorsFor( {
				...base,
				sale_start_date: '2026-12-01',
				sale_end_date: '2026-11-01',
			} ).sale_end_date
		).toBeTruthy();
		expect(
			errorsFor( {
				...base,
				sale_start_date: '2026-02-30',
				sale_end_date: '2026-03-30',
			} ).sale_start_date
		).toBeTruthy();
		expect(
			errorsFor( {
				...base,
				sale_start_date: '2026-11-01',
				sale_end_date: '2026-11-01',
			} )
		).toEqual( {} );
	} );
	it( 'needs both dates or neither for a discounted sale', () => {
		expect(
			errorsFor( { sale_price: '10', sale_end_date: '2026-11-30' } )
				.sale_end_date
		).toBeTruthy();
		expect( errorsFor( { sale_price: '10' } ) ).toEqual( {} );
	} );
	it( 'needs an end date for a free promotion but not a start', () => {
		expect( errorsFor( { sale_price: '0' } ).sale_end_date ).toBeTruthy();
		expect(
			errorsFor( { sale_price: '0', sale_end_date: '2026-11-30' } )
		).toEqual( {} );
	} );
	it( 'rejects sale dates without a sale price', () => {
		expect(
			errorsFor( {
				sale_start_date: '2026-11-01',
				sale_end_date: '2026-11-30',
			} ).sale_price
		).toBeTruthy();
	} );
	it( 'validates the billing interval', () => {
		for ( const value of [ '0', '366', '1.5', 'x', '' ] ) {
			expect(
				errorsFor( { billing_interval: value } ).billing_interval
			).toBeTruthy();
		}
		expect( errorsFor( { billing_interval: '365' } ) ).toEqual( {} );
	} );
	it( 'limits courses and refuses a course both included and excluded', () => {
		const course = { id: 1, title: 'A', status: 'publish' };
		expect(
			errorsFor( { courses: [ course ], excluded_courses: [ course ] } )
				.excluded_courses
		).toBeTruthy();
		const small = {
			...options,
			limits: { ...options.limits, max_courses: 1 },
		};
		expect(
			validateForm(
				{
					...form,
					courses: [ course, { ...course, id: 2 } ],
				},
				small
			).courses
		).toBeTruthy();
	} );
} );

describe( 'serverFieldError', () => {
	const error = ( code: string, details?: unknown ) =>
		new ApiError( { code, status: 422, message: 'Nope.', details } );
	it( 'maps rejections to the field they concern', () => {
		expect(
			serverFieldError(
				error( 'ohmylms_membership_sale_price_too_high' )
			)
		).toEqual( { path: 'sale_price', message: 'Nope.' } );
		expect(
			serverFieldError( error( 'ohmylms_membership_free_needs_end' ) )
				?.path
		).toBe( 'sale_end_date' );
		expect(
			serverFieldError( error( 'ohmylms_membership_invalid_courses' ) )
				?.path
		).toBe( 'courses' );
		expect(
			serverFieldError( error( 'ohmylms_membership_course_conflict' ) )
				?.path
		).toBe( 'excluded_courses' );
		expect(
			serverFieldError(
				error( 'ohmylms_membership_invalid_price', {
					field: 'sign_up_fee',
				} )
			)?.path
		).toBe( 'sign_up_fee' );
		expect(
			serverFieldError( error( 'ohmylms_membership_invalid_price' ) )
				?.path
		).toBe( 'regular_price' );
	} );
	it( 'ignores errors that are not about one field', () => {
		expect( serverFieldError( error( 'rest_forbidden' ) ) ).toBeNull();
	} );
} );

describe( 'display helpers', () => {
	it( 'formats prices with the plan currency and tolerates unknown codes', () => {
		expect( formatPrice( '29.5', 'USD', 'en-US' ) ).toBe( '$29.50' );
		expect( formatPrice( '5', 'NOPE-X' ) ).toBe( '5 NOPE-X' );
	} );
	it( 'describes billing', () => {
		expect(
			billingLabel( { ...summary, billing_period: 'one_time' } )
		).toBe( 'One time' );
		expect( billingLabel( summary ) ).toBe( 'Every month' );
		expect( billingLabel( { ...summary, billing_interval: 3 } ) ).toBe(
			'Every 3 months'
		);
		expect(
			billingLabel( {
				...summary,
				billing_period: 'year',
				billing_interval: 2,
			} )
		).toBe( 'Every 2 years' );
		expect(
			billingLabel( {
				...summary,
				billing_period: 'week',
				billing_interval: 1,
			} )
		).toBe( 'Every week' );
		expect(
			billingLabel( {
				...summary,
				billing_period: 'day',
				billing_interval: 5,
			} )
		).toBe( 'Every 5 days' );
	} );
} );
