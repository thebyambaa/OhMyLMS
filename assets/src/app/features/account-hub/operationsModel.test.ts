import {
	guardianInvite,
	plan,
	removal,
	report,
	rolloverResult,
	sampleSchool,
} from './fixtures';
import {
	gradeMapError,
	panelFromParam,
	parseClassWork,
	parseGuardian,
	parsePlan,
	parseRemoval,
	parseReport,
	parseReportExport,
	parseRolloverResult,
	parseSample,
	parseSubmissions,
	suggestGradeMap,
} from './operationsModel';
import { rolloverLines } from './SchoolRollover';
import { consequenceLines } from './SchoolRemovalSection';

describe( 'school operation parsers', () => {
	it( 'round-trips valid server data', () => {
		expect( parseRemoval( removal ) ).toEqual( removal );
		expect( parseGuardian( guardianInvite ) ).toEqual( guardianInvite );
		expect( parseReport( report ) ).toEqual( report );
		expect( parsePlan( plan ) ).toEqual( plan );
		expect( parseRolloverResult( rolloverResult ) ).toEqual(
			rolloverResult
		);
		expect( parseSample( sampleSchool ) ).toEqual( sampleSchool );
	} );
	it( 'rejects data outside the contract', () => {
		expect( () =>
			parseRemoval( { ...removal, impact: { classes: -1 } } )
		).toThrow( /invalid account data/ );
		expect( () =>
			parseGuardian( { ...guardianInvite, kind: 'email' } )
		).toThrow();
		expect( () => parsePlan( { ...plan, review: 'short' } ) ).toThrow();
		expect( () =>
			parseReport( { ...report, period: { ...report.period, days: 14 } } )
		).toThrow();
		expect( () =>
			parseReportExport( {
				filename: '../x.csv',
				content_type: 'text/csv',
				csv: '',
			} )
		).toThrow();
		expect( () =>
			parseSubmissions( {
				items: [
					{
						id: 1,
						assignment_id: 2,
						title: 'x',
						student_name: 'y',
						content: '',
						score: null,
						status: 'submitted',
						note: '',
						total_points: 5,
						file_url: 'javascript:alert(1)',
					},
				],
				has_more: false,
			} )
		).toThrow();
		expect( () =>
			parseClassWork( {
				id: 1,
				title: 'x',
				course_id: 0,
				course_title: '',
				content_id: 0,
				content_title: null,
				due_at: null,
				created_at: null,
				recipients: 0,
				completed: 0,
			} )
		).toThrow();
	} );
	it( 'reads the school panel from the address', () => {
		expect( panelFromParam( 'report' ) ).toBe( 'report' );
		expect( panelFromParam( 'x' ) ).toBe( 'details' );
		expect( panelFromParam( null ) ).toBe( 'details' );
	} );
} );

describe( 'grade maps', () => {
	it( 'moves whole-number grades up and lets the highest leave', () => {
		expect( suggestGradeMap( [ '10', '7', '7', '12', 'K', '' ] ) ).toEqual(
			[
				{ from: '7', to: '8' },
				{ from: '10', to: '11' },
				{ from: '12', to: '' },
				{ from: 'K', to: 'K' },
			]
		);
		expect( suggestGradeMap( [ '5' ] ) ).toEqual( [
			{ from: '5', to: '6' },
		] );
	} );
	it( 'validates rows', () => {
		expect( gradeMapError( [ { from: '7', to: '8' } ] ) ).toBe( '' );
		expect(
			gradeMapError( [
				{ from: '7', to: '8' },
				{ from: '7', to: '' },
			] )
		).toMatch( /only once/ );
		expect( gradeMapError( [ { from: ' ', to: '8' } ] ) ).toMatch(
			/up to 50/
		);
	} );
} );

describe( 'sentences', () => {
	it( 'names every consequence of a deletion and keeps accounts', () => {
		const lines = consequenceLines( removal.impact );
		expect( lines ).toContain(
			'3 course enrolments granted by class rosters are cancelled. Enrolments bought or granted another way stay.'
		);
		expect( lines.join( ' ' ) ).not.toMatch( /open invitation/ );
		expect( lines.at( -2 ) ).toMatch( /No account is deleted/ );
	} );
	it( 'summarises a rollover', () => {
		expect( rolloverLines( plan.totals )[ 0 ] ).toBe(
			'1 classes are archived; 1 new classes are created in the new year.'
		);
	} );
} );
