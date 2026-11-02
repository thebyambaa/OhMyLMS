import test from 'node:test';
import assert from 'node:assert/strict';
import {
	AUTH_ACTIONS,
	authDisplay,
	authOutcome,
	createAuth,
	formKind,
	safeRedirect,
	validateAuth,
} from '../../assets/interactivity/forms/auth.js';

const HERE = 'https://school.test/profile/';
const syncEvent = ( callback ) => callback;

function event( extra = {} ) {
	return {
		prevented: false,
		defaultPrevented: false,
		preventDefault() {
			this.prevented = true;
			this.defaultPrevented = true;
		},
		...extra,
	};
}

/**
 * A login or sign-up form double: `elements.namedItem()`, `dataset`, an optional error region.
 *
 * @param {Object<string,string>} values Fields.
 * @param {Object}                [opts] `region: false` for an override without the live region.
 */
function fakeForm( values, { region = true, dataset = {} } = {} ) {
	return {
		values,
		dataset,
		elements: {
			namedItem: ( name ) =>
				name in values ? { value: values[ name ] } : null,
		},
		querySelector: ( selector ) =>
			region && '.ohmylms-form-error' === selector ? {} : null,
	};
}

/**
 * Store harness: context, state, captured requests, toasts, events and navigation.
 *
 * @param {Object} opts Options.
 */
function harness( {
	context = {},
	reply = { ok: true, data: {} },
	form,
	doc = null,
	cancel = '',
} = {} ) {
	const state = {
		authView: '',
		pendingMessage: '',
		messages: {
			error: 'Something went wrong.',
			passwordShort: 'Too short.',
			usernameRequired: 'Username please.',
			consent: 'Accept the terms.',
		},
	};
	const calls = { requests: [], toasts: [], events: [], navigate: [] };
	let element = { ref: form };
	const auth = createAuth( {
		getContext: () => context,
		getElement: () => element,
		withSyncEvent: syncEvent,
		getState: () => state,
		request: async ( options ) => {
			calls.requests.push( options );
			return reply;
		},
		serializeForm: ( target, { as } = {} ) =>
			'object' === as ? { ...target.values } : { form: target.values },
		toast: ( message, options ) => calls.toasts.push( { message, options } ),
		emit: ( target, name, detail ) => {
			calls.events.push( { name, detail } );
			return name !== cancel;
		},
		plainText: ( value ) =>
			String( value ?? '' )
				.replace( /<[^>]*>/g, '' )
				.trim(),
		navigate: ( url ) => calls.navigate.push( url ),
		here: () => HERE,
		doc,
		later: ( callback ) => callback(),
	} );
	return {
		auth,
		state,
		context,
		calls,
		setElement: ( ref ) => ( element = { ref } ),
	};
}

test( 'the form kind comes from the hidden action field', () => {
	assert.equal(
		formKind( fakeForm( { action: 'ohmylms_login' } ) ),
		'login'
	);
	assert.equal(
		formKind( fakeForm( { action: 'ohmylms_signup' } ) ),
		'signup'
	);
	assert.equal( formKind( fakeForm( { action: 'other' } ) ), '' );
	assert.equal( formKind( null ), '' );
	assert.deepEqual( AUTH_ACTIONS, {
		login: 'ohmylms_login',
		signup: 'ohmylms_signup',
	} );
} );

test( 'client validation: username, 8-character password, terms', () => {
	const messages = {
		usernameRequired: 'u',
		passwordShort: 'p',
		consent: 'c',
	};
	assert.equal( validateAuth( 'login', { username: '  ' }, messages ), 'u' );
	assert.equal(
		validateAuth( 'login', { username: 'ana', password: 'x' }, messages ),
		'',
		'the server checks login passwords'
	);
	assert.equal(
		validateAuth(
			'signup',
			{ password: '1234567', 'tnc-accept': 'on' },
			messages
		),
		'p'
	);
	assert.equal(
		validateAuth( 'signup', { password: '12345678' }, messages ),
		'c'
	);
	assert.equal(
		validateAuth(
			'signup',
			{ password: '12345678', 'tnc-accept': 'on' },
			messages
		),
		''
	);
	assert.match( validateAuth( 'signup', {} ), /8 characters/ );
} );

test( 'redirects stay on the page origin', () => {
	assert.equal(
		safeRedirect( '/learn/', HERE ),
		'https://school.test/learn/'
	);
	assert.equal(
		safeRedirect( 'https://school.test/x?a=1', HERE ),
		'https://school.test/x?a=1'
	);
	assert.equal( safeRedirect( 'https://evil.test/', HERE ), null );
	assert.equal( safeRedirect( 'javascript:alert(1)', HERE ), null );
	assert.equal( safeRedirect( '', HERE ), null );
	assert.equal( safeRedirect( 42, HERE ), null );
} );

test( 'server answers map to redirect, pending or error', () => {
	assert.deepEqual(
		authOutcome(
			{
				ok: true,
				data: {
					status: 'success',
					redirect_url: '/learn/',
					message: 'ok',
				},
			},
			HERE
		),
		{ type: 'redirect', url: 'https://school.test/learn/', message: 'ok' }
	);
	assert.equal(
		authOutcome(
			{
				ok: true,
				data: { status: 'success', redirect_url: 'https://evil.test' },
			},
			HERE
		).url,
		HERE,
		'an unusable redirect reloads the page'
	);
	assert.deepEqual(
		authOutcome(
			{
				ok: true,
				data: { status: 'pending_verification', message: 'Check' },
			},
			HERE
		),
		{ type: 'pending', message: 'Check' }
	);
	assert.deepEqual(
		authOutcome(
			{ ok: false, message: '<li>Bad password</li>', data: {} },
			HERE
		),
		{ type: 'error', message: '<li>Bad password</li>' }
	);
	assert.deepEqual(
		authOutcome( { ok: false, message: '' }, HERE, { error: 'E' } ),
		{ type: 'error', message: 'E' }
	);
} );

test( 'form display follows the server default, then the chosen view', () => {
	const login = { authForm: 'login', authHidden: false };
	const signup = { authForm: 'signup', authHidden: true };
	assert.equal( authDisplay( login, {} ), false );
	assert.equal( authDisplay( signup, {} ), 'none' );
	assert.equal( authDisplay( login, { authView: 'signup' } ), 'none' );
	assert.equal( authDisplay( signup, { authView: 'signup' } ), 'block' );
	assert.equal(
		authDisplay( signup, { authView: 'signup', pendingMessage: 'x' } ),
		'none',
		'the pending notice replaces the sign-up form'
	);
} );

test( 'login posts the form to ohmylms_login and follows the redirect', async () => {
	const form = fakeForm( {
		action: 'ohmylms_login',
		username: 'ana',
		password: 'secret',
	} );
	const run = harness( {
		form,
		context: { authForm: 'login', busy: false, error: 'old' },
		reply: {
			ok: true,
			data: { status: 'success', redirect_url: '/checkout/' },
		},
	} );
	const submit = event();
	const pending = run.auth.actions.submit( submit );
	assert.equal( submit.prevented, true );
	assert.equal( run.context.busy, true );
	assert.equal( run.context.error, '' );
	assert.equal( run.auth.state.submitDisabled, true );
	assert.equal( run.auth.state.loaderDisplay, 'inline-block' );
	run.auth.actions.submit( event() );
	await pending;
	assert.equal( run.calls.requests.length, 1, 'a second submit is ignored' );
	assert.deepEqual( run.calls.requests[ 0 ], {
		action: 'ohmylms_login',
		data: { form: form.values },
	} );
	assert.deepEqual( run.calls.navigate, [ 'https://school.test/checkout/' ] );
	assert.equal( run.context.busy, true, 'stays busy while the page leaves' );
	assert.deepEqual(
		run.calls.events.map( ( item ) => item.name ),
		[ 'auth-before-submit', 'auth-complete' ]
	);
} );

test( 'a failed login shows the server message inline as text', async () => {
	const form = fakeForm( {
		action: 'ohmylms_login',
		username: 'ana',
		password: 'x',
	} );
	const run = harness( {
		form,
		context: { authForm: 'login', busy: false, error: '' },
		reply: { ok: false, message: '<ul><li>Wrong password.</li></ul>' },
	} );
	await run.auth.actions.submit( event() );
	assert.equal( run.context.busy, false );
	assert.equal( run.context.error, 'Wrong password.' );
	assert.equal( run.auth.state.errorHidden, false );
	assert.equal( run.calls.toasts.length, 0, 'errors go to the live region' );
	assert.equal( run.calls.events.at( -1 ).name, 'auth-error' );
} );

test( 'an override without the live region gets a toast instead', async () => {
	const form = fakeForm(
		{ action: 'ohmylms_login', username: '', password: 'x' },
		{ region: false }
	);
	const run = harness( {
		form,
		context: { authForm: 'login', busy: false, error: '' },
	} );
	await run.auth.actions.submit( event() );
	assert.equal( run.calls.requests.length, 0, 'invalid forms are not sent' );
	assert.deepEqual( run.calls.toasts, [
		{ message: 'Username please.', options: { type: 'danger' } },
	] );
} );

test( 'sign-up checks the password first and shows the pending notice', async () => {
	const values = {
		action: 'ohmylms_signup',
		email: 'a@b.test',
		password: 'short',
		'tnc-accept': 'on',
	};
	const doc = {
		querySelector: ( selector ) =>
			'.ohmylms-email-pending-notice' === selector ? {} : null,
	};
	const run = harness( {
		form: fakeForm( values ),
		doc,
		context: { authForm: 'signup', consent: true, busy: false, error: '' },
		reply: {
			ok: true,
			data: { status: 'pending_verification', message: 'Check <b>mail</b>' },
		},
	} );
	await run.auth.actions.submit( event() );
	assert.equal( run.context.error, 'Too short.' );
	assert.equal( run.calls.requests.length, 0 );

	values.password = 'long enough';
	await run.auth.actions.submit( event() );
	assert.equal( run.calls.requests[ 0 ].action, 'ohmylms_signup' );
	assert.equal( run.state.pendingMessage, 'Check mail' );
	assert.equal( run.auth.state.pendingHidden, false );
	assert.equal( run.auth.state.authDisplay, 'none' );
	assert.equal( run.context.busy, false );
	assert.equal( run.calls.toasts.length, 0, 'the template notice is used' );
	assert.equal(
		run.calls.events.at( -1 ).detail.status,
		'pending_verification'
	);
} );

test( 'the terms gate disables the sign-up button', () => {
	const run = harness( {
		context: { authForm: 'signup', consent: false, busy: false },
	} );
	assert.equal( run.auth.state.submitDisabled, true );
	run.context.consent = true;
	assert.equal( run.auth.state.submitDisabled, false );
} );

test( 'submission is skipped for other forms, React fields and cancelled events', async () => {
	const other = harness( { form: fakeForm( { action: 'search' } ) } );
	const plain = event();
	other.auth.actions.submit( plain );
	assert.equal( plain.prevented, false, 'other forms submit natively' );

	const react = harness( {
		form: fakeForm(
			{ action: 'ohmylms_signup' },
			{ dataset: { ohmylmsRegistration: 'react' } }
		),
		context: { authForm: 'signup', consent: true },
	} );
	const reactEvent = event();
	react.auth.actions.submit( reactEvent );
	assert.equal( reactEvent.prevented, false );

	const cancelled = harness( {
		form: fakeForm( {
			action: 'ohmylms_login',
			username: 'ana',
			password: 'x',
		} ),
		context: { authForm: 'login', busy: false, error: '' },
		cancel: 'auth-before-submit',
	} );
	await cancelled.auth.actions.submit( event() );
	assert.equal( cancelled.calls.requests.length, 0 );
	assert.equal( cancelled.context.busy, false );
} );

test( 'the switch links change the view, hide checkout and focus the form', () => {
	const checkout = { style: { display: '' } };
	const focused = [];
	const doc = {
		getElementById: ( id ) =>
			'ohmylms-checkout-form' === id ? checkout : null,
		querySelector: ( selector ) => ( {
			focus: () => focused.push( selector ),
		} ),
	};
	const link = {};
	const run = harness( { doc, form: link } );
	const click = event();
	run.auth.actions.showSignup( click );
	assert.equal( click.prevented, true );
	assert.equal( run.state.authView, 'signup' );
	assert.equal( checkout.style.display, 'none' );
	assert.deepEqual( focused, [
		'.ohmylms-form-signup input:not([type="hidden"])',
	] );
	assert.deepEqual( run.calls.events.at( -1 ), {
		name: 'auth-view-changed',
		detail: { view: 'signup' },
	} );
	run.auth.actions.showLogin( event() );
	assert.equal( run.state.authView, 'login' );
} );
