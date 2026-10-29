import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { createRequire } from 'node:module';

/** Loads recovered Webpack factories in Node, outside the browser.
 * Meant for characterization tests: it resolves `n( id )` against the extracted
 * modules in assets/src/modules/dist, so recovered code runs as shipped.
 * Browser globals the code touches at load time are stubbed; anything that really needs a
 * DOM must fail loudly rather than pretend. Install the stubs with `installBrowserStubs()`.
 */
const root = path.resolve( import.meta.dirname, '..' );
const dist = path.join( root, 'assets/src/modules/dist' );
const bundles = [ 'vendors/vendors', 'chunks/355', 'admin/ohmylms' ];

let files;
function index() {
	if ( files ) return files;
	files = new Map();
	for ( const bundle of bundles ) {
		for ( const name of fs.readdirSync( path.join( dist, bundle ) ) ) {
			const id = name.match( /^0-(\d+)\.js$/ )?.[ 1 ];
			if ( id && ! files.has( id ) )
				files.set( id, path.join( dist, bundle, name ) );
		}
	}
	return files;
}

/** Minimal globals read while recovered modules initialise. */
export function installBrowserStubs( {
	imagesUrl = 'https://assets.test/images/',
} = {} ) {
	const hostRequire = createRequire( path.join( root, 'package.json' ) );
	globalThis.React = hostRequire( 'react' );
	globalThis.ReactDOM = hostRequire( 'react-dom' );
	globalThis.window = globalThis;
	globalThis.MRM_Vars = { images_url: imagesUrl };
	globalThis.document = {
		querySelector: () => ( { getAttribute: () => null } ),
	};
	globalThis.DOMParser = class {
		parseFromString() {
			throw new Error( 'DOMParser is stubbed; this needs a real DOM' );
		}
	};
	return hostRequire;
}

/** Create an isolated loader. `overrides` maps module ids to replacement exports. */
export function createLoader( overrides = {} ) {
	const cache = new Map();
	function load( id ) {
		id = String( id );
		if ( id in overrides ) return overrides[ id ];
		if ( cache.has( id ) ) return cache.get( id ).exports;
		const file = index().get( id );
		if ( ! file ) throw new Error( `Recovered module ${ id } not found` );
		const module = { exports: {} };
		cache.set( id, module );
		const factory = vm.runInThisContext( fs.readFileSync( file, 'utf8' ), {
			filename: file,
		} );
		const require = ( dependency ) => load( dependency );
		require.r = ( target ) =>
			Object.defineProperty( target, '__esModule', { value: true } );
		require.d = ( target, definitions ) => {
			for ( const key of Object.keys( definitions ) )
				Object.defineProperty( target, key, {
					enumerable: true,
					get: definitions[ key ],
				} );
		};
		require.n = ( target ) => {
			const getter = target?.__esModule
				? () => target.default
				: () => target;
			require.d( getter, { a: getter } );
			return getter;
		};
		require.o = ( target, key ) =>
			Object.prototype.hasOwnProperty.call( target, key );
		require.g = globalThis;
		require.e = () => Promise.resolve();
		require.nmd = ( target ) => {
			target.paths = [];
			target.children ||= [];
			return target;
		};
		factory( module, module.exports, require );
		return module.exports;
	}
	return load;
}
