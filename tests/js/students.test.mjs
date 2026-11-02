import test from 'node:test';
import assert from 'node:assert/strict';
import {
	submitRegistration,
	registrationRedirect,
	registrationUrl,
} from '../../assets/src/features/students/registration-api.mjs';

test( 'registration posts to a real admin-ajax URL on pages without ohmylms_frontend_params', async () => {
	const moduleData = ( url ) => ( {
		getElementById: ( id ) =>
			'wp-script-module-data-ohmylms/interactivity' === id
				? { textContent: JSON.stringify( { ajaxUrl: url } ) }
				: null,
	} );
	assert.equal(
		registrationUrl(
			{ ohmylmsRegistration: { ajaxUrl: '/wp-admin/admin-ajax.php' } },
			moduleData( '/module-ajax' )
		),
		'/wp-admin/admin-ajax.php'
	);
	assert.equal( registrationUrl( {}, moduleData( '/module-ajax' ) ), '/module-ajax' );
	assert.equal(
		registrationUrl(
			{ ohmylms_checkout_params: { ajax_url: '/checkout-ajax' } },
			{ getElementById: () => ( { textContent: 'not json' } ) }
		),
		'/checkout-ajax'
	);
	assert.equal( registrationUrl( {}, { getElementById: () => null } ), '' );
	let called = false;
	await assert.rejects(
		submitRegistration( registrationUrl( {}, undefined ), new FormData(), async () => {
			called = true;
			return Response.json( { status: 'success' } );
		} ),
		/could not be completed/
	);
	assert.equal( called, false, 'nothing is posted to a relative "undefined"' );
} );

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
