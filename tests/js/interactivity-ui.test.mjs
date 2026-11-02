import test from 'node:test';
import assert from 'node:assert/strict';
import {
	createChrome,
	createToastQueue,
	dropdownKey,
	installChrome,
	navigationFallback,
	verificationNotice,
} from '../../assets/interactivity/ui/chrome.js';
import {
	createForms,
	filterPhone,
	isActivationKey,
	isFolded,
} from '../../assets/interactivity/forms/fields.js';

/** Manual clock: timers run only on `tick()`. */
function clock() {
	let now = 0;
	let seq = 0;
	const pending = new Map();
	return {
		timers: {
			setTimeout: ( callback, ms ) => {
				pending.set( ++seq, { at: now + ms, callback } );
				return seq;
			},
			clearTimeout: ( id ) => pending.delete( id ),
			now: () => now,
		},
		tick( ms ) {
			const end = now + ms;
			for (;;) {
				const due = [ ...pending.entries() ]
					.filter( ( [ , timer ] ) => timer.at <= end )
					.sort( ( a, b ) => a[ 1 ].at - b[ 1 ].at )[ 0 ];
				if ( ! due ) {
					break;
				}
				pending.delete( due[ 0 ] );
				now = due[ 1 ].at;
				due[ 1 ].callback();
			}
			now = end;
		},
	};
}

const item = ( id, duration = 5000, message = id, type = 'success' ) => ( {
	id,
	message,
	type,
	duration,
} );

function queueFixture( options = {} ) {
	const time = clock();
	let last = null;
	const queue = createToastQueue( {
		timers: time.timers,
		onChange: ( snapshot ) => ( last = snapshot ),
		...options,
	} );
	return { queue, time, last: () => last };
}

const classes = ( ...initial ) => {
	const set = new Set( initial );
	return {
		contains: ( name ) => set.has( name ),
		toggle: ( name, force ) => {
			const on = force === undefined ? ! set.has( name ) : force;
			if ( on ) {
				set.add( name );
			} else {
				set.delete( name );
			}
			return on;
		},
		values: () => [ ...set ],
	};
};

const syncEvent = ( callback ) => callback;
const event = ( extra = {} ) => {
	const record = { prevented: false, ...extra };
	record.preventDefault = () => ( record.prevented = true );
	return record;
};

test( 'a toast hides after its duration, then fades out before the next one', () => {
	const { queue, time, last } = queueFixture();
	queue.push( item( 'a' ) );
	assert.equal( last().current.id, 'a' );
	assert.equal( last().visible, true );
	time.tick( 4999 );
	assert.equal( last().visible, true );
	time.tick( 1 );
	assert.equal( last().visible, false );
	assert.equal( last().current.id, 'a', 'kept while fading out' );
	time.tick( 300 );
	assert.equal( last().current, null );
} );

test( 'a waiting toast shortens the current one, and repeats are not stacked', () => {
	const { queue, time, last } = queueFixture();
	queue.push( item( 'a' ) );
	time.tick( 1000 );
	queue.push( item( 'b', 5000, 'Saved', 'danger' ) );
	assert.equal( last().queued, 1 );
	queue.push( item( 'b2', 5000, 'Saved', 'danger' ) );
	assert.equal( last().queued, 1, 'same message and type is dropped' );
	time.tick( 1499 );
	assert.equal( last().current.id, 'a' );
	time.tick( 1 + 300 );
	assert.equal( last().current.id, 'b' );
	assert.equal( last().visible, true );
	queue.push( item( 'b3', 5000, 'Saved', 'danger' ) );
	time.tick( 4999 );
	assert.equal(
		last().visible,
		true,
		'a repeat of the current toast restarts it'
	);
	time.tick( 1 );
	assert.equal( last().visible, false );
} );

test( 'hover or focus pauses auto-hide and keeps the remaining time', () => {
	const { queue, time, last } = queueFixture();
	queue.push( item( 'a' ) );
	time.tick( 2000 );
	queue.pause();
	assert.equal( last().paused, true );
	time.tick( 60000 );
	assert.equal( last().visible, true );
	queue.resume();
	time.tick( 2999 );
	assert.equal( last().visible, true );
	time.tick( 1 );
	assert.equal( last().visible, false );
} );

test( 'resume gives at least a second, sticky toasts wait for dismissal', () => {
	const { queue, time, last } = queueFixture( { leaveMs: () => 0 } );
	queue.push( item( 'a', 1200 ) );
	time.tick( 1100 );
	queue.pause();
	queue.resume();
	time.tick( 999 );
	assert.equal( last().visible, true );
	time.tick( 1 );
	assert.equal( last().current, null, 'no fade-out with reduced motion' );

	queue.push( item( 'sticky', 0 ) );
	time.tick( 600000 );
	assert.equal( last().current.id, 'sticky' );
	queue.dismiss();
	assert.equal( last().current, null );
} );

test( 'the queue drops its oldest entry and can drop one by id', () => {
	const { queue, last } = queueFixture( { max: 2 } );
	queue.push( item( 'a', 0 ) );
	queue.push( item( 'b' ) );
	queue.push( item( 'c' ) );
	queue.push( item( 'd' ) );
	assert.equal( last().queued, 2 );
	queue.dismiss( 'c' );
	assert.equal( last().queued, 1 );
	assert.equal( last().current.id, 'a' );
} );

function chromeFixture( overrides = {} ) {
	const time = clock();
	const state = {
		toast: {
			id: '',
			message: '',
			type: 'success',
			visible: false,
			shown: false,
		},
		messages: {},
	};
	const emitted = [];
	const frames = [];
	let context = {};
	let element = { ref: null };
	const chrome = createChrome( {
		getContext: () => context,
		getElement: () => element,
		withSyncEvent: syncEvent,
		getState: () => state,
		emit: ( target, name, detail ) =>
			emitted.push( { target, name, detail } ),
		timers: time.timers,
		frame: ( callback ) => frames.push( callback ),
		...overrides,
	} );
	return {
		chrome,
		state,
		emitted,
		time,
		flush: () => frames.splice( 0 ).forEach( ( callback ) => callback() ),
		use( nextContext, ref ) {
			context = nextContext;
			element = { ref };
		},
	};
}

test( 'toast state follows the queue and announces shown/hidden', () => {
	const fixture = chromeFixture( {
		takePendingToasts: () => [
			item( 'early', 5000, 'Queued early', 'warning' ),
		],
	} );
	const { chrome, state, emitted, time } = fixture;
	const region = {
		contains: () => false,
		ownerDocument: { activeElement: null },
	};
	fixture.use( {}, region );
	chrome.callbacks.toastRegion();
	assert.equal( state.toast.message, 'Queued early' );
	assert.equal( state.toast.visible, true );
	assert.equal( chrome.state.toastWarning, true );
	assert.equal( chrome.state.toastSuccess, false );
	assert.equal( chrome.state.toastHidden, false );
	assert.deepEqual( emitted[ 0 ], {
		target: region,
		name: 'toast-shown',
		detail: { id: 'early', type: 'warning', message: 'Queued early' },
	} );

	const click = event();
	chrome.actions.dismissToast( click );
	assert.equal( click.prevented, true );
	assert.equal( state.toast.visible, false );
	assert.equal( emitted[ 1 ].name, 'toast-hidden' );
	time.tick( 300 );
	assert.equal( state.toast.message, '' );
	assert.equal( chrome.state.toastHidden, true );

	chrome.toasts.push( item( 'e', 5000, 'Escape me', 'danger' ) );
	assert.equal( chrome.state.toastDanger, true );
	chrome.actions.toastKeydown( event( { key: 'Escape' } ) );
	assert.equal( state.toast.visible, false );
} );

test( 'pointer and focus inside the region pause the toast', () => {
	const fixture = chromeFixture();
	const { chrome, state, time } = fixture;
	const inside = {};
	const opener = {
		isConnected: true,
		focus: () => ( opener.focused = true ),
	};
	const region = {
		contains: ( node ) => node === inside,
		matches: () => false,
		ownerDocument: { activeElement: null },
	};
	fixture.use( {}, region );
	chrome.callbacks.toastRegion();
	chrome.toasts.push( item( 'a' ) );
	chrome.actions.pauseToast( { type: 'focusin', relatedTarget: opener } );
	time.tick( 10000 );
	assert.equal( state.toast.visible, true );
	chrome.actions.resumeToast( { type: 'focusout', relatedTarget: inside } );
	time.tick( 10000 );
	assert.equal( state.toast.visible, true, 'focus moved within the region' );
	region.ownerDocument.activeElement = inside;
	chrome.actions.dismissToast( event() );
	assert.equal( opener.focused, true, 'focus returns to where it came from' );
} );

test( 'the account dropdown toggles, opens from the keyboard and closes outside or on Escape', () => {
	const fixture = chromeFixture( { focusable: ( menu ) => menu.items } );
	const { chrome, emitted, flush } = fixture;
	const first = { focus: () => ( first.focused = true ) };
	const menu = { items: [ first ] };
	const trigger = {
		focus: () => ( trigger.focused = true ),
		getAttribute: () => 'ohmylms-user-dropdown',
		ownerDocument: { getElementById: () => menu },
	};
	const context = { open: false };
	fixture.use( context, trigger );
	chrome.actions.dropdownToggle( event() );
	assert.equal( context.open, true );
	assert.equal( emitted.at( -1 ).name, 'dropdown-changed' );
	chrome.actions.dropdownToggle( event() );
	assert.equal( context.open, false );

	const down = event( { key: 'ArrowDown' } );
	chrome.actions.dropdownKeydown( down );
	assert.equal( down.prevented, true );
	assert.equal( context.open, true );
	flush();
	assert.equal( first.focused, true );
	const enter = event( { key: 'Enter' } );
	chrome.actions.dropdownKeydown( enter );
	assert.equal( enter.prevented, false, 'Enter is left to the link' );
	chrome.actions.dropdownKeydown( event( { key: ' ' } ) );
	assert.equal( context.open, false );

	const inside = {};
	const root = {
		contains: ( node ) => node === inside || node === trigger,
		querySelector: () => trigger,
		ownerDocument: { activeElement: inside },
	};
	fixture.use( context, root );
	context.open = true;
	chrome.actions.dropdownOutside( { target: inside } );
	assert.equal( context.open, true );
	chrome.actions.dropdownOutside( { target: {} } );
	assert.equal( context.open, false );

	context.open = true;
	chrome.actions.dropdownEscape( { key: 'Tab' } );
	assert.equal( context.open, true );
	chrome.actions.dropdownEscape( { key: 'Escape' } );
	assert.equal( context.open, false );
	assert.equal( trigger.focused, true );

	context.open = true;
	chrome.actions.dropdownFocusout( { relatedTarget: inside } );
	assert.equal( context.open, true );
	chrome.actions.dropdownFocusout( { relatedTarget: {} } );
	assert.equal( context.open, false );
} );

test( 'the profile drawer traps focus while open and closes outside or on Escape', () => {
	const traps = [];
	const fixture = chromeFixture( {
		trapFocus: ( panel, options ) => {
			const trap = { panel, options, released: false };
			traps.push( trap );
			return () => ( trap.released = true );
		},
	} );
	const { chrome, emitted, flush } = fixture;
	const panel = { getClientRects: () => [ {} ] };
	const root = { querySelector: () => panel };
	const toggle = {
		closest: () => root,
		focus: () => ( toggle.focused = true ),
	};
	const context = {
		drawerOpen: false,
		drawerPanel: '.ohmylms-student-profile-sidebar',
		drawerInside: '.ohmylms-student-profile-sidebar,.ohmylms-hamburger',
	};
	fixture.use( context, toggle );
	chrome.actions.drawerKey( event( { key: 'x' } ) );
	assert.equal( context.drawerOpen, false );
	chrome.actions.drawerKey( event( { key: 'Enter' } ) );
	assert.equal( context.drawerOpen, true );
	assert.deepEqual( emitted.at( -1 ), {
		target: root,
		name: 'drawer-changed',
		detail: { open: true },
	} );
	flush();
	assert.equal( traps[ 0 ].panel, panel );

	fixture.use( context, root );
	chrome.actions.drawerOutside( { target: { closest: () => panel } } );
	assert.equal(
		context.drawerOpen,
		true,
		'clicks in the panel keep it open'
	);
	chrome.actions.drawerOutside( { target: { closest: () => null } } );
	assert.equal( context.drawerOpen, false );
	assert.equal( traps[ 0 ].released, true );

	fixture.use( context, toggle );
	chrome.actions.drawerToggle( event() );
	fixture.use( context, root );
	chrome.actions.drawerEscape( { key: 'Escape' } );
	assert.equal( context.drawerOpen, false );
	assert.equal( toggle.focused, true );
	flush();
	assert.equal(
		traps.length,
		1,
		'no trap for a drawer closed before the frame'
	);
} );

test( 'scroll to top appears past the threshold and respects reduced motion', () => {
	const scrolled = [];
	const win = {
		scrollY: 700,
		scrollTo: ( options ) => scrolled.push( options ),
	};
	let reduced = false;
	const fixture = chromeFixture( { win, reducedMotion: () => reduced } );
	const context = { scrolled: false, threshold: 600 };
	fixture.use( context, {} );
	fixture.chrome.callbacks.trackScroll();
	assert.equal( context.scrolled, true );
	win.scrollY = 600;
	fixture.chrome.callbacks.trackScroll();
	assert.equal( context.scrolled, false );
	fixture.chrome.actions.scrollToTop( event() );
	reduced = true;
	fixture.chrome.actions.scrollToTop( event() );
	assert.deepEqual( scrolled, [
		{ top: 0, behavior: 'smooth' },
		{ top: 0, behavior: 'auto' },
	] );
} );

test( 'verification redirects map to a message and a cleaned query string', () => {
	assert.deepEqual( verificationNotice( '?ohmylms_email_verified=1' ), {
		key: 'emailVerified',
		type: 'success',
		query: '',
	} );
	assert.deepEqual( verificationNotice( '?a=1&ohmylms_verify_sent=1&b=2' ), {
		key: 'verifySent',
		type: 'success',
		query: '?a=1&b=2',
	} );
	assert.equal(
		verificationNotice( '?ohmylms_verify_error=expired' ).key,
		'verifyExpired'
	);
	assert.equal(
		verificationNotice( '?ohmylms_verify_error=x' ).key,
		'verifyInvalid'
	);
	assert.equal( verificationNotice( '?ohmylms_verify_sent=' ), null );
	assert.equal( verificationNotice( '' ), null );
} );

test( 'keyboard intents and the block-theme menu fallback', () => {
	assert.equal( dropdownKey( 'ArrowDown', false ), 'open-first' );
	assert.equal( dropdownKey( ' ', true ), 'toggle' );
	assert.equal( dropdownKey( 'Escape', true ), 'close' );
	assert.equal( dropdownKey( 'Escape', false ), null );
	assert.equal( dropdownKey( 'Enter', false ), null );
	assert.equal( navigationFallback( 'open', false, false ), true );
	assert.equal(
		navigationFallback( 'open', false, true ),
		null,
		'core opened it'
	);
	assert.equal( navigationFallback( 'close', true, true ), false );
	assert.equal( navigationFallback( 'close', true, false ), null );
	assert.equal( navigationFallback( 'open', true, true ), null );
} );

function fakeTarget() {
	const listeners = {};
	return {
		listeners,
		addEventListener: ( type, callback ) =>
			( listeners[ type ] = [
				...( listeners[ type ] || [] ),
				callback,
			] ),
		fire: ( type, payload ) =>
			( listeners[ type ] || [] ).forEach( ( callback ) =>
				callback( payload )
			),
	};
}

test( 'document-level chrome: toast events, verification notice, resend link and menu fallback', async () => {
	const doc = {
		...fakeTarget(),
		body: {
			classList: classes( 'ohmylms-page', 'theme-twentytwentyfive' ),
		},
		documentElement: { classList: classes() },
	};
	const replaced = [];
	const win = {
		...fakeTarget(),
		document: doc,
		location: {
			search: '?ohmylms_verify_error=expired&tab=2',
			pathname: '/dashboard/',
			hash: '#top',
			href: 'http://800.local/dashboard/?ohmylms_verify_error=expired&tab=2#top',
		},
		history: {
			state: { s: 1 },
			replaceState: ( ...args ) => replaced.push( args ),
		},
	};
	const pushed = [];
	const toasts = [];
	const requests = [];
	const frames = [];
	installChrome( {
		win,
		chrome: {
			toasts: {
				push: ( value ) => pushed.push( value ),
				dismiss: ( id ) => pushed.push( { dismissed: id } ),
			},
		},
		toast: ( message, options ) => toasts.push( { message, ...options } ),
		toastItem: ( detail, options, id ) => ( {
			id: detail.id || id,
			message: detail.message,
		} ),
		request: async ( options ) => {
			requests.push( options );
			return { ok: true, message: 'Verification email sent.' };
		},
		messages: () => ( {
			verifyExpired: 'Verification link expired.',
			error: 'Something went wrong.',
		} ),
		frame: ( callback ) => frames.push( callback ),
	} );

	assert.deepEqual( replaced, [ [ { s: 1 }, '', '/dashboard/?tab=2#top' ] ] );
	assert.deepEqual( toasts.shift(), {
		message: 'Verification link expired.',
		type: 'danger',
		duration: 5000,
	} );

	const detail = { message: 'From a classic script' };
	win.fire( 'ohmylms:toast', { defaultPrevented: false, detail } );
	assert.equal( detail.accepted, true );
	assert.deepEqual( pushed.shift(), {
		id: 'ohmylms-toast-event-1',
		message: 'From a classic script',
	} );
	win.fire( 'ohmylms:toast', {
		defaultPrevented: true,
		detail: { message: 'x' },
	} );
	assert.equal( pushed.length, 0, 'cancelled toasts are not shown' );
	win.fire( 'ohmylms:toast-dismiss', { detail: { id: 't9' } } );
	assert.deepEqual( pushed.shift(), { dismissed: 't9' } );

	const attributes = {};
	const link = {
		href: 'http://800.local/wp-admin/admin-ajax.php?action=ohmylms_resend_verification&nonce=abc',
		getAttribute: ( name ) => attributes[ name ] ?? null,
		setAttribute: ( name, value ) => ( attributes[ name ] = value ),
		removeAttribute: ( name ) => delete attributes[ name ],
	};
	const click = event( {
		button: 0,
		defaultPrevented: false,
		target: {
			closest: ( selector ) =>
				selector.includes( 'resend' ) ? link : null,
		},
	} );
	doc.fire( 'click', click );
	assert.equal( click.prevented, true );
	assert.equal( attributes[ 'aria-busy' ], 'true' );
	doc.fire( 'click', click );
	assert.equal( requests.length, 1, 'a second click while busy is ignored' );
	assert.equal(
		requests[ 0 ].url,
		'http://800.local/wp-admin/admin-ajax.php?action=ohmylms_resend_verification&nonce=abc&format=json'
	);
	await new Promise( ( resolve ) => setImmediate( resolve ) );
	assert.deepEqual( toasts.shift(), {
		message: 'Verification email sent.',
		type: 'success',
		duration: 5000,
	} );
	assert.equal( attributes[ 'aria-busy' ], undefined );

	const container = { classList: classes() };
	const open = {
		classList: classes( 'wp-block-navigation__responsive-container-open' ),
		parentElement: { querySelector: () => container },
	};
	doc.fire( 'click', {
		target: {
			closest: ( selector ) =>
				selector.includes( 'navigation' ) ? open : null,
		},
	} );
	frames.shift()();
	assert.equal( container.classList.contains( 'is-menu-open' ), true );
	assert.equal(
		doc.documentElement.classList.contains( 'has-modal-open' ),
		true
	);

	const close = {
		classList: classes( 'wp-block-navigation__responsive-container-close' ),
		closest: () => container,
	};
	doc.fire( 'click', {
		target: {
			closest: ( selector ) =>
				selector.includes( 'navigation' ) ? close : null,
		},
	} );
	container.classList.toggle( 'is-menu-open', false );
	frames.shift()();
	assert.equal(
		doc.documentElement.classList.contains( 'has-modal-open' ),
		true,
		'core closed the menu itself, the fallback stays out of the way'
	);
} );

test( 'password visibility toggles the field type and the eye icons', () => {
	const context = { revealed: false };
	const forms = createForms( {
		getContext: () => context,
		getElement: () => ( {} ),
		withSyncEvent: syncEvent,
	} );
	assert.equal( forms.state.passwordType, 'password' );
	assert.equal( forms.state.eyeOnDisplay, false );
	assert.equal( forms.state.eyeOffDisplay, false );
	const click = event();
	forms.actions.togglePassword( click );
	assert.equal(
		click.prevented,
		true,
		'the wrapping label must not toggle its checkbox too'
	);
	assert.equal( forms.state.passwordType, 'text' );
	assert.equal( forms.state.eyeOnDisplay, 'inline' );
	assert.equal( forms.state.eyeOffDisplay, 'none' );
	forms.actions.passwordKey( event( { key: 'a' } ) );
	assert.equal( context.revealed, true );
	forms.actions.passwordKey( event( { key: 'Enter' } ) );
	assert.equal( context.revealed, false );
} );

test( 'checkbox labels toggle from the keyboard and the terms gate the submit button', () => {
	const context = { consent: false };
	const box = { disabled: false, clicks: 0, click: () => box.clicks++ };
	const label = { control: box };
	const forms = createForms( {
		getContext: () => context,
		getElement: () => ( { ref: label } ),
		withSyncEvent: syncEvent,
	} );
	const space = event( { key: ' ', target: label } );
	forms.actions.checkboxKey( space );
	assert.equal( space.prevented, true );
	assert.equal( box.clicks, 1 );
	forms.actions.checkboxKey( event( { key: 'Enter', target: {} } ) );
	assert.equal(
		box.clicks,
		1,
		'keys on a link inside the label are left alone'
	);
	box.disabled = true;
	forms.actions.checkboxKey( event( { key: 'Enter', target: label } ) );
	assert.equal( box.clicks, 1 );

	assert.equal( forms.state.consentMissing, true );
	forms.actions.consentChange( { target: { checked: true } } );
	assert.equal( forms.state.consentMissing, false );
} );

test( 'phone fields keep digits, + and separators, with the caret in place', () => {
	assert.deepEqual( filterPhone( '+976 99a11-22', 8 ), {
		value: '+976 9911-22',
		caret: 7,
	} );
	assert.deepEqual( filterPhone( '(011) 22', null ), {
		value: '(011) 22',
		caret: 8,
	} );
	assert.deepEqual( filterPhone( undefined ), { value: '', caret: 0 } );
	const field = {
		value: '99x',
		selectionStart: 3,
		setSelectionRange: ( start ) => ( field.caret = start ),
	};
	const forms = createForms( {
		getContext: () => ( {} ),
		getElement: () => ( {} ),
		withSyncEvent: syncEvent,
	} );
	forms.actions.filterPhone( { target: field } );
	assert.equal( field.value, '99' );
	assert.equal( field.caret, 2 );
} );

test( 'floating labels fold while focused or filled, including autofill after load', () => {
	assert.equal( isFolded( '', true ), true );
	assert.equal( isFolded( ' a ', false ), true );
	assert.equal( isFolded( '  ', false ), false );
	assert.equal( isActivationKey( 'Spacebar' ), true );
	assert.equal( isActivationKey( 'Tab' ), false );

	const row = { classList: classes() };
	const doc = { activeElement: null };
	const field = {
		value: '',
		ownerDocument: doc,
		matches: ( selector ) => '.ohmylms-input-text' === selector,
		closest: () => row,
	};
	const later = [];
	const forms = createForms( {
		getContext: () => ( {} ),
		getElement: () => ( {
			ref: { ownerDocument: doc, querySelectorAll: () => [ field ] },
		} ),
		withSyncEvent: syncEvent,
		later: ( callback ) => later.push( callback ),
	} );
	forms.actions.fieldFocus( { target: field } );
	assert.equal( row.classList.contains( 'ohmylms-folded' ), true );
	forms.actions.fieldBlur( { target: field } );
	assert.equal( row.classList.contains( 'ohmylms-folded' ), false );
	forms.callbacks.foldFilled();
	field.value = 'saved@example.com';
	later.shift()();
	assert.equal( row.classList.contains( 'ohmylms-folded' ), true );
	forms.actions.fieldFocus( { target: { matches: () => false } } );
} );
