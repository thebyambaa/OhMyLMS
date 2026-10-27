import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import {
	allowed,
	manifest,
	review,
	applyReview,
} from '../../tools/coding-worker/files.mjs';

async function cleanupFixture( directory ) {
	const resolved = await fs.realpath( directory );
	assert.equal( path.dirname( resolved ), await fs.realpath( os.tmpdir() ) );
	assert.match( path.basename( resolved ), /^oml-(review|integrity)-/ );
	await fs.rm( resolved, { recursive: true, force: true } );
}

test( 'coding updates reject traversal, dependencies, secrets and worker changes', () => {
	for ( const name of [
		'../wp-config.php',
		'includes/../../wp-config.php',
		'includes\\test.php',
		'/includes/test.php',
		'includes/.env.php',
		'vendor/test.php',
		'assets/src/node_modules/test.js',
		'tools/coding-worker/worker.mjs',
		'includes/file.php:payload.js',
		'includes/CON.php',
		'includes/trailing /test.php',
	] ) {
		assert.equal( allowed( name ), false, name );
	}
	assert.equal( allowed( 'includes/AI/Example.php' ), true );
} );

test( 'reviewed files require matching hashes; apply and rollback preserve originals', async () => {
	const directory = await fs.mkdtemp(
		path.join( os.tmpdir(), 'oml-review-' )
	);
	try {
		const live = path.join( directory, 'live' );
		const work = path.join( directory, 'work' );
		for ( const root of [ live, work ] ) {
			await fs.mkdir( path.join( root, 'includes' ), {
				recursive: true,
			} );
			await fs.writeFile(
				path.join( root, 'includes/test.php' ),
				'<?php // original'
			);
		}
		const baseline = await manifest( work );
		await fs.writeFile(
			path.join( work, 'includes/test.php' ),
			'<?php // updated'
		);
		const bundle = await review( baseline, work );
		const journal = path.join( directory, 'originals.json' );
		await fs.writeFile(
			path.join( live, 'includes/test.php' ),
			'<?php // another editor'
		);
		await assert.rejects(
			applyReview( live, bundle, journal ),
			/Checkout changed/
		);
		assert.equal(
			await fs.readFile( path.join( live, 'includes/test.php' ), 'utf8' ),
			'<?php // another editor'
		);
		await fs.writeFile(
			path.join( live, 'includes/test.php' ),
			'<?php // original'
		);
		await applyReview( live, bundle, journal );
		assert.equal(
			await fs.readFile( path.join( live, 'includes/test.php' ), 'utf8' ),
			'<?php // updated'
		);
		await assert.rejects(
			applyReview( live, bundle, journal ),
			/Checkout changed/
		);
		await applyReview( live, bundle, journal, true );
		assert.equal(
			await fs.readFile( path.join( live, 'includes/test.php' ), 'utf8' ),
			'<?php // original'
		);
	} finally {
		await cleanupFixture( directory );
	}
} );

test( 'tampered bundles and linked update paths are refused before a write', async () => {
	const directory = await fs.mkdtemp(
		path.join( os.tmpdir(), 'oml-integrity-' )
	);
	try {
		await assert.rejects(
			applyReview(
				directory,
				{ changes: [], digest: 'tampered' },
				path.join( directory, 'journal' )
			),
			/integrity/
		);
		const external = path.join( directory, 'external' );
		const root = path.join( directory, 'root' );
		await fs.mkdir( external );
		await fs.mkdir( root );
		await fs.symlink(
			external,
			path.join( root, 'includes' ),
			process.platform === 'win32' ? 'junction' : 'dir'
		);
		await assert.rejects( manifest( root ), /linked/ );
	} finally {
		await cleanupFixture( directory );
	}
} );
