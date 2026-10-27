import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

/** Locate PHP without requiring a machine-specific path in repository scripts. */
function phpBinary() {
	if ( process.env.PHP_BINARY ) {
		return process.env.PHP_BINARY;
	}
	if (
		spawnSync( 'php', [ '--version' ], { windowsHide: true } ).status === 0
	) {
		return 'php';
	}
	if ( process.platform === 'win32' && process.env.APPDATA ) {
		const services = path.join(
			process.env.APPDATA,
			'Local',
			'lightning-services'
		);
		if ( fs.existsSync( services ) ) {
			for ( const directory of fs
				.readdirSync( services )
				.filter( ( name ) => name.startsWith( 'php-' ) )
				.sort()
				.reverse() ) {
				const binary = path.join(
					services,
					directory,
					'bin',
					'win64',
					'php.exe'
				);
				if ( fs.existsSync( binary ) ) {
					return binary;
				}
			}
		}
	}
	throw new Error(
		'PHP was not found. Install PHP or set PHP_BINARY to its executable.'
	);
}

try {
	const command = process.argv[ 2 ] === 'fix' ? 'phpcbf' : 'phpcs';
	const checker = path.resolve(
		'tools/coding-standards/vendor/bin',
		command
	);
	if ( ! fs.existsSync( checker ) ) {
		throw new Error(
			'Install the standards tools first: composer install --working-dir=tools/coding-standards --no-plugins --no-scripts'
		);
	}
	const php = phpBinary();
	const args = [ '-d', 'memory_limit=512M' ];
	const extensions = path.join( path.dirname( php ), 'ext' );
	if (
		process.platform === 'win32' &&
		fs.existsSync( path.join( extensions, 'php_mbstring.dll' ) )
	) {
		args.push(
			'-d',
			`extension_dir=${ extensions }`,
			'-d',
			'extension=mbstring'
		);
	}
	const result = spawnSync(
		php,
		[
			...args,
			checker,
			'--standard=phpcs.xml.dist',
			...process.argv.slice( 3 ),
		],
		{ stdio: 'inherit', windowsHide: true }
	);
	if ( result.error ) {
		throw result.error;
	}
	process.exitCode = result.status ?? 1;
} catch ( error ) {
	process.stderr.write( `${ error.message }
` );
	process.exitCode = 1;
}
