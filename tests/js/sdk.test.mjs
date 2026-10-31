import test from 'node:test';
import assert from 'node:assert/strict';
import {
	ajaxPost,
	apiFetch,
	debounce,
	dismissToast,
	entriesToObject,
	getConfig,
	jqueryTrigger,
	normalizeResponse,
	plainText,
	request,
	setConfig,
	takePendingToasts,
	toast,
	toastItem,
	toFormData,
	toSearchParams,
	trapIndex,
} from '../../assets/interactivity/sdk.js';

const messages = getConfig().messages;

/**
 * Replace `fetch` with a queue of canned answers and record every call.
 *
 * @param {Object} t       Test context.
 * @param {Array}  answers `{ status, body }` (string body sent as is) or a function of the call.
 */
function mockFetch( t, answers ) {
	const calls = [];
	t.mock.method( globalThis, 'fetch', async ( url, init = {} ) => {
		calls.push( { url: String( url ), init } );
		let answer = answers.shift();
		if ( typeof answer === 'function' ) {
			answer = await answer( url, init );
		}
		if ( answer instanceof Error ) {
			throw answer;
		}
		const body =
			typeof answer.body === 'string'
				? answer.body
				: JSON.stringify( answer.body );
		return new Response( body, { status: answer.status || 200 } );
	} );
	return calls;
}

test.beforeEach( () => {
	setConfig( {
		restRoot: 'http://800.local/wp-json/',
		restNonce: 'aaaaaaaaaa',
		ajaxUrl: 'http://800.local/wp-admin/admin-ajax.php',
	} );
} );

test( 'REST answers keep their body; WordPress errors become { code, message, status }', () => {
	const ok = normalizeResponse( {
		transport: 'rest',
		status: 200,
		body: { id: 3 },
	} );
	assert.equal( ok.ok, true );
	assert.deepEqual( ok.data, { id: 3 } );
	assert.equal( ok.error, null );

	const denied = normalizeResponse( {
		transport: 'rest',
		status: 403,
		body: {
			code: 'rest_forbidden',
			message: 'Sorry, you are not allowed to do that.',
			data: { status: 401 },
		},
	} );
	assert.equal( denied.ok, false );
	assert.deepEqual( denied.error, {
		code: 'rest_forbidden',
		message: 'Sorry, you are not allowed to do that.',
		status: 401,
	} );
	assert.equal( denied.message, denied.error.message );

	const bare = normalizeResponse( {
		transport: 'rest',
		status: 502,
		body: null,
	} );
	assert.deepEqual( bare.error, {
		code: 'http_502',
		message: messages.error,
		status: 502,
	} );
} );

test( 'admin-ajax envelopes, legacy shapes and bare -1/0 answers', () => {
	const success = normalizeResponse( {
		transport: 'ajax',
		status: 200,
		body: { success: true, data: { message: 'Saved', url: '/x' } },
	} );
	assert.equal( success.ok, true );
	assert.deepEqual( success.data, { message: 'Saved', url: '/x' } );
	assert.equal( success.message, 'Saved' );

	const failure = normalizeResponse( {
		transport: 'ajax',
		status: 200,
		body: { success: false, data: { message: 'Nope', code: 'not_owner' } },
	} );
	assert.equal( failure.ok, false );
	assert.deepEqual( failure.error, {
		code: 'not_owner',
		message: 'Nope',
		status: 200,
	} );

	const wpError = normalizeResponse( {
		transport: 'ajax',
		status: 400,
		body: {
			success: false,
			data: [ { code: 'bad', message: 'Bad input' } ],
		},
	} );
	assert.deepEqual( wpError.error, {
		code: 'bad',
		message: 'Bad input',
		status: 400,
	} );

	const text = normalizeResponse( {
		transport: 'ajax',
		status: 200,
		body: { success: false, data: 'Plain reason' },
	} );
	assert.equal( text.message, 'Plain reason' );
	assert.equal( text.error.code, 'ajax_error' );

	const legacy = normalizeResponse( {
		transport: 'url',
		status: 200,
		body: { status: 'success', message: 'Sent', redirect_url: '/d' },
	} );
	assert.equal( legacy.ok, true );
	assert.equal( legacy.data.redirect_url, '/d' );
	assert.equal( legacy.message, 'Sent' );

	const legacyError = normalizeResponse( {
		transport: 'ajax',
		status: 200,
		body: { status: 'error', message: 'Invalid username' },
	} );
	assert.equal( legacyError.ok, false );
	assert.equal( legacyError.error.code, 'error' );
	assert.equal( legacyError.message, 'Invalid username' );

	const checkout = normalizeResponse( {
		transport: 'ajax',
		status: 200,
		body: { result: 'failure', messages: '<li>x</li>' },
	} );
	assert.equal( checkout.ok, false );

	assert.equal(
		normalizeResponse( { transport: 'ajax', status: 400, body: -1 } ).error
			.code,
		'invalid_nonce'
	);
	assert.equal(
		normalizeResponse( { transport: 'ajax', status: 400, body: 0 } ).error
			.code,
		'invalid_action'
	);
	assert.equal(
		normalizeResponse( {
			transport: 'ajax',
			status: 200,
			body: '<html>',
			invalid: true,
		} ).error.code,
		'invalid_json'
	);
	assert.equal(
		normalizeResponse( { transport: 'rest', failure: 'network' } ).error
			.code,
		'network_error'
	);
	const html = normalizeResponse( {
		transport: 'ajax',
		status: 200,
		body: '<div class="card"></div>',
		parse: 'text',
	} );
	assert.equal( html.ok, true );
	assert.equal( html.data, '<div class="card"></div>' );
} );

test( 'REST requests send the server nonce, JSON bodies and GET query arguments', async ( t ) => {
	const calls = mockFetch( t, [
		{ body: { saved: true } },
		{ body: [ 1, 2 ] },
	] );
	const saved = await request( {
		path: '/ohmylms/v1/notes',
		data: { text: 'hi' },
	} );
	assert.equal( saved.ok, true );
	assert.equal( saved.transport, 'rest' );
	assert.equal( calls[ 0 ].url, 'http://800.local/wp-json/ohmylms/v1/notes' );
	assert.equal( calls[ 0 ].init.method, 'POST' );
	assert.equal( calls[ 0 ].init.headers[ 'X-WP-Nonce' ], 'aaaaaaaaaa' );
	assert.equal( calls[ 0 ].init.body, '{"text":"hi"}' );

	await request( {
		path: 'ohmylms/v1/courses',
		method: 'get',
		data: { category: [ 'a', 'b' ], page: 2 },
		nonce: false,
	} );
	assert.equal(
		calls[ 1 ].url,
		'http://800.local/wp-json/ohmylms/v1/courses?category%5B%5D=a&category%5B%5D=b&page=2'
	);
	assert.equal( calls[ 1 ].init.method, 'GET' );
	assert.equal( calls[ 1 ].init.headers[ 'X-WP-Nonce' ], undefined );
} );

test( 'an expired REST nonce is renewed once and the request retried', async ( t ) => {
	const calls = mockFetch( t, [
		{
			status: 403,
			body: { code: 'rest_cookie_invalid_nonce', message: 'Expired' },
		},
		{ body: 'bbbbbbbbbb' },
		{ body: { ok: 1 } },
	] );
	const result = await request( { path: 'ohmylms/v1/me' } );
	assert.equal( result.ok, true );
	assert.match( calls[ 1 ].url, /admin-ajax\.php\?action=rest-nonce$/ );
	assert.equal( calls[ 2 ].init.headers[ 'X-WP-Nonce' ], 'bbbbbbbbbb' );
	assert.equal( getConfig().restNonce, 'bbbbbbbbbb' );
} );

test( 'path + action falls back to admin-ajax when the route is missing', async ( t ) => {
	const calls = mockFetch( t, [
		{
			status: 404,
			body: { code: 'rest_no_route', message: 'No route' },
		},
		{ body: { success: true, data: { html: '<li>' } } },
	] );
	const result = await request( {
		path: 'ohmylms/v1/catalog',
		action: 'ohmylms_search_filter',
		nonce: 'n1',
		data: { price_slug: [ 'free' ] },
	} );
	assert.equal( result.ok, true );
	assert.equal( result.transport, 'ajax' );
	assert.deepEqual( result.data, { html: '<li>' } );
	assert.equal( calls[ 1 ].url, 'http://800.local/wp-admin/admin-ajax.php' );
	const body = calls[ 1 ].init.body;
	assert.ok( body instanceof URLSearchParams );
	assert.equal(
		body.toString(),
		'price_slug%5B%5D=free&action=ohmylms_search_filter&nonce=n1'
	);
} );

test( 'admin-ajax uploads switch to FormData and keep the nonce field name', async ( t ) => {
	const calls = mockFetch( t, [ { body: { success: true, data: {} } } ] );
	const file = new Blob( [ 'x' ], { type: 'text/plain' } );
	await request( {
		action: 'ohmylms_student_cover_image_upload',
		nonce: 'n2',
		nonceField: 'security',
		data: { file },
	} );
	const body = calls[ 0 ].init.body;
	assert.ok( body instanceof FormData );
	assert.equal( body.get( 'action' ), 'ohmylms_student_cover_image_upload' );
	assert.equal( body.get( 'security' ), 'n2' );
	assert.ok( body.get( 'file' ) instanceof Blob );
} );

test( 'aborts, timeouts and network failures resolve instead of throwing', async ( t ) => {
	const abortable = () => ( url, init ) =>
		new Promise( ( resolve, reject ) => {
			init.signal.addEventListener( 'abort', () =>
				reject( new DOMException( 'aborted', 'AbortError' ) )
			);
		} );
	mockFetch( t, [ abortable(), abortable(), new TypeError( 'offline' ) ] );
	const controller = new AbortController();
	const pending = request( { path: 'x', signal: controller.signal } );
	controller.abort();
	const aborted = await pending;
	assert.equal( aborted.ok, false );
	assert.equal( aborted.error.code, 'aborted' );
	assert.equal( aborted.status, 0 );

	const late = await request( { path: 'x', timeout: 5 } );
	assert.equal( late.error.code, 'timeout' );

	const offline = await request( { action: 'ohmylms_login' } );
	assert.equal( offline.error.code, 'network_error' );
	assert.equal( offline.message, messages.network );

	await assert.rejects( () => request( {} ), TypeError );
} );

test( 'text parsing returns HTML fragments', async ( t ) => {
	mockFetch( t, [ { body: '<article>1</article>' } ] );
	const result = await request( {
		action: 'ohmylms_loadmore',
		parse: 'text',
	} );
	assert.equal( result.ok, true );
	assert.equal( result.data, '<article>1</article>' );
} );

test( 'apiFetch keeps the commerce contract', async ( t ) => {
	const calls = mockFetch( t, [
		{ body: { status: 'success', redirect_url: '/checkout' } },
		{
			status: 400,
			body: {
				code: 'invalid',
				message: 'Bad course',
				data: { status: 400 },
			},
		},
		() => new DOMException( 'aborted', 'AbortError' ),
	] );
	const body = await apiFetch( 'ohmylms-commerce/v1/cart/items', {
		method: 'POST',
		data: { course_id: 3 },
		root: 'http://shop.local/wp-json/',
		nonce: 'cccccccccc',
	} );
	assert.deepEqual( body, { status: 'success', redirect_url: '/checkout' } );
	assert.equal(
		calls[ 0 ].url,
		'http://shop.local/wp-json/ohmylms-commerce/v1/cart/items'
	);
	assert.equal( calls[ 0 ].init.headers[ 'X-WP-Nonce' ], 'cccccccccc' );

	await assert.rejects(
		() => apiFetch( 'ohmylms-commerce/v1/cart/items' ),
		( error ) =>
			'Bad course' === error.message &&
			'invalid' === error.code &&
			400 === error.status &&
			'invalid' === error.data.code
	);

	const controller = new AbortController();
	controller.abort();
	await assert.rejects(
		() => apiFetch( 'x', { signal: controller.signal } ),
		( error ) => 'AbortError' === error.name
	);
} );

test( 'ajaxPost returns the raw JSON whatever the status, and rejects invalid JSON', async ( t ) => {
	const calls = mockFetch( t, [
		{ status: 500, body: { result: 'failure', messages: 'x' } },
		{ body: -1 },
		{ status: 502, body: '<html>Bad gateway</html>' },
	] );
	assert.deepEqual(
		await ajaxPost( '/wp-admin/admin-ajax.php', {
			action: 'ohmylms_apply_coupon',
			coupon_code: 'SAVE',
			security: undefined,
		} ),
		{ result: 'failure', messages: 'x' }
	);
	assert.equal(
		calls[ 0 ].init.body.toString(),
		'action=ohmylms_apply_coupon&coupon_code=SAVE&security=undefined'
	);
	assert.equal(
		await ajaxPost( '/a', new URLSearchParams( 'action=x' ) ),
		-1
	);
	await assert.rejects(
		() => ajaxPost( '/a', {} ),
		( error ) =>
			502 === error.status && messages.invalidJson === error.message
	);
} );

test( 'PHP-style encoding of nested fields', () => {
	assert.equal(
		toSearchParams( {
			a: 1,
			skip: undefined,
			empty: null,
			list: [ 'x', 'y' ],
			rows: [ { id: 1 } ],
			map: { k: 'v' },
		} ).toString(),
		'a=1&empty=&list%5B%5D=x&list%5B%5D=y&rows%5B0%5D%5Bid%5D=1&map%5Bk%5D=v'
	);
	const form = toFormData( { tags: [ 'a' ] } );
	assert.deepEqual( [ ...form ], [ [ 'tags[]', 'a' ] ] );
	assert.deepEqual(
		entriesToObject( [
			[ 'name', 'Ann' ],
			[ 'tags[]', 'a' ],
			[ 'tags[]', 'b' ],
			[ 'pick', '1' ],
			[ 'pick', '2' ],
		] ),
		{ name: 'Ann', tags: [ 'a', 'b' ], pick: [ '1', '2' ] }
	);
} );

test( 'toast messages are plain text with a type and a duration', () => {
	assert.equal(
		plainText( '<strong>Saved</strong> &amp; done&nbsp;&#8230; <br>ok' ),
		'Saved & done … ok'
	);
	assert.equal( plainText( '<script>alert(1)</script>' ), 'alert(1)' );
	assert.equal( plainText( null ), '' );
	assert.deepEqual( toastItem( 'Hi', {}, 't1' ), {
		id: 't1',
		message: 'Hi',
		type: 'success',
		duration: 5000,
	} );
	assert.equal( toastItem( 'x', { type: 'error' } ).type, 'danger' );
	assert.equal( toastItem( 'x', { type: 'error' } ).duration, 8000 );
	assert.equal( toastItem( 'x', { type: 'info' } ).type, 'success' );
	assert.equal( toastItem( 'x', { duration: 0 } ).duration, 0 );
	assert.equal( toastItem( 'x', { duration: -3 } ).duration, 5000 );
	assert.deepEqual(
		toastItem( { message: 'From event', type: 'warning', id: 'e1' } ),
		{ id: 'e1', message: 'From event', type: 'warning', duration: 5000 }
	);
	assert.equal( toastItem( '   ' ), null );
} );

test( 'toast() dispatches a cancelable event and queues toasts nobody accepted', ( t ) => {
	const doc = new EventTarget();
	globalThis.document = doc;
	t.after( () => delete globalThis.document );
	takePendingToasts();

	const seen = [];
	const handle = toast( 'Before the store loads', { type: 'warning' } );
	assert.match( handle.id, /^ohmylms-toast-\d+$/ );
	assert.deepEqual(
		takePendingToasts().map( ( item ) => item.message ),
		[ 'Before the store loads' ]
	);

	const accept = ( event ) => {
		seen.push( event.detail );
		event.detail.accepted = true;
	};
	doc.addEventListener( 'ohmylms:toast', accept );
	toast( 'Shown', { duration: 0 } );
	assert.equal( seen[ 0 ].message, 'Shown' );
	assert.equal( seen[ 0 ].apiVersion, 1 );
	assert.equal( takePendingToasts().length, 0 );
	doc.removeEventListener( 'ohmylms:toast', accept );

	const veto = ( event ) => event.preventDefault();
	doc.addEventListener( 'ohmylms:toast', veto );
	toast( 'Handled by an add-on' );
	assert.equal( takePendingToasts().length, 0 );
	doc.removeEventListener( 'ohmylms:toast', veto );

	const dismissed = [];
	doc.addEventListener( 'ohmylms:toast-dismiss', ( event ) =>
		dismissed.push( event.detail.id )
	);
	const queued = toast( 'Never mind' );
	queued.dismiss();
	assert.equal( takePendingToasts().length, 0 );
	dismissToast();
	assert.deepEqual( dismissed, [ queued.id, '' ] );
	assert.equal( toast( '' ), null );
} );

test( 'focus trap wraps at both ends and pulls focus back in', () => {
	assert.equal( trapIndex( 3, 2, false ), 0 );
	assert.equal( trapIndex( 3, 0, true ), 2 );
	assert.equal( trapIndex( 3, 1, false ), -1 );
	assert.equal( trapIndex( 3, -1, false ), 0 );
	assert.equal( trapIndex( 3, -1, true ), 2 );
	assert.equal( trapIndex( 0, -1, false ), -1 );
} );

test( 'debounce runs the last call once, and can be flushed or cancelled', ( t ) => {
	t.mock.timers.enable( { apis: [ 'setTimeout' ] } );
	const calls = [];
	const run = debounce( ( value ) => calls.push( value ), 200 );
	run( 1 );
	run( 2 );
	t.mock.timers.tick( 199 );
	assert.deepEqual( calls, [] );
	t.mock.timers.tick( 1 );
	assert.deepEqual( calls, [ 2 ] );
	run( 3 );
	run.flush();
	run( 4 );
	run.cancel();
	t.mock.timers.tick( 500 );
	assert.deepEqual( calls, [ 2, 3 ] );
} );

test( 'jqueryTrigger only fires when jQuery is loaded', ( t ) => {
	const body = {};
	globalThis.document = { body };
	t.after( () => {
		delete globalThis.document;
		delete globalThis.window;
	} );
	globalThis.window = {};
	assert.equal( jqueryTrigger( 'ohmylms_toast_shown' ), false );
	const calls = [];
	globalThis.window = {
		jQuery: ( target ) => ( {
			trigger: ( name, args ) => calls.push( [ target, name, args ] ),
		} ),
	};
	assert.equal( jqueryTrigger( 'course_added_to_cart', [ 1 ] ), true );
	assert.deepEqual( calls, [ [ body, 'course_added_to_cart', [ 1 ] ] ] );
} );
