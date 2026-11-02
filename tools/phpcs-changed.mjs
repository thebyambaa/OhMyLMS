/**
 * WordPress coding standards for changed lines only.
 *
 * The full `npm run lint:php` still reports the legacy findings in includes/, packages/ and templates/. This
 * check runs phpcs (phpcs.xml.dist) on the PHP files that differ from a base revision and fails on errors in
 * lines that were added or changed, so new code is held to the standard without a baseline file.
 * Warnings on changed lines are printed but do not fail.
 *
 *   node tools/phpcs-changed.mjs [base]    # base defaults to origin/main; compared with the working tree
 *
 * Needs `composer install --working-dir=tools/coding-standards --no-plugins --no-scripts`.
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	'..'
);
const SCOPE = [
	'ohmylms.php',
	'includes/',
	'modules/',
	'packages/',
	'templates/',
];
const base = process.argv[ 2 ] || process.env.PHPCS_BASE || 'origin/main';

function git( args ) {
	const result = spawnSync( 'git', args, {
		cwd: ROOT,
		encoding: 'utf8',
		maxBuffer: 256 * 1024 * 1024,
	} );
	if ( result.status !== 0 ) {
		throw new Error(
			`git ${ args.join( ' ' ) } failed: ${ result.stderr.trim() }`
		);
	}
	return result.stdout;
}

/** Lines added or changed per file, from a zero-context diff of the working tree against the merge base. */
function changedLines( from ) {
	const diff = git( [
		'diff',
		'--no-color',
		'--no-ext-diff',
		'-U0',
		'--diff-filter=AMR',
		from,
		'--',
		'*.php',
	] );
	const changes = new Map();
	let current = null;
	for ( const line of diff.split( '\n' ) ) {
		if ( line.startsWith( '+++ ' ) ) {
			const file = line.slice( 4 ).replace( /^b\//, '' );
			current =
				file !== '/dev/null' &&
				SCOPE.some(
					( scope ) => file === scope || file.startsWith( scope )
				)
					? file
					: null;
			if (
				current &&
				! /\/vendor\/|\/node_modules\//.test( `/${ current }` )
			) {
				changes.set( current, new Set() );
			} else {
				current = null;
			}
			continue;
		}
		const hunk = current && line.match( /^@@ -\S+ \+(\d+)(?:,(\d+))? @@/ );
		if ( hunk ) {
			const start = Number( hunk[ 1 ] );
			const count = hunk[ 2 ] === undefined ? 1 : Number( hunk[ 2 ] );
			for ( let n = start; n < start + count; n++ ) {
				changes.get( current ).add( n );
			}
		}
	}
	return changes;
}

try {
	const mergeBase = git( [ 'merge-base', base, 'HEAD' ] ).trim();
	const changes = changedLines( mergeBase );
	const files = [ ...changes.keys() ].filter( ( file ) =>
		fs.existsSync( path.join( ROOT, file ) )
	);
	process.stdout.write(
		`phpcs on changed lines since ${ base } (${ mergeBase.slice( 0, 12 ) }): ${ files.length } PHP file(s)\n`
	);
	if ( ! files.length ) {
		process.exit( 0 );
	}
	const list = path.join( os.tmpdir(), `ohmylms-phpcs-${ process.pid }.txt` );
	fs.writeFileSync( list, files.join( '\n' ) + '\n' );
	const run = spawnSync(
		process.execPath,
		[
			path.join( ROOT, 'tools/wp-standards.mjs' ),
			'check',
			'--report=json',
			'-q',
			`--file-list=${ list }`,
		],
		{
			cwd: ROOT,
			encoding: 'utf8',
			maxBuffer: 256 * 1024 * 1024,
		}
	);
	fs.rmSync( list, { force: true } );
	const start = run.stdout.indexOf( '{' );
	if ( start < 0 ) {
		throw new Error(
			`phpcs did not produce a report:\n${ run.stdout }${ run.stderr }`
		);
	}
	const report = JSON.parse( run.stdout.slice( start ) );
	let errors = 0;
	let warnings = 0;
	let legacy = 0;
	for ( const [ absolute, result ] of Object.entries( report.files ) ) {
		const file = path
			.relative( ROOT, path.resolve( ROOT, absolute ) )
			.split( path.sep )
			.join( '/' );
		const lines = changes.get( file ) ?? new Set();
		for ( const message of result.messages ) {
			if ( ! lines.has( message.line ) ) {
				legacy++;
				continue;
			}
			const severity = message.type === 'ERROR' ? 'error' : 'warning';
			if ( severity === 'error' ) {
				errors++;
			} else {
				warnings++;
			}
			process.stdout.write(
				`${ file }:${ message.line }:${ message.column } ${ severity } ${ message.message } (${ message.source })\n`
			);
		}
	}
	process.stdout.write(
		`${ errors } error(s) and ${ warnings } warning(s) on changed lines; ${ legacy } finding(s) on unchanged lines of these files not counted.\n`
	);
	process.exitCode = errors ? 1 : 0;
} catch ( error ) {
	process.stderr.write( `${ error.message }\n` );
	process.exitCode = 1;
}
