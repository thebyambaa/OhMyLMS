import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
	ADVISORY_LOCK_ALLOWLIST,
	ADVISORY_LOCK_OWNER,
	advisoryLockLines,
	checkAdvisoryLocks,
} from '../../tools/architecture-rules/advisory-locks.mjs';

const pluginRoot = path.resolve( import.meta.dirname, '../..' );

/** A throwaway plugin root holding the given files (path relative to the root => contents). */
function tree( files ) {
	const root = fs.mkdtempSync(
		path.join( os.tmpdir(), 'ohmylms-advisory-locks-' )
	);
	for ( const [ file, contents ] of Object.entries( files ) ) {
		const full = path.join( root, file );
		fs.mkdirSync( path.dirname( full ), { recursive: true } );
		fs.writeFileSync( full, contents );
	}
	test.after( () => fs.rmSync( root, { recursive: true, force: true } ) );
	return root;
}

const LOCKING = `<?php
namespace OhMyLMS\\Example;

class Locking {
	public static function run() {
		global $wpdb;
		// SELECT GET_LOCK( in a comment does not count.
		/* Nor GET_LOCK(%s, 5) in a block comment. */
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', 'ohmylms-x' ) ) ) {
			return false;
		}
		$wpdb->get_var( $wpdb->prepare( "SELECT get_lock (%s, 0)", 'ohmylms-y' ) );
		return true;
	}
}
`;

const HELPER = `<?php
namespace OhMyLMS\\Utility;

final class Lock {
	public static function acquire( $name, $timeout ) {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, $timeout ) );
	}
}
`;

const CLEAN = `<?php
namespace OhMyLMS\\Example;

use OhMyLMS\\Utility\\Lock;

/** Uses Lock::with() instead of GET_LOCK(). */
class Clean {
	public static function run() {
		return Lock::with( 'ohmylms-x', 5, '__return_true', false );
	}
}
`;

test( 'finds GET_LOCK calls in code, case-insensitively, not in comments', () => {
	assert.deepEqual( advisoryLockLines( LOCKING ), [ 9, 12 ] );
	assert.deepEqual( advisoryLockLines( CLEAN ), [] );
	assert.deepEqual(
		advisoryLockLines( '<?php\r\n$a = "SELECT GET_LOCK(%s, 1), GET_LOCK(%s, 1)";\r\n# GET_LOCK(\r\n' ),
		[ 2 ]
	);
	assert.deepEqual( advisoryLockLines( '<?php $x = "MY_GET_LOCK(1)";' ), [] );
} );

test( 'fails outside the helper; vendor and tests are not read', () => {
	const root = tree( {
		[ ADVISORY_LOCK_OWNER ]: HELPER,
		'includes/Example/Locking.php': LOCKING,
		'includes/Example/Clean.php': CLEAN,
		'packages/shop/includes/Gateway.php': LOCKING,
		'vendor/lib/Locks.php': LOCKING,
		'tests/php/lock.php': LOCKING,
	} );
	const result = checkAdvisoryLocks( root );
	assert.deepEqual( result.failures, [
		'includes/Example/Locking.php:9,12 takes a MySQL advisory lock itself; use OhMyLMS\\Utility\\Lock::with( $name, $timeout, $work, $busy )',
		'packages/shop/includes/Gateway.php:9,12 takes a MySQL advisory lock itself; use OhMyLMS\\Utility\\Lock::with( $name, $timeout, $work, $busy )',
	] );
	assert.deepEqual( result.allowed, [] );
} );

test( 'allow-listed files pass; entries need a reason and go stale', () => {
	const root = tree( {
		'includes/Example/Locking.php': LOCKING,
		'includes/Example/Other.php': LOCKING,
	} );
	const result = checkAdvisoryLocks( root, [
		{ file: 'includes/Example/Locking.php', reason: 'Fixture.' },
		{ file: 'includes/Example/Other.php' },
		{ file: 'includes/Example/Gone.php', reason: 'No longer locks.' },
	] );
	assert.deepEqual( result.failures, [
		'advisory lock allow-list: entry {"file":"includes/Example/Other.php"} has no reason',
		'includes/Example/Other.php:9,12 takes a MySQL advisory lock itself; use OhMyLMS\\Utility\\Lock::with( $name, $timeout, $work, $busy )',
		'stale allow-list entry (no longer violated, remove it): advisory locks includes/Example/Gone.php',
	] );
	assert.deepEqual(
		result.allowed.map( ( entry ) => entry.file ),
		[ 'includes/Example/Locking.php' ]
	);
} );

test( 'the plugin takes advisory locks only through OhMyLMS\\Utility\\Lock', () => {
	const result = checkAdvisoryLocks( pluginRoot );
	assert.deepEqual( result.failures, [] );
	assert.deepEqual( result.violations, [] );
	assert.deepEqual( ADVISORY_LOCK_ALLOWLIST, [] );
	assert.deepEqual(
		advisoryLockLines(
			fs.readFileSync( path.join( pluginRoot, ADVISORY_LOCK_OWNER ), 'utf8' )
		).length,
		1
	);
} );
