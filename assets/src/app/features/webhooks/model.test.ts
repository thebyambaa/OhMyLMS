import { cleanInput, parseOptions, parseWebhook, validateInput } from './model';
import { options, webhook } from './fixtures';

describe( 'webhook boundary', () => {
	it( 'validates response fields and strips secrets from list records', () => {
		expect(
			parseWebhook( { ...webhook, secret: 'never-store-in-list' } )
		).toEqual( webhook );
		expect( () => parseWebhook( { ...webhook, id: '7' } ) ).toThrow();
		expect( () =>
			parseWebhook( { ...webhook, data_mapping: [ null ] } )
		).toThrow();
		expect( () =>
			parseWebhook( { ...webhook, status: 'unknown' } )
		).toThrow();
		expect( () => parseOptions( { ...options, methods: [] } ) ).toThrow();
		expect( parseOptions( { ...options, triggers: [] } ).triggers ).toEqual(
			[]
		);
	} );
	it( 'trims writable fields, preserving mapping input without mutating the editor', () => {
		const input = {
			...webhook,
			name: ' Name ',
			data_mapping: [ { key: ' email ', value: ' user.email ' } ],
		};
		const result = cleanInput( input );
		expect( result.name ).toBe( 'Name' );
		expect( result.data_mapping ).toEqual( [
			{ key: 'email', value: 'user.email' },
		] );
		expect( input.data_mapping[ 0 ]?.key ).toBe( ' email ' );
		expect( result ).not.toHaveProperty( 'id' );
		expect( result ).not.toHaveProperty( 'secret_hint' );
	} );
	it.each( [
		'javascript:alert(1)',
		'ftp://example.com',
		'https://user:password@example.com',
	] )( 'rejects an unsafe or unsupported URL: %s', ( webhookUrl ) => {
		expect(
			validateInput( { ...webhook, webhook_url: webhookUrl }, options )
		).not.toBeNull();
	} );
	it( 'requires supported options and unique valid mapping rows', () => {
		expect( validateInput( webhook, options ) ).toBeNull();
		expect(
			validateInput(
				{ ...webhook, trigger_event: 'unregistered' },
				options
			)
		).not.toBeNull();
		expect(
			validateInput( { ...webhook, name: ' ' }, options )
		).not.toBeNull();
		expect(
			validateInput(
				{
					...webhook,
					data_mapping: [
						{ key: 'email', value: 'user.email' },
						{ key: 'email', value: 'other.email' },
					],
				},
				options
			)
		).not.toBeNull();
		expect(
			validateInput(
				{ ...webhook, data_mapping: [ { key: 'email', value: '' } ] },
				options
			)
		).not.toBeNull();
	} );
} );
