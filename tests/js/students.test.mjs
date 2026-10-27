import test from 'node:test';
import assert from 'node:assert/strict';
import {
	studentQuery,
	listStudents,
	changeStudentAccess,
} from '../../assets/src/features/students/api.mjs';
import {
	submitRegistration,
	registrationRedirect,
} from '../../assets/src/features/students/registration-api.mjs';

test( 'student requests use the PHP sorting and date filter contract', async () => {
	const query = new URLSearchParams(
		studentQuery( {
			page: 3,
			perPage: 5,
			search: 'A&B',
			orderby: 'email',
			order: 'ASC',
			dateFilter: [ '2026-01-01', '2026-02-01' ],
		} )
	);
	assert.equal( query.get( 'order_by' ), 'email' );
	assert.equal( query.has( 'orderby' ), false );
	assert.equal( query.get( 'offset' ), '10' );
	assert.equal( query.get( 'search' ), 'A&B' );
	assert.equal( query.get( 'date_filter' ), 'custom' );
	assert.equal( query.get( 'end_date' ), '2026-02-01' );
	const result = await listStudents(
		{},
		async () =>
			new Response( JSON.stringify( [ { user_id: 7 } ] ), {
				headers: { 'X-WP-Total': '12' },
			} )
	);
	assert.deepEqual( result, { students: [ { user_id: 7 } ], total: 12 } );
} );

test( 'access changes preserve server errors and use the unblock endpoint', async () => {
	await changeStudentAccess( [ 7 ], false, async ( request ) => {
		assert.equal( request.path, '/ohmylms/v1/students/unban' );
		assert.deepEqual( request.data, { ids: [ 7 ] } );
		return { status: 'success' };
	} );
	await assert.rejects(
		changeStudentAccess( [ 7 ], true, async () => ( {
			status: 'error',
			message: 'Forbidden',
		} ) ),
		/Forbidden/
	);
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
