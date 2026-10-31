import test from 'node:test';
import assert from 'node:assert/strict';
import {
	NAVIGATE_DELAY,
	START_ACTION,
	SUBMIT_ACTION,
	TIME_EXPIRED,
	createAssignment,
	fileEntry,
	fileProblem,
	formatRemaining,
	formatSize,
	parseDeadline,
	remainingSeconds,
	submitDisabled,
	timerWidth,
} from '../../assets/interactivity/assignment/store.js';

const ORIGIN = 'https://school.example/assignment/essay/';
const T0 = Date.parse( '2026-10-31T10:00:00Z' );

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

const MESSAGES = {
	submitFailed: 'submit failed',
	selected: 'Selected file: %s',
	removed: 'removed',
	timeUp: 'time is up',
	autoSubmit: 'submitting',
	startFailed: 'start failed',
};

/** A store with a fake runtime and SDK. `clock.device` is the device time; the server is 5 s ahead. */
function fixture( options = {} ) {
	const log = {
		requests: [],
		toasts: [],
		events: [],
		announced: [],
		timers: [],
		visits: [],
		intervals: [],
		stopped: 0,
	};
	const clock = { device: T0 };
	const inputs = [ { value: 'C:\\fakepath\\essay.pdf' } ];
	const form = {
		querySelector: () => null,
		querySelectorAll: () => inputs,
	};
	const page = { dispatchEvent: () => true };
	const state = {
		assignmentId: 31,
		timeLimit: 600,
		deadline: 0,
		deadlineLabel: '',
		remaining: 600,
		serverNow: T0 + 5000,
		maxBytes: 1000,
		started: false,
		expired: false,
		locked: false,
		busy: false,
		error: '',
		files: [],
		tooLarge: false,
		nonces: { submit: 'n-submit', start: 'n-start' },
		messages: MESSAGES,
		...options.state,
	};
	const env = { element: { id: 'start' }, context: {} };
	const responses = [ ...( options.responses || [] ) ];
	const assignment = createAssignment( {
		getContext: () => env.context,
		getElement: () => ( { ref: env.element } ),
		withSyncEvent: ( callback ) => callback,
		getState: () => state,
		request: async ( request ) => {
			log.requests.push( request );
			return responses.shift() || ok();
		},
		toast: ( message, toastOptions = {} ) =>
			log.toasts.push( [ message, toastOptions.type || 'success' ] ),
		emit: ( node, name, detail, cancelable ) => {
			log.events.push( [ name, detail ] );
			return ! ( cancelable && options.block === name );
		},
		announce: ( message, politeness = 'polite' ) =>
			log.announced.push( [ message, politeness ] ),
		win: {
			location: {
				href: ORIGIN,
				assign: ( url ) => log.visits.push( url ),
				reload: () => log.visits.push( 'reload' ),
			},
		},
		doc: {
			querySelector: ( selector ) => {
				if ( '.ohmylms-assignment-form' === selector ) {
					return form;
				}
				return selector.includes( 'ohmylms/assignment' ) ? page : null;
			},
		},
		formData: ( target ) => ( { fieldsOf: target } ),
		now: () => clock.device,
		later: ( callback, ms ) => log.timers.push( [ callback, ms ] ),
		every: ( callback, ms ) => {
			log.intervals.push( [ callback, ms ] );
			return log.intervals.length;
		},
		stopEvery: () => log.stopped++,
	} );
	assignment.callbacks.mount();
	return { assignment, state, log, clock, form, inputs, env };
}

const settle = () => new Promise( ( resolve ) => setImmediate( resolve ) );

function submitEvent( form ) {
	return {
		target: form,
		defaultPrevented: false,
		preventDefault() {
			this.defaultPrevented = true;
		},
	};
}

function pick( assignment, file ) {
	assignment.actions.pick( { target: { files: file ? [ file ] : [] } } );
}

test( 'formatRemaining keeps the countdown format of the learner page', () => {
	assert.equal( formatRemaining( 0 ), '00m 00s' );
	assert.equal( formatRemaining( 309 ), '05m 09s' );
	assert.equal( formatRemaining( 3909 ), '01h 05m 09s' );
	assert.equal( formatRemaining( 2 * 86400 + 3909 ), '02d 01h 05m 09s' );
	assert.equal( formatRemaining( 86400 ), '01d 00h 00m 00s' );
	assert.equal( formatRemaining( -5 ), '00m 00s' );
	assert.equal( formatRemaining( Number.NaN ), '00m 00s' );
} );

test( 'remainingSeconds, timerWidth and parseDeadline', () => {
	assert.equal( remainingSeconds( T0 + 10_000, T0 ), 10 );
	assert.equal( remainingSeconds( T0 + 10_001, T0 ), 11, 'rounds up so zero means the time is over' );
	assert.equal( remainingSeconds( T0, T0 + 1 ), 0 );
	assert.equal( timerWidth( 300, 600 ), '50%' );
	assert.equal( timerWidth( 900, 600 ), '100%' );
	assert.equal( timerWidth( 0, 600 ), '0%' );
	assert.equal( timerWidth( 5, 0 ), '100%' );
	assert.equal( parseDeadline( '2026-10-31T10:10:00Z' ), T0 + 600_000 );
	assert.equal( parseDeadline( 'soon' ), 0 );
	assert.equal( parseDeadline( null ), 0 );
} );

test( 'file limit: size, entries and the submit button', () => {
	assert.equal( fileProblem( null, 10 ), 'empty' );
	assert.equal( fileProblem( { size: 10 }, 10 ), '' );
	assert.equal( fileProblem( { size: 11 }, 10 ), 'size' );
	assert.equal( fileProblem( { size: 11 }, 0 ), '', 'no client limit' );
	assert.equal( formatSize( 1048576 ), '1.00 MB' );
	assert.deepEqual( fileEntry( { name: 'a.pdf', size: 2097152, lastModified: 7 } ), {
		key: 'a.pdf:2097152:7',
		name: 'a.pdf',
		size: '2.00 MB',
	} );
	const file = [ { key: 'k' } ];
	assert.equal( submitDisabled( { files: [] } ), true, 'the server needs a file' );
	assert.equal( submitDisabled( { files: file } ), false );
	assert.equal( submitDisabled( { files: file, tooLarge: true } ), true );
	assert.equal( submitDisabled( { files: file, busy: true } ), true );
	assert.equal( submitDisabled( { files: file, locked: true } ), true );
} );

test( 'Start asks the server for the deadline, counts down from it with the server clock, and shows the form', async () => {
	const { assignment, state, log, clock } = fixture( {
		responses: [
			ok( {
				deadline: '2026-10-31T10:10:05Z',
				deadline_label: 'October 31, 2026 at 6:10 pm',
				remaining: 600,
			} ),
		],
	} );
	assert.equal( assignment.state.panelDisplay, 'none' );
	assert.equal( assignment.state.timerDisplay, 'none' );
	assignment.actions.start();
	assignment.actions.start();
	await settle();
	assert.equal( log.requests.length, 1, 'one start request' );
	assert.deepEqual( log.requests[ 0 ], {
		action: START_ACTION,
		nonce: 'n-start',
		data: { content_id: 31, type: 'assignment' },
	} );
	assert.equal( state.started, true );
	assert.equal( state.deadline, T0 + 605_000 );
	assert.equal( state.deadlineLabel, 'October 31, 2026 at 6:10 pm' );
	assert.equal( assignment.state.panelDisplay, 'block' );
	assert.equal( assignment.state.timerDisplay, '' );
	assert.equal( state.remaining, 600, 'the device is 5 s behind the server' );
	assert.equal( assignment.state.timeLabel, '10m 00s' );
	assert.equal( log.intervals.length, 1 );
	assert.equal( log.intervals[ 0 ][ 1 ], 1000 );
	clock.device += 300_000;
	assignment.tick();
	assert.equal( state.remaining, 300 );
	assert.equal( assignment.state.timerWidth, '50%' );
	assert.deepEqual(
		log.events.map( ( [ name ] ) => name ),
		[ 'assignment-before-start', 'assignment-started' ]
	);
} );

test( 'without a time limit Start only shows the form', async () => {
	const { assignment, state, log } = fixture( { state: { timeLimit: 0, remaining: 0 } } );
	assignment.actions.start();
	await settle();
	assert.equal( log.requests.length, 0 );
	assert.equal( state.started, true );
	assert.equal( assignment.state.timerDisplay, 'none' );
	assert.equal( log.intervals.length, 0 );
} );

test( 'a refused start (time over on the server) locks and says why', async () => {
	const { assignment, state, log } = fixture( {
		responses: [ failed( 'The time for this assignment ran out.', TIME_EXPIRED ) ],
	} );
	assignment.actions.start();
	await settle();
	assert.equal( state.started, false );
	assert.equal( state.locked, true );
	assert.deepEqual( log.toasts, [ [ 'The time for this assignment ran out.', 'danger' ] ] );
	assert.equal( log.events.at( -1 )[ 1 ].code, TIME_EXPIRED );
} );

test( 'picking a file lists it, checks the size, and removing it clears the input', async () => {
	const { assignment, state, log, env, inputs } = fixture();
	pick( assignment, { name: 'essay.pdf', size: 500, lastModified: 1 } );
	assert.equal( state.files.length, 1 );
	assert.equal( state.files[ 0 ].name, 'essay.pdf' );
	assert.equal( assignment.state.hasFiles, true );
	assert.equal( assignment.state.attachedDisplay, 'block' );
	assert.equal( assignment.state.submitDisabled, false );
	assert.deepEqual( log.announced.at( -1 ), [ 'Selected file: essay.pdf', 'polite' ] );
	pick( assignment, { name: 'huge.mp4', size: 5000, lastModified: 2 } );
	assert.equal( state.tooLarge, true );
	assert.equal( assignment.state.sizeAlertDisplay, 'block' );
	assert.equal( assignment.state.submitDisabled, true );
	env.context = { file: state.files[ 0 ] };
	const event = submitEvent( null );
	assignment.actions.remove( event );
	assert.equal( event.defaultPrevented, true );
	assert.equal( state.files.length, 0 );
	assert.equal( state.tooLarge, false );
	assert.equal( inputs[ 0 ].value, '' );
	assert.equal( assignment.state.submitDisabled, true );
	pick( assignment, null );
	assert.equal( state.files.length, 0 );
} );

test( 'submitting text and a file posts the form once and goes to the assignment page', async () => {
	const { assignment, state, log, form } = fixture( {
		responses: [
			ok(
				{ redirect_url: 'https://school.example/assignment/essay/' },
				'Assignment submitted successfully.'
			),
		],
	} );
	pick( assignment, { name: 'essay.pdf', size: 500 } );
	const event = submitEvent( form );
	assignment.actions.submit( event );
	assignment.actions.submit( submitEvent( form ) );
	assert.equal( event.defaultPrevented, true );
	assert.equal( assignment.state.submitDisabled, true, 'busy' );
	await settle();
	assert.equal( log.requests.length, 1, 'double submit sends once' );
	assert.deepEqual( log.requests[ 0 ], {
		action: SUBMIT_ACTION,
		nonce: 'n-submit',
		data: { fieldsOf: form },
	} );
	assert.equal( state.submitted, true );
	assert.deepEqual( log.toasts, [ [ 'Assignment submitted successfully.', 'success' ] ] );
	assert.equal( log.timers.at( -1 )[ 1 ], NAVIGATE_DELAY );
	log.timers.at( -1 )[ 0 ]();
	assert.deepEqual( log.visits, [ 'https://school.example/assignment/essay/' ] );
	assignment.actions.submit( submitEvent( form ) );
	await settle();
	assert.equal( log.requests.length, 1, 'nothing after a successful submission' );
} );

test( 'a refused submission shows the server message in the form and keeps it usable', async () => {
	const { assignment, state, log, form } = fixture( {
		responses: [
			failed( 'Complete Reading before you submit this assignment.', 'ohmylms_assignment_prerequisites' ),
		],
	} );
	pick( assignment, { name: 'essay.pdf', size: 500 } );
	assignment.actions.submit( submitEvent( form ) );
	await settle();
	assert.equal( state.error, 'Complete Reading before you submit this assignment.' );
	assert.equal( assignment.state.errorDisplay, 'flex' );
	assert.equal( state.locked, false );
	assert.equal( state.busy, false );
	assert.equal( log.events.at( -1 )[ 0 ], 'assignment-error' );
	assert.equal( log.visits.length, 0 );
} );

test( 'a cancelled ohmylms:assignment-before-submit posts nothing', async () => {
	const { assignment, log, form } = fixture( { block: 'assignment-before-submit' } );
	pick( assignment, { name: 'essay.pdf', size: 500 } );
	assignment.actions.submit( submitEvent( form ) );
	await settle();
	assert.equal( log.requests.length, 0 );
} );

test( 'at zero a picked file is submitted through the assignment action, never the quiz exit', async () => {
	const { assignment, state, log, clock } = fixture( {
		responses: [
			ok( { deadline: '2026-10-31T10:10:05Z' } ),
			ok( { redirect_url: ORIGIN }, 'Assignment submitted successfully.' ),
		],
	} );
	assignment.actions.start();
	await settle();
	pick( assignment, { name: 'essay.pdf', size: 500 } );
	clock.device += 600_000;
	assignment.tick();
	await settle();
	assert.equal( state.expired, true );
	assert.equal( log.requests.length, 2 );
	assert.equal( log.requests[ 1 ].action, SUBMIT_ACTION );
	assert.ok( log.requests.every( ( request ) => 'ohmylms_quiz_exit_submission' !== request.action ) );
	assert.equal( log.events.find( ( [ name ] ) => 'assignment-before-submit' === name )[ 1 ].reason, 'timeout' );
	assert.equal( state.submitted, true );
	assert.ok( log.stopped >= 1, 'the countdown stops' );
	assignment.tick();
	await settle();
	assert.equal( log.requests.length, 2, 'expiry runs once' );
} );

test( 'at zero without a file the form locks with the reason from the learner-state route', async () => {
	const { assignment, state, log, clock } = fixture( {
		responses: [
			ok( { deadline: '2026-10-31T10:10:05Z' } ),
			ok( { can_submit: false, reason: 'time_expired', message: 'The time for this assignment ran out on October 31.' } ),
		],
	} );
	assignment.actions.start();
	await settle();
	clock.device += 601_000;
	assignment.tick();
	await settle();
	assert.equal( state.locked, true );
	assert.deepEqual( log.requests[ 1 ], { path: 'ohmylms/v1/assignments/31/learner-state' } );
	assert.equal( state.error, 'The time for this assignment ran out on October 31.' );
	assert.deepEqual( log.announced.at( -1 ), [ state.error, 'assertive' ] );
	assert.equal( assignment.state.submitDisabled, true );
	pick( assignment, { name: 'late.pdf', size: 1 } );
	assignment.actions.submit( submitEvent( {} ) );
	await settle();
	assert.equal( log.requests.length, 2, 'a locked form posts nothing' );
} );

test( 'at zero within the grace period the module still locks with its own message', async () => {
	const { assignment, state, clock } = fixture( {
		responses: [ ok( { deadline: '2026-10-31T10:10:05Z' } ), ok( { can_submit: true, message: '' } ) ],
	} );
	assignment.actions.start();
	await settle();
	clock.device += 600_000;
	assignment.tick();
	await settle();
	assert.equal( state.locked, true );
	assert.equal( state.error, 'time is up' );
} );

test( 'a timeout submission the server refuses locks the form', async () => {
	const { assignment, state, clock } = fixture( {
		responses: [
			ok( { deadline: '2026-10-31T10:10:05Z' } ),
			failed( 'The time for this assignment ran out.', TIME_EXPIRED ),
		],
	} );
	assignment.actions.start();
	await settle();
	pick( assignment, { name: 'essay.pdf', size: 500 } );
	clock.device += 700_000;
	assignment.tick();
	await settle();
	assert.equal( state.locked, true );
	assert.equal( state.error, 'The time for this assignment ran out.' );
} );

test( 'a timer started earlier resumes from the stored deadline when the server sends none', async () => {
	const { assignment, state } = fixture( {
		state: { deadline: T0 + 125_000, remaining: 120 },
		responses: [ ok( { message: 'saved' } ) ],
	} );
	assignment.actions.start();
	await settle();
	assert.equal( state.deadline, T0 + 125_000 );
	assert.equal( state.remaining, 120 );
} );

test( 'Enter or Space on the drop zone opens the file dialog', () => {
	const { assignment, env } = fixture();
	let clicks = 0;
	const label = { querySelector: () => ( { click: () => clicks++ } ) };
	env.element = label;
	const key = ( value, from = label ) =>
		assignment.actions.pickKey( { key: value, target: from, preventDefault() {} } );
	key( 'Enter' );
	key( ' ' );
	key( 'x' );
	key( 'Enter', {} );
	assert.equal( clicks, 2 );
} );
