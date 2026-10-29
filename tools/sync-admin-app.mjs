import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

/** Publish the authored entry and its named feature chunks without copying unrelated SDK assets. */
export function syncAdminApp( root ) {
	const source = path.join( root, 'build/sdk' );
	const destination = path.join( root, 'assets/dist/admin' );
	for ( const entry of [
		'admin-app.js',
		'admin-app.asset.php',
		'admin-app.css',
	] ) {
		const file = path.join( source, entry );
		if ( ! fs.existsSync( file ) || ! fs.statSync( file ).isFile() ) {
			throw new Error(
				`Missing authored app asset: ${ entry }. Run npm run build:sdk first.`
			);
		}
	}
	const files = [ source, path.join( source, 'chunks' ) ]
		.filter( ( directory ) => fs.existsSync( directory ) )
		.flatMap( ( directory ) =>
			fs
				.readdirSync( directory, { withFileTypes: true } )
				.filter(
					( entry ) =>
						entry.isFile() &&
						/^admin-app(?:-[a-z0-9-]+)?(?:\.[a-f0-9]{12})?\.(?:js(?:\.map|\.LICENSE\.txt)?|css(?:\.map)?|asset\.php)$/.test(
							entry.name
						)
				)
				.map( ( entry ) =>
					path
						.relative( source, path.join( directory, entry.name ) )
						.replaceAll( '\\', '/' )
				)
		)
		.sort();
	fs.mkdirSync( destination, { recursive: true } );
	for ( const file of files ) {
		fs.mkdirSync( path.dirname( path.join( destination, file ) ), {
			recursive: true,
		} );
		fs.copyFileSync(
			path.join( source, file ),
			path.join( destination, file )
		);
	}
	return files;
}

if (
	process.argv[ 1 ] &&
	pathToFileURL( path.resolve( process.argv[ 1 ] ) ).href === import.meta.url
) {
	const files = syncAdminApp( path.resolve( import.meta.dirname, '..' ) );
	process.stdout.write(
		`Published ${ files.length } authored admin assets.\n`
	);
}
