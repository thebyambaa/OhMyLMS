import { ApiError } from '../../lib/api';
import {
	children,
	invitation,
	member,
	parentAccount,
	person,
	school,
	schoolClass,
	teacher,
} from './fixtures';
import {
	confirmed,
	isCalendarDate,
	isConflict,
	parseChildren,
	parseClass,
	parseInvitation,
	parseMember,
	parseParent,
	parsePerson,
	parseSchool,
	parseSchoolDetail,
	parseTeacher,
	positiveId,
	tabFromParam,
	validateClass,
	validateSchool,
	validateYear,
} from './model';

const invalid = expect.objectContaining( { code: 'invalid_response' } );

describe( 'account hub boundary parsers', () => {
	it( 'accept every documented shape unchanged', () => {
		expect( parseSchoolDetail( school ) ).toEqual( school );
		const { years, ...row } = school;
		expect( years ).toHaveLength( 1 );
		expect( parseSchool( row ) ).toEqual( row );
		expect( parseClass( schoolClass ) ).toEqual( schoolClass );
		expect( parseMember( member ) ).toEqual( member );
		expect( parsePerson( person ) ).toEqual( person );
		expect( parseTeacher( teacher ) ).toEqual( teacher );
		expect( parseParent( parentAccount ) ).toEqual( parentAccount );
		expect( parseChildren( children ) ).toEqual( children );
		expect( parseInvitation( invitation ) ).toEqual( invitation );
	} );
	it( 'accept independent classes and hidden emails', () => {
		expect(
			parseClass( {
				...schoolClass,
				school_id: 0,
				school_name: null,
				academic_year_id: 0,
				academic_year_label: null,
			} ).school_name
		).toBeNull();
		expect( parseTeacher( { ...teacher, email: null } ).email ).toBeNull();
	} );
	it( 'reject malformed data', () => {
		expect( () => parseSchool( { ...school, id: 0 } ) ).toThrowError(
			invalid
		);
		expect( () =>
			parseSchool( { ...school, version: 'not-a-version' } )
		).toThrowError( invalid );
		expect( () =>
			parseSchoolDetail( { ...school, years: [ { id: 1 } ] } )
		).toThrowError( invalid );
		expect( () =>
			parseClass( { ...schoolClass, status: 'deleted' } )
		).toThrowError( invalid );
		expect( () =>
			parseMember( { ...member, role: 'admin' } )
		).toThrowError( invalid );
		expect( () =>
			parseTeacher( { ...teacher, schools: 'x' } )
		).toThrowError( invalid );
		expect( () =>
			parseParent( { ...parentAccount, child_count: -1 } )
		).toThrowError( invalid );
		expect( () => parseChildren( { children: [] } ) ).toThrowError(
			invalid
		);
		expect( () =>
			parseInvitation( { ...invitation, url: 'javascript:alert(1)' } )
		).toThrowError( invalid );
		expect( () => parsePerson( [] ) ).toThrowError( invalid );
		expect( () => positiveId( 1.5 ) ).toThrowError( invalid );
	} );
	it( 'requires confirmation flags', () => {
		expect( () => confirmed( { removed: true }, 'removed' ) ).not.toThrow();
		expect( () => confirmed( { removed: false }, 'removed' ) ).toThrowError(
			invalid
		);
	} );
} );

describe( 'account hub helpers', () => {
	it( 'reads the tab parameter', () => {
		expect( tabFromParam( 'parents' ) ).toBe( 'parents' );
		expect( tabFromParam( 'students' ) ).toBe( 'schools' );
		expect( tabFromParam( null ) ).toBe( 'schools' );
	} );
	it( 'validates school forms', () => {
		expect( validateSchool( { name: 'A', timezone: 'UTC' } ) ).toEqual(
			{}
		);
		const errors = validateSchool( { name: '  ', timezone: 'Asia /X' } );
		expect( errors.name ).toBeTruthy();
		expect( errors.timezone ).toBeTruthy();
		expect(
			validateSchool( { name: 'x'.repeat( 191 ), timezone: 'UTC' } ).name
		).toBeTruthy();
	} );
	it( 'validates class forms', () => {
		expect(
			validateClass( { name: 'Math', subject: '', grade: '' } )
		).toEqual( {} );
		const errors = validateClass( {
			name: '',
			subject: 'x'.repeat( 101 ),
			grade: 'x'.repeat( 51 ),
		} );
		expect( Object.keys( errors ).sort() ).toEqual( [
			'grade',
			'name',
			'subject',
		] );
	} );
	it( 'validates academic years with real dates in order', () => {
		expect(
			validateYear( {
				label: '2026',
				starts_at: '2026-09-01',
				ends_at: '2027-06-30',
			} )
		).toEqual( {} );
		expect( isCalendarDate( '2026-02-30' ) ).toBe( false );
		expect(
			validateYear( {
				label: '',
				starts_at: '2026-02-30',
				ends_at: '2026-01-01',
			} )
		).toEqual( {
			label: expect.any( String ),
			starts_at: expect.any( String ),
		} );
		expect(
			validateYear( {
				label: 'x',
				starts_at: '2026-09-01',
				ends_at: '2026-09-01',
			} ).ends_at
		).toBeTruthy();
	} );
	it( 'recognises only the stale-save conflict', () => {
		expect(
			isConflict(
				new ApiError( {
					status: 409,
					code: 'ohmylms_account_hub_conflict',
					message: 'x',
				} )
			)
		).toBe( true );
		expect(
			isConflict(
				new ApiError( {
					status: 409,
					code: 'ohmylms_account_hub_already_member',
					message: 'x',
				} )
			)
		).toBe( false );
		expect( isConflict( new Error( 'x' ) ) ).toBe( false );
	} );
} );
