import test from 'node:test';
import assert from 'node:assert/strict';
import { createQuizPlayer } from '../../assets/interactivity/quiz-player/player.js';

test( 'expiry preserves embedded navigation and retains standalone redirects', async ( t ) => {
	const destinations = [];
	t.mock.method( globalThis, 'fetch', async () => ( {
		ok: true,
		json: async () => ( { success: true, data: { url: '/quiz/results' } } ),
	} ) );
	const originalFormData = globalThis.FormData;
	const originalWindow = globalThis.window;
	globalThis.FormData = class extends URLSearchParams {};
	globalThis.window = {
		location: { assign: ( url ) => destinations.push( url ) },
	};
	t.after( () => {
		globalThis.FormData = originalFormData;
		if ( originalWindow === undefined ) {
			delete globalThis.window;
		} else {
			globalThis.window = originalWindow;
		}
	} );
	for ( const returnUrl of [ '/?ohmylms_quiz_embed=7', undefined ] ) {
		const context = {
			quizId: 7,
			returnUrl,
			ajaxUrl: '/ajax',
			attemptId: 12,
		};
		const root = { querySelector: () => ( { closest: () => undefined } ) };
		const player = createQuizPlayer( {
			getContext: () => context,
			getElement: () => ( { ref: { closest: () => root } } ),
			withSyncEvent: ( callback ) => callback,
			withScope: ( callback ) => callback,
			emit: () => true,
			isAnswered: () => true,
		} );
		const expiry = player.actions.expire();
		let step = expiry.next();
		while ( ! step.done ) {
			step = expiry.next( await step.value );
		}
		assert.equal( context.submitting, true );
	}
	assert.deepEqual( destinations, [
		'/?ohmylms_quiz_embed=7',
		'/quiz/results',
	] );
} );

function fixture( overrides = {} ) {
	const context = {
		quizId: 7,
		page: 1,
		totalPages: 2,
		layout: 'one_question_per_page',
		errors: {},
		...overrides,
	};
	const events = [];
	const inlineCalls = [];
	const boxes = [ true, false ].map( ( answered ) => ( {
		answered,
		focused: false,
		querySelector( selector ) {
			return selector === '.is-required'
				? { value: '1', getAttribute: () => 'custom-type' }
				: {
						focus: () => {
							this.focused = true;
						},
					};
		},
	} ) );
	const root = { querySelectorAll: () => boxes };
	const player = createQuizPlayer( {
		getContext: () => context,
		getElement: () => ( { ref: { closest: () => root } } ),
		withSyncEvent: ( callback ) => callback,
		withScope: ( callback ) => callback,
		isAnswered: ( box, type ) => {
			assert.equal( type, 'custom-type' );
			return box.answered;
		},
		emit: ( root, name, detail ) => {
			events.push( { name, detail } );
			return ! context.cancel;
		},
		expire: () => {
			context.expired = true;
		},
		inlineSubmit: ( form, operation ) => {
			inlineCalls.push( { form, operation } );
			return Promise.resolve();
		},
	} );
	const event = {
		prevented: false,
		preventDefault() {
			this.prevented = true;
		},
		stopPropagation() {},
		target: { querySelector: () => ( { value: 'normal-submit' } ) },
	};
	return { context, boxes, player, event, events, inlineCalls, root };
}

test( 'inline submit preserves validation and exit uses the inline engine transport', () => {
	const { boxes, player, event, inlineCalls } = fixture( {
		inlineEndpoint: '/attempt',
	} );
	player.actions.submit( event );
	assert.equal( inlineCalls.length, 0 );
	boxes[ 1 ].answered = true;
	player.actions.submit( event );
	assert.equal( inlineCalls[ 0 ].operation, 'submit' );
	assert.equal( event.prevented, true );
	const exited = fixture( { inlineEndpoint: '/attempt' } );
	exited.event.target.querySelector = () => ( {
		value: 'ohmylms-quiz-exit-submission',
	} );
	exited.player.actions.submit( exited.event );
	assert.equal( exited.inlineCalls[ 0 ].operation, 'exit' );
} );

test( 'inline expiry uses the same transport without a page redirect', async () => {
	const { player, root, inlineCalls } = fixture( {
		inlineEndpoint: '/attempt',
	} );
	const form = {};
	root.querySelector = () => ( { closest: () => form } );
	const expiry = player.actions.expire();
	await expiry.next().value;
	assert.equal( expiry.next().done, true );
	assert.deepEqual( inlineCalls, [ { form, operation: 'timeout' } ] );
} );

test( 'player navigation validates only the current page and honors cancellation', () => {
	const { context, boxes, player, event, events } = fixture();
	player.actions.next( event );
	assert.equal( context.page, 2 );
	assert.equal( events.at( -1 ).name, 'quiz-navigated' );
	player.actions.submit( event );
	assert.equal( context.submitting, undefined );
	assert.equal( event.prevented, true );
	assert.equal( boxes[ 1 ].focused, true );
	context.cancel = true;
	player.actions.previous( event );
	assert.equal( context.page, 2 );
} );

test( 'full submission checks other pages and exit bypasses required-answer checks', () => {
	const { context, boxes, player, event } = fixture( { page: 2 } );
	boxes[ 0 ].answered = false;
	boxes[ 1 ].answered = true;
	player.actions.submit( event );
	assert.deepEqual( context.errors, { 1: true } );
	assert.equal( context.page, 1 );
	event.target.querySelector = () => ( {
		value: 'ohmylms-quiz-preview-exit',
	} );
	event.prevented = false;
	player.actions.submit( event );
	assert.equal( context.submitting, true );
	assert.equal( event.prevented, false );
} );

test( 'player state reads current context and formats the timer', () => {
	const { context, player } = fixture( { remaining: 3661, duration: 7322 } );
	assert.equal( player.state.timeLabel, '01h 01m 01s' );
	assert.equal( player.state.timerWidth, '50%' );
	assert.equal( player.state.nextDisplay, '' );
	assert.equal( player.state.pageLabel, '1 / 2' );
	assert.equal( player.state.pageWidth, '50%' );
	context.page = 2;
	assert.equal( player.state.nextDisplay, 'none' );
	assert.equal( player.state.submitDisplay, '' );
	assert.equal( player.state.pageLabel, '2 / 2' );
	assert.equal( player.state.pageWidth, '100%' );
	context.remaining = 0;
	assert.equal( player.state.timeLabel, '00m 00s' );
} );

test( 'per-page validation moves to the page containing the first unanswered question', () => {
	const { context, player, event } = fixture( {
		layout: 'number_of_questions_per_page',
		perPage: 2,
	} );
	player.actions.next( event );
	assert.equal( context.page, 1 );
	assert.deepEqual( context.errors, { 2: true } );
} );

test( 'timer mount uses the injected scoped expiry and cleanup allows remounting', ( t ) => {
	t.mock.timers.enable( { apis: [ 'setInterval', 'Date' ] } );
	const context = {
		timed: true,
		remaining: 1,
		quizId: 9,
		layout: 'one_question_per_page',
	};
	const box = {
		querySelector: () => ( { getAttribute: () => 'single-choice' } ),
	};
	const root = { querySelectorAll: () => [ box ] };
	let expired = 0;
	const player = createQuizPlayer( {
		getContext: () => context,
		getElement: () => ( { ref: root } ),
		withScope: ( callback ) => callback,
		withSyncEvent: ( callback ) => callback,
		emit: () => true,
		isAnswered: () => true,
		expire: () => expired++,
	} );
	const cleanup = player.callbacks.mount();
	assert.deepEqual( context.answered, { 1: true } );
	assert.equal( player.callbacks.mount(), undefined );
	t.mock.timers.tick( 1000 );
	assert.equal( expired, 1 );
	cleanup();
	context.remaining = 2;
	const cleanupAgain = player.callbacks.mount();
	cleanupAgain();
	t.mock.timers.tick( 2000 );
	assert.equal( expired, 1 );
} );

test( 'an answer change re-reads only its own question, and only in the one-question-per-page layout', () => {
	const reads = [];
	const makeBox = ( name, answered ) => ( {
		name,
		answered,
		querySelector: () => ( { getAttribute: () => 'single-choice' } ),
	} );
	const boxes = [
		makeBox( 'one', false ),
		makeBox( 'two', false ),
		makeBox( 'three', false ),
	];
	const build = ( context ) => {
		const root = { querySelectorAll: () => boxes };
		root.closest = () => root;
		return createQuizPlayer( {
			getContext: () => context,
			getElement: () => ( { ref: root } ),
			withScope: ( callback ) => callback,
			withSyncEvent: ( callback ) => callback,
			emit: () => true,
			isAnswered: ( box ) => {
				reads.push( box.name );
				return box.answered;
			},
		} );
	};
	const input = ( box ) => ( {
		getAttribute: () => null,
		matches: () => false,
		parentElement: { querySelector: () => null },
		closest: () => box,
		value: '',
	} );
	const event = ( target ) => ( { stopPropagation() {}, target } );

	const context = { layout: 'one_question_per_page', quizId: 1, errors: {} };
	const player = build( context );
	player.callbacks.mount();
	assert.deepEqual(
		reads,
		[ 'one', 'two', 'three' ],
		'mount reads every question once'
	);
	assert.deepEqual( context.answered, { 1: false, 2: false, 3: false } );

	reads.length = 0;
	boxes[ 1 ].answered = true;
	context.questionNumber = 2;
	player.actions.answerChanged( event( input( boxes[ 1 ] ) ) );
	assert.deepEqual(
		reads,
		[ 'two' ],
		'a change reads only the changed question'
	);
	assert.deepEqual( context.answered, { 1: false, 2: true, 3: false } );

	reads.length = 0;
	context.questionNumber = 3;
	boxes[ 2 ].answered = true;
	const noClosest = input( boxes[ 2 ] );
	delete noClosest.closest;
	player.actions.answerChanged( event( noClosest ) );
	assert.deepEqual(
		reads,
		[ 'three' ],
		'the box is found by number without closest()'
	);

	reads.length = 0;
	const other = {
		layout: 'all_questions',
		quizId: 2,
		errors: {},
		questionNumber: 1,
	};
	const plain = build( other );
	plain.callbacks.mount();
	plain.actions.answerChanged( event( input( boxes[ 0 ] ) ) );
	assert.deepEqual( reads, [], 'other layouts never read answers' );
	assert.equal( other.answered, undefined );
} );

test( 'paper review counts answers on a single page and focuses the first blank', () => {
	const { context, boxes, player, event } = fixture( {
		layout: 'all_questions_in_one_page',
		totalPages: 1,
		questionCount: 2,
		paperPresentation: true,
	} );
	boxes.forEach( ( box ) => {
		box.setAttribute = () => {};
		box.focus = () => {
			box.focused = true;
		};
		box.scrollIntoView = () => {};
	} );
	player.actions.reviewPaper( event );
	assert.equal( player.state.answeredCount, 1 );
	assert.equal( boxes[ 1 ].focused, true );
	assert.equal( context.page, 1 );
} );

test( 'paper submission confirms optional blanks and cancellation preserves answers', ( t ) => {
	const { context, boxes, player, event } = fixture( {
		layout: 'all_questions_in_one_page',
		totalPages: 1,
		questionCount: 2,
		paperPresentation: true,
		confirmSubmit: '%d unanswered',
	} );
	boxes.forEach( ( box ) => {
		const original = box.querySelector;
		box.querySelector = ( selector ) =>
			selector === '.is-required'
				? { value: '0', getAttribute: () => 'custom-type' }
				: original.call( box, selector );
	} );
	const oldWindow = globalThis.window;
	const messages = [];
	globalThis.window = {
		confirm: ( message ) => {
			messages.push( message );
			return false;
		},
	};
	t.after( () => {
		globalThis.window = oldWindow;
	} );
	player.actions.submit( event );
	assert.deepEqual( messages, [ '1 unanswered' ] );
	assert.equal( event.prevented, true );
	assert.equal( context.submitting, undefined );
	assert.equal( boxes[ 0 ].answered, true );
	globalThis.window.confirm = () => true;
	player.actions.submit( event );
	assert.equal( context.submitting, true );
} );
