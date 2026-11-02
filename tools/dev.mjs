import { watch } from 'node:fs';
import { spawn } from 'node:child_process';
import path from 'node:path';
const root = path.resolve( import.meta.dirname, '..' );
// Sources of tools/build-assets.mjs; webpack watches the rest of assets/src itself.
const assetSources = /^(?:frontend|runtimes|static)[\\/]|^manifest\.json$/;
let building = false,
	pending = false,
	timer;
function rebuild() {
	if ( building ) {
		pending = true;
		return;
	}
	building = true;
	const child = spawn( process.execPath, [ 'tools/build-assets.mjs' ], {
		cwd: root,
		stdio: 'inherit',
	} );
	child.on( 'exit', ( code ) => {
		building = false;
		if ( code )
			console.error( 'Asset build failed; fix source and save again.' );
		if ( pending ) {
			pending = false;
			rebuild();
		}
	} );
}
rebuild();
const watcher = watch(
	path.join( root, 'assets/src' ),
	{ recursive: true },
	( event, file ) => {
		if ( file && ! assetSources.test( file ) ) return;
		clearTimeout( timer );
		timer = setTimeout( rebuild, 250 );
	}
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
	watcher.close();
	sdk.kill();
	process.exit();
} );
