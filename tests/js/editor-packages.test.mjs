import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import * as npmLowlight from 'lowlight';
import { auditHighlightConsumers } from '../../tools/editor-package-adapters.mjs';

const read = ( file ) => fs.readFileSync( file, 'utf8' );
function recoveredLowlight() {
	const queue = [];
	vm.runInNewContext( read( 'build/parity/assets/dist/vendors/vendors.js' ), {
		webpackChunkohmylms: queue,
	} );
	const factories = queue[ 0 ][ 1 ];
	const cache = {};
	function require( id ) {
		if ( cache[ id ] ) return cache[ id ].exports;
		const module = ( cache[ id ] = { exports: {} } );
		factories[ id ]( module, module.exports, require );
		return module.exports;
	}
	require.r = ( exports ) =>
		Object.defineProperty( exports, '__esModule', { value: true } );
	require.d = ( exports, getters ) =>
		Object.entries( getters ).forEach( ( [ key, get ] ) =>
			Object.defineProperty( exports, key, { enumerable: true, get } )
		);
	return require( 7871 );
}
const json = ( value ) => JSON.parse( JSON.stringify( value ) );

test( 'npm Lowlight preserves all recovered languages, common languages, and highlighting trees', () => {
	const recovered = recoveredLowlight();
	assert.deepEqual(
		Object.keys( npmLowlight.all ),
		Object.keys( recovered.all )
	);
	assert.deepEqual(
		Object.keys( npmLowlight.common ),
		Object.keys( recovered.common )
	);
	const before = recovered.createLowlight( recovered.all );
	const after = npmLowlight.createLowlight( npmLowlight.all );
	const samples = [
		'const value = 42; // Монгол хэл\nreturn "<script> & text";',
		'<div class="example">Hello &amp; world</div>\n# comment\nSELECT count(*) FROM lessons;',
		'',
	];
	for ( const language of Object.keys( recovered.all ) ) {
		for ( const content of samples )
			assert.deepEqual(
				json( after.highlight( language, content ) ),
				json( before.highlight( language, content ) ),
				language
			);
	}
	for ( const language of [
		'js',
		'ts',
		'py',
		'html',
		'sh',
		'c++',
		'yml',
		'md',
		'tex',
	] ) {
		assert.equal(
			after.registered( language ),
			before.registered( language )
		);
		assert.deepEqual(
			json( after.highlight( language, samples[ 0 ] ) ),
			json( before.highlight( language, samples[ 0 ] ) ),
			language
		);
	}
	for ( const content of samples )
		assert.deepEqual(
			json( after.highlightAuto( content ) ),
			json( before.highlightAuto( content ) )
		);
	assert.throws(
		() => after.highlight( 'not-a-language', 'text' ),
		/Unknown language/
	);
} );

test( 'the production bridge shares the npm instance and removes the recovered core', () => {
	const queue = [];
	vm.runInNewContext( read( 'assets/dist/vendors/vendors.js' ), {
		webpackChunkohmylms: queue,
	} );
	assert.equal( queue[ 0 ][ 1 ][ 38416 ], undefined );
	const factory = queue[ 0 ][ 1 ][ 7871 ];
	const require = {
		r: ( exports ) =>
			Object.defineProperty( exports, '__esModule', { value: true } ),
		d: ( exports, getters ) =>
			Object.entries( getters ).forEach( ( [ key, get ] ) =>
				Object.defineProperty( exports, key, { get } )
			),
	};
	const exports = {};
	// A fresh context evaluates just this factory, without starting the admin application.
	const bridge = vm.runInNewContext( `(${ factory.toString() })`, {
		window: { ohmylmsEditorPackages: { lowlight: npmLowlight } },
	} );
	bridge( {}, exports, require );
	assert.equal( exports.createLowlight, npmLowlight.createLowlight );
	assert.equal( exports.all, npmLowlight.all );
	const missing = vm.runInNewContext( `(${ factory.toString() })`, {
		window: {},
	} );
	assert.throws(
		() => missing( {}, {}, require ),
		/must load before the editor/
	);
	const map = JSON.parse(
		read( 'assets/dist/admin/editor-lowlight.js.map' )
	);
	assert.ok(
		map.sources.some( ( source ) =>
			source.includes( 'node_modules/lowlight/' )
		)
	);
	assert.ok(
		! map.sources.some( ( source ) =>
			/node_modules\/(react|prosemirror)/.test( source )
		)
	);
	assert.match(
		read( 'assets/dist/admin/editor-lowlight.LICENSES.txt' ),
		/highlight\.js 11\.11\.1/
	);
} );

test( 'retiring the recovered core fails if another consumer appears', () => {
	const manifest = JSON.parse( read( 'assets/src/manifest.json' ) );
	const source = ( file ) => read( `assets/src/${ file }` );
	auditHighlightConsumers( manifest, source );
	assert.throws(
		() =>
			auditHighlightConsumers(
				manifest,
				( file ) =>
					source( file ) +
					( file.endsWith( '0-35358.js' ) ? ';n(38416);' : '' )
			),
		/still used/
	);
} );
