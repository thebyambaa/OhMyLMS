import { ApiError } from '../../lib/api';
import {
	DEFAULT_RANGE,
	MAX_SPAN_DAYS,
	buildCards,
	buildChart,
	buildCourseCards,
	buildCourseQuery,
	buildRangeQuery,
	buildStudentQuery,
	buildTransactionQuery,
	courseReportPath,
	daysBetween,
	describeChange,
	describeError,
	describeSeries,
	formatChange,
	formatMoney,
	isIsoDay,
	isRetryable,
	niceMax,
	parseCourseId,
	parseCourseReport,
	parseCourseStudent,
	parseDashboard,
	parseEarnings,
	parseTransaction,
	percentChange,
	rangeFromSearch,
	rangeToSearch,
	validateCustomRange,
	EMPTY_COURSE_QUERY,
	EMPTY_STUDENT_QUERY,
	EMPTY_TRANSACTION_QUERY,
} from './model';
import {
	courseReport,
	currency,
	dashboard,
	earnings,
	series,
	student,
	transaction,
} from './fixtures';

describe( 'percentChange', () => {
	it( 'computes the change and rounds to one decimal', () => {
		expect( percentChange( 150, 100 ) ).toBe( 50 );
		expect( percentChange( 50, 200 ) ).toBe( -75 );
		expect( percentChange( 1, 3 ) ).toBe( -66.7 );
	} );
	it( 'guards a zero or non-finite previous value', () => {
		expect( percentChange( 0, 0 ) ).toBe( 0 );
		expect( percentChange( 5, 0 ) ).toBeNull();
		expect( percentChange( Number.NaN, 5 ) ).toBeNull();
		expect( percentChange( 5, Number.POSITIVE_INFINITY ) ).toBeNull();
	} );
	it( 'describes changes in text and words', () => {
		expect( formatChange( 12.5 ) ).toBe( '+12.5%' );
		expect( formatChange( -3 ) ).toBe( '−3%' );
		expect( formatChange( 0 ) ).toBe( '0%' );
		expect( formatChange( null ) ).toBe( 'New' );
		expect( describeChange( 12.5 ) ).toBe(
			'Up 12.5% from the previous period.'
		);
		expect( describeChange( -3 ) ).toBe(
			'Down 3% from the previous period.'
		);
		expect( describeChange( 0 ) ).toBe(
			'No change from the previous period.'
		);
		expect( describeChange( null ) ).toContain( 'nothing' );
	} );
} );

describe( 'formatMoney', () => {
	it( 'uses Intl with the site currency and decimals', () => {
		expect( formatMoney( 1234.5, currency, 'en-US' ) ).toBe( '$1,234.50' );
		expect(
			formatMoney(
				1234,
				{ ...currency, code: 'JPY', decimals: 0 },
				'en-US'
			)
		).toContain( '1,234' );
	} );
	it( 'falls back to the symbol for an unknown currency code', () => {
		expect(
			formatMoney( 5, {
				code: 'not a code',
				symbol: '€',
				decimals: 2,
				position: 'right',
			} )
		).toBe( '5.00€' );
		expect(
			formatMoney( 5, {
				code: 'x',
				symbol: '€',
				decimals: 1,
				position: 'left',
			} )
		).toBe( '€5.0' );
	} );
} );

describe( 'date ranges', () => {
	it( 'checks calendar days strictly', () => {
		expect( isIsoDay( '2024-02-29' ) ).toBe( true );
		expect( isIsoDay( '2023-02-29' ) ).toBe( false );
		expect( isIsoDay( '2001-3-1' ) ).toBe( false );
		expect( isIsoDay( 'tomorrow' ) ).toBe( false );
	} );
	it( 'counts days inclusively, across leap days', () => {
		expect( daysBetween( '2001-03-01', '2001-03-31' ) ).toBe( 31 );
		expect( daysBetween( '2024-02-28', '2024-03-01' ) ).toBe( 3 );
		expect( daysBetween( '2026-10-30', '2026-10-30' ) ).toBe( 1 );
	} );
	it( 'validates custom ranges like the server', () => {
		expect( validateCustomRange( '2001-03-01', '2001-03-31' ) ).toBeNull();
		expect( validateCustomRange( '', '2001-03-31' ) ).toMatch( /both/ );
		expect( validateCustomRange( '2001-02-30', '2001-03-31' ) ).toMatch(
			/valid dates/
		);
		expect( validateCustomRange( '2001-03-31', '2001-03-01' ) ).toMatch(
			/after/
		);
		expect( validateCustomRange( '2001-01-01', '2002-12-31' ) ).toBeNull();
		expect( validateCustomRange( '2001-01-01', '2003-01-02' ) ).toContain(
			String( MAX_SPAN_DAYS )
		);
	} );
	it( 'reads and writes the range in the URL', () => {
		expect( rangeFromSearch( new URLSearchParams( '' ) ) ).toEqual(
			DEFAULT_RANGE
		);
		expect(
			rangeFromSearch( new URLSearchParams( 'range=last_7_days' ) )
		).toEqual( { preset: 'last_7_days', start: '', end: '' } );
		expect(
			rangeFromSearch( new URLSearchParams( 'range=bogus' ) )
		).toEqual( DEFAULT_RANGE );
		expect(
			rangeFromSearch(
				new URLSearchParams(
					'range=custom&start=2001-03-01&end=2001-03-31'
				)
			)
		).toEqual( {
			preset: 'custom',
			start: '2001-03-01',
			end: '2001-03-31',
		} );
		expect(
			rangeFromSearch(
				new URLSearchParams( 'range=custom&start=2001-03-01' )
			)
		).toEqual( DEFAULT_RANGE );
		expect( rangeToSearch( DEFAULT_RANGE ) ).toEqual( {} );
		expect(
			rangeToSearch( { preset: 'this_year', start: '', end: '' } )
		).toEqual( { range: 'this_year' } );
		expect(
			rangeToSearch( { preset: 'custom', start: 'a', end: 'b' } )
		).toEqual( { range: 'custom', start: 'a', end: 'b' } );
	} );
	it( 'builds request queries', () => {
		expect( buildRangeQuery( DEFAULT_RANGE ) ).toEqual( {
			range: 'last_30_days',
		} );
		expect(
			buildRangeQuery( { preset: 'custom', start: 'a', end: 'b' } )
		).toEqual( { range: 'custom', start: 'a', end: 'b' } );
		expect(
			buildTransactionQuery( DEFAULT_RANGE, {
				...EMPTY_TRANSACTION_QUERY,
				search: ' 77 ',
				payment_method: 'paypal',
			} )
		).toEqual( {
			range: 'last_30_days',
			page: 1,
			per_page: 20,
			orderby: 'date',
			order: 'desc',
			search: '77',
			payment_method: 'paypal',
		} );
		expect( buildCourseQuery( DEFAULT_RANGE, EMPTY_COURSE_QUERY ) ).toEqual(
			{
				range: 'last_30_days',
				page: 1,
				per_page: 20,
				orderby: 'net',
				order: 'desc',
			}
		);
		expect(
			buildStudentQuery( {
				...EMPTY_STUDENT_QUERY,
				progress: 'running',
				search: 'ada',
			} )
		).toEqual( {
			page: 1,
			per_page: 20,
			orderby: 'name',
			order: 'asc',
			search: 'ada',
			progress: 'running',
		} );
		expect( buildStudentQuery( EMPTY_STUDENT_QUERY ) ).not.toHaveProperty(
			'progress'
		);
	} );
	it( 'parses course ids from routes', () => {
		expect( parseCourseId( '41' ) ).toBe( 41 );
		expect( parseCourseId( '0' ) ).toBeNull();
		expect( parseCourseId( '4a' ) ).toBeNull();
		expect( parseCourseId( null ) ).toBeNull();
		expect( courseReportPath( 41 ) ).toBe( '/reports/courses/41' );
	} );
} );

describe( 'cards', () => {
	it( 'builds the five overview cards with guarded changes', () => {
		const cards = buildCards( dashboard.summary, currency );
		expect( cards.map( ( card ) => card.key ) ).toEqual( [
			'gross',
			'refunds',
			'net',
			'sales',
			'enrollments',
		] );
		expect( cards[ 0 ] ).toMatchObject( {
			value: formatMoney( 330, currency ),
			change: '+10%',
		} );
		expect( cards[ 1 ]?.change ).toBe( 'New' );
		expect( cards[ 2 ]?.change ).toBe( '−26.7%' );
		expect( cards[ 3 ]?.change ).toBe( 'New' );
		expect( cards[ 4 ]?.change ).toBe( '0%' );
	} );
	it( 'drops the student card for one course', () => {
		const cards = buildCourseCards(
			courseReport.summary.current,
			courseReport.summary.previous,
			currency
		);
		expect( cards.map( ( card ) => card.key ) ).toEqual( [
			'gross',
			'refunds',
			'net',
			'sales',
		] );
	} );
} );

describe( 'chart geometry', () => {
	it( 'rounds the axis maximum up to a clean value', () => {
		expect( niceMax( 0 ) ).toBe( 1 );
		expect( niceMax( -4 ) ).toBe( 1 );
		expect( niceMax( 250 ) ).toBe( 500 );
		expect( niceMax( 1000 ) ).toBe( 1000 );
		expect( niceMax( 1001 ) ).toBe( 2000 );
		expect( niceMax( 0.03 ) ).toBeCloseTo( 0.05 );
	} );
	it( 'stacks net and refunds inside the plot area', () => {
		const chart = buildChart( series );
		expect( chart.max ).toBe( 500 );
		expect( chart.bars ).toHaveLength( 3 );
		expect( chart.ticks ).toHaveLength( 5 );
		const first = chart.bars[ 0 ];
		expect( first ).toBeDefined();
		if ( first ) {
			const plot = chart.bottom - chart.top;
			expect( first.netHeight ).toBeCloseTo( ( 220 / 500 ) * plot );
			expect( first.refundHeight ).toBeCloseTo( ( 30 / 500 ) * plot );
			expect( first.refundY + first.refundHeight ).toBeCloseTo(
				first.netY
			);
			expect( first.netY + first.netHeight ).toBeCloseTo( chart.bottom );
			expect( first.x ).toBeGreaterThanOrEqual( chart.left );
		}
		const empty = chart.bars[ 1 ];
		expect( empty?.netHeight ).toBe( 0 );
		expect( buildChart( [] ).bars ).toEqual( [] );
	} );
	it( 'describes the series in words', () => {
		expect( describeSeries( series, currency, 'day' ) ).toContain(
			formatMoney( 330, currency )
		);
		expect( describeSeries( [], currency, 'day' ) ).toBe(
			'No earnings in this period.'
		);
	} );
} );

describe( 'response validation', () => {
	it( 'accepts the documented shapes and drops unknown members', () => {
		expect(
			parseDashboard( { ...dashboard, _links: {}, extension: 'x' } )
		).toEqual( dashboard );
		expect( parseEarnings( { ...earnings, _links: {} } ) ).toEqual(
			earnings
		);
		expect( parseTransaction( { ...transaction, x: 1 } ) ).toEqual(
			transaction
		);
		expect( parseCourseReport( courseReport ) ).toEqual( courseReport );
		expect( parseCourseStudent( student ) ).toEqual( student );
	} );
	it( 'rejects malformed data with a typed error', () => {
		const bad: unknown[] = [
			null,
			[],
			{ ...dashboard, series: 'no' },
			{ ...dashboard, currency: { ...currency, decimals: 9 } },
			{
				...dashboard,
				range: { ...dashboard.range, granularity: 'week' },
			},
			{
				...dashboard,
				summary: {
					...dashboard.summary,
					current: { ...dashboard.summary.current, gross: '5' },
				},
			},
			{ ...dashboard, series: [ { ...series[ 0 ], date: 'Monday' } ] },
		];
		for ( const value of bad ) {
			expect( () => parseDashboard( value ) ).toThrow( ApiError );
		}
		expect( () =>
			parseTransaction( { ...transaction, status: 'pending' } )
		).toThrow( ApiError );
		expect( () =>
			parseCourseStudent( { ...student, percent_complete: 101 } )
		).toThrow( ApiError );
		expect( () =>
			parseCourseStudent( { ...student, user_id: 0 } )
		).toThrow( ApiError );
	} );
} );

describe( 'errors', () => {
	it( 'explains permission and missing-course errors without retrying', () => {
		const forbidden = new ApiError( {
			status: 403,
			code: 'ohmylms_analytics_cannot_view',
			message: 'No',
		} );
		expect( describeError( forbidden ) ).toBe(
			'You do not have permission to view analytics.'
		);
		expect( isRetryable( forbidden ) ).toBe( false );
		const missing = new ApiError( {
			status: 404,
			code: 'ohmylms_analytics_course_not_found',
			message: 'Course not found.',
		} );
		expect( describeError( missing ) ).toBe(
			'That course does not exist.'
		);
		expect( isRetryable( missing ) ).toBe( false );
		const server = new ApiError( {
			status: 500,
			code: 'ohmylms_analytics_query_failed',
			message: 'The report could not be loaded.',
		} );
		expect( describeError( server ) ).toBe(
			'The report could not be loaded.'
		);
		expect( isRetryable( server ) ).toBe( true );
	} );
} );
