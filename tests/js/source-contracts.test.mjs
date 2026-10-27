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
	fs.mkdirSync( path.join( root, 'extensions' ) );
	const directory = path.join( root, 'features', 'example' );
	fs.mkdirSync( directory, { recursive: true } );
	const write = ( file, content ) =>
		fs.writeFileSync( path.join( directory, file ), content );
	write(
		'components.json',
		JSON.stringify( [
			{
				name: 'Example',
				binding: 'x',
				file: 'Example.jsx',
				dependencies: [],
			},
		] )
	);
	write(
		'Example.jsx',
		'export function createExample(){return function Example(){return null;};}'
	);
	write(
		'index.js',
		"import {createExample} from './Example'; export const components={Example:createExample};"
	);
	return { root, write };
}

test( 'valid feature factories and extensionless imports pass contract checks', ( t ) => {
	const { root } = fixture( t );
	assert.deepEqual( checkContracts( root ), {
		errors: [],
		features: 1,
		components: 1,
	} );
} );

test( 'missing imports and unregistered components produce actionable diagnostics', ( t ) => {
	const { root, write } = fixture( t );
	write(
		'index.js',
		"import {createExample} from './Missing'; export const components={};"
	);
	const { errors } = checkContracts( root );
	assert.ok(
		errors.some( ( error ) =>
			error.includes( 'unresolved import ./Missing' )
		)
	);
	assert.ok(
		errors.some( ( error ) =>
			error.includes( 'Example must register createExample' )
		)
	);
} );

test( 'a renamed factory cannot silently break a manifest contract', ( t ) => {
	const { root, write } = fixture( t );
	write( 'Example.jsx', 'export function renamed() {}' );
	assert.ok(
		checkContracts( root ).errors.some( ( error ) =>
			error.includes( 'missing exported factory createExample' )
		)
	);
} );
