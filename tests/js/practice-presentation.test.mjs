import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';
import { buttonClass } from '../../assets/src/ui/button.mjs';

// A small DOM double runs the authored runner; no grading or selection logic is duplicated.
function fixture(
	failAnswer = false,
	remembered = null,
	extra = {},
	answerResult = null,
	client = {}
) {
	let focus;
	class Element {
		constructor( tag ) {
			this.tag = tag;
			this.children = [];
			this.attributes = {};
			this.events = {};
			this.value = '';
			this.classList = {
				contains: ( name ) =>
					( this.attributes.class || '' )
						.split( ' ' )
						.includes( name ),
				add: ( name ) => {
					this.attributes.class = [
						...new Set( [
							...( this.attributes.class || '' ).split( ' ' ),
							name,
						] ),
					]
						.filter( Boolean )
						.join( ' ' );
				},
			};
		}
		setAttribute( name, value ) {
			this.attributes[ name ] = String( value );
		}
		appendChild( child ) {
			this.children.push( child );
			child.parentNode = this;
			return child;
		}
		insertBefore( child, before ) {
			const at = this.children.indexOf( before );
			this.children.splice(
				at < 0 ? this.children.length : at,
				0,
				child
			);
			child.parentNode = this;
			return child;
		}
		set innerHTML( value ) {
			this.children = [];
		}
		set textContent( value ) {
			this.children = [];
			this.text = value;
		}
		addEventListener( name, listener ) {
			this.events[ name ] = listener;
		}
		focus() {
			focus = this;
		}
		querySelector() {
			return null;
		}
		remove() {
			this.parentNode.children = this.parentNode.children.filter(
				( child ) => child !== this
			);
		}
	}
	const stage = new Element( 'div' );
	const events = [];
	const root = {
		dataset: { course: '7', skill: '9', items: '3' },
		querySelector: () => stage,
		dispatchEvent: ( event ) => events.push( event ),
	};
	const requests = [];
	const state = {
		uuid: 'session-a',
		status: 'active',
		item_limit: 3,
		answered: 0,
		correct: 0,
		current: { item_id: 10, name: 'Add', settings: { type: 'numerical' } },
		...extra,
	};
	let stored = remembered;
	const context = {
		CustomEvent: class {
			constructor( type, options ) {
				this.type = type;
				Object.assign( this, options );
			}
		},
		document: {
			querySelector: () => root,
			createElement: ( tag ) => new Element( tag ),
			createTextNode: ( text ) => ( {
				text,
				children: [],
				attributes: {},
			} ),
		},
		window: {
			ohmylmsPractice: {
				root: '/api/',
				studentId: 4,
				loggedIn: true,
				...client,
				practiceI18n: {
					start: 'Start',
					resume: 'Resume',
					check: 'Check',
					next: 'Next',
					progress: 'Question %1$d of %2$d',
					correct: 'Correct',
					done: '%1$d of %2$d',
					again: 'Again',
				},
			},
		},
		localStorage: { getItem: () => null },
		sessionStorage: {
			getItem: () => stored,
			setItem: ( key, value ) => {
				stored = value;
			},
			removeItem: () => {
				stored = null;
			},
		},
		fetch: async ( url, options ) => {
			requests.push( { url, options } );
			if ( url.endsWith( '/answer' ) && answerResult )
				return { ok: true, json: async () => answerResult };
			if ( url.endsWith( '/answer' ) )
				return {
					ok: ! failAnswer,
					json: async () =>
						failAnswer
							? { message: 'Try again' }
							: {
									correct: true,
									feedback: {},
									session: {
										...state,
										status: 'completed',
										answered: 3,
										correct: 3,
										current: null,
									},
								},
				};
			if ( url.endsWith( '/hint' ) )
				return {
					ok: true,
					json: async () => ( {
						hint: '<p>First</p><hr><p>Second</p>',
						lessons: [],
					} ),
				};
			if ( url.endsWith( '/skip' ) )
				return {
					ok: true,
					json: async () => ( {
						session: {
							...state,
							results: [],
							skipped: 1,
							current: {
								item_id: 11,
								name: 'Next',
								settings: { type: 'numerical' },
							},
						},
					} ),
				};
			return { ok: true, json: async () => state };
		},
	};
	vm.runInNewContext(
		fs.readFileSync(
			new URL( '../../assets/js/practice.js', import.meta.url ),
			'utf8'
		),
		context
	);
	const all = ( node = stage ) => [
		node,
		...node.children.flatMap( ( child ) => all( child ) ),
	];
	return {
		stage,
		events,
		requests,
		all,
		focus: () => focus,
		stored: () => stored,
		button: ( text ) =>
			all().find(
				( node ) => node.tag === 'button' && node.text === text
			),
	};
}
const settle = async () => {
	for ( let i = 0; i < 12; i++ ) await Promise.resolve();
};

test( 'practice uses the shared button classes while preserving its public hooks', async () => {
	const buttonClasses = {
		primary: buttonClass( { variant: 'primary' } ),
		secondary: buttonClass(),
		tertiary: buttonClass( { variant: 'tertiary', size: 'sm' } ),
	};
	const f = fixture( false, null, { can_skip: true }, null, {
		buttonClasses,
	} );
	f.button( 'Start' ).events.click();
	await settle();
	for ( const [ button, variant, hook ] of [
		[ f.button( 'Check' ), 'primary', 'ohmylms-button' ],
		[ f.button( 'Skip' ), 'secondary', 'ohmylms-practice-skip' ],
		[ f.button( 'Start over' ), 'tertiary', 'ohmylms-practice-restart' ],
	] ) {
		assert.ok( button, variant );
		assert.ok( button.classList.contains( hook ), hook );
		for ( const name of buttonClasses[ variant ].split( ' ' ) )
			assert.ok( button.classList.contains( name ), name );
	}
} );

test( 'practice entry does not start an attempt; checking is single-flight and Next uses the server state', async () => {
	const f = fixture();
	assert.equal( f.requests.length, 0 );
	f.button( 'Start' ).events.click();
	await settle();
	assert.equal( f.stored(), 'session-a' );
	assert.equal( f.focus().attributes.class, 'ohmylms-practice-progress' );
	const form = f.all().find( ( node ) => node.tag === 'form' );
	const controls = f
		.all()
		.find(
			( node ) => node.attributes.class === 'ohmylms-practice-controls'
		);
	assert.ok( controls.children.includes( f.button( 'Check' ) ) );
	form.events.submit( { preventDefault() {} } );
	form.events.submit( { preventDefault() {} } );
	await settle();
	assert.equal(
		f.requests.filter( ( r ) => r.url.endsWith( '/answer' ) ).length,
		1
	);
	assert.equal( f.button( 'Check' ).hidden, true );
	assert.equal( f.focus(), f.button( 'Next' ) );
	f.button( 'Next' ).events.click();
	assert.equal( f.stored(), null );
	assert.ok( f.button( 'Again' ) );
	assert.equal( f.focus().attributes.class, 'ohmylms-practice-summary' );
} );

test( 'answer failures keep the question and permit retry', async () => {
	const f = fixture( true );
	f.button( 'Start' ).events.click();
	await settle();
	const form = f.all().find( ( node ) => node.tag === 'form' );
	form.events.submit( { preventDefault() {} } );
	await settle();
	assert.equal( f.button( 'Check' ).disabled, false );
	assert.ok( f.all().includes( form ) );
	assert.equal( f.stored(), 'session-a' );
} );

test( 'resume reads the remembered session without creating or answering one', async () => {
	const f = fixture( false, 'session-a' );
	f.button( 'Resume' ).events.click();
	await settle();
	assert.equal( f.requests.length, 1 );
	assert.equal( f.requests[ 0 ].options.method, 'GET' );
	assert.equal( f.requests[ 0 ].url, '/api/practice/sessions/session-a' );
} );

test( 'progress dots follow the server results and Skip replaces the question without grading it', async () => {
	const f = fixture( false, null, {
		can_skip: true,
		results: [ 'right', 'help', 'wrong' ],
		answered: 3,
		item_limit: 5,
	} );
	f.button( 'Start' ).events.click();
	await settle();
	const dots = f
		.all()
		.find( ( node ) => node.attributes.class === 'ohmylms-practice-dots' );
	assert.deepEqual(
		dots.children.map( ( dot ) => dot.attributes.class ),
		[ 'is-right', 'is-help', 'is-wrong', 'is-current', 'is-left' ]
	);
	assert.ok( f.all().find( ( node ) => node.text === 'Skip' ) );
	f.all()
		.find( ( node ) => node.text === 'Skip' )
		.events.click();
	await settle();
	assert.equal(
		f.requests.filter( ( r ) => r.url.endsWith( '/skip' ) ).length,
		1
	);
	assert.equal(
		f.requests.filter( ( r ) => r.url.endsWith( '/answer' ) ).length,
		0
	);
} );

test( 'Skip is not offered when the server does not allow it, or in lesson style', async () => {
	const f = fixture();
	f.button( 'Start' ).events.click();
	await settle();
	assert.equal(
		f.all().find( ( node ) => node.text === 'Skip' ),
		undefined
	);
	const g = fixture( false, null, {
		can_skip: true,
		style: 'lesson',
		planned: 3,
	} );
	g.button( 'Start' ).events.click();
	await settle();
	assert.equal(
		g.all().find( ( node ) => node.text === 'Skip' ),
		undefined
	);
} );

test( 'a hint with horizontal rules is shown one step at a time', async () => {
	const f = fixture( false, null, {
		current: {
			item_id: 10,
			name: 'Add',
			has_hint: true,
			settings: { type: 'numerical' },
		},
	} );
	f.button( 'Start' ).events.click();
	await settle();
	const hint = f
		.all()
		.find(
			( node ) =>
				node.tag === 'button' &&
				node.attributes.class === 'ohmylms-button outline'
		);
	hint.events.click();
	await settle();
	const steps = () =>
		f
			.all()
			.filter(
				( node ) =>
					node.attributes.class === 'ohmylms-practice-hint-step'
			);
	assert.equal( steps().length, 1 );
	const more = f
		.all()
		.find( ( node ) =>
			String( node.attributes.class ).includes(
				'ohmylms-practice-more-hint'
			)
		);
	assert.ok( more );
	more.events.click();
	assert.equal( steps().length, 2 );
	assert.equal(
		f
			.all()
			.some( ( node ) =>
				String( node.attributes.class ).includes(
					'ohmylms-practice-more-hint'
				)
			),
		false
	);
} );

test( 'a wrong answer that can be tried again keeps the question open and reveals nothing', async () => {
	const f = fixture(
		false,
		null,
		{ retry: true },
		{
			correct: false,
			retry: true,
			feedback: {},
		}
	);
	f.button( 'Start' ).events.click();
	await settle();
	const form = f.all().find( ( node ) => node.tag === 'form' );
	const check = f.button( 'Check' );
	form.events.submit( { preventDefault() {} } );
	await settle();
	assert.equal( check.text, 'Try again' );
	assert.equal( check.disabled, false );
	assert.equal( check.hidden, undefined );
	assert.equal( f.button( 'Next' ), undefined );
	const verdict = f
		.all()
		.find(
			( node ) => node.className === 'ohmylms-practice-verdict is-wrong'
		);
	assert.equal( verdict.text, 'Not quite! Give it another try!' );
	assert.equal( f.stored(), 'session-a' );
} );

test( 'a finished practice lists how each skill level changed', async () => {
	const f = fixture( false, null, {
		status: 'complete',
		current: null,
		answered: 3,
		correct: 2,
		levels: [
			{
				skill: 'Decimals',
				before: 'attempted',
				after: 'familiar',
				change: 'up',
			},
			{
				skill: 'Rounding',
				before: 'proficient',
				after: 'proficient',
				change: 'same',
			},
		],
	} );
	f.button( 'Start' ).events.click();
	await settle();
	const list = f
		.all()
		.find( ( node ) => node.attributes.class === 'ohmylms-level-changes' );
	assert.equal( list.children.length, 2 );
	assert.equal( f.events[ 0 ].type, 'ohmylms:practice-complete' );
	assert.equal( f.events[ 0 ].detail.courseId, 7 );
	assert.equal( f.events[ 0 ].detail.skillId, 9 );
	assert.deepEqual( Object.keys( f.events[ 0 ].detail ).sort(), [
		'courseId',
		'skillId',
	] );
	assert.deepEqual(
		list.children.map( ( item ) => item.attributes.class ),
		[ 'is-up', 'is-same' ]
	);
} );
