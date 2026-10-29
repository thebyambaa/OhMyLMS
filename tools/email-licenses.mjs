import fs from 'node:fs';
import path from 'node:path';

/**
 * Licence notices for the npm packages that the email bundles ship.
 *
 * Both the eager bootstrap (Easy Email core) and the lazy chunks (Arco, MJML) need them.
 * Packages are found from the source maps, so a package that stops being bundled drops out
 * of the notice and one that starts being bundled without a licence text fails the build.
 */

/** Libraries whose code is copied inside a package's own bundle, so no source map lists them. */
export const inlinedPackages = {
	// easy-email-core ships its own copies of `he` and an older js-beautify.
	'easy-email-core': [ 'he' ],
};

/** Upstream packages that publish no licence file. */
const declaredOnly = new Set( [ 'number-precision' ] );

/** `node_modules/<package>` directories named by the given source maps. */
export function packageDirectories( maps ) {
	const directories = new Set();
	for ( const map of maps ) {
		for ( const source of map.sources ) {
			const start = source.indexOf( 'node_modules/' );
			if ( start < 0 ) continue;
			const segments = source.slice( start ).split( '/' );
			const last = segments.lastIndexOf( 'node_modules' );
			const count = segments[ last + 1 ].startsWith( '@' ) ? 2 : 1;
			directories.add(
				segments.slice( 0, last + 1 + count ).join( '/' )
			);
		}
	}
	for ( const [ name, inlined ] of Object.entries( inlinedPackages ) ) {
		if ( directories.has( `node_modules/${ name }` ) )
			inlined.forEach( ( item ) =>
				directories.add( `node_modules/${ item }` )
			);
	}
	return directories;
}

/**
 * Build the notice text for the packages in the source maps.
 *
 * @param {string}   root Plugin directory.
 * @param {Object[]} maps Parsed source maps of every email bundle.
 */
export function emailNotices( root, maps ) {
	return [ ...packageDirectories( maps ) ]
		.sort()
		.map( ( directory ) => {
			const location = path.join( root, directory );
			const metadata = JSON.parse(
				fs.readFileSync( path.join( location, 'package.json' ), 'utf8' )
			);
			const licenses = fs
				.readdirSync( location )
				.filter( ( file ) => /^licen[cs]e(?:[.\-_]|$)/i.test( file ) )
				.map( ( file ) =>
					fs.readFileSync( path.join( location, file ), 'utf8' )
				);
			if ( ! licenses.length && 'mjml-browser' === metadata.name )
				licenses.push(
					fs.readFileSync(
						path.join( root, 'tools/licenses/mjml-MIT.txt' ),
						'utf8'
					)
				);
			if (
				! licenses.length &&
				declaredOnly.has( metadata.name ) &&
				'MIT' === metadata.license
			)
				licenses.push(
					`Upstream declares MIT in package.json and README.md. Author: ${ metadata.author }. Source: ${ metadata.homepage }. No standalone license text is distributed upstream.`
				);
			if ( ! licenses.length )
				throw new Error(
					`Missing email package license: ${ metadata.name }`
				);
			return `${ metadata.name } ${ metadata.version } (${ metadata.license })\n${ licenses.join( '\n' ) }`;
		} )
		.join( '\n\n' );
}
