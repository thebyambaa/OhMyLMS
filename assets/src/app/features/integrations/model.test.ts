import { ApiError } from '../../lib/api';
import {
	authNotice,
	buildChanges,
	categoryLabel,
	categoryOptions,
	createDraft,
	fieldErrorsFrom,
	filterIntegrations,
	integrationId,
	isPermissionError,
	parseIntegration,
	parseSettings,
	replaceIntegration,
	validateDraft,
} from './model';
import {
	gamification,
	integrations,
	wpfusion,
	zoom,
	zoomSettings,
} from './fixtures';
import type { SettingsDraft } from './types';

describe( 'parsing server data', () => {
	it( 'accepts a complete integration and rejects wrong types', () => {
		expect( parseIntegration( zoom ) ).toEqual( zoom );
		for ( const broken of [
			null,
			[],
			{ ...zoom, enabled: 1 },
			{ ...zoom, categories: 'live-classes' },
			{ ...zoom, dependency: 5 },
			{ ...zoom, id: 'Bad Id' },
			{ ...zoom, requires_reload: undefined },
		] ) {
			expect( () => parseIntegration( broken ) ).toThrow( ApiError );
		}
	} );
	it( 'accepts text and secret fields and never invents a secret value', () => {
		const parsed = parseSettings( zoomSettings );
		expect( parsed.fields[ 2 ] ).toMatchObject( {
			kind: 'secret',
			configured: true,
		} );
		expect( parsed.fields[ 2 ] ).not.toHaveProperty( 'value' );
		expect( () =>
			parseSettings( {
				...zoomSettings,
				fields: [ { ...zoomSettings.fields[ 0 ], kind: 'number' } ],
			} )
		).toThrow( ApiError );
		expect( () =>
			parseSettings( {
				...zoomSettings,
				fields: [ zoomSettings.fields[ 0 ], zoomSettings.fields[ 0 ] ],
			} )
		).toThrow( ApiError );
		expect( () =>
			parseSettings( {
				...zoomSettings,
				fields: [ { ...zoomSettings.fields[ 0 ], max_length: 0 } ],
			} )
		).toThrow( ApiError );
	} );
	it( 'only lets safe ids into URLs', () => {
		expect( integrationId( 'google_signin' ) ).toBe( 'google_signin' );
		expect( () => integrationId( '../users' ) ).toThrow( ApiError );
		expect( () => integrationId( '' ) ).toThrow( ApiError );
	} );
} );

describe( 'list helpers', () => {
	it( 'filters by text and category and replaces items in place', () => {
		expect(
			filterIntegrations( integrations, {
				search: ' ZOOM ',
				category: 'all',
			} )
		).toEqual( [ zoom ] );
		expect(
			filterIntegrations( integrations, {
				search: 'points',
				category: 'all',
			} )
		).toEqual( [ gamification ] );
		expect(
			filterIntegrations( integrations, { search: '', category: 'crm' } )
		).toEqual( [ wpfusion ] );
		expect(
			filterIntegrations( integrations, {
				search: 'zoom',
				category: 'crm',
			} )
		).toEqual( [] );
		const next = { ...zoom, enabled: true };
		expect( replaceIntegration( integrations, next ) ).toEqual( [
			next,
			gamification,
			wpfusion,
		] );
	} );
	it( 'derives sorted category options and humanises unknown slugs', () => {
		const options = categoryOptions( integrations );
		expect( options[ 0 ] ).toEqual( { value: 'all', label: 'All' } );
		expect( options.map( ( option ) => option.label ).slice( 1 ) ).toEqual(
			[ 'Course engagement', 'CRM', 'Live classes' ]
		);
		expect( categoryLabel( 'my_new-thing' ) ).toBe( 'My new thing' );
		expect( categoryLabel( 'constructor' ) ).toBe( 'Constructor' );
	} );
} );

describe( 'settings form logic', () => {
	const draft = ( patch: SettingsDraft[ 'values' ] = {} ) => ( {
		...createDraft( zoomSettings ),
		values: { ...createDraft( zoomSettings ).values, ...patch },
	} );
	it( 'starts with stored text and empty secret inputs', () => {
		expect( createDraft( zoomSettings ).values ).toEqual( {
			account_id: 'acct-1',
			client_id: 'cid-1',
			client_secret: '',
			webhook_secret_token: '',
		} );
	} );
	it( 'sends nothing for an untouched form and omits untouched secrets', () => {
		expect(
			buildChanges( zoomSettings, createDraft( zoomSettings ) )
		).toEqual( {} );
		expect(
			buildChanges( zoomSettings, draft( { client_id: '  cid-2 ' } ) )
		).toEqual( { client_id: 'cid-2' } );
	} );
	it( 'replaces a secret only when one is typed and clears with null', () => {
		expect(
			buildChanges(
				zoomSettings,
				draft( { client_secret: 'new-secret' } )
			)
		).toEqual( { client_secret: 'new-secret' } );
		expect(
			buildChanges( zoomSettings, {
				...draft(),
				cleared: { webhook_secret_token: true },
			} )
		).toEqual( { webhook_secret_token: null } );
	} );
	it( 'validates required text, lengths and secret characters', () => {
		expect(
			validateDraft( zoomSettings, createDraft( zoomSettings ) )
		).toEqual( {} );
		const errors = validateDraft(
			zoomSettings,
			draft( {
				account_id: '   ',
				client_id: 'x'.repeat( 129 ),
				client_secret: 'has space',
				webhook_secret_token: 'é',
			} )
		);
		expect( Object.keys( errors ).sort() ).toEqual( [
			'account_id',
			'client_id',
			'client_secret',
			'webhook_secret_token',
		] );
	} );
	it( 'requires a first-time secret and forbids removing a required one', () => {
		const unconfigured = {
			...zoomSettings,
			fields: zoomSettings.fields.map( ( field ) =>
				'secret' === field.kind
					? { ...field, configured: false }
					: field
			),
		};
		expect(
			validateDraft( unconfigured, createDraft( unconfigured ) )
		).toHaveProperty( 'client_secret' );
		expect(
			validateDraft( zoomSettings, {
				...draft(),
				cleared: { client_secret: true },
			} )
		).toHaveProperty( 'client_secret' );
		expect(
			validateDraft( zoomSettings, {
				...draft(),
				cleared: { webhook_secret_token: true },
			} )
		).toEqual( {} );
	} );
} );

describe( 'error helpers', () => {
	it( 'reads field messages from a 422 and ignores malformed details', () => {
		const make = ( details: unknown ) =>
			new ApiError( { status: 422, code: 'x', message: 'm', details } );
		expect(
			fieldErrorsFrom(
				make( { params: { client_id: 'Required', n: 5 } } )
			)
		).toEqual( { client_id: 'Required' } );
		expect( fieldErrorsFrom( make( { params: [ 'a' ] } ) ) ).toEqual( {} );
		expect( fieldErrorsFrom( make( undefined ) ) ).toEqual( {} );
	} );
	it( 'treats 401 and 403 as permission errors', () => {
		const make = ( status: number ) =>
			new ApiError( { status, code: 'x', message: 'm' } );
		expect( isPermissionError( make( 401 ) ) ).toBe( true );
		expect( isPermissionError( make( 403 ) ) ).toBe( true );
		expect( isPermissionError( make( 500 ) ) ).toBe( false );
	} );
} );

describe( 'authNotice', () => {
	it( 'describes the Google Meet OAuth callback result', () => {
		expect( authNotice( 'success', null ) ).toEqual( {
			status: 'success',
			text: 'Google Meet is connected.',
		} );
		expect( authNotice( 'error', 'oauth_failed' ) ).toEqual( {
			status: 'error',
			text: 'Google Meet could not be connected. Google could not be reached. Try again.',
		} );
		expect( authNotice( 'error', 'no_access_token' )?.text ).toContain(
			'access token'
		);
		expect( authNotice( 'error', 'toString' )?.text ).toBe(
			'Google Meet could not be connected. Try connecting again.'
		);
		expect( authNotice( 'error', null )?.status ).toBe( 'error' );
	} );
	it( 'ignores other values', () => {
		expect( authNotice( null, null ) ).toBeNull();
		expect( authNotice( 'maybe', 'oauth_failed' ) ).toBeNull();
	} );
} );
