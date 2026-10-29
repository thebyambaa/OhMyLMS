import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
import test from 'node:test';
import { syncAdminApp } from '../../tools/sync-admin-app.mjs';

test( 'published authored app includes lazy chunks, maps and licenses but excludes unrelated runtimes', () => {
	const root = fs.mkdtempSync( path.join( os.tmpdir(), 'oml-admin-app-' ) );
	try {
		const source = path.join( root, 'build/sdk' );
		fs.mkdirSync( source, { recursive: true } );
		const expected = [
			'admin-app.js',
			'admin-app.asset.php',
			'admin-app.css',
			'admin-app.js.map',
			'admin-app.js.LICENSE.txt',
			'chunks/admin-app-webhooks.012345abcdef.js',
			'chunks/admin-app-webhooks.012345abcdef.js.map',
		];
		for ( const name of [
			...expected,
			'extensions.js',
			'355.js',
			'editor-tiptap.js',
			'chunks/unrelated.012345abcdef.js',
		] ) {
			fs.mkdirSync( path.dirname( path.join( source, name ) ), {
				recursive: true,
			} );
			fs.writeFileSync( path.join( source, name ), name );
		}
		assert.deepEqual( syncAdminApp( root ).sort(), expected.sort() );
		assert.deepEqual(
			fs
				.readdirSync( path.join( root, 'assets/dist/admin' ), {
					recursive: true,
				} )
				.filter( ( name ) =>
					fs
						.statSync(
							path.join( root, 'assets/dist/admin', name )
						)
						.isFile()
				)
				.map( ( name ) => name.replaceAll( '\\', '/' ) )
				.sort(),
			expected.sort()
		);
		for ( const name of expected )
			assert.equal(
				fs.readFileSync(
					path.join( root, 'assets/dist/admin', name ),
					'utf8'
				),
				name
			);
	} finally {
		fs.rmSync( root, { recursive: true, force: true } );
	}
} );

test( 'missing required entry fails before publishing a partial app', () => {
	const root = fs.mkdtempSync( path.join( os.tmpdir(), 'oml-admin-app-' ) );
	try {
		assert.throws(
			() => syncAdminApp( root ),
			/Missing authored app asset/
		);
		assert.equal(
			fs.existsSync( path.join( root, 'assets/dist/admin' ) ),
			false
		);
	} finally {
		fs.rmSync( root, { recursive: true, force: true } );
	}
} );
