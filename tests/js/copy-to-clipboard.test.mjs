import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import {
	RESET_DELAY,
	canUseClipboardApi,
	copyText,
} from '../../assets/src/shared/copy-to-clipboard/model.mjs';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';

const hostRequire = installBrowserStubs();
const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );
const { createElement } = createRequire( import.meta.url )( 'react' );
const i18n = hostRequire( '@wordpress/i18n' );
const recovered = createLoader( {
	41594: hostRequire( 'react' ),
	12470: i18n,
} )( 21186 ).A;
const { CopyToClipboard } =
	await import( '../../assets/src/shared/copy-to-clipboard/CopyToClipboard.mjs' );

test( 'initial markup matches the recovered component', () => {
	for ( const textToCopy of [ 'abc', '', 'a "quoted" <tag> & more' ] ) {
		assert.equal(
			renderToStaticMarkup(
				createElement( CopyToClipboard, { textToCopy } )
			),
			renderToStaticMarkup( createElement( recovered, { textToCopy } ) )
		);
	}
	assert.match(
		renderToStaticMarkup(
			createElement( CopyToClipboard, { textToCopy: 'x' } )
		),
		/class="ohmylms-copy-to-clipboard"/
	);
} );

test( 'the Clipboard API needs a secure context', () => {
	assert.equal(
		canUseClipboardApi( { clipboard: {} }, { isSecureContext: true } ),
		true
	);
	assert.equal(
		canUseClipboardApi( { clipboard: {} }, { isSecureContext: false } ),
		false
	);
	assert.equal( canUseClipboardApi( {}, { isSecureContext: true } ), false );
	assert.equal( canUseClipboardApi( undefined, undefined ), false );
	assert.equal( RESET_DELAY, 2000 );
} );

test( 'copyText uses the Clipboard API when available', async () => {
	const written = [];
	await copyText( 'hi', {
		navigator: {
			clipboard: { writeText: async ( t ) => written.push( t ) },
		},
		window: { isSecureContext: true },
		document: {},
	} );
	assert.deepEqual( written, [ 'hi' ] );
} );

test( 'copyText falls back to a hidden textarea and cleans it up', async () => {
	const log = [];
	const textarea = {
		style: {},
		focus: () => log.push( 'focus' ),
		select: () => log.push( 'select' ),
	};
	await copyText( 'hi', {
		navigator: {},
		window: { isSecureContext: false },
		document: {
			createElement: ( tag ) => {
				log.push( tag );
				return textarea;
			},
			execCommand: ( command ) => log.push( command ),
			body: {
				appendChild: () => log.push( 'append' ),
				removeChild: () => log.push( 'remove' ),
			},
		},
	} );
	assert.equal( textarea.value, 'hi' );
	assert.deepEqual( textarea.style, { position: 'fixed', opacity: '0' } );
	assert.deepEqual( log, [
		'textarea',
		'append',
		'focus',
		'select',
		'copy',
		'remove',
	] );
} );

test( 'copyText rejects when the Clipboard API fails', async () => {
	await assert.rejects(
		copyText( 'hi', {
			navigator: {
				clipboard: {
					writeText: async () => {
						throw new Error( 'denied' );
					},
				},
			},
			window: { isSecureContext: true },
			document: {},
		} ),
		/denied/
	);
} );
