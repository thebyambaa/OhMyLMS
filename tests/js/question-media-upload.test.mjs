import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs
	.readFileSync(
		'assets/src/features/question-editor/QuestionMediaUpload.jsx',
		'utf8'
	)
	.replace( /^import .*;$/gm, '' )
	.replace( 'export function', 'function' );

test( 'question media picker opens once, selects library data, and releases its listener', () => {
	let listener,
		cleanup,
		saved,
		frames = 0,
		opens = 0,
		closes = 0,
		detached = 0;
	const image = {
		id: 9,
		url: 'https://example.test/image.png',
		alt: 'Diagram',
	};
	const frame = {
		on: ( event, callback ) => {
			assert.equal( event, 'select' );
			listener = callback;
		},
		off: ( event ) => {
			assert.equal( event, 'select' );
			detached++;
		},
		open: () => {
			opens++;
		},
		close: () => {
			closes++;
		},
		state: () => ( {
			get: () => ( { first: () => ( { toJSON: () => image } ) } ),
		} ),
	};
	const originalUnderscore = { VERSION: '1.13.7' };
	const browserWindow = { wp: {} };
	const vendorLodash = {
		VERSION: '4.17.21',
		noConflict: () => {
			browserWindow._ = originalUnderscore;
			return vendorLodash;
		},
	};
	browserWindow._ = vendorLodash;
	const media = ( options ) => {
		assert.equal( browserWindow._, originalUnderscore );
		frames++;
		assert.deepEqual( options.library.type, [ 'image' ] );
		assert.equal( options.multiple, false );
		return frame;
	};
	const Component = new Function(
		'useRef',
		'useEffect',
		'__',
		'window',
		source + ';return QuestionMediaUpload;'
	)(
		( value ) => ( { current: value } ),
		( effect ) => {
			cleanup = effect();
		},
		( text ) => text,
		Object.assign( browserWindow, { wp: { media } } )
	);
	const open = Component( {
		onSelect: ( value ) => {
			saved = value;
		},
		render: ( { open } ) => open,
	} );
	open();
	open();
	listener();
	assert.equal( frames, 1 );
	assert.equal( opens, 2 );
	assert.deepEqual( saved, image );
	cleanup();
	assert.equal( detached, 1 );
	assert.equal( closes, 1 );
} );
