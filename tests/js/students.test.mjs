import test from 'node:test';
import assert from 'node:assert/strict';
import {
	submitRegistration,
	registrationRedirect,
} from '../../assets/src/features/students/registration-api.mjs';

test( 'registration preserves nonce and extension fields and handles verification', async () => {
	const data = new FormData();
	data.set( 'ohmylms-signup-nonce', 'nonce' );
	data.set( 'extension', 'custom' );
	const result = await submitRegistration(
		'/ajax',
		data,
		async ( url, options ) => {
			assert.equal( options.body, data );
			assert.equal( options.credentials, 'same-origin' );
			return Response.json( {
				status: 'pending_verification',
				message: 'Check inbox',
			} );
		}
	);
	assert.equal( result.status, 'pending_verification' );
	await assert.rejects(
		submitRegistration( '/ajax', data, async () => new Response( '0' ) ),
		/could not be completed/
	);
	await assert.rejects(
		submitRegistration( '/ajax', data, async () =>
			Response.json( { status: 'error', message: 'Email exists' } )
		),
		/Email exists/
	);
} );

test( 'registration only follows nonempty same-origin redirects', () => {
	const origin = 'https://school.example/signup';
	for ( const value of [
		undefined,
		null,
		'',
		'  ',
		'https://other.example',
		'javascript:alert(1)',
	] )
		assert.equal( registrationRedirect( value, origin ), null );
	assert.equal(
		registrationRedirect( '/dashboard', origin ),
		'https://school.example/dashboard'
	);
} );
