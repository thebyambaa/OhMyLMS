import fs from 'node:fs';
import path from 'node:path';
import { stripPhpComments } from './legacy-routes.mjs';

/**
 * Advisory locks go through OhMyLMS\Utility\Lock (docs/ENFORCEMENT.md, section p; docs/BACKEND-BOUNDARIES.md,
 * plan step 9).
 *
 * `Lock::with()` (and `acquire()` / `release()`) prefix and limit the name, release in `finally` and
 * return the caller's busy value. Code (not comments) that writes `GET_LOCK(` in SQL fails outside
 * ADVISORY_LOCK_OWNER unless ADVISORY_LOCK_ALLOWLIST lists the file with a reason (`{ file, reason }`).
 * A listed file that no longer calls GET_LOCK fails too, so the list only shrinks.
 */

/** Folders and files scanned, relative to the plugin root. Tests hold locks from a second connection on purpose. */
export const ADVISORY_LOCK_ROOTS = [
	'includes',
	'templates',
	'packages',
	'modules',
	'ohmylms.php',
];

/** The shared helper: the one place that takes and releases advisory locks. */
export const ADVISORY_LOCK_OWNER = 'includes/Utility/Lock.php';

/** Other files that may write GET_LOCK themselves, each `{ file, reason }`. None today. */
export const ADVISORY_LOCK_ALLOWLIST = [];

const SKIPPED_DIRECTORIES = new Set( [ 'vendor', 'node_modules' ] );
const toPosix = ( file ) => file.split( path.sep ).join( '/' );

function phpFiles( target ) {
	if ( ! fs.existsSync( target ) ) return [];
	if ( fs.statSync( target ).isFile() )
		return target.endsWith( '.php' ) ? [ target ] : [];
	const files = [];
	for ( const entry of fs.readdirSync( target, { withFileTypes: true } ) ) {
		const full = path.join( target, entry.name );
		if ( entry.isDirectory() ) {
			if ( ! SKIPPED_DIRECTORIES.has( entry.name ) )
				files.push( ...phpFiles( full ) );
		} else if ( entry.name.endsWith( '.php' ) ) files.push( full );
	}
	return files.sort();
}

const GET_LOCK_CALL = /(?<![\w$])GET_LOCK\s*\(/gi;

/**
 * Lines of one PHP source whose code (comments and inline HTML blanked, strings kept) calls GET_LOCK.
 *
 * @param {string} text PHP source.
 * @return {number[]} 1-based line numbers.
 */
export function advisoryLockLines( text ) {
	const code = stripPhpComments( text.replace( /\r\n/g, '\n' ) );
	return [
		...new Set(
			[ ...code.matchAll( GET_LOCK_CALL ) ].map(
				( match ) => code.slice( 0, match.index ).split( '\n' ).length
			)
		),
	];
}

/** p. GET_LOCK only in ADVISORY_LOCK_OWNER and in allow-listed files. */
export function checkAdvisoryLocks( root, allowlist = ADVISORY_LOCK_ALLOWLIST ) {
	const violations = [];
	for ( const target of ADVISORY_LOCK_ROOTS ) {
		for ( const file of phpFiles( path.join( root, target ) ) ) {
			const relative = toPosix( path.relative( root, file ) );
			if ( relative === ADVISORY_LOCK_OWNER ) continue;
			const lines = advisoryLockLines( fs.readFileSync( file, 'utf8' ) );
			if ( lines.length ) violations.push( { file: relative, lines } );
		}
	}
	const failures = [];
	const allowed = [];
	const justified = allowlist.filter(
		( entry ) =>
			typeof entry?.file === 'string' &&
			typeof entry.reason === 'string' &&
			entry.reason.trim()
	);
	for ( const entry of allowlist ) {
		if ( ! justified.includes( entry ) )
			failures.push(
				`advisory lock allow-list: entry ${ JSON.stringify( entry ) } has no reason`
			);
	}
	for ( const violation of violations ) {
		const message = `${ violation.file }:${ violation.lines.join( ',' ) } takes a MySQL advisory lock itself; use OhMyLMS\\Utility\\Lock::with( $name, $timeout, $work, $busy )`;
		const entry = justified.find(
			( candidate ) => candidate.file === violation.file
		);
		if ( entry )
			allowed.push( { ...violation, reason: entry.reason, message } );
		else failures.push( message );
	}
	for ( const entry of justified ) {
		if (
			! violations.some( ( violation ) => violation.file === entry.file )
		)
			failures.push(
				`stale allow-list entry (no longer violated, remove it): advisory locks ${ entry.file }`
			);
	}
	return { failures, allowed, violations };
}
