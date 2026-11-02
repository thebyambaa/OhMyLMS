import test from 'node:test';
import assert from 'node:assert/strict';
import {
	countFlags,
	format,
	hasMath,
	itemStatus,
	parseFlags,
	serializeFlags,
	stepIndex,
	submitCheck,
	timeWarning,
	toggleFlag,
	waitFor,
	warningText,
} from '../../assets/interactivity/paper/model.mjs';
import { createPaperView } from '../../assets/interactivity/paper/view.js';

const strings = {
	flaggedText: 'Question %s flagged for review.',
	unflaggedText: 'Question %s no longer flagged.',
	confirmFlags: '%d questions are flagged for review. Submit anyway?',
	minutesLeft: '%d minutes left.',
	oneMinuteLeft: 'One minute left.',
	answeredText: 'answered',
	blankText: 'not answered',
	flagText: 'flagged',
};

test( 'flags survive storage round trips and ignore junk', () => {
	assert.deepEqual( parseFlags( '[3, "12", 0, -1, "x", 4.5]' ), {
		3: true,
		12: true,
	} );
	assert.deepEqual( parseFlags( '{"3":true}' ), {} );
	assert.deepEqual( parseFlags( 'not json' ), {} );
	assert.deepEqual( parseFlags( null ), {} );
	assert.equal( serializeFlags( { 12: true, 3: true, 7: false } ), '[3,12]' );
	assert.deepEqual( parseFlags( serializeFlags( { 9: true } ) ), { 9: true } );
	const flags = toggleFlag( {}, 5 );
	assert.deepEqual( flags, { 5: true } );
	assert.deepEqual( toggleFlag( flags, '5' ), {} );
	assert.equal( countFlags( { 1: true, 2: false, 3: true } ), 2 );
} );

test( 'timer warnings fire once when five minutes and one minute remain', () => {
	assert.equal( timeWarning( null, 200 ), 0, 'Nothing on the first tick' );
	assert.equal( timeWarning( 301, 300 ), 300 );
	assert.equal( timeWarning( 300, 299 ), 0, 'Only when crossing' );
	assert.equal( timeWarning( 400, 50 ), 300, 'A long gap reports the first' );
	assert.equal( timeWarning( 61, 60 ), 60 );
	assert.equal( warningText( 300, strings ), '5 minutes left.' );
	assert.equal( warningText( 60, strings ), 'One minute left.' );
	assert.equal( format( 'Question %s flagged', 'Q2' ), 'Question Q2 flagged' );
} );

test( 'submit confirms flags only when every question has an answer', () => {
	assert.equal(
		submitCheck( { questions: 3, answered: 2, flagged: 1 } ),
		'blank'
	);
	assert.equal(
		submitCheck( { questions: 3, answered: 3, flagged: 2 } ),
		'flags'
	);
	assert.equal( submitCheck( { questions: 3, answered: 3, flagged: 0 } ), '' );
} );

test( 'navigator keys move between questions and wrap', () => {
	assert.equal( stepIndex( 4, 0, 'ArrowRight' ), 1 );
	assert.equal( stepIndex( 4, 3, 'ArrowDown' ), 0 );
	assert.equal( stepIndex( 4, 0, 'ArrowLeft' ), 3 );
	assert.equal( stepIndex( 4, -1, 'ArrowUp' ), 3 );
	assert.equal( stepIndex( 4, 2, 'Home' ), 0 );
	assert.equal( stepIndex( 4, 0, 'End' ), 3 );
	assert.equal( stepIndex( 4, 1, 'a' ), -1 );
	assert.equal( stepIndex( 0, 0, 'ArrowRight' ), -1 );
	assert.equal(
		itemStatus( { answered: true, flagged: true }, strings ),
		', answered, flagged'
	);
	assert.equal(
		itemStatus( { answered: false, flagged: false }, strings ),
		', not answered'
	);
} );

test( 'math detection and waiting for the runtime', async () => {
	assert.ok( hasMath( 'Solve $x^2 = 4$' ) );
	assert.ok( hasMath( '[[ohmylms-math:latex:inline]]x[[/ohmylms-math]]' ) );
	assert.ok( ! hasMath( 'Plain text' ) );
	let ready = false;
	const timers = [];
	const waiting = waitFor( () => ready, {
		timeout: 300,
		interval: 100,
		setTimer: ( callback ) => timers.push( callback ),
	} );
	timers.shift()();
	ready = true;
	timers.shift()();
	assert.equal( await waiting, true );
	const late = waitFor( () => false, {
		timeout: 200,
		interval: 100,
		setTimer: ( callback ) => callback(),
	} );
	assert.equal( await late, false );
} );

/**
 * A store with a fake runtime: contexts by element and the player's quiz context.
 *
 * @param {Object} options Options.
 * @return {Object} Store, state and helpers.
 */
function fixture( options = {} ) {
	const listeners = {};
	const focused = [];
	const box = {
		attributes: {},
		hasAttribute: ( name ) => name in box.attributes,
		setAttribute: ( name, value ) => ( box.attributes[ name ] = value ),
		focus: ( settings ) => focused.push( [ 'box', settings ] ),
		scrollIntoView: ( settings ) => ( box.scrolled = settings ),
		ownerDocument: {
			defaultView: { matchMedia: () => ( { matches: true } ) },
		},
	};
	const root = {
		querySelector: ( selector ) =>
			selector.includes( 'data-question-id="12"' ) ? box : null,
		addEventListener: ( name, callback ) =>
			( listeners[ name ] = callback ),
		removeEventListener: ( name ) => delete listeners[ name ],
	};
	const quiz = {
		questionCount: 2,
		answered: { 1: true },
		timed: true,
		remaining: 400,
		...options.quiz,
	};
	let context = { storageKey: 'flags:7:9' };
	const element = { ref: { closest: () => root } };
	const stored = { 'flags:7:9': '[12]' };
	const confirms = [];
	let state;
	const view = createPaperView( {
		getContext: ( namespace ) =>
			namespace === 'ohmylms/quiz' ? quiz : context,
		getElement: () => element,
		withSyncEvent: ( callback ) => callback,
		state: () => state,
		storage: {
			getItem: ( key ) => stored[ key ] ?? null,
			setItem: ( key, value ) => ( stored[ key ] = value ),
		},
		confirm: ( message ) => {
			confirms.push( message );
			return options.confirm ?? false;
		},
	} );
	// The Interactivity API calls getters with the store state as `this`; this fake calls them directly.
	state = Object.assign( view.state, strings );
	return {
		view,
		state,
		quiz,
		box,
		focused,
		stored,
		confirms,
		listeners,
		setContext: ( next ) => ( context = next ),
	};
}

test( 'the store restores flags, toggles them and announces the change', () => {
	const { view, state, stored, setContext } = fixture();
	const cleanup = view.callbacks.init();
	assert.deepEqual( state.flags, { 12: true } );
	assert.equal( state.storageKey, 'flags:7:9' );
	setContext( { questionId: 12, questionNumber: 1, label: 'Q1' } );
	assert.equal( state.isFlagged, true );
	assert.equal( state.flagPressed, 'true' );
	assert.equal( state.isAnswered, true, 'Reads the player answered map' );
	assert.equal( state.itemStatus, ', answered, flagged' );
	view.actions.toggleFlag( { preventDefault() {} } );
	assert.equal( state.isFlagged, false );
	assert.equal( state.announcement, 'Question Q1 no longer flagged.' );
	assert.equal( stored[ 'flags:7:9' ], '[]' );
	view.actions.toggleFlag( { preventDefault() {} } );
	assert.equal( state.announcement, 'Question Q1 flagged for review.' );
	assert.equal( state.flaggedCount, 1 );
	assert.equal( typeof cleanup, 'function' );
} );

test( 'jumping focuses and scrolls to the question and closes the list', () => {
	const { view, state, box, focused, setContext } = fixture();
	state.navOpen = true;
	setContext( { questionId: 12, questionNumber: 1, label: 'Q1' } );
	view.actions.jump( { preventDefault() {} } );
	assert.equal( state.navOpen, false );
	assert.equal( box.attributes.tabindex, '-1' );
	assert.deepEqual( focused, [ [ 'box', { preventScroll: true } ] ] );
	assert.deepEqual( box.scrolled, { block: 'start', behavior: 'auto' } );
	setContext( { questionId: 99 } );
	view.actions.jump( { preventDefault() {} } );
	assert.equal( focused.length, 1, 'Unknown questions are ignored' );
} );

test( 'submitting with flags asks first, but never on top of the unanswered check', () => {
	const complete = fixture( { quiz: { answered: { 1: true, 2: true } } } );
	complete.view.callbacks.init();
	const event = ( exit = false ) => ( {
		detail: { exit },
		prevented: false,
		preventDefault() {
			this.prevented = true;
		},
	} );
	const cancelled = event();
	complete.listeners[ 'ohmylms:quiz-before-submit' ]( cancelled );
	assert.equal( cancelled.prevented, true );
	assert.deepEqual( complete.confirms, [
		'1 questions are flagged for review. Submit anyway?',
	] );
	const exit = event( true );
	complete.listeners[ 'ohmylms:quiz-before-submit' ]( exit );
	assert.equal( exit.prevented, false, 'Leaving the exam is not a submit' );

	const blank = fixture();
	blank.view.callbacks.init();
	const submitted = event();
	blank.listeners[ 'ohmylms:quiz-before-submit' ]( submitted );
	assert.equal( submitted.prevented, false );
	assert.deepEqual( blank.confirms, [], 'The player confirms blanks' );

	const accepted = fixture( {
		confirm: true,
		quiz: { answered: { 1: true, 2: true } },
	} );
	accepted.view.callbacks.init();
	const go = event();
	accepted.listeners[ 'ohmylms:quiz-before-submit' ]( go );
	assert.equal( go.prevented, false );
} );

test( 'the timer announces the warnings once', () => {
	const { view, state, quiz } = fixture();
	view.callbacks.watchTime();
	assert.equal( state.announcement, '' );
	quiz.remaining = 300;
	view.callbacks.watchTime();
	assert.equal( state.announcement, '5 minutes left.' );
	state.announcement = '';
	quiz.remaining = 299;
	view.callbacks.watchTime();
	assert.equal( state.announcement, '' );
	quiz.remaining = 60;
	view.callbacks.watchTime();
	assert.equal( state.announcement, 'One minute left.' );
} );
