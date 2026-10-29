/**
 * The authored webhooks slice (features/webhooks/api.mjs + store.mjs) against the webhooks code
 * of the recovered application store, evaluated from factory 86169 in a vm sandbox.
 *
 * Both implementations get the same scripted `apiFetch` and the same store harness. The test
 * compares the requests (path, method, data, other options), the plain actions they dispatch
 * (including notifications), the loading transitions, the resulting state, the return value and
 * the rethrown error.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { createRestClient } from '../../assets/src/shared/api/index.mjs';
import {
	createWebhooksApi,
	listWebhooks,
	createWebhook,
	updateWebhook,
	deleteWebhook,
	bulkWebhooks,
	loadWebhookTriggers,
	testWebhook,
} from '../../assets/src/features/webhooks/api.mjs';
import {
	createWebhooksSlice,
	createWebhookThunks,
	legacyShowNotification,
	webhooksInitialState,
	webhooksReducer,
	webhooksSelectors,
	webhookActionTypes,
	createDefaultWebhookFormData,
} from '../../assets/src/features/webhooks/store.mjs';
import { loadRecoveredWebhooks, plain } from './webhooks-api-recovered.mjs';
import {
	createApiMock,
	createResponse,
	createTimers,
	createStore,
	settle,
} from './webhooks-api-harness.mjs';

const LIST = [
	{ id: 1, name: 'First', status: 'active' },
	{ id: 2, name: 'Second', status: 'inactive' },
];
const PRELOAD = [
	[ 'setWebhooks', { data: LIST, total: 2, totalPages: 1 } ],
	[ 'updateWebhookFormData', 'name', 'Draft' ],
];
const REST_ERROR = () => ( {
	code: 'rest_forbidden',
	message: 'Sorry, you are not allowed to do that.',
	data: { status: 403 },
} );

/**
 * Build the recovered or the authored implementation around one scripted `apiFetch`.
 *
 * @param {string} kind   `recovered` or `authored`.
 * @param {Object} mock   Scripted apiFetch.
 * @param {Object} timers Timer recorder.
 * @return {{reducer: Function, actions: Object, selectors: Object, initialState: Object}} Implementation.
 */
function build( kind, mock, timers ) {
	if ( 'recovered' === kind ) {
		return loadRecoveredWebhooks( {
			apiFetch: mock.fetch,
			setTimeout: timers.setTimeout,
		} );
	}
	const api = createWebhooksApi(
		createRestClient( { apiFetch: mock.fetch } )
	);
	return createWebhooksSlice( {
		api,
		showNotification: ( message, status ) =>
			legacyShowNotification( message, status, timers.setTimeout ),
	} );
}

/**
 * Run one action against an implementation.
 *
 * @param {string} kind `recovered` or `authored`.
 * @param {Object} spec `{ name, args, script, preload }`.
 * @return {Promise<Object>} Everything observable about the run.
 */
async function run( kind, spec ) {
	const steps = spec.script();
	const mock = createApiMock( steps );
	const timers = createTimers();
	const impl = build( kind, mock, timers );
	const store = createStore( impl.reducer );
	for ( const [ name, ...args ] of spec.preload || PRELOAD ) {
		store.dispatch( impl.actions[ name ]( ...args ) );
	}
	store.log.length = 0;
	store.loadingSteps.length = 0;
	let outcome;
	try {
		outcome = {
			value: await store.dispatch(
				impl.actions[ spec.name ]( ...spec.args )
			),
		};
	} catch ( error ) {
		outcome = { error };
	}
	await settle();
	const delays = timers.timers.map( ( timer ) => timer.delay );
	timers.run();
	return {
		steps,
		calls: mock.calls,
		log: store.log,
		loadingSteps: store.loadingSteps,
		state: plain( store.getState() ),
		outcome,
		delays,
	};
}

/**
 * Run a spec on both implementations and assert that everything observable is equal.
 *
 * @param {Object} spec Case.
 * @return {Promise<Object>} Both results.
 */
async function parity( spec ) {
	const recovered = await run( 'recovered', spec );
	const authored = await run( 'authored', spec );
	assert.deepStrictEqual( authored.calls, recovered.calls, 'requests' );
	assert.deepStrictEqual( authored.log, recovered.log, 'dispatched actions' );
	assert.deepStrictEqual(
		authored.loadingSteps,
		recovered.loadingSteps,
		'loading transitions'
	);
	assert.deepStrictEqual( authored.state, recovered.state, 'final state' );
	assert.deepStrictEqual( authored.delays, recovered.delays, 'timers' );
	for ( const result of [ recovered, authored ] ) {
		const injected = result.steps.find( ( step ) => 'error' in step );
		if ( 'error' in result.outcome && injected ) {
			assert.strictEqual(
				result.outcome.error,
				injected.error,
				'the original error is rethrown'
			);
		}
	}
	assert.strictEqual(
		'error' in authored.outcome,
		'error' in recovered.outcome
	);
	if ( 'error' in recovered.outcome ) {
		assert.equal(
			authored.outcome.error.message,
			recovered.outcome.error.message
		);
	} else {
		// A returned response or object is the one the mock handed out, in both implementations.
		const owner = ( result ) =>
			result.steps.findIndex(
				( step ) =>
					'result' in step && step.result === result.outcome.value
			);
		assert.equal( owner( authored ), owner( recovered ) );
		if ( owner( recovered ) < 0 ) {
			assert.deepStrictEqual(
				plain( authored.outcome.value ),
				plain( recovered.outcome.value ),
				'return value'
			);
		}
	}
	return { recovered, authored };
}

const types = ( result ) => result.log.map( ( action ) => action.type );

test( 'the authored slice exposes the recovered action and selector names', () => {
	const recovered = loadRecoveredWebhooks( {
		apiFetch: () => Promise.reject( new Error( 'unused' ) ),
		setTimeout() {},
	} );
	const slice = createWebhooksSlice( {
		api: createWebhooksApi( createRestClient( { apiFetch() {} } ) ),
	} );
	assert.equal( Object.keys( recovered.actions ).length, 17 );
	assert.equal( Object.keys( recovered.selectors ).length, 7 );
	assert.deepStrictEqual(
		Object.keys( slice.actions ).sort(),
		Object.keys( recovered.actions ).sort()
	);
	assert.deepStrictEqual(
		Object.keys( slice.selectors ).sort(),
		Object.keys( recovered.selectors ).sort()
	);
	assert.equal( slice.key, 'webhooks' );
	assert.equal( typeof slice.actions.getWebhooks(), 'function' );
	assert.equal( typeof recovered.actions.getWebhooks(), 'function' );
	assert.deepStrictEqual( Object.values( webhookActionTypes ).sort(), [
		'ADD_WEBHOOK',
		'CLEAR_WEBHOOK_FORM_DATA',
		'DELETE_WEBHOOK',
		'SET_WEBHOOK',
		'SET_WEBHOOKS',
		'SET_WEBHOOK_FORM_DATA',
		'SET_WEBHOOK_LOADING',
		'SET_WEBHOOK_TRIGGERS',
		'UPDATE_WEBHOOK',
		'UPDATE_WEBHOOK_FORM_DATA',
	] );
} );

test( 'initial state and unknown actions match the recovered reducer', () => {
	const recovered = loadRecoveredWebhooks( {
		apiFetch() {},
		setTimeout() {},
	} );
	assert.deepStrictEqual(
		webhooksInitialState,
		plain( recovered.initialState )
	);
	assert.deepStrictEqual( Object.keys( webhooksInitialState ), [
		'webhooks',
		'webhook',
		'triggers',
		'loading',
		'total',
		'totalPages',
		'formData',
	] );
	assert.strictEqual(
		webhooksReducer( undefined, { type: 'OTHER' } ),
		webhooksInitialState
	);
	assert.strictEqual(
		recovered.reducer( undefined, { type: 'OTHER' } ),
		recovered.initialState
	);
	const state = { ...webhooksInitialState };
	assert.strictEqual( webhooksReducer( state, { type: 'OTHER' } ), state );
	// A new empty form each time, never the shared initial object.
	assert.notStrictEqual(
		createDefaultWebhookFormData().data_mapping,
		webhooksInitialState.formData.data_mapping
	);
} );

test( 'plain action creators and the reducer agree with the recovered store', () => {
	const recovered = loadRecoveredWebhooks( {
		apiFetch() {},
		setTimeout() {},
	} );
	const slice = createWebhooksSlice( {
		api: createWebhooksApi( createRestClient( { apiFetch() {} } ) ),
	} );
	const sequence = [
		[ 'setWebhooks', { data: LIST, total: 2, totalPages: 1 } ],
		[ 'setWebhooks', [ { id: 7, name: 'Bare array' } ] ],
		[ 'setWebhooks', { data: [], total: NaN, totalPages: undefined } ],
		[ 'setWebhook', { id: 1 } ],
		[ 'setWebhookTriggers', { course_purchase: 'Course purchase' } ],
		[ 'setWebhookLoading', true ],
		[ 'addWebhook', { id: 9, name: 'Nine' } ],
		[ 'updateWebhook', 9, { name: 'Nine, renamed', status: 'inactive' } ],
		[ 'updateWebhook', 404, { name: 'No such webhook' } ],
		[ 'deleteWebhook', 9 ],
		[
			'setWebhookFormData',
			{ name: 'Loaded', webhook_url: 'https://example.test' },
		],
		[ 'updateWebhookFormData', 'timeout', 5 ],
		[
			'updateWebhookFormData',
			'data_mapping',
			[ { key: 'a', value: 'b' } ],
		],
		[ 'clearWebhookFormData' ],
		[ 'setWebhookLoading', false ],
	];
	let expected = plain( recovered.initialState );
	let actual = webhooksInitialState;
	let recoveredState = recovered.initialState;
	for ( const [ name, ...args ] of sequence ) {
		const recoveredAction = recovered.actions[ name ]( ...args );
		const authoredAction = slice.actions[ name ]( ...args );
		assert.deepStrictEqual(
			plain( authoredAction ),
			plain( recoveredAction ),
			name
		);
		const before = structuredClone( actual );
		recoveredState = recovered.reducer( recoveredState, recoveredAction );
		actual = slice.reducer( actual, authoredAction );
		expected = plain( recoveredState );
		assert.deepStrictEqual( actual, expected, 'state after ' + name );
		assert.deepStrictEqual(
			structuredClone( before ),
			before,
			'the previous state is not mutated by ' + name
		);
	}
	// The totals fall back to 0 when the payload has none or NaN.
	assert.equal( expected.total, 0 );
	assert.equal( expected.totalPages, 0 );
} );

test( 'selectors read the same root state keys as the recovered selectors', () => {
	const recovered = loadRecoveredWebhooks( {
		apiFetch() {},
		setTimeout() {},
	} );
	const slice = webhooksReducer( webhooksInitialState, {
		type: 'SET_WEBHOOKS',
		payload: { data: LIST, total: 2, totalPages: 1 },
	} );
	const root = {
		webhooks: {
			...slice,
			webhook: { id: 2 },
			triggers: { a: 'A' },
			loading: true,
		},
		notification: { message: '', status: '', visible: false },
	};
	for ( const name of Object.keys( recovered.selectors ) ) {
		assert.deepStrictEqual(
			webhooksSelectors[ name ]( root ),
			plain( recovered.selectors[ name ]( root ) ),
			name
		);
	}
	assert.equal( webhooksSelectors.getWebhookTotal( root ), 2 );
} );

test( 'getWebhooks: requests, loading transitions and totals', async () => {
	const response = () =>
		createResponse( LIST, { 'x-wp-total': '42', 'x-wp-totalpages': '5' } );
	const params = {
		page: 2,
		per_page: 10,
		search: 'order paid',
		status: 'active',
		orderby: 'created_at',
		order: 'DESC',
	};
	const { authored } = await parity( {
		name: 'getWebhooks',
		args: [ params ],
		script: () => [ { result: response() } ],
	} );
	assert.deepStrictEqual( authored.calls, [
		{
			path: '/ohmylms/v1/webhooks?page=2&per_page=10&search=order+paid&status=active&orderby=created_at&order=DESC',
			parse: false,
		},
	] );
	assert.deepStrictEqual( types( authored ), [
		'SET_WEBHOOK_LOADING',
		'SET_WEBHOOKS',
		'SET_WEBHOOK_LOADING',
	] );
	assert.deepStrictEqual( authored.loadingSteps, [ true, true, false ] );
	assert.equal( authored.state.total, 42 );
	assert.equal( authored.state.totalPages, 5 );
	assert.deepStrictEqual( authored.state.webhooks, LIST );
	assert.equal( authored.outcome.value.headers.get( 'X-WP-Total' ), '42' );
} );

test( 'getWebhooks: default parameters, missing headers and a wrapped body', async () => {
	const { authored } = await parity( {
		name: 'getWebhooks',
		args: [],
		script: () => [
			{ result: createResponse( { data: [ { id: 3 } ] }, {} ) },
		],
	} );
	// With no parameters the path still ends in `?`; the recovered request is kept as is.
	assert.deepStrictEqual( authored.calls, [
		{ path: '/ohmylms/v1/webhooks?', parse: false },
	] );
	// Missing headers: parseInt( null ) is NaN, which the reducer turns into 0.
	assert.equal( authored.state.total, 0 );
	assert.equal( authored.state.totalPages, 0 );
	// The body is stored as received, so a wrapped `{ data: [] }` body stays wrapped.
	assert.deepStrictEqual( authored.state.webhooks, { data: [ { id: 3 } ] } );
} );

test( 'getWebhooks: a failed request notifies, rethrows and clears loading', async () => {
	const { authored } = await parity( {
		name: 'getWebhooks',
		args: [ { page: 1 } ],
		script: () => [ { error: REST_ERROR() } ],
	} );
	assert.deepStrictEqual( types( authored ), [
		'SET_WEBHOOK_LOADING',
		'SHOW_NOTIFICATION',
		'SET_WEBHOOK_LOADING',
		'HIDE_NOTIFICATION',
	] );
	assert.deepStrictEqual( authored.loadingSteps, [
		true,
		true,
		false,
		false,
	] );
	assert.deepStrictEqual( authored.log[ 1 ].payload, {
		message: { message: 'Failed to load webhooks', type: 'error' },
		status: 'success',
	} );
	assert.deepStrictEqual( authored.delays, [ 100 ] );
	assert.equal( authored.state.loading, false );
	assert.deepStrictEqual( authored.state.webhooks, LIST, 'the list is kept' );
} );

test( 'getWebhooks: an unreadable body is reported like a failed request', async () => {
	const { authored } = await parity( {
		name: 'getWebhooks',
		args: [ {} ],
		script: () => [
			{
				result: createResponse(
					null,
					{ 'x-wp-total': '1' },
					new SyntaxError( 'Unexpected token' )
				),
			},
		],
	} );
	assert.equal( 'error' in authored.outcome, true );
	assert.equal( authored.state.loading, false );
} );

test( 'createWebhook: POST, appended to the list, success and failure notices', async () => {
	const data = { name: 'New', webhook_url: 'https://example.test/hook' };
	const created = () => ( { id: 3, ...data } );
	const ok = await parity( {
		name: 'createWebhook',
		args: [ data ],
		script: () => [ { result: created() } ],
	} );
	assert.deepStrictEqual( ok.authored.calls, [
		{ path: '/ohmylms/v1/webhooks', method: 'POST', data },
	] );
	assert.deepStrictEqual( types( ok.authored ), [
		'ADD_WEBHOOK',
		'SHOW_NOTIFICATION',
		'HIDE_NOTIFICATION',
	] );
	assert.deepStrictEqual( ok.authored.state.webhooks.at( -1 ), created() );
	assert.deepStrictEqual( ok.authored.log[ 1 ].payload, {
		message: { message: 'Webhook created successfully', type: 'success' },
		status: 'success',
	} );
	const failed = await parity( {
		name: 'createWebhook',
		args: [ data ],
		script: () => [ { error: REST_ERROR() } ],
	} );
	assert.deepStrictEqual( types( failed.authored ), [
		'SHOW_NOTIFICATION',
		'HIDE_NOTIFICATION',
	] );
	assert.deepStrictEqual( failed.authored.state.webhooks, LIST );
} );

test( 'updateWebhookById: PUT, merges the response into the matching webhook', async () => {
	const changes = { name: 'Renamed', status: 'inactive' };
	const ok = await parity( {
		name: 'updateWebhookById',
		args: [ 2, changes ],
		script: () => [
			{
				result: {
					name: 'Renamed',
					status: 'inactive',
					retry_attempts: 5,
				},
			},
		],
	} );
	assert.deepStrictEqual( ok.authored.calls, [
		{ path: '/ohmylms/v1/webhooks/2', method: 'PUT', data: changes },
	] );
	assert.deepStrictEqual( ok.authored.log[ 0 ], {
		type: 'UPDATE_WEBHOOK',
		payload: {
			webhookId: 2,
			keyValuePair: {
				name: 'Renamed',
				status: 'inactive',
				retry_attempts: 5,
			},
		},
	} );
	assert.deepStrictEqual( ok.authored.state.webhooks[ 1 ], {
		id: 2,
		name: 'Renamed',
		status: 'inactive',
		retry_attempts: 5,
	} );
	assert.deepStrictEqual( ok.authored.state.webhooks[ 0 ], LIST[ 0 ] );
	await parity( {
		name: 'updateWebhookById',
		args: [ 2, changes ],
		script: () => [ { error: REST_ERROR() } ],
	} );
} );

test( 'deleteWebhookById: DELETE, removed from the list, no return value', async () => {
	const ok = await parity( {
		name: 'deleteWebhookById',
		args: [ 1 ],
		script: () => [ { result: { deleted: true } } ],
	} );
	assert.deepStrictEqual( ok.authored.calls, [
		{ path: '/ohmylms/v1/webhooks/1', method: 'DELETE' },
	] );
	assert.deepStrictEqual( ok.authored.state.webhooks, [ LIST[ 1 ] ] );
	assert.equal( ok.authored.outcome.value, undefined );
	const failed = await parity( {
		name: 'deleteWebhookById',
		args: [ 1 ],
		script: () => [ { error: REST_ERROR() } ],
	} );
	assert.deepStrictEqual( failed.authored.state.webhooks, LIST );
} );

test( 'bulkWebhookAction: POST, notice text per action, then a default list reload', async () => {
	for ( const action of [ 'delete', 'active', 'inactive' ] ) {
		const { authored } = await parity( {
			name: 'bulkWebhookAction',
			args: [ action, [ 1, 2 ] ],
			script: () => [
				{ result: { results: { 1: action, 2: action } } },
				{
					result: createResponse( [ LIST[ 1 ] ], {
						'x-wp-total': '1',
						'x-wp-totalpages': '1',
					} ),
				},
			],
		} );
		assert.deepStrictEqual( authored.calls, [
			{
				path: '/ohmylms/v1/webhooks/bulk',
				method: 'POST',
				data: { action, ids: [ 1, 2 ] },
			},
			{ path: '/ohmylms/v1/webhooks?', parse: false },
		] );
		const notice = authored.log.find(
			( entry ) => 'SHOW_NOTIFICATION' === entry.type
		);
		assert.equal(
			notice.payload.message.message,
			{
				delete: 'Selected webhooks deleted successfully',
				// The server actions are `active` and `inactive`; the recovered text map is
				// keyed `activate` and `deactivate`, so no text is shown for them.
				active: undefined,
				inactive: undefined,
			}[ action ]
		);
		assert.deepStrictEqual( authored.state.webhooks, [ LIST[ 1 ] ] );
		assert.equal( authored.state.loading, false );
	}
	const failed = await parity( {
		name: 'bulkWebhookAction',
		args: [ 'delete', [ 1 ] ],
		script: () => [ { error: REST_ERROR() } ],
	} );
	assert.equal(
		failed.authored.calls.length,
		1,
		'no reload after a failure'
	);
	assert.deepStrictEqual( failed.authored.log[ 0 ].payload.message, {
		message: 'Failed to perform bulk action',
		type: 'error',
	} );
} );

test( 'bulkWebhookAction: a failing reload does not raise an unhandled rejection', async () => {
	// The recovered store leaves that rejection unhandled, so only the authored slice is run.
	const authored = await run( 'authored', {
		name: 'bulkWebhookAction',
		args: [ 'delete', [ 1 ] ],
		script: () => [ { result: {} }, { error: REST_ERROR() } ],
	} );
	assert.equal( 'error' in authored.outcome, false );
	assert.equal( authored.calls.length, 2 );
	assert.equal( authored.state.loading, false );
	assert.ok(
		authored.log.some(
			( entry ) =>
				'SHOW_NOTIFICATION' === entry.type &&
				'Failed to load webhooks' === entry.payload.message.message
		)
	);
} );

test( 'getWebhookTriggers: GET without a method, stored, no notice on failure', async () => {
	const triggers = {
		course_purchase: 'Course purchase',
		lesson_complete: 'Lesson complete',
	};
	const ok = await parity( {
		name: 'getWebhookTriggers',
		args: [],
		script: () => [ { result: triggers } ],
	} );
	assert.deepStrictEqual( ok.authored.calls, [
		{ path: '/ohmylms/v1/webhooks/triggers' },
	] );
	assert.deepStrictEqual( ok.authored.state.triggers, triggers );
	const failed = await parity( {
		name: 'getWebhookTriggers',
		args: [],
		script: () => [ { error: REST_ERROR() } ],
	} );
	assert.deepStrictEqual( failed.authored.log, [] );
} );

test( 'testWebhook: POST to the test route, notices only', async () => {
	const payload = {
		webhook_url: 'https://example.test/hook',
		http_method: 'POST',
	};
	const ok = await parity( {
		name: 'testWebhook',
		args: [ payload ],
		script: () => [ { result: { success: true } } ],
	} );
	assert.deepStrictEqual( ok.authored.calls, [
		{ path: '/ohmylms/v1/webhooks/test', method: 'POST', data: payload },
	] );
	assert.deepStrictEqual( ok.authored.log[ 0 ].payload.message, {
		message: 'Test webhook sent successfully',
		type: 'success',
	} );
	const failed = await parity( {
		name: 'testWebhook',
		args: [ payload ],
		script: () => [
			{
				error: {
					code: 'rest_no_route',
					message:
						'No route was found matching the URL and request method.',
					data: { status: 404 },
				},
			},
		],
	} );
	assert.deepStrictEqual( failed.authored.log[ 0 ].payload.message, {
		message: 'Failed to test webhook',
		type: 'error',
	} );
} );

test( 'request shapes: exact options per function and no extra keys', async () => {
	const mock = createApiMock( [
		{ result: createResponse( [], {} ) },
		{ result: {} },
		{ result: {} },
		{ result: {} },
		{ result: {} },
		{ result: {} },
		{ result: {} },
	] );
	const client = createRestClient( { apiFetch: mock.fetch } );
	await listWebhooks(
		{ page: 3, per_page: 5, search: '', status: 'all' },
		client
	);
	await createWebhook( { name: 'A' }, client );
	await updateWebhook( 7, { name: 'B' }, client );
	await deleteWebhook( 7, client );
	await bulkWebhooks( 'inactive', [ 7, 8 ], client );
	await loadWebhookTriggers( client );
	await testWebhook( { webhook_url: 'https://example.test' }, client );
	assert.deepStrictEqual( mock.calls, [
		{
			path: '/ohmylms/v1/webhooks?page=3&per_page=5&search=&status=all',
			parse: false,
		},
		{ path: '/ohmylms/v1/webhooks', method: 'POST', data: { name: 'A' } },
		{ path: '/ohmylms/v1/webhooks/7', method: 'PUT', data: { name: 'B' } },
		{ path: '/ohmylms/v1/webhooks/7', method: 'DELETE' },
		{
			path: '/ohmylms/v1/webhooks/bulk',
			method: 'POST',
			data: { action: 'inactive', ids: [ 7, 8 ] },
		},
		{ path: '/ohmylms/v1/webhooks/triggers' },
		{
			path: '/ohmylms/v1/webhooks/test',
			method: 'POST',
			data: { webhook_url: 'https://example.test' },
		},
	] );
} );

test( 'the default client sends requests through wp.apiFetch at call time', async () => {
	const saved = globalThis.wp;
	const mock = createApiMock( [ { result: { id: 1 } } ] );
	globalThis.wp = { apiFetch: mock.fetch };
	try {
		const api = createWebhooksApi();
		assert.deepStrictEqual( await api.create( { name: 'A' } ), { id: 1 } );
		assert.deepStrictEqual( mock.calls, [
			{
				path: '/ohmylms/v1/webhooks',
				method: 'POST',
				data: { name: 'A' },
			},
		] );
		delete globalThis.wp;
		await assert.rejects( api.remove( 1 ), {
			message: 'The WordPress apiFetch package is not loaded.',
		} );
	} finally {
		globalThis.wp = saved;
		if ( undefined === saved ) {
			delete globalThis.wp;
		}
	}
} );

test( 'a custom notifier receives { message, type } and its result is dispatched', async () => {
	const seen = [];
	const mock = createApiMock( [ { result: { id: 5 } } ] );
	const thunks = createWebhookThunks( {
		api: createWebhooksApi( createRestClient( { apiFetch: mock.fetch } ) ),
		showNotification: ( message ) => {
			seen.push( message );
			return { type: 'CUSTOM_NOTICE', payload: message };
		},
	} );
	const store = createStore( webhooksReducer );
	await store.dispatch( thunks.createWebhook( { name: 'X' } ) );
	assert.deepStrictEqual( seen, [
		{ message: 'Webhook created successfully', type: 'success' },
	] );
	assert.equal( store.log.at( -1 ).type, 'CUSTOM_NOTICE' );
} );

test( 'the default notifier hides the notice after 100 ms using setTimeout', async ( t ) => {
	t.mock.timers.enable( { apis: [ 'setTimeout' ] } );
	const log = [];
	await legacyShowNotification(
		'Saved',
		'warning'
	)( {
		dispatch: ( action ) => log.push( action ),
	} );
	assert.deepStrictEqual( log, [
		{
			type: 'SHOW_NOTIFICATION',
			payload: { message: 'Saved', status: 'success' },
		},
	] );
	t.mock.timers.tick( 99 );
	assert.equal( log.length, 1 );
	t.mock.timers.tick( 1 );
	assert.deepStrictEqual( log[ 1 ], { type: 'HIDE_NOTIFICATION' } );
	const info = [];
	await legacyShowNotification(
		'FYI',
		'info'
	)( {
		dispatch: ( action ) => info.push( action ),
	} );
	assert.equal( info[ 0 ].payload.status, 'info' );
} );
