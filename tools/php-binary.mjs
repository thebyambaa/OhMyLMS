import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

/** Locate PHP without requiring a machine-specific path in repository scripts. */
export function phpBinary() {
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
