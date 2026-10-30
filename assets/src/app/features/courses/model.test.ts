import { ApiError } from '../../lib/api';
import contract from './contract.json';
import { detail, options, summary } from './fixtures';
import {
	durationLabel,
	FIELD_ERRORS,
	formatPrice,
	formFromCourse,
	isCalendarDate,
	isDirty,
	isValid,
	levelLabel,
	parseCourse,
	parseCourseDetail,
	parseLocalDateTime,
	parseMedia,
	parseOptions,
	serverFieldError,
	statusLabel,
	toInput,
	validateForm,
} from './model';
import type { FormState } from './types';

const base = formFromCourse( detail );
const NOW = Date.UTC( 2026, 9, 30, 12, 0 );

function form( changes: Partial< FormState > ): FormState {
	return { ...base, ...changes };
}

describe( 'parsers', () => {
	it( 'accept the contract shape', () => {
		expect( parseCourse( summary ) ).toEqual( summary );
		expect( parseCourseDetail( detail ) ).toEqual( detail );
		expect( parseOptions( options ) ).toEqual( options );
	} );
	it( 'ignore fields added by extensions', () => {
		expect(
			parseCourse( {
				...summary,
				has_community: 'no',
				extension_settings: {},
			} )
		).toEqual( summary );
	} );
	it.each( [
		[ 'null', null ],
		[ 'array', [] ],
		[ 'bad id', { ...summary, id: '7' } ],
		[ 'zero id', { ...summary, id: 0 } ],
		[ 'unknown status', { ...summary, status: 'inherit' } ],
		[ 'unknown level', { ...summary, level: 'godlike' } ],
		[ 'numeric price', { ...summary, regular_price: 49.5 } ],
		[ 'bad date', { ...summary, sale_start_date: '2026-02-30' } ],
		[
			'bad duration',
			{ ...summary, duration: { hours: 'x', minutes: 1 } },
		],
		[ 'negative students', { ...summary, enrolled_count: -1 } ],
		[ 'zero capacity', { ...summary, capacity: 0 } ],
		[ 'missing flag', { ...summary, has_password: undefined } ],
	] )( 'reject %s', ( _name, value ) => {
		expect( () => parseCourse( value ) ).toThrowError(
			expect.objectContaining( { code: 'invalid_response', status: 502 } )
		);
	} );
	it( 'require the edit-only text fields for details', () => {
		expect( () =>
			parseCourseDetail( { ...summary, description: 'x' } )
		).toThrowError(
			expect.objectContaining( { code: 'invalid_response' } )
		);
	} );
	it( 'reject incomplete options', () => {
		expect( () => parseOptions( { ...options, statuses: [] } ) ).toThrow();
		expect( () =>
			parseOptions( {
				...options,
				limits: { ...options.limits, max_price: '1' },
			} )
		).toThrow();
		expect( () =>
			parseOptions( { ...options, abilities: { publish: true } } )
		).toThrow();
	} );
	it( 'reads media items and falls back to the full image', () => {
		expect(
			parseMedia( {
				id: 3,
				source_url: 'https://example.test/a.png',
				title: { rendered: 'A' },
				media_details: {
					sizes: {
						thumbnail: {
							source_url: 'https://example.test/a-150.png',
						},
					},
				},
			} )
		).toEqual( {
			id: 3,
			url: 'https://example.test/a.png',
			thumbnail_url: 'https://example.test/a-150.png',
			title: 'A',
		} );
		expect(
			parseMedia( { id: 4, source_url: 'https://example.test/b.png' } )
				.thumbnail_url
		).toBe( 'https://example.test/b.png' );
		expect( () => parseMedia( { id: 4 } ) ).toThrow();
	} );
} );

describe( 'dates', () => {
	it( 'checks real calendar dates', () => {
		expect( isCalendarDate( '2026-02-28' ) ).toBe( true );
		expect( isCalendarDate( '2024-02-29' ) ).toBe( true );
		expect( isCalendarDate( '2026-02-29' ) ).toBe( false );
		expect( isCalendarDate( '2026-13-01' ) ).toBe( false );
		expect( isCalendarDate( '26-01-01' ) ).toBe( false );
	} );
	it( 'parses local date times', () => {
		expect( parseLocalDateTime( '2026-11-02T10:30' ) ).toBe(
			new Date( 2026, 10, 2, 10, 30 ).getTime()
		);
		expect( parseLocalDateTime( '2026-11-02T24:00' ) ).toBeNull();
		expect( parseLocalDateTime( '2026-02-30T10:00' ) ).toBeNull();
		expect( parseLocalDateTime( '' ) ).toBeNull();
	} );
} );

describe( 'form state', () => {
	it( 'is built from a course with strings for numbers', () => {
		expect( base ).toMatchObject( {
			title: 'Algebra I',
			status: 'publish',
			regular_price: '49.50',
			sale_price: '29.00',
			duration_hours: '3',
			duration_minutes: '30',
			capacity: '30',
			has_capacity: true,
			thumbnail: { id: 21, url: 'https://example.test/thumb.png' },
		} );
	} );
	it( 'uses empty strings for missing values and maps the trash to a draft', () => {
		const state = formFromCourse( {
			...detail,
			status: 'trash',
			sale_price: null,
			duration: null,
			capacity: null,
			has_capacity: false,
			thumbnail_id: null,
			scheduled_at: '2026-12-01T10:00:00',
		} );
		expect( state ).toMatchObject( {
			status: 'draft',
			sale_price: '',
			duration_hours: '',
			capacity: '',
			thumbnail: null,
			scheduled_at: '2026-12-01T10:00',
		} );
	} );
	it( 'detects unsaved changes', () => {
		expect( isDirty( base, formFromCourse( detail ) ) ).toBe( false );
		expect( isDirty( form( { title: 'x' } ), base ) ).toBe( true );
	} );
} );

describe( 'validateForm', () => {
	it( 'accepts the saved course', () => {
		expect( validateForm( base, options, NOW ) ).toEqual( {} );
		expect( isValid( {} ) ).toBe( true );
	} );
	it( 'requires a bounded title and a usable slug', () => {
		expect(
			validateForm( form( { title: '  ' } ), options, NOW ).title
		).toMatch( /1 to 200/ );
		expect(
			validateForm( form( { title: 'a'.repeat( 201 ) } ), options, NOW )
				.title
		).toBeDefined();
		expect(
			validateForm( form( { slug: '!!!' } ), options, NOW ).slug
		).toBeDefined();
		expect(
			validateForm( form( { slug: '' } ), options, NOW ).slug
		).toBeUndefined();
	} );
	it( 'limits the long text fields', () => {
		const errors = validateForm(
			form( { requirements: 'x'.repeat( 5001 ) } ),
			options,
			NOW
		);
		expect( errors.requirements ).toMatch( /5000/ );
	} );
	it( 'checks prices of paid courses', () => {
		expect(
			validateForm( form( { regular_price: '' } ), options, NOW )
				.regular_price
		).toMatch( /decimal/ );
		expect(
			validateForm( form( { regular_price: '9.999' } ), options, NOW )
				.regular_price
		).toBeDefined();
		expect(
			validateForm( form( { regular_price: '-1' } ), options, NOW )
				.regular_price
		).toBeDefined();
		expect(
			validateForm( form( { regular_price: '0' } ), options, NOW )
				.regular_price
		).toMatch( /above zero/ );
		expect(
			validateForm( form( { regular_price: '100000000' } ), options, NOW )
				.regular_price
		).toBeDefined();
		expect(
			validateForm( form( { sale_price: '60' } ), options, NOW )
				.sale_price
		).toMatch( /higher/ );
		expect(
			validateForm( form( { sale_price: 'abc' } ), options, NOW )
				.sale_price
		).toBeDefined();
	} );
	it( 'ignores price fields of free courses', () => {
		expect(
			validateForm(
				form( {
					price_type: 'free',
					regular_price: 'junk',
					sale_price: 'junk',
				} ),
				options,
				NOW
			)
		).toEqual( {} );
	} );
	it( 'checks sale dates', () => {
		expect(
			validateForm(
				form( { sale_start_date: '2026-02-30' } ),
				options,
				NOW
			).sale_start_date
		).toBeDefined();
		expect(
			validateForm(
				form( {
					sale_start_date: '2026-12-01',
					sale_end_date: '2026-11-01',
				} ),
				options,
				NOW
			).sale_end_date
		).toMatch( /on or after/ );
		expect(
			validateForm( form( { sale_end_date: '' } ), options, NOW )
				.sale_end_date
		).toMatch( /both/ );
		expect(
			validateForm(
				form( {
					sale_price: '',
					sale_start_date: '2026-01-01',
					sale_end_date: '',
				} ),
				options,
				NOW
			).sale_price
		).toMatch( /sale price/ );
	} );
	it( 'checks the available date and duration', () => {
		const errors = validateForm(
			form( {
				available_date: '2026-02-30',
				duration_hours: 'x',
				duration_minutes: '75',
			} ),
			options,
			NOW
		);
		expect( errors.available_date ).toBeDefined();
		expect( errors.duration_hours ).toBeDefined();
		expect( errors.duration_minutes ).toBeDefined();
	} );
	it( 'requires a capacity when enrolment is limited', () => {
		expect(
			validateForm( form( { capacity: '' } ), options, NOW ).capacity
		).toBeDefined();
		expect(
			validateForm( form( { capacity: '0' } ), options, NOW ).capacity
		).toBeDefined();
		expect(
			validateForm( form( { capacity: '1.5' } ), options, NOW ).capacity
		).toBeDefined();
		expect(
			validateForm(
				form( { has_capacity: false, capacity: '' } ),
				options,
				NOW
			)
		).toEqual( {} );
	} );
	it( 'requires a password for protected courses and forbids private ones', () => {
		expect(
			validateForm(
				form( { access_type: 'password_protected', password: '' } ),
				options,
				NOW
			).password
		).toBeDefined();
		expect(
			validateForm(
				form( {
					access_type: 'password_protected',
					password: 'x'.repeat( 101 ),
				} ),
				options,
				NOW
			).password
		).toBeDefined();
		expect(
			validateForm(
				form( {
					access_type: 'password_protected',
					password: 'pw',
					status: 'private',
				} ),
				options,
				NOW
			).access_type
		).toBeDefined();
	} );
	it( 'requires a future time for scheduled courses', () => {
		const at = ( value: string ) =>
			validateForm(
				form( { status: 'future', scheduled_at: value } ),
				options,
				NOW
			).scheduled_at;
		expect( at( '' ) ).toBeDefined();
		expect( at( '2020-01-01T10:00' ) ).toBeDefined();
		expect( at( '2099-01-01T10:00' ) ).toBeUndefined();
	} );
} );

describe( 'toInput', () => {
	it( 'builds the typed payload with fixed decimals', () => {
		expect(
			toInput( form( { regular_price: '49.5', sale_price: '29' } ), 2 )
		).toEqual( {
			title: 'Algebra I',
			slug: 'algebra-i',
			status: 'publish',
			description: '<p>Learn algebra.</p>',
			excerpt: 'A short course.',
			level: 'beginner',
			price_type: 'paid',
			regular_price: '49.50',
			sale_price: '29.00',
			sale_start_date: '2026-11-01',
			sale_end_date: '2026-11-30',
			access_type: 'public',
			available_date: null,
			duration: { hours: 3, minutes: 30 },
			has_capacity: true,
			capacity: 30,
			enable_reviews: true,
			learning_outcomes: 'Solve equations',
			target_audience: 'Beginners',
			requirements: 'None',
			thumbnail_id: 21,
		} );
	} );
	it( 'drops sale and capacity data that does not apply', () => {
		const input = toInput(
			form( {
				price_type: 'free',
				has_capacity: false,
				thumbnail: null,
			} ),
			2
		);
		expect( input ).toMatchObject( {
			regular_price: '0.00',
			sale_price: null,
			sale_start_date: null,
			sale_end_date: null,
			capacity: null,
			thumbnail_id: null,
		} );
	} );
	it( 'sends the password only for protected courses and the schedule only when scheduled', () => {
		const open = toInput( form( { password: 'left over' } ), 2 );
		expect( open ).not.toHaveProperty( 'password' );
		expect( open ).not.toHaveProperty( 'scheduled_at' );
		const locked = toInput(
			form( {
				access_type: 'password_protected',
				password: 'pw',
				status: 'future',
				scheduled_at: '2099-01-01T10:00',
			} ),
			2
		);
		expect( locked.password ).toBe( 'pw' );
		expect( locked.scheduled_at ).toBe( '2099-01-01T10:00' );
	} );
	it( 'sends no duration when both parts are empty and trims text', () => {
		const input = toInput(
			form( {
				duration_hours: '',
				duration_minutes: '',
				title: '  Hi  ',
			} ),
			2
		);
		expect( input.duration ).toBeNull();
		expect( input.title ).toBe( 'Hi' );
	} );
	it( 'does not modify the form', () => {
		const copy = JSON.stringify( base );
		toInput( base, 2 );
		expect( JSON.stringify( base ) ).toBe( copy );
	} );
} );

describe( 'serverFieldError', () => {
	it( 'maps every field error of the contract', () => {
		expect( FIELD_ERRORS ).toEqual( contract.fieldErrors );
		for ( const [ code, path ] of Object.entries( contract.fieldErrors ) ) {
			expect(
				serverFieldError(
					new ApiError( { code, status: 422, message: 'm' } )
				)
			).toEqual( { path, message: 'm' } );
		}
	} );
	it( 'uses the field named by the server for prices and texts', () => {
		expect(
			serverFieldError(
				new ApiError( {
					code: 'ohmylms_course_invalid_price',
					status: 422,
					message: 'm',
					details: { field: 'sale_price' },
				} )
			)
		).toEqual( { path: 'sale_price', message: 'm' } );
		expect(
			serverFieldError(
				new ApiError( {
					code: 'ohmylms_course_invalid_price',
					status: 422,
					message: 'm',
				} )
			)?.path
		).toBe( 'regular_price' );
	} );
	it( 'returns null for other errors', () => {
		expect(
			serverFieldError(
				new ApiError( { code: 'x', status: 500, message: 'm' } )
			)
		).toBeNull();
	} );
	it( 'lists every 422 code of the contract', () => {
		const fieldCodes = Object.keys( contract.fieldErrors );
		for ( const code of contract.errorCodes[ '422' ] ) {
			if (
				! [
					'ohmylms_course_invalid_price',
					'ohmylms_course_invalid_text',
				].includes( code )
			) {
				expect( fieldCodes ).toContain( code );
			}
		}
	} );
} );

describe( 'labels', () => {
	it( 'formats money with the currency', () => {
		expect( formatPrice( '29.5', 'USD', 'en-US' ) ).toBe( '$29.50' );
		expect( formatPrice( '5', 'NOT-A-CODE', 'en-US' ) ).toBe(
			'5 NOT-A-CODE'
		);
	} );
	it( 'names statuses and levels', () => {
		expect( statusLabel( 'publish' ) ).toBe( 'Published' );
		expect( statusLabel( 'trash' ) ).toBe( 'Trashed' );
		expect( statusLabel( 'draft' ) ).toBe( 'Draft' );
		expect( levelLabel( 'experience' ) ).toBe( 'Intermediate' );
		expect( levelLabel( 'all' ) ).toBe( 'All levels' );
	} );
	it( 'describes durations', () => {
		expect( durationLabel( { hours: 3, minutes: 30 } ) ).toBe(
			'3 h 30 min'
		);
		expect( durationLabel( { hours: 0, minutes: 5 } ) ).toBe( '5 min' );
		expect( durationLabel( null ) ).toBe( '' );
	} );
} );
