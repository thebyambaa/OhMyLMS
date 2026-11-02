import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { checkContracts } from '../../tools/source-contracts.mjs';

function fixture( t ) {
	const root = fs.mkdtempSync(
		path.join( os.tmpdir(), 'ohmylms-contracts-' )
	);
	t.after( () => fs.rmSync( root, { recursive: true, force: true } ) );
	const directory = path.join( root, 'features', 'example' );
	fs.mkdirSync( directory, { recursive: true } );
	fs.mkdirSync( path.join( root, 'app', 'extensions' ), { recursive: true } );
	fs.writeFileSync(
		path.join( root, 'app', 'extensions', 'api.ts' ),
		'export const api = 1;'
	);
	const write = ( file, content ) =>
		fs.writeFileSync( path.join( directory, file ), content );
	write( 'Example.jsx', 'export function Example(){return null;}' );
	write(
		'index.js',
		"import {Example} from './Example'; import {api} from '../../app/extensions/api'; export {Example, api};"
	);
	return { root, write };
}

test( 'extensionless imports of authored and typed modules pass', ( t ) => {
	const { root } = fixture( t );
	assert.deepEqual( checkContracts( root ), { errors: [], files: 2 } );
} );

test( 'missing imports produce actionable diagnostics', ( t ) => {
	const { root, write } = fixture( t );
	write(
		'index.js',
		"import {Example} from './Missing'; export const components={Example};"
	);
	const { errors } = checkContracts( root );
	assert.equal( errors.length, 1 );
	assert.ok( errors[ 0 ].includes( 'unresolved import ./Missing' ) );
} );

test( 'absent authored folders are skipped', ( t ) => {
	const root = fs.mkdtempSync(
		path.join( os.tmpdir(), 'ohmylms-contracts-' )
	);
	t.after( () => fs.rmSync( root, { recursive: true, force: true } ) );
	assert.deepEqual( checkContracts( root ), { errors: [], files: 0 } );
} );
