import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

test( 'autosave mounts every player, handles dynamically started players and releases online listeners', async () => {
	const requests = [];
	const online = new Set();
	const documentEvents = {};
	const makeRoot = ( id ) => {
		const events = {};
		const form = {
			elements: [],
			querySelector: () => ( {} ),
			addEventListener() {},
			appendChild() {},
		};
		return {
			dataset: { autosave: `/attempts/${ id }/responses` },
			querySelectorAll: () => [ form ],
			querySelector: () => form,
			addEventListener: ( name, handler ) => {
				events[ name ] = handler;
			},
			events,
		};
	};
	const roots = [ makeRoot( 11 ), makeRoot( 22 ) ];
	const sandbox = {
		window: {
			fetch: true,
			ohmylmsQuizAutosave: { nonce: 'nonce' },
			addEventListener: ( name, handler ) => online.add( handler ),
			removeEventListener: ( name, handler ) => online.delete( handler ),
		},
		document: {
			querySelectorAll: () => roots,
			createElement: () => ( { setAttribute() {} } ),
			addEventListener: ( name, handler ) => {
				documentEvents[ name ] = handler;
			},
		},
		fetch: async ( url, options ) => {
			requests.push( url );
			assert.equal( options.headers[ 'X-WP-Nonce' ], 'nonce' );
			return { ok: true, json: async () => ( { responses: {} } ) };
		},
		setTimeout,
		clearTimeout,
	};
	vm.runInNewContext(
		fs.readFileSync(
			new URL( '../../assets/js/quiz-autosave.js', import.meta.url ),
			'utf8'
		),
		sandbox
	);
	assert.deepEqual( requests, [ '/attempts/11', '/attempts/22' ] );
	assert.equal( online.size, 2 );
	const third = makeRoot( 33 );
	documentEvents[ 'ohmylms:quiz-mounted' ]( { target: third } );
	documentEvents[ 'ohmylms:quiz-mounted' ]( { target: third } );
	assert.deepEqual( requests, [
		'/attempts/11',
		'/attempts/22',
		'/attempts/33',
	] );
	assert.equal( online.size, 3 );
	third.events[ 'ohmylms:quiz-unmounted' ]();
	assert.equal( online.size, 2 );
	await Promise.resolve();
} );
