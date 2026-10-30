import { ApiError } from '../../lib/api';
import { reportRow } from './reportsFixtures';
import {
	DEFAULT_REPORT_QUERY,
	filterParams,
	paramsFromQuery,
	parseReportRow,
	parseReportSummary,
	passRateText,
	queryFromParams,
} from './reportsModel';

describe( 'parseReportRow', () => {
	it( 'accepts a full row and a row without attempts', () => {
		expect( parseReportRow( reportRow ) ).toEqual( reportRow );
		const empty = {
			...reportRow,
			course: null,
			attempts: 0,
			learners: 0,
			in_progress: 0,
			to_grade: 0,
			completed: 0,
			average_score: null,
			passing_score: 0,
			passed: null,
			pass_rate: null,
			last_submitted_at: null,
		};
		expect( parseReportRow( empty ) ).toEqual( empty );
	} );

	it.each( [
		[ 'unknown status', { status: 'trash' } ],
		[ 'more parts than attempts', { completed: 9 } ],
		[ 'more passes than completed attempts', { passed: 3 } ],
		[ 'pass rate above 100', { pass_rate: 101 } ],
		[ 'negative average', { average_score: -1 } ],
		[ 'fractional attempts', { attempts: 1.5 } ],
		[ 'bad date', { last_submitted_at: 'soon' } ],
		[ 'missing permission flag', { can_grade: undefined } ],
		[ 'course without id', { course: { title: 'x' } } ],
	] )( 'rejects %s', ( _name, change ) => {
		expect( () => parseReportRow( { ...reportRow, ...change } ) ).toThrow(
			ApiError
		);
	} );
} );

it( 'validates the summary', () => {
	const summary = {
		quizzes: 3,
		attempts: 5,
		learners: 3,
		to_grade: 1,
		completed: 3,
	};
	expect( parseReportSummary( summary ) ).toEqual( summary );
	expect( () => parseReportSummary( { ...summary, quizzes: -1 } ) ).toThrow(
		ApiError
	);
} );

describe( 'address state', () => {
	it( 'falls back to defaults for unknown values', () => {
		expect(
			queryFromParams(
				new URLSearchParams(
					'page=0&course=x&status=trash&activity=all&orderby=meta&order=up'
				)
			)
		).toEqual( DEFAULT_REPORT_QUERY );
	} );

	it( 'round-trips non-default values and drops defaults', () => {
		const query = {
			...DEFAULT_REPORT_QUERY,
			page: 2,
			search: 'frac',
			course: 5,
			activity: 'to_grade' as const,
			orderby: 'title' as const,
			order: 'asc' as const,
		};
		const params = paramsFromQuery( query );
		expect( params.toString() ).toBe(
			'page=2&search=frac&course=5&activity=to_grade&orderby=title&order=asc'
		);
		expect( queryFromParams( params ) ).toEqual( query );
		expect( paramsFromQuery( DEFAULT_REPORT_QUERY ).toString() ).toBe( '' );
	} );

	it( 'sends only set filters', () => {
		expect(
			filterParams( { ...DEFAULT_REPORT_QUERY, search: '  ' } )
		).toEqual( { status: 'all', activity: 'all' } );
		expect(
			filterParams( {
				...DEFAULT_REPORT_QUERY,
				search: ' a ',
				course: 5,
			} )
		).toEqual( { search: 'a', course: 5, status: 'all', activity: 'all' } );
	} );
} );

it( 'describes the pass rate', () => {
	expect( passRateText( parseReportRow( reportRow ) ) ).toBe(
		'50% (1 of 2)'
	);
	expect(
		passRateText(
			parseReportRow( { ...reportRow, passed: null, pass_rate: null } )
		)
	).toBe( '—' );
} );
