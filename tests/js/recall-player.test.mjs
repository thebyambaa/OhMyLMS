import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

test( 'expired study hides targets immediately and defers its request until mounting finishes', async () => {
	class Node {
		constructor( tag ) {
			this.tag = tag;
			this.children = [];
			this.isConnected = true;
		}
		append( ...children ) {
			this.children.push( ...children );
		}
		replaceChildren() {
			this.children = [];
		}
		setAttribute() {}
		addEventListener() {}
		focus() {}
		querySelectorAll( tag ) {
			return this.children.flatMap( ( child ) => [
				...( child.tag === tag ? [ child ] : [] ),
				...child.querySelectorAll( tag ),
			] );
		}
	}
	const cleared = [];
	let transition;
	const render = vm.runInNewContext(
		fs
			.readFileSync( 'assets/interactivity/recall-player.js', 'utf8' )
			.replace( 'export function', 'function' ) + ';renderRecall;',
		{
			window: {
				wp: {
					i18n: {
						__: ( value ) => value,
						sprintf: ( value ) => value,
					},
				},
			},
			document: {
				createElement: ( tag ) => new Node( tag ),
				addEventListener() {},
				removeEventListener() {},
			},
			setInterval: () => 41,
			clearInterval: ( id ) => cleared.push( id ),
			setTimeout: ( callback ) => {
				transition = callback;
				return 42;
			},
			clearTimeout: ( id ) => cleared.push( id ),
		}
	);
	const stage = new Node( 'section' );
	const actions = [];
	const cleanup = render(
		stage,
		{
			phase: 'study',
			studyItems: [ { text: 'Үүл' } ],
			remainingSeconds: 0,
		},
		async ( action ) => actions.push( action ),
		() => {}
	);
	assert.equal(
		stage.children.find( ( child ) => child.tag === 'ul' ).children.length,
		0
	);
	assert.deepEqual( actions, [] );
	transition();
	await Promise.resolve();
	assert.deepEqual( actions, [ 'recall' ] );
	cleanup();
	assert.ok( cleared.includes( 41 ) && cleared.includes( 42 ) );
} );
