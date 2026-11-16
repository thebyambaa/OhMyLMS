import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { renderHangman } from '../../assets/interactivity/live-hangman.mjs';

test( 'live Hangman reveals server results, recovers failures and restarts without submitting the quiz', async ( t ) => {
	const dom = new JSDOM(
		'<form><div id="game" data-question-id="1"><input type="hidden" data-answer-key="guesses" value=""></div></form>'
	);
	const root = dom.window.document.getElementById( 'game' );
	const answers = [];
	const requests = [];
	let fail = false;
	t.mock.method( globalThis, 'fetch', async ( url, options ) => {
		const request = JSON.parse( options.body );
		requests.push( request );
		if ( fail ) {
			throw new Error( 'Connection lost' );
		}
		const solved = request.guesses.includes( 'a' );
		return {
			ok: true,
			json: async () => ( {
				guessed: Array.from( request.guesses ),
				letters: [ solved ? 'A' : null ],
				mistakes: 0,
				limit: 6,
				done: solved,
				solved,
			} ),
		};
	} );
	const el = ( tag, props, children = [] ) => {
		const node = dom.window.document.createElement( tag );
		for ( const [ key, value ] of Object.entries( props ) ) {
			if ( key === 'text' ) {
				node.textContent = value;
			} else {
				node.setAttribute( key, value );
			}
		}
		node.append( ...children );
		return node;
	};
	renderHangman(
		root,
		{ play_url: '/play', play_nonce: 'nonce', play_token: 'token' },
		{ el, setAnswer: ( node, key, value ) => answers.push( value ) },
		{
			__: ( text ) => text,
			sprintf: ( text, ...args ) =>
				text.replace( /%\d\$d/g, () => String( args.shift() ) ),
		}
	);
	const settle = () => new Promise( ( resolve ) => setImmediate( resolve ) );
	await settle();
	assert.equal(
		root.querySelector( '.ohmylms-hangman-shape' ).textContent,
		'_'
	);
	const key = [
		...root.querySelectorAll( '.ohmylms-hangman-keys button' ),
	].find( ( button ) => button.textContent === 'A' );
	fail = true;
	key.click();
	await settle();
	assert.match( root.textContent, /Connection lost/ );
	assert.equal( key.disabled, false );
	fail = false;
	key.click();
	await settle();
	assert.equal(
		root.querySelector( '.ohmylms-hangman-shape' ).textContent,
		'A'
	);
	assert.match( root.textContent, /You found the word/ );
	assert.equal( key.disabled, true );
	assert.equal( answers.at( -1 ), 'a' );
	[ ...root.querySelectorAll( 'button' ) ]
		.find( ( button ) => button.textContent === 'Play again' )
		.click();
	await settle();
	assert.equal( key.disabled, false );
	assert.equal( requests.at( -1 ).guesses, '' );
	root.querySelector( 'input[data-answer-key="guesses"]' ).value = 'a';
	root.closest( 'form' ).dispatchEvent(
		new dom.window.CustomEvent( 'ohmylms:answer-restored', {
			detail: { questionId: 1 },
		} )
	);
	await settle();
	assert.equal( requests.at( -1 ).guesses, 'a' );
	assert.match( root.textContent, /You found the word/ );
	assert.ok(
		[ ...root.querySelectorAll( 'button' ) ].every(
			( button ) => button.type === 'button'
		)
	);
	dom.window.close();
} );
