import test from 'node:test';
import assert from 'node:assert/strict';
import { sendInline } from '../../assets/interactivity/quiz-player/inline.js';

test( 'inline submissions preserve form data and use cookie authentication with a REST nonce', async () => {
	let calls = 0;
	let refreshed = 0;
	const context = {
		inlineEndpoint: '/attempt',
		restNonce: 'nonce',
		submitting: true,
	};
	await sendInline( context, {}, 'submit', {
		encode: () =>
			new URLSearchParams( {
				'attempt[11][quiz_question][17][]': 'a&b',
				quiz_attempt_id: 11,
			} ),
		request: async ( url, options ) => {
			calls++;
			assert.equal( url, '/attempt' );
			assert.equal( options.credentials, 'same-origin' );
			assert.equal( options.headers[ 'X-WP-Nonce' ], 'nonce' );
			assert.equal( options.body.get( 'operation' ), 'submit' );
			assert.equal(
				options.body.get( 'attempt[11][quiz_question][17][]' ),
				'a&b'
			);
			return { ok: true, json: async () => ( { saved: true } ) };
		},
		refresh: async () => refreshed++,
	} );
	assert.equal( calls, 1 );
	assert.equal( refreshed, 1 );
	assert.equal( context.submitting, false );
	assert.equal( context.saved, false );
} );

test( 'a failed refresh can retry without recording a second attempt', async () => {
	let calls = 0;
	let refreshed = 0;
	const context = {
		inlineEndpoint: '/attempt',
		restNonce: 'nonce',
		submitting: true,
	};
	const runtime = {
		encode: () => new URLSearchParams(),
		request: async () => {
			calls++;
			return { ok: true, json: async () => ( { saved: true } ) };
		},
		refresh: async () => {
			if ( ++refreshed === 1 ) throw new Error( 'offline' );
		},
	};
	await sendInline( context, {}, 'start', runtime );
	assert.equal( context.error, 'offline' );
	assert.equal( context.saved, true );
	await sendInline( context, {}, 'start', runtime );
	assert.equal( calls, 1 );
	assert.equal( refreshed, 2 );
	assert.equal( context.error, '' );
} );

test( 'access failures remain inline and allow retry without discarding answers', async () => {
	const context = { submitting: true };
	await sendInline( context, {}, 'start', {
		encode: () => new URLSearchParams(),
		request: async () => ( {
			ok: false,
			json: async () => ( { message: 'Enrollment required.' } ),
		} ),
		refresh: async () =>
			assert.fail( 'Failed mutations must not navigate.' ),
	} );
	assert.equal( context.error, 'Enrollment required.' );
	assert.equal( context.submitting, false );
	assert.equal( context.saved, undefined );
} );
