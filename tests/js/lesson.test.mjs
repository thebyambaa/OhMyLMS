import test from 'node:test';
import assert from 'node:assert/strict';
import {
	COMPLETE_ACTION,
	COMPLETE_REQUEST_EVENT,
	LEGACY_VIDEO_EVENT,
	NAVIGATE_DELAY,
	completeDisabled,
	completionOutcome,
	createLesson,
	requestMatches,
	sameOriginUrl,
} from '../../assets/interactivity/lesson/store.js';
import {
	BLUR_CLASS,
	blurOnLeave,
	installProtection,
	shortcutAction,
} from '../../assets/interactivity/protection/rules.js';

const ORIGIN = 'https://school.example/lesson/one/';

const ok = ( data = null, message = '' ) => ( {
	ok: true,
	status: 200,
	data,
	message,
	error: null,
} );
const failed = ( message, code = 'ajax_error' ) => ( {
	ok: false,
	status: 403,
	data: { code, message },
	message,
	error: { code, message, status: 403 },
} );

/** A document-like event target. */
function target() {
	const listeners = new Map();
	return {
		listeners,
		addEventListener( name, callback ) {
			listeners.set( name, [ ...( listeners.get( name ) || [] ), callback ] );
		},
		removeEventListener( name, callback ) {
			listeners.set(
				name,
				( listeners.get( name ) || [] ).filter( ( item ) => item !== callback )
			);
		},
		fire( name, event ) {
			( listeners.get( name ) || [] ).forEach( ( callback ) => callback( event ) );
		},
	};
}

function fixture( options = {} ) {
	const log = { requests: [], toasts: [], events: [], timers: [], visits: [] };
	const doc = target();
	const root = { ownerDocument: doc };
	const env = {
		element: root,
		context: {
			lessonId: 12,
			completed: false,
			busy: false,
			videoPending: false,
			nextUrl: 'https://school.example/lesson/two/',
			...options.context,
		},
	};
	const responses = [ ...( options.responses || [] ) ];
	const jquery = { handlers: new Map() };
	const lesson = createLesson( {
		getContext: () => env.context,
		getElement: () => ( { ref: env.element } ),
		withSyncEvent: ( callback ) => callback,
		getState: () => ( {
			nonces: { complete: 'n-complete' },
			messages: {
				markComplete: 'Mark as Complete',
				completed: 'Completed',
				failed: 'failed',
			},
		} ),
		request: async ( request ) => {
			log.requests.push( request );
			return responses.shift() || ok( { next_content_link: '' }, 'Done.' );
		},
		toast: ( message, toastOptions = {} ) =>
			log.toasts.push( [ message, toastOptions.type || 'success' ] ),
		emit: ( node, name, detail, cancelable ) => {
			log.events.push( [ name, detail ] );
			return ! ( cancelable && options.block === name );
		},
		win: {
			location: {
				href: ORIGIN,
				assign: ( url ) => log.visits.push( url ),
				reload: () => log.visits.push( 'reload' ),
			},
			jQuery: options.jquery
				? ( node ) => ( {
						on: ( name, callback ) => jquery.handlers.set( name, { node, callback } ),
						off: ( name ) => jquery.handlers.delete( name ),
				  } )
				: undefined,
		},
		later: ( callback, ms ) => log.timers.push( [ callback, ms ] ),
	} );
	return { lesson, env, log, doc, root, jquery };
}

const settle = () => new Promise( ( resolve ) => setImmediate( resolve ) );

function checkbox( root, checked = true ) {
	return { checked, disabled: false, closest: () => root };
}

test( 'sameOriginUrl follows only links on the page origin', () => {
	assert.equal( sameOriginUrl( '/lesson/two/', ORIGIN ), 'https://school.example/lesson/two/' );
	for ( const value of [ '', '  ', null, 'https://evil.example/x', 'javascript:alert(1)' ] ) {
		assert.equal( sameOriginUrl( value, ORIGIN ), '' );
	}
} );

test( 'completionOutcome: next link, reload on the last lesson, or the server error', () => {
	assert.deepEqual(
		completionOutcome( ok( { next_content_link: '/lesson/two/' } ), ORIGIN ),
		{ type: 'navigate', url: 'https://school.example/lesson/two/' }
	);
	assert.deepEqual( completionOutcome( ok( { next_content_link: '' } ), ORIGIN ), {
		type: 'reload',
	} );
	assert.deepEqual(
		completionOutcome( ok( { next_content_link: 'https://evil.example/' } ), ORIGIN ),
		{ type: 'reload' }
	);
	assert.deepEqual(
		completionOutcome( failed( 'Locked.', 'ohmylms_lesson_locked' ), ORIGIN, 'fallback' ),
		{ type: 'error', code: 'ohmylms_lesson_locked', message: 'Locked.' }
	);
	assert.equal(
		completionOutcome( { ok: false, message: '' }, ORIGIN, 'fallback' ).message,
		'fallback'
	);
} );

test( 'completeDisabled and requestMatches', () => {
	assert.equal( completeDisabled( {} ), false );
	assert.equal( completeDisabled( { completed: true } ), true );
	assert.equal( completeDisabled( { busy: true } ), true );
	assert.equal( completeDisabled( { videoPending: true } ), true );
	assert.equal( requestMatches( {}, { lessonId: 3 } ), true );
	assert.equal( requestMatches( { lessonId: 3 }, { lessonId: 3 } ), true );
	assert.equal( requestMatches( { lessonId: '3' }, { lessonId: 3 } ), true );
	assert.equal( requestMatches( { lessonId: 4 }, { lessonId: 3 } ), false );
} );

test( 'ticking the box completes the lesson once and goes to the next content', async () => {
	const { lesson, env, log, root } = fixture( {
		responses: [ ok( { next_content_link: '/lesson/two/' }, 'Lesson completed successfully.' ) ],
	} );
	const input = checkbox( root );
	lesson.actions.complete( { target: input } );
	assert.equal( env.context.busy, true );
	assert.equal( lesson.state.completeDisabled, true, 'disabled while the request runs' );
	lesson.actions.complete( { target: input } );
	await settle();
	assert.equal( log.requests.length, 1, 'a double click sends one request' );
	assert.deepEqual( log.requests[ 0 ], {
		action: COMPLETE_ACTION,
		nonce: 'n-complete',
		data: { lesson_id: 12 },
	} );
	assert.equal( env.context.completed, true );
	assert.equal( lesson.state.completeLabel, 'Completed' );
	assert.deepEqual( log.toasts, [ [ 'Lesson completed successfully.', 'success' ] ] );
	assert.deepEqual( log.events.map( ( [ name ] ) => name ), [
		'lesson-before-complete',
		'lesson-completed',
	] );
	assert.equal( log.events[ 1 ][ 1 ].nextUrl, 'https://school.example/lesson/two/' );
	assert.equal( log.timers[ 0 ][ 1 ], NAVIGATE_DELAY );
	log.timers[ 0 ][ 0 ]();
	assert.deepEqual( log.visits, [ 'https://school.example/lesson/two/' ] );
	lesson.actions.complete( { target: input } );
	await settle();
	assert.equal( log.requests.length, 1, 'a completed lesson sends nothing' );
} );

test( 'the last lesson reloads; an unticked box sends nothing', async () => {
	const { lesson, log, root } = fixture( {
		responses: [ ok( { next_content_link: '' }, 'Done.' ) ],
	} );
	lesson.actions.complete( { target: checkbox( root, false ) } );
	await settle();
	assert.equal( log.requests.length, 0 );
	lesson.actions.complete( { target: checkbox( root ) } );
	await settle();
	log.timers[ 0 ][ 0 ]();
	assert.deepEqual( log.visits, [ 'reload' ] );
} );

test( 'a refusal unticks the box and shows the server message', async () => {
	const { lesson, env, log, root } = fixture( {
		responses: [ failed( 'Watch at least 50% of the video.', 'ohmylms_lesson_video_not_watched' ) ],
	} );
	const input = checkbox( root );
	lesson.actions.complete( { target: input } );
	await settle();
	assert.equal( input.checked, false );
	assert.equal( env.context.completed, false );
	assert.equal( env.context.busy, false );
	assert.equal( env.context.videoPending, true, 'the video rule comes back' );
	assert.deepEqual( log.toasts, [ [ 'Watch at least 50% of the video.', 'danger' ] ] );
	assert.equal( log.events.at( -1 )[ 0 ], 'lesson-complete-error' );
	assert.equal( log.events.at( -1 )[ 1 ].code, 'ohmylms_lesson_video_not_watched' );
	assert.equal( log.timers.length, 0 );
} );

test( 'a cancelled ohmylms:lesson-before-complete sends nothing', async () => {
	const { lesson, log, root } = fixture( { block: 'lesson-before-complete' } );
	const input = checkbox( root );
	lesson.actions.complete( { target: input } );
	await settle();
	assert.equal( log.requests.length, 0 );
	assert.equal( input.checked, false );
} );

test( 'Enter or Space on the label clicks an enabled checkbox', () => {
	const { lesson, env } = fixture();
	let clicks = 0;
	const input = { disabled: false, click: () => clicks++ };
	const label = { querySelector: () => input };
	env.element = label;
	const key = ( value, from = label ) => {
		const event = { key: value, target: from, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
		lesson.actions.checkboxKey( event );
		return event;
	};
	assert.equal( key( 'Enter' ).defaultPrevented, true );
	key( ' ' );
	key( 'a' );
	key( 'Enter', input );
	assert.equal( clicks, 2 );
	input.disabled = true;
	key( 'Enter' );
	assert.equal( clicks, 2 );
} );

test( 'ohmylms:lesson-complete-request completes through the same endpoint, without leaving a playing video', async () => {
	const { lesson, env, log, doc } = fixture( {
		responses: [ ok( { next_content_link: '/lesson/two/' }, 'Lesson completed successfully.' ) ],
	} );
	const cleanup = lesson.callbacks.listen();
	doc.fire( COMPLETE_REQUEST_EVENT, { detail: { lessonId: 99, source: 'video' } } );
	await settle();
	assert.equal( log.requests.length, 0, 'another lesson is ignored' );
	doc.fire( COMPLETE_REQUEST_EVENT, { detail: { source: 'video' } } );
	await settle();
	assert.equal( log.requests.length, 1 );
	assert.equal( log.requests[ 0 ].action, COMPLETE_ACTION );
	assert.equal( env.context.completed, true );
	assert.equal( log.events.at( -1 )[ 1 ].source, 'video' );
	assert.equal( log.timers.length, 0, 'no navigation unless asked' );
	cleanup();
	assert.equal( doc.listeners.get( COMPLETE_REQUEST_EVENT ).length, 0 );
} );

test( 'a request with completed: true only updates the page (the server already completed it)', async () => {
	const { lesson, env, log, doc } = fixture( { context: { videoPending: true } } );
	lesson.callbacks.listen();
	doc.fire( COMPLETE_REQUEST_EVENT, { detail: { lessonId: 12, source: 'video', completed: true } } );
	await settle();
	assert.equal( log.requests.length, 0 );
	assert.equal( env.context.completed, true );
	assert.equal( env.context.videoPending, false );
	assert.equal( log.events.at( -1 )[ 0 ], 'lesson-completed' );
	doc.fire( COMPLETE_REQUEST_EVENT, { detail: { completed: true } } );
	assert.equal( log.events.filter( ( [ name ] ) => 'lesson-completed' === name ).length, 1 );
} );

test( 'navigate: true follows the next link like the checkbox', async () => {
	const { lesson, log, doc } = fixture( {
		responses: [ ok( { next_content_link: '/lesson/two/' }, 'ok' ) ],
	} );
	lesson.callbacks.listen();
	doc.fire( COMPLETE_REQUEST_EVENT, { detail: { navigate: true } } );
	await settle();
	log.timers[ 0 ][ 0 ]();
	assert.deepEqual( log.visits, [ 'https://school.example/lesson/two/' ] );
} );

test( 'the legacy video tracker jQuery event marks the lesson completed', () => {
	const { lesson, env, jquery } = fixture( { jquery: true } );
	const cleanup = lesson.callbacks.listen();
	jquery.handlers.get( LEGACY_VIDEO_EVENT ).callback( {}, { lessonId: 12, courseId: 3 } );
	assert.equal( env.context.completed, true );
	cleanup();
	assert.equal( jquery.handlers.has( LEGACY_VIDEO_EVENT ), false );
} );

test( 'protection: blocked shortcuts', () => {
	const cases = [
		[ { key: 'F12' }, 'blur' ],
		[ { key: 'PrintScreen' }, 'blur' ],
		[ { key: 'u', ctrlKey: true }, 'blur' ],
		[ { key: 's', metaKey: true }, 'blur' ],
		[ { key: 'p', ctrlKey: true }, 'blur' ],
		[ { key: 'I', ctrlKey: true, shiftKey: true }, 'blur' ],
		[ { key: 'j', metaKey: true, altKey: true }, 'blur' ],
		[ { key: '4', metaKey: true, shiftKey: true }, 'blur' ],
		[ { key: 'F1' }, 'block' ],
		[ { key: 'ContextMenu' }, 'block' ],
		[ { key: 'A', ctrlKey: true, shiftKey: true }, 'block' ],
		[ { key: 'a', ctrlKey: true }, '' ],
		[ { key: 'f', ctrlKey: true }, '' ],
		[ { key: 'c', ctrlKey: true }, '' ],
		[ { key: 't', metaKey: true, shiftKey: true }, '' ],
		[ { key: 'F5' }, '' ],
		[ { key: 'Tab' }, '' ],
		[ { key: 'x' }, '' ],
	];
	for ( const [ event, expected ] of cases ) {
		assert.equal( shortcutAction( event ), expected, JSON.stringify( event ) );
	}
} );

test( 'protection: leaving the window blurs, unless focus went into an embedded player', () => {
	assert.equal( blurOnLeave( null ), true );
	assert.equal( blurOnLeave( { tagName: 'IFRAME', classList: { contains: () => false } } ), false );
	assert.equal( blurOnLeave( { tagName: 'BODY', classList: { contains: ( name ) => 'single-ohmylms-lesson' === name } } ), false );
	assert.equal( blurOnLeave( { tagName: 'BUTTON', classList: { contains: () => false } } ), true );
} );

test( 'protection: listeners block copy and shortcuts and blur the page, and clean up', () => {
	const doc = target();
	const win = target();
	const classes = new Set();
	doc.body = {
		classList: {
			toggle: ( name, on ) => ( on ? classes.add( name ) : classes.delete( name ) ),
			remove: ( name ) => classes.delete( name ),
		},
	};
	let recorder = null;
	doc.querySelector = () => recorder;
	doc.activeElement = { tagName: 'BUTTON', classList: { contains: () => false } };
	const timers = [];
	const polls = [];
	const cleanup = installProtection( {
		win,
		doc,
		later: ( callback ) => timers.push( callback ),
		every: ( callback ) => {
			polls.push( callback );
			return 'poll';
		},
		stopEvery: ( handle ) => polls.push( `stopped:${ handle }` ),
	} );
	const event = ( extra = {} ) => ( {
		prevented: false,
		stopped: false,
		preventDefault() {
			this.prevented = true;
		},
		stopPropagation() {
			this.stopped = true;
		},
		...extra,
	} );
	const copy = event();
	doc.fire( 'copy', copy );
	assert.equal( copy.prevented, true );
	const menu = event();
	doc.fire( 'contextmenu', menu );
	assert.equal( menu.prevented, true );
	const print = event( { key: 'p', ctrlKey: true } );
	doc.fire( 'keydown', print );
	assert.equal( print.prevented, true );
	assert.equal( classes.has( BLUR_CLASS ), true );
	timers.shift()();
	assert.equal( classes.has( BLUR_CLASS ), false, 'the shortcut blur lifts' );
	const typing = event( { key: 'a' } );
	doc.fire( 'keydown', typing );
	assert.equal( typing.prevented, false );
	win.fire( 'blur' );
	assert.equal( classes.has( BLUR_CLASS ), true );
	win.fire( 'focus' );
	assert.equal( classes.has( BLUR_CLASS ), false );
	recorder = {};
	polls[ 0 ]();
	assert.equal( classes.has( BLUR_CLASS ), true, 'a screen recorder blurs the page' );
	cleanup();
	assert.equal( classes.has( BLUR_CLASS ), false );
	assert.equal( polls.at( -1 ), 'stopped:poll' );
	assert.equal( doc.listeners.get( 'copy' ).length, 0 );
	assert.equal( win.listeners.get( 'blur' ).length, 0 );
} );
