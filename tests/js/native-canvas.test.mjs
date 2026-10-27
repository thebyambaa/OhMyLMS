import test from 'node:test';
import assert from 'node:assert/strict';
import { recoverNativeCanvas } from '../../assets/src/features/learning/nativeCanvas.mjs';

function canvas( {
	healthy = false,
	source = 'blob:http://800.local/test',
} = {} ) {
	const attributes = new Map( [ [ 'src', source ] ] );
	const html =
		'<!doctype html><html><head><style>body{margin:0}</style></head></html>';
	let reads = 0;
	return {
		attributes,
		html,
		get reads() {
			return reads;
		},
		frame: {
			isConnected: true,
			contentDocument: healthy
				? { head: { childNodes: [ {} ] }, body: { childNodes: [] } }
				: null,
			getAttribute: ( key ) => attributes.get( key ),
			hasAttribute: ( key ) => attributes.has( key ),
			setAttribute: ( key, value ) => attributes.set( key, value ),
			ownerDocument: {
				defaultView: {
					fetch: async () => {
						reads++;
						return { ok: true, text: async () => html };
					},
				},
			},
		},
	};
}

test( 'unloaded native canvas receives the exact core document and only recovers once', async () => {
	const fixture = canvas();
	assert.equal( await recoverNativeCanvas( fixture.frame ), true );
	assert.equal( fixture.attributes.get( 'srcdoc' ), fixture.html );
	assert.equal( await recoverNativeCanvas( fixture.frame ), false );
	assert.equal( fixture.reads, 1 );
} );

test( 'working native canvases and ordinary frame URLs are left intact', async () => {
	for ( const options of [ { healthy: true }, { source: '/editor' } ] ) {
		const fixture = canvas( options );
		assert.equal( await recoverNativeCanvas( fixture.frame ), false );
		assert.equal( fixture.reads, 0 );
		assert.equal( fixture.attributes.has( 'srcdoc' ), false );
	}
} );

test( 'removed canvases and unavailable blobs are not replaced', async () => {
	const fixture = canvas();
	fixture.frame.isConnected = false;
	assert.equal( await recoverNativeCanvas( fixture.frame ), false );
	assert.equal( fixture.attributes.has( 'srcdoc' ), false );
	fixture.frame.isConnected = true;
	fixture.frame.ownerDocument.defaultView.fetch = async () => {
		throw new Error( 'Blob unavailable' );
	};
	assert.equal( await recoverNativeCanvas( fixture.frame ), false );
	assert.equal( fixture.attributes.has( 'srcdoc' ), false );
} );
