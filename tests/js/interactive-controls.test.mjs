import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { JSDOM, VirtualConsole } from 'jsdom';

const script = fs.readFileSync(
	path.resolve(
		import.meta.dirname,
		'../../assets/js/interactive-controls.js'
	),
	'utf8'
);

/**
 * Run the script as a head script of a parsing document, then wait for DOMContentLoaded.
 *
 * @param {string}  html       Document markup.
 * @param {boolean} removeBody Drop the body before the document finishes loading.
 * @return {Promise<{dom: JSDOM, errors: string[]}>} Window and uncaught listener errors.
 */
async function load( html, removeBody ) {
	const errors = [];
	const virtualConsole = new VirtualConsole();
	virtualConsole.on( 'jsdomError', ( error ) =>
		errors.push( error.message )
	);
	const dom = new JSDOM( html, {
		runScripts: 'outside-only',
		virtualConsole,
	} );
	const { document } = dom.window;
	if ( removeBody ) {
		document.body.remove();
	}
	dom.window.eval( script );
	if ( document.readyState === 'loading' ) {
		await new Promise( ( resolve ) =>
			document.addEventListener( 'DOMContentLoaded', resolve )
		);
	}
	return { dom, errors };
}

const settle = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

test( 'starts in a document without a body and enhances the body mounted later', async () => {
	// The block editor canvas iframe removes its body during parsing; React mounts one afterwards.
	const { dom, errors } = await load(
		'<!doctype html><html><head></head><body></body></html>',
		true
	);
	const { document } = dom.window;
	assert.equal( document.body, null );
	assert.deepEqual( errors, [] );

	const body = document.createElement( 'body' );
	body.innerHTML =
		'<p><input type="text" class="ohmylms-expression-input"></p>';
	document.documentElement.append( body );
	await settle();
	const input = body.querySelector( '.ohmylms-expression-input' );
	assert.equal( input.getAttribute( 'data-keys' ), '1' );
	assert.ok( body.querySelector( '.ohmylms-expression-keys' ) );
	dom.window.close();
} );

test( 'pages with a body keep observing it for added questions', async () => {
	const { dom, errors } = await load(
		'<!doctype html><html><body></body></html>',
		false
	);
	const { document } = dom.window;
	assert.deepEqual( errors, [] );
	document.body.insertAdjacentHTML(
		'beforeend',
		'<div><input type="text" class="ohmylms-expression-input"></div>'
	);
	await settle();
	assert.equal(
		document
			.querySelector( '.ohmylms-expression-input' )
			.getAttribute( 'data-keys' ),
		'1'
	);
	dom.window.close();
} );
