import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import {
	focusQuizCanvas,
	quizQuestions,
} from '../../assets/src/features/learning/quizWorkspace.mjs';

test( 'quiz navigation preserves nested document order and original placements', () => {
	const question = ( clientId ) => ( {
		clientId,
		name: 'ohmylms/shared-multiple-choice',
		attributes: {},
		innerBlocks: [],
	} );
	const first = question( 'first' );
	const nested = question( 'nested' );
	const last = question( 'last' );
	const blocks = [
		first,
		{ name: 'core/group', innerBlocks: [ nested ] },
		last,
	];
	assert.deepEqual( quizQuestions( blocks ), [ first, nested, last ] );
	assert.equal( blocks[ 1 ].innerBlocks[ 0 ], nested );
} );

test( 'focus retains nested question inputs and ancestors, then restores the full document', async () => {
	const dom = new JSDOM( `<html><head></head><body>
		<div data-block="intro" data-type="core/paragraph">Instructions</div>
		<div data-block="group" data-type="core/group">
			<div data-block="first" data-type="ohmylms/shared-multiple-choice"><input value="unfinished answer"></div>
			<div data-block="second" data-type="ohmylms/quiz-question-reference"></div>
		</div></body></html>` );
	const { document } = dom.window;
	// These fixture IDs need no escaping. Browsers supply CSS.escape natively.
	dom.window.CSS = { escape: ( value ) => value };
	const original = document.body.innerHTML;
	const input = document.querySelector( 'input' );
	let cleanup = focusQuizCanvas( document, 'question', 'first' );
	const rules = () => document.querySelector( 'style' ).textContent;
	assert.match( rules(), /data-block="intro"/ );
	assert.match( rules(), /data-block="second"/ );
	assert.doesNotMatch( rules(), /data-block="group"/ );
	assert.doesNotMatch( rules(), /data-block="first"/ );
	const later = document.createElement( 'div' );
	later.setAttribute( 'data-block', 'later' );
	document.body.append( later );
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	assert.match( rules(), /data-block="later"/ );
	later.remove();
	cleanup();
	assert.equal( document.querySelector( 'input' ), input );
	assert.equal( document.body.innerHTML, original );
	cleanup = focusQuizCanvas( document, 'introduction', '' );
	assert.match( rules(), /data-block="first"/ );
	assert.match( rules(), /data-block="second"/ );
	assert.doesNotMatch( rules(), /data-block="intro"/ );
	cleanup();
	cleanup = focusQuizCanvas( document, 'document', 'first' );
	assert.equal( rules(), '' );
	cleanup();
	assert.equal( document.querySelector( 'style' ), null );
	dom.window.close();
} );

test( 'a focused question gets the full width and room for its toolbar; other views do not', () => {
	const dom = new JSDOM( `<html><head></head><body>
		<div class="ohmylms-paper-columns-body">
			<div data-block="first" data-type="ohmylms/shared-multiple-choice"></div>
			<div data-block="second" data-type="ohmylms/shared-multiple-choice"></div>
		</div></body></html>` );
	const { document } = dom.window;
	dom.window.CSS = { escape: ( value ) => value };
	const rules = () => document.querySelector( 'style' ).textContent;
	let cleanup = focusQuizCanvas( document, 'question', 'first' );
	assert.match( rules(), /\.ohmylms-paper-columns-body \{[^}]*column-count: 1/ );
	assert.match( rules(), /\.is-root-container \{ padding-top/ );
	cleanup();
	cleanup = focusQuizCanvas( document, 'document', 'first' );
	assert.equal( rules(), '' );
	cleanup();
} );
