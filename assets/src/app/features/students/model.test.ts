import { completedEnrollment, courses, enrollment, student } from './fixtures';
import {
	EMPTY_QUERY,
	buildEnrollmentQuery,
	buildStudentQuery,
	enrollmentStatus,
	formatDate,
	getSortChoices,
	parseCourseOption,
	parseEnrollment,
	parseStudent,
	parseStudentId,
	parseUnenrolled,
	sortValue,
	studentPath,
	validateEnrollment,
} from './model';

describe( 'student boundary', () => {
	it( 'keeps exactly the screen fields and drops everything else', () => {
		expect(
			parseStudent( {
				...student,
				_links: { self: [] },
				user_pass: '$P$hash',
				phone: '123',
			} )
		).toEqual( student );
	} );
	it.each( [
		[ 'a string id', { id: '12' } ],
		[ 'a zero id', { id: 0 } ],
		[ 'a fractional count', { enrolled_courses: 1.5 } ],
		[ 'a negative count', { completed_courses: -1 } ],
		[ 'a non-boolean flag', { blocked: 'no' } ],
		[ 'a missing email', { email: undefined } ],
		[ 'a non-string date', { registered: 5 } ],
	] )( 'rejects %s', ( _label, patch ) => {
		expect( () => parseStudent( { ...student, ...patch } ) ).toThrow();
	} );
	it( 'accepts null dates and rejects non-objects', () => {
		expect(
			parseStudent( { ...student, registered: null, last_login: null } )
		).toMatchObject( { registered: null, last_login: null } );
		expect( () => parseStudent( null ) ).toThrow();
		expect( () => parseStudent( [ student ] ) ).toThrow();
	} );
	it( 'validates enrolments, their enums and percentages', () => {
		expect( parseEnrollment( enrollment ) ).toEqual( enrollment );
		expect( () =>
			parseEnrollment( { ...enrollment, status: 'cancelled' } )
		).toThrow();
		expect( () =>
			parseEnrollment( { ...enrollment, progress: 'paused' } )
		).toThrow();
		expect( () =>
			parseEnrollment( { ...enrollment, percent_complete: 101 } )
		).toThrow();
		expect( () =>
			parseEnrollment( { ...enrollment, lessons: { completed: 1 } } )
		).toThrow();
		expect( parseCourseOption( courses[ 0 ] ) ).toEqual( courses[ 0 ] );
		expect( () => parseCourseOption( { id: 1 } ) ).toThrow();
	} );
	it( 'requires deleted: true when unenrolling', () => {
		expect(
			parseUnenrolled( {
				deleted: true,
				previous: completedEnrollment,
			} )
		).toEqual( completedEnrollment );
		expect( () =>
			parseUnenrolled( { deleted: false, previous: enrollment } )
		).toThrow();
		expect( () => parseUnenrolled( { deleted: true } ) ).toThrow();
	} );
} );

describe( 'queries', () => {
	it( 'omits blank search and the all-courses filter', () => {
		expect( buildStudentQuery( { ...EMPTY_QUERY, search: '  ' } ) ).toEqual(
			{
				page: 1,
				per_page: 20,
				orderby: 'name',
				order: 'asc',
			}
		);
	} );
	it( 'sends trimmed search, course and order', () => {
		expect(
			buildStudentQuery( {
				page: 3,
				search: ' ada ',
				course: 40,
				orderby: 'registered',
				order: 'desc',
			} )
		).toEqual( {
			page: 3,
			per_page: 20,
			orderby: 'registered',
			order: 'desc',
			search: 'ada',
			course: 40,
		} );
	} );
	it( 'only sends a progress filter when one is chosen', () => {
		expect(
			buildEnrollmentQuery( { page: 1, search: '', progress: 'all' } )
		).toEqual( { page: 1, per_page: 20 } );
		expect(
			buildEnrollmentQuery( {
				page: 2,
				search: 'alg',
				progress: 'completed',
			} )
		).toEqual( {
			page: 2,
			per_page: 20,
			search: 'alg',
			progress: 'completed',
		} );
	} );
	it( 'offers sort choices that round trip through the select value', () => {
		const choices = getSortChoices();
		expect( new Set( choices.map( ( c ) => c.value ) ).size ).toBe(
			choices.length
		);
		for ( const choice of choices ) {
			expect(
				sortValue( {
					...EMPTY_QUERY,
					orderby: choice.orderby,
					order: choice.order,
				} )
			).toBe( choice.value );
		}
	} );
} );

describe( 'routing and formatting', () => {
	it( 'accepts only plain positive integer ids', () => {
		expect( parseStudentId( '12' ) ).toBe( 12 );
		for ( const bad of [
			'',
			'0',
			'-3',
			'1.5',
			'12abc',
			'012',
			' 4',
			null,
			undefined,
		] ) {
			expect( parseStudentId( bad ) ).toBeNull();
		}
		expect( parseStudentId( '9'.repeat( 30 ) ) ).toBeNull();
	} );
	it( 'builds the profile route', () => {
		expect( studentPath( 12 ) ).toBe( '/students?student=12' );
	} );
	it( 'formats dates and tolerates null or garbage', () => {
		expect( formatDate( null ) ).toBe( '' );
		expect( formatDate( 'not a date' ) ).toBe( '' );
		expect( formatDate( '2026-03-01T09:00:00' ) ).toMatch( /2026/ );
	} );
	it( 'requires a course to enrol', () => {
		expect( validateEnrollment( null ) ).toMatch( /Choose a course/ );
		expect( validateEnrollment( 0 ) ).not.toBeNull();
		expect( validateEnrollment( 40 ) ).toBeNull();
	} );
} );

describe( 'enrolment status label', () => {
	it( 'puts blocked access before learning state', () => {
		expect( enrollmentStatus( enrollment ) ).toBe( 'In progress' );
		expect( enrollmentStatus( completedEnrollment ) ).toBe( 'Completed' );
		expect(
			enrollmentStatus( { ...completedEnrollment, status: 'banned' } )
		).toBe( 'Blocked' );
	} );
} );
