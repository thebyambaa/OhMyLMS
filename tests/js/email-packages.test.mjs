import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import { adaptEmailPackages } from '../../tools/email-package-adapters.mjs';
const read = ( file ) => fs.readFileSync( file, 'utf8' );
const fixtures = JSON.parse( read( 'tests/fixtures/email-mjml.json' ) );
function renderer( baseline ) {
	const module = { exports: {} };
	const math = Object.create( Math );
	let seed;
	math.random = () => {
		seed = ( seed * 1664525 + 1013904223 ) >>> 0;
		return seed / 4294967296;
	};
	const context = {
		window: {},
		module,
		exports: module.exports,
		Math: math,
		console: { warn() {}, log() {} },
	};
	if ( baseline )
		vm.runInNewContext(
			read( 'assets/src/modules/dist/vendors/vendors/0-44098.js' ),
			context
		)( module );
	else
		vm.runInNewContext(
			read( 'node_modules/mjml-browser/lib/index.js' ),
			context
		);
	return ( source, options ) => {
		seed = 42;
		return JSON.parse(
			JSON.stringify( module.exports( source, options ) )
		);
	};
}
const original = renderer( true );
const npm = renderer( false );
test( 'email package adaptation fails when the expected loader or factory boundary changes', () => {
	assert.throws(
		() =>
			adaptEmailPackages(
				parse( 'load.e = () => 1;' ),
				'assets/dist/admin/ohmylms.js'
			),
		/Expected one email chunk loader/
	);
	assert.throws(
		() =>
			adaptEmailPackages(
				parse( 'register({3270:()=>{}});' ),
				'assets/dist/chunks/355.js'
			),
		/Expected two npm email bridges/
	);
} );

test( 'email package bridges preserve the Arco namespace and CommonJS MJML call contract', () => {
	const queue = [];
	const window = {
		ohmylmsEmailPackages: {
			arco: { Button() {}, version: '2.66.5' },
			mjml() {},
		},
	};
	vm.runInNewContext( read( 'assets/dist/chunks/355.js' ), {
		webpackChunkohmylms: queue,
		window,
	} );
	const factories = queue[ 0 ][ 1 ];
	const require = () => {
		throw new Error( 'Unexpected recovered dependency' );
	};
	require.r = ( target ) =>
		Object.defineProperty( target, '__esModule', { value: true } );
	require.d = ( target, getters ) =>
		Object.entries( getters ).forEach( ( [ name, get ] ) =>
			Object.defineProperty( target, name, { enumerable: true, get } )
		);
	const arco = {};
	factories[ 3270 ]( {}, arco, require );
	assert.equal( arco.Button, window.ohmylmsEmailPackages.arco.Button );
	assert.equal( arco.version, '2.66.5' );
	assert.equal( arco.__esModule, true );
	const module = {};
	factories[ 44098 ]( module );
	assert.equal( module.exports, window.ohmylmsEmailPackages.mjml );
	delete window.ohmylmsEmailPackages;
	assert.throws( () => factories[ 44098 ]( {} ), /not ready/ );
} );

test( 'email packages remain asynchronous with host React and license notices', () => {
	const bootstrap = JSON.parse(
		read( 'assets/dist/admin/email-packages.js.map' )
	);
	assert.equal(
		bootstrap.sources.some( ( source ) => /node_modules\//.test( source ) ),
		false
	);
	const directory = 'assets/dist/admin/chunks';
	const sources = fs
		.readdirSync( directory )
		.filter( ( name ) => name.endsWith( '.js.map' ) )
		.flatMap( ( name ) => {
			const map = JSON.parse( read( `${ directory }/${ name }` ) );
			return map.sources.some( ( source ) =>
				source.includes( 'node_modules/mjml-browser/' )
			)
				? map.sources
				: [];
		} );
	assert.ok(
		sources.some( ( source ) =>
			source.includes( '@arco-design/web-react/' )
		)
	);
	assert.equal(
		sources.some( ( source ) =>
			/node_modules\/(react|react-dom)\//.test( source )
		),
		false
	);
	const notices = read( 'assets/dist/admin/email-packages.LICENSES.txt' );
	assert.ok( notices.includes( 'mjml-browser 4.16.1' ) );
	assert.ok( notices.includes( '@arco-design/web-react 2.66.5' ) );
	assert.match(
		read( 'assets/dist/admin/email-packages.asset.php' ),
		/'react-dom'/
	);
} );
for ( const fixture of fixtures ) {
	for ( const options of [
		{ beautify: true, validationLevel: 'soft' },
		{ minify: true, validationLevel: 'skip' },
		{ validationLevel: 'soft' },
	] ) {
		test( `npm MJML preserves ${ fixture.name } (${ JSON.stringify( options ) })`, () => {
			assert.deepEqual(
				npm( fixture.source, options ),
				original( fixture.source, options )
			);
		} );
	}
}
