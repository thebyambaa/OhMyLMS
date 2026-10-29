import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { createRequire } from 'node:module';
import {
	vendorPackages,
	retiredVendorPackages,
	auditVendorPackages,
} from '../../tools/vendor-package-adapters.mjs';

const nodeRequire = createRequire( import.meta.url );
const read = ( file ) => fs.readFileSync( file, 'utf8' );
const window = {
	React: nodeRequire( 'react' ),
	ReactJSXRuntime: nodeRequire( 'react/jsx-runtime' ),
};
for ( const name of [ 'charts', 'emotion' ] )
	vm.runInNewContext( read( `assets/dist/admin/vendor-${ name }.js` ), {
		window,
		console,
		setTimeout,
		clearTimeout,
	} );
function capture( file ) {
	const queue = [];
	vm.runInNewContext( read( file ), { window, webpackChunkohmylms: queue } );
	return queue[ 0 ];
}
function exportsOf( factory ) {
	const exports = {};
	const require = () => {
		throw new Error( 'An npm bridge must not import recovered factories' );
	};
	require.d = ( target, getters ) =>
		Object.entries( getters ).forEach( ( [ name, get ] ) =>
			Object.defineProperty( target, name, { enumerable: true, get } )
		);
	factory( {}, exports, require );
	return exports;
}

test( 'every chart and Emotion factory exposes the exact named npm instances', () => {
	for ( const [ file, mapping ] of Object.entries( vendorPackages ) ) {
		const [ chunks, factories ] = capture( file );
		assert.deepEqual( Array.from( chunks ), [
			file.includes( 'chartjs' ) ? 552 : 222,
		] );
		assert.deepEqual(
			Object.keys( factories ).sort(),
			Object.keys( mapping ).sort()
		);
		for ( const [ id, { group, names } ] of Object.entries( mapping ) ) {
			const actual = exportsOf( factories[ id ] );
			assert.deepEqual(
				Object.keys( actual ).sort(),
				Object.keys( names ).sort()
			);
			for ( const [ key, name ] of Object.entries( names ) ) {
				assert.notEqual(
					window.ohmylmsVendorPackages[ group ][ name ],
					undefined
				);
				assert.equal(
					actual[ key ],
					window.ohmylmsVendorPackages[ group ][ name ]
				);
			}
		}
	}
	assert.equal( window.ohmylmsVendorPackages.charts.Chart.version, '4.5.0' );
	const factories = capture( 'assets/dist/vendors/vendors.js' )[ 1 ];
	for ( const id of retiredVendorPackages )
		assert.equal( factories[ id ], undefined, String( id ) );
} );

test( 'vendor guard rejects new implementations and direct, bound or context-map consumers', () => {
	const manifest = JSON.parse( read( 'assets/src/manifest.json' ) );
	const readSource = ( source ) => read( `assets/src/${ source }` );
	auditVendorPackages( manifest, readSource );
	const changed = structuredClone( manifest );
	changed.assets
		.find( ( asset ) => asset.output.endsWith( '/chartjs.js' ) )
		.factories.push( { id: 'unknown' } );
	assert.throws(
		() => auditVendorPackages( changed, readSource ),
		/Unmapped vendor package/
	);
	for ( const expression of [
		'load(46447)',
		'load.bind(load,40390)',
		'({"./color":46447})',
	] ) {
		assert.throws(
			() =>
				auditVendorPackages(
					manifest,
					( source ) =>
						readSource( source ) +
						( source === 'runtimes/dist/chunks/655.js'
							? `;${ expression };`
							: '' )
				),
			/Retired vendor/
		);
	}
} );

test( 'npm vendor entries externalize host React and ship every package license', () => {
	for ( const name of [ 'charts', 'emotion' ] ) {
		const sources = JSON.parse(
			read( `assets/dist/admin/vendor-${ name }.js.map` )
		).sources;
		assert.equal(
			sources.some( ( source ) =>
				/node_modules\/(react|react-dom)\//.test( source )
			),
			false
		);
		const licenses = read(
			`assets/dist/admin/vendor-${ name }.LICENSES.txt`
		);
		const packages = new Set(
			sources.flatMap( ( source ) => {
				const path = source.split( 'node_modules/' )[ 1 ];
				return path
					? [
							path
								.split( '/' )
								.slice( 0, path.startsWith( '@' ) ? 2 : 1 )
								.join( '/' ),
						]
					: [];
			} )
		);
		for ( const packageName of packages )
			assert.ok( licenses.includes( packageName + ' ' ), packageName );
	}
	assert.match(
		read( 'assets/dist/admin/vendor-charts.asset.php' ),
		/'react-jsx-runtime'/
	);
} );
