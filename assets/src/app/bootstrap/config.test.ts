import { hasCapability, parseBootstrap } from './config';

const valid = {
	restRoot: 'http://example.test/wp-json/',
	restNonce: 'abc',
	locale: 'de_DE',
	adminUrl: 'http://example.test/wp-admin/',
	version: '1.0.0',
	user: { id: 3, capabilities: { manage_options: true, edit_users: 0 } },
};

describe( 'parseBootstrap', () => {
	it( 'returns typed data and coerces capabilities to booleans', () => {
		expect( parseBootstrap( valid )?.user.capabilities ).toEqual( {
			manage_options: true,
			edit_users: false,
		} );
	} );

	it( 'rejects missing or malformed data', () => {
		expect( parseBootstrap( undefined ) ).toBeNull();
		expect( parseBootstrap( { ...valid, restNonce: 5 } ) ).toBeNull();
		expect( parseBootstrap( { ...valid, user: { id: '3' } } ) ).toBeNull();
	} );
} );

describe( 'hasCapability', () => {
	it( 'allows everyone when no capability is required', () => {
		expect( hasCapability( {} ) ).toBe( true );
	} );

	it( 'requires an explicit true', () => {
		expect( hasCapability( { a: true }, 'a' ) ).toBe( true );
		expect( hasCapability( { a: false }, 'a' ) ).toBe( false );
		expect( hasCapability( {}, 'a' ) ).toBe( false );
	} );
} );
