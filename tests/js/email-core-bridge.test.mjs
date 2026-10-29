import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import {
	adaptEmailPackages,
	bridgeEasyEmailCore,
} from '../../tools/email-package-adapters.mjs';
import { EASY_EMAIL_CORE_EXPORTS } from '../../assets/src/shared/easy-email/export-names.mjs';

const generate = generatorModule.default || generatorModule;
const recovered = fs
	.readFileSync(
		'assets/src/modules/dist/vendors/vendors/0-49050.js',
		'utf8'
	)
	.replace( /^\/\/.*\n/, '' )
	.trim()
	.replace( /;$/, '' );

const host = {};
function bridged( source = `({49050:${ recovered }})` ) {
	const ast = parse( source );
	bridgeEasyEmailCore( ast );
	return vm.runInNewContext( generate( ast ).code, {
		window: host,
	} );
}
function webpackRequire() {
	const require = () => {
		throw new Error( 'The bridge must not load recovered dependencies' );
	};
	require.r = ( target ) =>
		Object.defineProperty( target, '__esModule', { value: true } );
	require.d = ( target, getters ) =>
		Object.entries( getters ).forEach( ( [ name, get ] ) =>
			Object.defineProperty( target, name, { enumerable: true, get } )
		);
	return require;
}

test( 'the wrapper provides exactly the names the recovered core factory exports', () => {
	// bridgeEasyEmailCore throws if the recovered factory declares any other set of names.
	assert.doesNotThrow( () => bridged() );
	assert.equal( new Set( EASY_EMAIL_CORE_EXPORTS ).size, 39 );
} );

test( 'the bridge fails closed when the recovered exports or the factory change', () => {
	assert.throws(
		() =>
			bridgeEasyEmailCore(
				parse( '({49050:(e,t,n)=>{n.r(t),n.d(t,{JsonToMjml:()=>1})}})' )
			),
		/exports differ/
	);
	assert.throws(
		() => bridgeEasyEmailCore( parse( '({1:(e,t,n)=>{}})' ) ),
		/Expected one Easy Email core factory/
	);
	assert.throws(
		() =>
			adaptEmailPackages(
				parse( 'register({1:()=>{}});' ),
				'assets/dist/vendors/vendors.js'
			),
		/Expected one Easy Email core factory/
	);
} );

test( 'the bridged factory builds the core when first required and exposes live exports', () => {
	let created = 0;
	const core = Object.fromEntries(
		EASY_EMAIL_CORE_EXPORTS.map( ( name ) => [ name, { name } ] )
	);
	host.ohmylmsEmailPackages = {
		createEasyEmailCore() {
			created++;
			return core;
		},
	};
	const factories = bridged();
	assert.equal(
		created,
		0,
		'nothing runs while the factory is only registered'
	);
	const exports = {};
	factories[ 49050 ]( {}, exports, webpackRequire() );
	assert.equal( created, 1 );
	assert.equal( exports.__esModule, true );
	assert.deepEqual(
		Object.keys( exports ).sort(),
		[ ...EASY_EMAIL_CORE_EXPORTS ].sort()
	);
	assert.equal( exports.JsonToMjml, core.JsonToMjml );
	assert.equal( exports.BlockManager, core.BlockManager );
} );

test( 'the bridged factory reports a missing bootstrap or a missing export', () => {
	const factories = bridged();
	host.ohmylmsEmailPackages = {};
	assert.throws(
		() => factories[ 49050 ]( {}, {}, webpackRequire() ),
		/must load before its consumers/
	);
	host.ohmylmsEmailPackages = { createEasyEmailCore: () => ( {} ) };
	assert.throws(
		() => factories[ 49050 ]( {}, {}, webpackRequire() ),
		/Missing npm Easy Email export AdvancedType/
	);
} );
