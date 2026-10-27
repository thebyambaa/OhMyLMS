import { watch } from 'node:fs';
import { spawn } from 'node:child_process';
import path from 'node:path';
const root = path.resolve( import.meta.dirname, '..' );
let building = false,
	pending = false,
	timer;
function rebuild() {
	if ( building ) {
		pending = true;
		return;
	}
	building = true;
	const child = spawn(
		process.execPath,
		[ 'tools/build-assets.mjs', '--extensions' ],
		{ cwd: root, stdio: 'inherit' }
	);
	child.on( 'exit', ( code ) => {
		building = false;
		if ( code )
			console.error(
				'Application build failed; fix source and save again.'
			);
		if ( pending ) {
			pending = false;
			rebuild();
		}
	} );
}
rebuild();
const watchers = [ 'assets/src', 'tools' ].map( ( dir ) =>
	watch( path.join( root, dir ), { recursive: true }, () => {
		clearTimeout( timer );
		timer = setTimeout( rebuild, 250 );
	} )
);
const sdk = spawn(
	process.execPath,
	[
		'node_modules/webpack-cli/bin/cli.js',
		'--config',
		'webpack.config.cjs',
		'--mode',
		'development',
		'--watch',
	],
	{ cwd: root, stdio: 'inherit' }
);
process.on( 'SIGINT', () => {
	watchers.forEach( ( w ) => w.close() );
	sdk.kill();
	process.exit();
} );
