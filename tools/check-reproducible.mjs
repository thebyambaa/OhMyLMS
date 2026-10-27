import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
const root = path.resolve( import.meta.dirname, '..' );
const walk = ( dir ) =>
	fs
		.readdirSync( dir, { withFileTypes: true } )
		.flatMap( ( e ) =>
			e.isDirectory() && e.name !== 'parity'
				? walk( path.join( dir, e.name ) )
				: e.isFile()
					? [ path.join( dir, e.name ) ]
					: []
		);
const hashes = () =>
	Object.fromEntries(
		walk( path.join( root, 'build' ) )
			.sort()
			.map( ( p ) => [
				path.relative( root, p ),
				crypto
					.createHash( 'sha256' )
					.update( fs.readFileSync( p ) )
					.digest( 'hex' ),
			] )
	);
const build = () => {
	for ( const args of [
		[ 'tools/build-assets.mjs', '--extensions' ],
		[
			'node_modules/webpack-cli/bin/cli.js',
			'--config',
			'webpack.config.cjs',
			'--mode',
			'production',
		],
	] ) {
		const run = spawnSync( process.execPath, args, {
			cwd: root,
			encoding: 'utf8',
		} );
		if ( run.status !== 0 ) throw new Error( run.stdout + run.stderr );
	}
};
build();
const before = hashes();
build();
const after = hashes();
if ( JSON.stringify( before ) !== JSON.stringify( after ) )
	throw new Error( 'Build is not reproducible.' );
console.log(
	`${ Object.keys( after ).length } generated artifacts are byte-identical across two builds.`
);
