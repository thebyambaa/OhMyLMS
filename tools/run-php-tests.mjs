/**
 * Runs the plain-PHP test suites in tests/ and tests/php/ (one process per file).
 *
 *   node tools/run-php-tests.mjs unit                 # suites that stub WordPress (no wp-load.php)
 *   node tools/run-php-tests.mjs integration          # suites that load a real WordPress
 *   node tools/run-php-tests.mjs integration --list   # show how each suite would run
 *   node tools/run-php-tests.mjs integration tests/php/courses-rest-integration.php
 *
 * Integration suites find WordPress in one of four ways, read from the file itself:
 *   local-site   `dirname( __DIR__, 5 ) . '/wp-load.php'` (the plugin must sit in <root>/wp-content/plugins/<dir>);
 *                every `'1' !== getenv( 'X' )` gate in the file is set to 1, and OHMYLMS_TEST_HOST is passed on.
 *   argv-site    `$argv[1] . '/wp-load.php'`: the WordPress root is passed as the first argument.
 *   site-path    OHMYLMS_TEST_SITE_PATH, set to the WordPress root unless already set.
 *   credentials  OHMYLMS_TEST_CREDENTIALS (disposable site from tools/create-test-site.py); never set here.
 *
 * `*-fixture.php` files seed data for the browser specs and are never run here. Files listed in
 * tests/php-suites.json are not run either; each entry needs a reason. `--all`, or naming the file, runs them.
 *
 * Environment:
 *   PHP_BINARY             PHP executable (otherwise `php` on PATH or Local's lightning-services PHP).
 *   OHMYLMS_TEST_WP_ROOT   WordPress root for argv-site and site-path suites (default: three levels above the plugin).
 *   OHMYLMS_TEST_HOST      Host header for local-site suites (they default to 800.local).
 *   OHMYLMS_TEST_DB_PORT   Sets mysqli.default_port, for a DB_HOST without a port (Local uses 10006).
 *   OHMYLMS_PHP_INI        php.ini to use instead of the generated one.
 *   OHMYLMS_PHP_TIMEOUT    Per-suite timeout in seconds (default 600).
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { phpBinary } from './php-binary.mjs';

const ROOT = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	'..'
);
const MANIFEST = 'tests/php-suites.json';
const EXTENSIONS = [
	'mbstring',
	'mysqli',
	'openssl',
	'curl',
	'sodium',
	'zip',
	'intl',
	'gd',
	'fileinfo',
	'exif',
];
const NOT_RUN = [
	/^Set [A-Z0-9_]+=1\b/m,
	/^Usage: /m,
	/tests need the \w+ extension/i,
];

const args = process.argv.slice( 2 );
const mode = args[ 0 ];
if ( ! [ 'unit', 'integration' ].includes( mode ) ) {
	process.stderr.write(
		'Usage: node tools/run-php-tests.mjs <unit|integration> [--list] [--all] [files...]\n'
	);
	process.exit( 2 );
}
const listOnly = args.includes( '--list' );
const includeSkipped = args.includes( '--all' );
const only = args
	.slice( 1 )
	.filter( ( arg ) => ! arg.startsWith( '--' ) )
	.map( toPosix );
const timeout = Number( process.env.OHMYLMS_PHP_TIMEOUT || 600 ) * 1000;

function toPosix( file ) {
	return path
		.relative( ROOT, path.resolve( ROOT, file ) )
		.split( path.sep )
		.join( '/' );
}

/** Suites are the PHP files directly in tests/ and tests/php/; subfolders hold shared fixtures. */
function discover() {
	return [ 'tests', 'tests/php' ]
		.flatMap( ( dir ) =>
			fs
				.readdirSync( path.join( ROOT, dir ), { withFileTypes: true } )
				.filter(
					( entry ) => entry.isFile() && entry.name.endsWith( '.php' )
				)
				.map( ( entry ) => `${ dir }/${ entry.name }` )
		)
		.sort();
}

function classify( file ) {
	const source = fs.readFileSync( path.join( ROOT, file ), 'utf8' );
	const flags = [
		...source.matchAll( /'1'\s*!==\s*getenv\(\s*'([A-Z0-9_]+)'\s*\)/g ),
	].map( ( match ) => match[ 1 ] );
	const databases = [
		...source.matchAll(
			/DB_NAME\s*!==\s*'([^']+)'|'([^']+)'\s*!==\s*DB_NAME/g
		),
	].map( ( match ) => match[ 1 ] || match[ 2 ] );
	let kind = 'unit';
	if ( file.endsWith( '-fixture.php' ) ) {
		kind = 'fixture';
	} else if ( /wp-load\.php/.test( source ) ) {
		if ( /OHMYLMS_TEST_CREDENTIALS/.test( source ) ) {
			kind = 'credentials';
		} else if ( /OHMYLMS_TEST_SITE_PATH/.test( source ) ) {
			kind = 'site-path';
		} else if ( /\$argv\[\s*1\s*\][^\n]*wp-load\.php/.test( source ) ) {
			kind = 'argv-site';
		} else {
			kind = 'local-site';
		}
	}
	return {
		file,
		kind,
		flags: [ ...new Set( flags ) ],
		databases: [ ...new Set( databases ) ],
	};
}

function readManifest() {
	const manifest = JSON.parse(
		fs.readFileSync( path.join( ROOT, MANIFEST ), 'utf8' )
	);
	const entries = new Map();
	const problems = [];
	for ( const group of manifest[ mode ] ?? [] ) {
		if (
			typeof group.reason !== 'string' ||
			group.reason.trim().length < 10
		) {
			problems.push(
				`${ MANIFEST }: the group with ${ group.files?.[ 0 ] } needs a reason.`
			);
		}
		if ( group.expect !== undefined && group.expect !== 'fail' ) {
			problems.push( `${ MANIFEST }: "expect" can only be "fail".` );
		}
		if ( group.skipIn !== undefined && group.skipIn !== 'ci' ) {
			problems.push( `${ MANIFEST }: "skipIn" can only be "ci".` );
		}
		for ( const file of group.files ?? [] ) {
			if ( ! fs.existsSync( path.join( ROOT, file ) ) ) {
				problems.push(
					`${ MANIFEST }: ${ file } does not exist; remove its entry.`
				);
			}
			if ( entries.has( file ) ) {
				problems.push( `${ MANIFEST }: ${ file } is listed twice.` );
			}
			entries.set( file, group );
		}
	}
	return { entries, problems };
}

/** Why a listed suite is not run here, or null when it runs (expected failures run too). */
function skipReason( entry ) {
	if ( ! entry || entry.expect === 'fail' ) {
		return null;
	}
	if ( entry.skipIn === 'ci' && ! process.env.CI ) {
		return null;
	}
	return entry.reason;
}

/** Use the loaded php.ini when there is one; otherwise write one that loads the extensions the suites need. */
function phpIniArgs( php ) {
	const port = process.env.OHMYLMS_TEST_DB_PORT;
	if ( process.env.OHMYLMS_PHP_INI ) {
		return [
			'-c',
			process.env.OHMYLMS_PHP_INI,
			...( port ? [ '-d', `mysqli.default_port=${ port }` ] : [] ),
		];
	}
	const info = spawnSync(
		php,
		[
			'-r',
			'echo json_encode([php_ini_loaded_file(), ini_get("extension_dir"), get_loaded_extensions(), PHP_BINARY]);',
		],
		{ encoding: 'utf8', windowsHide: true }
	);
	if ( info.status !== 0 ) {
		throw new Error( `${ php } failed: ${ info.stderr || info.stdout }` );
	}
	const [ loadedIni, extensionDir, loaded, binary ] = JSON.parse(
		info.stdout
	);
	if ( loadedIni ) {
		return port ? [ '-d', `mysqli.default_port=${ port }` ] : [];
	}
	const lines = [
		'memory_limit=1G',
		'display_errors=stderr',
		'error_reporting=E_ALL',
	];
	let dir = extensionDir;
	if ( ! fs.existsSync( dir ) || ! path.isAbsolute( dir ) ) {
		dir = path.join( path.dirname( binary || php ), 'ext' );
	}
	lines.push( `extension_dir="${ dir }"` );
	const lowered = loaded.map( ( name ) => name.toLowerCase() );
	for ( const extension of EXTENSIONS ) {
		const file =
			process.platform === 'win32'
				? `php_${ extension }.dll`
				: `${ extension }.so`;
		if (
			! lowered.includes( extension ) &&
			fs.existsSync( path.join( dir, file ) )
		) {
			lines.push( `extension=${ extension }` );
		}
	}
	if ( port ) {
		lines.push( `mysqli.default_port=${ port }` );
	}
	const ini = path.join(
		os.tmpdir(),
		`ohmylms-php-tests-${ process.pid }.ini`
	);
	fs.writeFileSync( ini, lines.join( '\n' ) + '\n' );
	process.on( 'exit', () => fs.rmSync( ini, { force: true } ) );
	return [ '-c', ini ];
}

function wordpressRoot() {
	return path.resolve(
		process.env.OHMYLMS_TEST_WP_ROOT || path.join( ROOT, '..', '..', '..' )
	);
}

function invocation( suite ) {
	const env = { ...process.env };
	const suiteArgs = [];
	for ( const flag of suite.flags ) {
		env[ flag ] = '1';
	}
	if ( suite.kind === 'argv-site' ) {
		suiteArgs.push( wordpressRoot() );
	}
	if ( suite.kind === 'site-path' && ! env.OHMYLMS_TEST_SITE_PATH ) {
		env.OHMYLMS_TEST_SITE_PATH = wordpressRoot();
	}
	return { env, suiteArgs };
}

function tail( text, lines ) {
	// A fatal error under WordPress also prints the wp_die() page; it says nothing the PHP error does not.
	const plain = text.replace( /<!DOCTYPE html>[\s\S]*?<\/html>/gi, '' );
	return plain.trimEnd().split( /\r?\n/ ).slice( -lines ).join( '\n' );
}

function runSuite( php, iniArgs, suite ) {
	const { env, suiteArgs } = invocation( suite );
	const begin = Date.now();
	const run = spawnSync( php, [ ...iniArgs, suite.file, ...suiteArgs ], {
		cwd: ROOT,
		env,
		encoding: 'utf8',
		timeout,
		maxBuffer: 64 * 1024 * 1024,
		windowsHide: true,
	} );
	const output = `${ run.stdout || '' }${ run.stderr || '' }`;
	const seconds = ( ( Date.now() - begin ) / 1000 ).toFixed( 1 );
	if ( run.error ) {
		return {
			passed: false,
			seconds,
			detail:
				run.error.code === 'ETIMEDOUT'
					? `timed out after ${ timeout / 1000 }s`
					: run.error.message,
		};
	}
	if ( run.status !== 0 ) {
		return {
			passed: false,
			seconds,
			detail: `exit ${ run.status }\n${ tail( output, 25 ) }`,
		};
	}
	if ( NOT_RUN.some( ( pattern ) => pattern.test( output ) ) ) {
		return {
			passed: false,
			seconds,
			detail: `the suite did not run its checks: ${ tail( output, 3 ) }`,
		};
	}
	return { passed: true, seconds, detail: tail( output, 1 ) };
}

const { entries, problems } = readManifest();
const suites = discover()
	.map( classify )
	.filter(
		( suite ) =>
			suite.kind !== 'fixture' &&
			( mode === 'unit' ) === ( suite.kind === 'unit' )
	)
	.filter( ( suite ) => ! only.length || only.includes( suite.file ) );
for ( const file of only ) {
	if ( ! suites.some( ( suite ) => suite.file === file ) ) {
		problems.push( `${ file } is not a ${ mode } suite.` );
	}
}
for ( const file of entries.keys() ) {
	const suite = classify( file );
	if (
		suite.kind === 'fixture' ||
		( mode === 'unit' ) !== ( suite.kind === 'unit' )
	) {
		problems.push(
			`${ MANIFEST }: ${ file } is listed under "${ mode }" but is a ${ suite.kind } file.`
		);
	}
}
if ( problems.length ) {
	process.stderr.write( problems.join( '\n' ) + '\n' );
	process.exit( 1 );
}

if ( listOnly ) {
	for ( const suite of suites ) {
		const entry = entries.get( suite.file );
		const reason = skipReason( entry );
		const notes = [
			suite.kind,
			...suite.flags.map( ( flag ) => `${ flag }=1` ),
			...suite.databases.map( ( db ) => `DB_NAME=${ db }` ),
		];
		const action = reason
			? 'skip'
			: entry?.expect === 'fail'
				? 'xfail'
				: 'run';
		process.stdout.write(
			`${ action.padEnd( 5 ) } ${ suite.file.padEnd( 62 ) } ${ notes.join( ' ' ) }\n`
		);
	}
	process.exit( 0 );
}

const php = phpBinary();
const iniArgs = phpIniArgs( php );
const results = [];
const started = Date.now();
process.stdout.write( `PHP: ${ php } ${ iniArgs.join( ' ' ) }\n` );
if ( mode === 'integration' ) {
	process.stdout.write(
		`WordPress: ${ path.resolve( ROOT, '..', '..', '..' ) } (local-site), ${ wordpressRoot() } (argv-site, site-path); host ${ process.env.OHMYLMS_TEST_HOST || '800.local (suite default)' }\n`
	);
}
for ( const suite of suites ) {
	const entry = entries.get( suite.file );
	const forced = includeSkipped || only.includes( suite.file );
	const reason = skipReason( entry );
	if ( reason && ! forced ) {
		results.push( { ...suite, status: 'skipped', reason } );
		continue;
	}
	const outcome = runSuite( php, iniArgs, suite );
	const expectFail = entry?.expect === 'fail' && ! forced;
	let status = outcome.passed ? 'passed' : 'failed';
	let label = outcome.passed ? 'PASS ' : 'FAIL ';
	if ( expectFail ) {
		status = outcome.passed ? 'failed' : 'xfail';
		label = outcome.passed ? 'XPASS' : 'XFAIL';
	}
	results.push( { ...suite, status } );
	process.stdout.write(
		`${ label } ${ suite.file } (${ outcome.seconds }s)  ${ outcome.passed ? outcome.detail.slice( 0, 160 ) : '' }\n`
	);
	if ( expectFail && outcome.passed ) {
		process.stdout.write(
			`      passes now: remove it from the expected failures in ${ MANIFEST }.\n`
		);
	} else if ( ! outcome.passed ) {
		process.stdout.write(
			( expectFail ? tail( outcome.detail, 4 ) : outcome.detail ).replace(
				/^/gm,
				'      '
			) + '\n'
		);
	}
}

const count = ( status ) =>
	results.filter( ( result ) => result.status === status );
if ( count( 'skipped' ).length ) {
	process.stdout.write( `\nNot run (${ MANIFEST }):\n` );
	for ( const reason of new Set(
		count( 'skipped' ).map( ( result ) => result.reason )
	) ) {
		process.stdout.write( `- ${ reason }\n` );
		for ( const result of count( 'skipped' ).filter(
			( item ) => item.reason === reason
		) ) {
			process.stdout.write( `    ${ result.file }\n` );
		}
	}
}
process.stdout.write(
	`\n${ mode }: ${ count( 'passed' ).length } passed, ${ count( 'failed' ).length } failed, ${ count( 'xfail' ).length } expected failures, ${ count( 'skipped' ).length } not run, ${ ( ( Date.now() - started ) / 1000 ).toFixed( 1 ) }s\n`
);
if ( count( 'failed' ).length ) {
	process.stdout.write(
		`Failed: ${ count( 'failed' )
			.map( ( result ) => result.file )
			.join( ', ' ) }\n`
	);
	process.exitCode = 1;
}
