import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { buildInventory } from '../../tools/runtime-inventory.mjs';
import {
	renderInventory,
	reportPath,
} from '../../tools/runtime-inventory-report.mjs';

const root = path.resolve( import.meta.dirname, '../..' );
const inventory = buildInventory( root );

test( 'every runtime binding read by a feature has an owner and a step', () => {
	const missing = inventory.dependencies.filter(
		( dependency ) =>
			dependency.owner === 'unassigned' ||
			dependency.kind === 'unresolved' ||
			! dependency.step
	);
	assert.deepEqual(
		missing.map( ( dependency ) => dependency.binding ),
		[]
	);
} );

test( 'components read only the runtime bindings their contract declares', () => {
	const undeclared = inventory.components
		.filter( ( component ) => component.undeclared.length )
		.map(
			( component ) =>
				`${ component.feature }/${
					component.name
				}: ${ component.undeclared.join( ',' ) }`
		);
	assert.deepEqual( undeclared, [] );
} );

test( 'recovered library families are all accounted for', () => {
	assert.ok( inventory.libraries.length > 0 );
	for ( const library of inventory.libraries ) {
		assert.ok( library.factories > 0, library.library );
		assert.ok( library.bundles.length > 0, library.library );
	}
} );

test( 'routes resolve to a mounted screen or are reported as missing', () => {
	assert.equal( inventory.routes.length, inventory.application.routes );
	for ( const route of inventory.routes ) {
		assert.ok( route.kind, route.route );
	}
} );

test( 'the checked-in inventory report matches the source', () => {
	const current = fs
		.readFileSync( path.join( root, reportPath ), 'utf8' )
		.replace( /\r\n/g, '\n' );
	assert.equal(
		current,
		renderInventory( inventory ),
		'Run node tools/runtime-inventory-report.mjs --write'
	);
} );
