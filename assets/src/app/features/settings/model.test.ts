import { ApiError } from '../../lib/api';
import {
	account,
	currency,
	fixtures,
	general,
	permalink,
	tax,
} from './fixtures';
import {
	buildPatch,
	changedFields,
	describeFailure,
	parseGroupResponse,
	parseValues,
	tabFromParam,
	toSwatch,
	validateGroup,
} from './model';
import { FIELD_NAMES, GROUP_IDS } from './types';

describe( 'parsing server responses', () => {
	it( 'accepts every group fixture and keeps exactly the registered fields', () => {
		for ( const id of GROUP_IDS ) {
			const parsed = parseGroupResponse( id, {
				group: id,
				values: fixtures[ id ],
				changed: [],
			} );
			expect( Object.keys( parsed.values ) ).toEqual( [
				...FIELD_NAMES[ id ],
			] );
		}
	} );

	it( 'rejects a wrong group, a missing field and a wrong kind', () => {
		expect( () =>
			parseGroupResponse( 'tax', {
				group: 'currency',
				values: tax,
				changed: [],
			} )
		).toThrow( ApiError );
		const { tax_label: _omitted, ...missing } = tax;
		expect( () => parseValues( 'tax', missing ) ).toThrow( ApiError );
		expect( () =>
			parseValues( 'tax', { ...tax, tax_enabled: 'yes' } )
		).toThrow( ApiError );
		expect( () =>
			parseValues( 'tax', { ...tax, tax_rates: [ { country: 'DE' } ] } )
		).toThrow( ApiError );
		expect( () => parseValues( 'general', null ) ).toThrow( ApiError );
		expect( () =>
			parseValues( 'currency', { ...currency, price_num_decimals: NaN } )
		).toThrow( ApiError );
	} );

	it( 'tolerates stored values outside the input limits so a tab never bricks', () => {
		expect(
			parseValues( 'tax', { ...tax, tax_label: '' } ).tax_label
		).toBe( '' );
	} );
} );

describe( 'validation mirrors the server', () => {
	it( 'accepts every fixture', () => {
		for ( const id of GROUP_IDS ) {
			expect( validateGroup( id, fixtures[ id ] ) ).toEqual( {} );
		}
	} );

	it( 'checks numbers, text lengths, patterns and enums', () => {
		expect(
			validateGroup( 'currency', {
				...currency,
				price_num_decimals: 9,
				price_decimal_sep: '',
				currency: 'usd',
				currency_pos: 'middle',
			} )
		).toEqual(
			expect.objectContaining( {
				price_num_decimals: expect.any( String ),
				price_decimal_sep: expect.any( String ),
				currency: expect.any( String ),
				currency_pos: expect.any( String ),
			} )
		);
		expect(
			validateGroup( 'currency', {
				...currency,
				price_num_decimals: NaN,
			} )
		).toHaveProperty( 'price_num_decimals' );
		expect(
			validateGroup( 'currency', {
				...currency,
				price_num_decimals: 1.5,
			} )
		).toHaveProperty( 'price_num_decimals' );
		expect(
			validateGroup( 'account-and-privacy', {
				...account,
				checkout_phone_field: 'maybe',
				privacy_policy_message: 'a'.repeat( 2001 ),
			} )
		).toEqual(
			expect.objectContaining( {
				checkout_phone_field: expect.any( String ),
				privacy_policy_message: expect.any( String ),
			} )
		);
		expect(
			validateGroup( 'tax', {
				...tax,
				fallback_tax_rate: 100.5,
				tax_label: '',
			} )
		).toEqual(
			expect.objectContaining( {
				fallback_tax_rate: expect.any( String ),
				tax_label: expect.any( String ),
			} )
		);
	} );

	it( 'allows a space as thousands separator and an empty one', () => {
		expect(
			validateGroup( 'currency', {
				...currency,
				price_thousand_sep: ' ',
			} )
		).toEqual( {} );
		expect(
			validateGroup( 'currency', { ...currency, price_thousand_sep: '' } )
		).toEqual( {} );
		expect(
			validateGroup( 'currency', {
				...currency,
				price_thousand_sep: '5',
			} )
		).toHaveProperty( 'price_thousand_sep' );
	} );

	it( 'requires different separators', () => {
		const errors = validateGroup( 'currency', {
			...currency,
			price_thousand_sep: '.',
			price_decimal_sep: '.',
		} );
		expect( errors ).toHaveProperty( 'price_decimal_sep' );
	} );

	it( 'requires distinct course, checkout and thank you pages', () => {
		const errors = validateGroup( 'general', {
			...general,
			thank_you_page_id: 12,
		} );
		expect( Object.keys( errors ).sort() ).toEqual( [
			'checkout_page_id',
			'thank_you_page_id',
		] );
		expect(
			validateGroup( 'general', { ...general, terms_page_id: 11 } )
		).toEqual( {} );
	} );

	it( 'validates colours and the logo URL', () => {
		const base = fixtures.branding;
		expect(
			validateGroup( 'branding', { ...base, primary_color: '#abc' } )
		).toEqual( {} );
		expect(
			validateGroup( 'branding', { ...base, heading_color: 'red' } )
		).toHaveProperty( 'heading_color' );
		expect(
			validateGroup( 'branding', {
				...base,
				video_player_logo: 'https://example.com/a.png',
			} )
		).toEqual( {} );
		for ( const bad of [
			'javascript:alert(1)',
			'ftp://x.test/a',
			'logo',
		] ) {
			expect(
				validateGroup( 'branding', { ...base, video_player_logo: bad } )
			).toHaveProperty( 'video_player_logo' );
		}
	} );

	it( 'checks permalink format, reserved bases and duplicates', () => {
		expect(
			validateGroup( 'permalink', {
				...permalink,
				course_base: 'learn/courses',
			} )
		).toEqual( {} );
		for ( const bad of [
			'Has Space',
			'/lead',
			'trail/',
			'a//b',
			'',
			'UP',
		] ) {
			expect(
				validateGroup( 'permalink', { ...permalink, tag_base: bad } )
			).toHaveProperty( 'tag_base' );
		}
		expect(
			validateGroup( 'permalink', {
				...permalink,
				course_base: 'wp-admin',
			} )
		).toHaveProperty( 'course_base' );
		expect(
			validateGroup( 'permalink', {
				...permalink,
				course_base: 'feed/courses',
			} )
		).toHaveProperty( 'course_base' );
		const duplicate = validateGroup( 'permalink', {
			...permalink,
			lesson_base: 'members',
		} );
		expect( Object.keys( duplicate ).sort() ).toEqual( [
			'lesson_base',
			'membership_base',
		] );
	} );

	it( 'checks tax rate rows', () => {
		const row = { country: 'DE', state: '', rate: 19, country_wide: true };
		const check = ( rates: unknown[] ) =>
			validateGroup( 'tax', { ...tax, tax_rates: rates as never } );
		expect( check( [ row ] ) ).toEqual( {} );
		expect( check( [ row, row ] ).tax_rates ).toMatch( /Row 2/ );
		expect( check( [ { ...row, country: '' } ] ).tax_rates ).toMatch(
			/Row 1/
		);
		expect( check( [ { ...row, state: 'BY' } ] ).tax_rates ).toBeDefined();
		expect(
			check( [ { ...row, country_wide: false, state: '' } ] ).tax_rates
		).toBeDefined();
		expect( check( [ { ...row, rate: 101 } ] ).tax_rates ).toBeDefined();
		expect( check( [ { ...row, rate: NaN } ] ).tax_rates ).toBeDefined();
		expect(
			check( [
				row,
				{ country: 'DE', state: 'BY', rate: 7, country_wide: false },
			] )
		).toEqual( {} );
	} );
} );

describe( 'drafts and patches', () => {
	it( 'reports only changed fields and sends nothing else', () => {
		const draft = { ...currency, price_num_decimals: 3 };
		expect( changedFields( currency, draft ) ).toEqual( [
			'price_num_decimals',
		] );
		expect( buildPatch( currency, draft ) ).toEqual( {
			price_num_decimals: 3,
		} );
		expect( buildPatch( currency, currency ) ).toEqual( {} );
	} );

	it( 'compares tax rate lists by content', () => {
		const rate = { country: 'DE', state: '', rate: 19, country_wide: true };
		const saved = { ...tax, tax_rates: [ rate ] };
		expect(
			changedFields( saved, { ...tax, tax_rates: [ { ...rate } ] } )
		).toEqual( [] );
		expect(
			changedFields( saved, {
				...tax,
				tax_rates: [ { ...rate, rate: 20 } ],
			} )
		).toEqual( [ 'tax_rates' ] );
		expect( changedFields( saved, { ...tax, tax_rates: [] } ) ).toEqual( [
			'tax_rates',
		] );
	} );
} );

describe( 'helpers', () => {
	it( 'normalises swatch colours', () => {
		expect( toSwatch( '#ABC' ) ).toBe( '#aabbcc' );
		expect( toSwatch( '#6E42D3' ) ).toBe( '#6e42d3' );
		expect( toSwatch( 'nope' ) ).toBe( '#000000' );
	} );

	it( 'reads the tab from the query parameter', () => {
		expect( tabFromParam( 'tax' ) ).toBe( 'tax' );
		expect( tabFromParam( 'account-and-privacy' ) ).toBe(
			'account-and-privacy'
		);
		expect( tabFromParam( 'payment-gateways' ) ).toBe( 'payment-gateways' );
		expect( tabFromParam( 'mcp' ) ).toBe( 'mcp' );
		expect( tabFromParam( 'bogus' ) ).toBe( 'general' );
		expect( tabFromParam( null ) ).toBe( 'general' );
	} );

	it( 'extracts per-field messages and permission state from API errors', () => {
		const invalid = new ApiError( {
			status: 422,
			code: 'ohmylms_settings_invalid_value',
			message: 'Some settings are not valid.',
			details: {
				status: 422,
				params: { terms_page_id: 'Pick a page', bad: 3 },
			},
		} );
		expect( describeFailure( invalid ) ).toEqual( {
			message: 'Some settings are not valid.',
			permission: false,
			fieldErrors: { terms_page_id: 'Pick a page' },
		} );
		const denied = new ApiError( {
			status: 403,
			code: 'ohmylms_settings_cannot_edit',
			message: 'No.',
		} );
		expect( describeFailure( denied ) ).toMatchObject( {
			permission: true,
			fieldErrors: {},
		} );
	} );
} );
