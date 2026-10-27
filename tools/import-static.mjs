import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
const root = path.resolve( import.meta.dirname, '..' ),
	source = path.join( root, 'assets/src' );
const manifest = JSON.parse(
	fs.readFileSync( path.join( source, 'manifest.json' ), 'utf8' )
);
const seen = new Set( manifest.assets.map( ( a ) => a.output ) );
const walk = ( d ) =>
	fs
		.readdirSync( d, { withFileTypes: true } )
		.flatMap( ( e ) =>
			e.isDirectory() && ! [ 'src' ].includes( e.name )
				? walk( path.join( d, e.name ) )
				: e.isFile()
					? [ path.join( d, e.name ) ]
					: []
		);
let count = 0;
for ( const file of walk( path.join( root, 'assets' ) ) ) {
	const output = path.relative( root, file ).replaceAll( '\\', '/' );
	if (
		seen.has( output ) ||
		! /\.(css|svg|png|jpg|jpeg|gif|webp|woff2?|ttf|eot|json)$/i.test( file )
	)
		continue;
	const data = fs.readFileSync( file ),
		relative = 'static/' + output.slice( 7 ),
		destination = path.join( source, relative );
	fs.mkdirSync( path.dirname( destination ), { recursive: true } );
	fs.writeFileSync( destination, data );
	manifest.assets.push( {
		output,
		source: relative,
		sha256: crypto.createHash( 'sha256' ).update( data ).digest( 'hex' ),
		bytes: data.length,
	} );
	count++;
}
fs.writeFileSync(
	path.join( source, 'manifest.json' ),
	JSON.stringify( manifest, null, 2 ) + '\n'
);
console.log( `Imported ${ count } supporting static assets.` );
