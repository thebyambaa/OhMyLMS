import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { adaptVendors } from '../../tools/vendor-adapters.mjs';
import { adaptEditorPackages } from '../../tools/editor-package-adapters.mjs';
import { adaptEmailModel } from '../../tools/email-runtime-adapters.mjs';
import { adaptEditorRuntime } from '../../tools/editor-runtime-adapters.mjs';
import {
	auditEmailVendors,
	emailVendorModules,
	emailVendorSource,
	emailVendorTarget,
} from '../../tools/email-vendor-chunks.mjs';

const generate = generatorModule.default || generatorModule;
const read = ( name ) => fs.readFileSync( name, 'utf8' );
function capture( code ) {
	const queue = [];
	vm.runInNewContext( code, { webpackChunkohmylms: queue } );
	assert.equal( queue.length, 1 );
	return queue[ 0 ];
}
const canonical = ( factory ) =>
	generate( parse( `(${ factory.toString() })` ), {
		compact: true,
		comments: false,
	} ).code;

test( 'email vendor relocation preserves every factory and its chunk registration', () => {
	const baselineVendor = parse(
		read( `build/parity/${ emailVendorSource }` )
	);
	adaptVendors( baselineVendor );
	adaptEditorPackages( baselineVendor );
	adaptEmailModel( baselineVendor );
	adaptEditorRuntime( baselineVendor, emailVendorSource );
	const [ vendorIds, vendors ] = capture( read( emailVendorSource ) );
	const [ emailIds, email ] = capture( read( emailVendorTarget ) );
	assert.deepEqual( Array.from( vendorIds ), [ 96 ] );
	assert.deepEqual( Array.from( emailIds ), [ 355 ] );
	const original = {
		...capture( generate( baselineVendor ).code )[ 1 ],
		...capture( read( `build/parity/${ emailVendorTarget }` ) )[ 1 ],
	};
	const relocated = { ...vendors, ...email };
	assert.deepEqual(
		Object.keys( relocated ).sort(),
		Object.keys( original ).sort()
	);
	assert.equal(
		Object.keys( vendors ).length + Object.keys( email ).length,
		Object.keys( original ).length
	);
	for ( const id of Object.keys( original ) ) {
		assert.equal(
			canonical( relocated[ id ] ),
			canonical( original[ id ] ),
			`Factory ${ id } changed`
		);
	}
	for ( const { id } of emailVendorModules ) {
		assert.equal( vendors[ id ], undefined );
		assert.equal( typeof email[ id ], 'function' );
	}
	assert.match(
		read( 'assets/dist/admin/ohmylms.js' ),
		/chunks\/355\.js\?ver=[a-f0-9]{12}/
	);
} );

test( 'the audit rejects new eager, bound or context-map consumers', () => {
	const manifest = JSON.parse( read( 'assets/src/manifest.json' ) );
	const readSource = ( name ) => read( `assets/src/${ name }` );
	const plan = auditEmailVendors( manifest, readSource );
	assert.deepEqual(
		plan.map( ( module ) => module.id ),
		emailVendorModules.map( ( module ) => module.id )
	);
	for ( const expression of [
		'n(3270)',
		'n.bind(n,3270)',
		'({"./component":3270})',
	] ) {
		assert.throws(
			() =>
				auditEmailVendors( manifest, ( name ) =>
					name === 'modules/dist/admin/ohmylms/0-35358.js'
						? readSource( name ) + `;${ expression };`
						: readSource( name )
				),
			/outside the lazy email chunk/
		);
	}
	assert.throws(
		() =>
			auditEmailVendors( manifest, ( name ) =>
				name === 'runtimes/dist/admin/ohmylms.js'
					? readSource( name ) + ';load(50587);'
					: readSource( name )
			),
		/outside the lazy email chunk/
	);
} );
