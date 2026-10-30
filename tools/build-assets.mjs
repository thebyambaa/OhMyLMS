import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { buildFrontendStyles } from './build-frontend-styles.mjs';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
const generate = generatorModule.default || generatorModule;
const root = path.resolve( import.meta.dirname, '..' );
const sourceRoot = path.join( root, 'assets/src' );
const manifest = JSON.parse(
	fs.readFileSync( path.join( sourceRoot, 'manifest.json' ), 'utf8' )
);
const outputRoot = path.resolve(
	process.env.OHMYLMS_BUILD_DIR || path.join( root, 'build' )
);
// Outputs of the retired recovered admin; they are no longer built or shipped.
const isRetiredAdminOutput = ( output ) =>
	/^assets\/dist\/(?:admin|chunks|vendors|css|packages)\//.test( output ) ||
	output === 'assets/css/tailwind.css';
const hashes = {};
buildFrontendStyles( root, outputRoot );
// Retired block assets must not survive rebuilds in an existing output directory.
for ( const retired of manifest.retiredAssets || [] ) {
	for ( const suffix of [ '', '.map' ] ) {
		const destination = path.resolve( outputRoot, retired + suffix );
		if ( ! destination.startsWith( outputRoot + path.sep ) ) {
			throw new Error(
				`Retired asset outside build directory: ${ retired }`
			);
		}
		if ( fs.existsSync( destination ) ) {
			fs.unlinkSync( destination );
		}
	}
}
for ( const asset of manifest.assets ) {
	if ( isRetiredAdminOutput( asset.output ) ) {
		continue;
	}
	if ( asset.factories?.length ) {
		throw new Error(
			`Recovered factories are not built: ${ asset.output }`
		);
	}
	const destination = path.join( outputRoot, asset.output );
	fs.mkdirSync( path.dirname( destination ), { recursive: true } );
	if ( ! asset.output.endsWith( '.js' ) ) {
		if (
			! [ 'assets/css/style.css', 'assets/css/style-rtl.css' ].includes(
				asset.output
			)
		) {
			fs.copyFileSync(
				path.join( sourceRoot, asset.source ),
				destination
			);
		}
	} else {
		const label = 'ohmylms-source:///' + asset.source;
		const code = fs.readFileSync(
			path.join( sourceRoot, asset.source ),
			'utf8'
		);
		const ast = parse( code, {
			sourceType: 'unambiguous',
			sourceFilename: label,
			allowReturnOutsideFunction: true,
		} );
		const result = generate(
			ast,
			{ sourceMaps: true, comments: true, compact: true },
			{ [ label ]: code }
		);
		fs.writeFileSync(
			destination,
			result.code +
				'\n//# sourceMappingURL=' +
				path.basename( destination ) +
				'.map\n'
		);
		fs.writeFileSync( destination + '.map', JSON.stringify( result.map ) );
	}
	hashes[ asset.output ] = crypto
		.createHash( 'sha256' )
		.update( fs.readFileSync( destination ) )
		.digest( 'hex' );
}
fs.writeFileSync(
	path.join( outputRoot, 'asset-hashes.json' ),
	JSON.stringify( hashes, null, 2 ) + '\n'
);
console.log(
	`Built ${ Object.keys( hashes ).length } assets solely from checked-in source.`
);
