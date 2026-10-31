import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { phpBinary } from './php-binary.mjs';

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
