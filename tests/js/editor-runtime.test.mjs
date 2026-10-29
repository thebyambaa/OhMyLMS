import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { createRequire } from 'node:module';
import {
	editorRuntime,
	sharedEditorRuntime,
	retiredEditorFactories,
} from '../../tools/editor-runtime-manifest.mjs';
import { auditEditorRuntime } from '../../tools/editor-runtime-adapters.mjs';

const nodeRequire = createRequire( import.meta.url );
const read = ( file ) => fs.readFileSync( file, 'utf8' );
const window = {
	React: nodeRequire( 'react' ),
	ReactDOM: nodeRequire( 'react-dom' ),
	ohmylmsEditorPackages: {
		highlight: nodeRequire( 'highlight.js/lib/core' ),
	},
};
vm.runInNewContext( read( 'assets/dist/admin/editor-tiptap.js' ), {
	window,
	console,
	setTimeout,
	clearTimeout,
} );
const runtime = window.ohmylmsEditorPackages.tiptap;
const json = ( value ) => JSON.parse( JSON.stringify( value ) );
function webpackRequire( factories ) {
	const cache = {};
	function require( id ) {
		if ( cache[ id ] ) return cache[ id ].exports;
		const module = ( cache[ id ] = { exports: {} } );
		factories[ id ]( module, module.exports, require );
		return module.exports;
	}
	require.r = ( target ) =>
		Object.defineProperty( target, '__esModule', { value: true } );
	require.d = ( target, getters ) =>
		Object.entries( getters ).forEach( ( [ name, get ] ) =>
			Object.defineProperty( target, name, { enumerable: true, get } )
		);
	return require;
}
function capture( file ) {
	const queue = [];
	vm.runInNewContext( read( file ), { window, webpackChunkohmylms: queue } );
	return queue[ 0 ][ 1 ];
}

test( 'every recovered editor bridge resolves to the same npm module instance', () => {
	for ( const [ file, mapping ] of [
		[ 'assets/dist/vendors/editor.js', editorRuntime ],
		[ 'assets/dist/vendors/vendors.js', sharedEditorRuntime ],
	] ) {
		const factories = capture( file );
		const require = webpackRequire( factories );
		for ( const [ id, { group, names } ] of Object.entries( mapping ) ) {
			const actual = require( id );
			const expected =
				names ||
				Object.fromEntries(
					Object.keys( runtime[ group ] ).map( ( key ) => [
						key,
						key,
					] )
				);
			assert.deepEqual(
				Object.keys( actual ).sort(),
				Object.keys( expected ).sort(),
				id
			);
			for ( const [ key, name ] of Object.entries( expected ) ) {
				assert.notEqual(
					runtime[ group ][ name ],
					undefined,
					`${ id }.${ key }`
				);
				assert.equal(
					actual[ key ],
					runtime[ group ][ name ],
					`${ id }.${ key }`
				);
			}
		}
		for ( const id of retiredEditorFactories[ file ] )
			assert.equal( factories[ id ], undefined );
		if ( file.endsWith( '/editor.js' ) )
			assert.equal(
				Object.keys( factories ).length,
				Object.keys( mapping ).length
			);
	}
} );

test( 'editor audit rejects unknown implementations and new collaboration consumers', () => {
	const manifest = JSON.parse( read( 'assets/src/manifest.json' ) );
	const readSource = ( source ) => read( `assets/src/${ source }` );
	auditEditorRuntime( manifest, readSource );
	const changed = structuredClone( manifest );
	changed.assets
		.find( ( asset ) => asset.output.endsWith( '/editor.js' ) )
		.factories.push( { id: 123456789 } );
	assert.throws(
		() => auditEditorRuntime( changed, readSource ),
		/Unmapped recovered editor/
	);
	assert.throws(
		() =>
			auditEditorRuntime(
				manifest,
				( source ) => readSource( source ) + ';load(98405)'
			),
		/Retired collaboration/
	);
} );

test( 'npm icons preserve all recovered SVGs, display names and export aliases', () => {
	const legacy = {};
	const React = {
		forwardRef: ( render ) => ( { render } ),
		createElement: ( type, props ) => ( { type, props } ),
	};
	const require = webpackRequire( {
		41594: ( module ) => {
			module.exports = React;
		},
	} );
	const factory = vm.runInNewContext(
		read( 'assets/src/modules/dist/vendors/vendors/0-6165.js' )
	);
	factory( {}, legacy, require );
	const actual = runtime.icons;
	assert.equal( Object.keys( actual.icons ).length, 1534 );
	assert.deepEqual(
		Object.keys( actual ).sort(),
		Object.keys( legacy ).sort()
	);
	const aliases = new Map(
		Object.entries( legacy.icons ).map( ( [ name, icon ] ) => [
			icon,
			name,
		] )
	);
	for ( const [ name, icon ] of Object.entries( legacy.icons ) ) {
		assert.equal(
			actual.icons[ name ].displayName,
			icon.displayName,
			name
		);
		assert.deepEqual(
			json( actual.icons[ name ].render( {}, null ).props.iconNode ),
			json( icon.render( {}, null ).props.iconNode ),
			name
		);
	}
	for ( const [ name, value ] of Object.entries( legacy ) )
		if ( aliases.has( value ) )
			assert.equal(
				actual[ name ],
				actual.icons[ aliases.get( value ) ],
				name
			);
} );

test( 'compiled mergeAttributes keeps __proto__ as data without inheriting DOM attributes', () => {
	const attrs = runtime.core.mergeAttributes(
		JSON.parse( '{"__proto__":{"onerror":"unexpected()"},"class":"safe"}' )
	);
	assert.equal(
		Object.getPrototypeOf( attrs ),
		Object.getPrototypeOf( runtime.core.mergeAttributes( {} ) )
	);
	assert.equal( Object.hasOwn( attrs, '__proto__' ), true );
	assert.equal( attrs.onerror, undefined );
	assert.equal( attrs.class, 'safe' );
} );

test( 'editor build uses one ProseMirror implementation and WordPress React externals', () => {
	const sources = JSON.parse(
		read( 'assets/dist/admin/editor-tiptap.js.map' )
	).sources;
	assert.equal(
		sources.some( ( source ) =>
			/node_modules\/(react|react-dom)\//.test( source )
		),
		false
	);
	assert.equal(
		sources.some( ( source ) =>
			/node_modules\/prosemirror-[^/]+\/dist\/index\.cjs/.test( source )
		),
		false
	);
	assert.equal(
		sources.some( ( source ) =>
			/node_modules\/highlight.js\//.test( source )
		),
		false
	);
	for ( const name of [ 'state', 'model', 'view', 'transform' ] )
		assert.equal(
			sources.filter( ( source ) =>
				source.endsWith(
					`node_modules/prosemirror-${ name }/dist/index.js`
				)
			).length,
			1
		);
} );
