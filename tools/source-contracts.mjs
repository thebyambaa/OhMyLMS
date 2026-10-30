import fs from 'node:fs';
import path from 'node:path';
import { parse } from '@babel/parser';

export function walk( directory ) {
	return fs
		.readdirSync( directory, { withFileTypes: true } )
		.flatMap( ( entry ) =>
			entry.isDirectory()
				? walk( path.join( directory, entry.name ) )
				: [ path.join( directory, entry.name ) ]
		);
}

export function parseSource( file ) {
	return parse( fs.readFileSync( file, 'utf8' ), {
		sourceType: 'unambiguous',
		plugins: [ 'jsx' ],
		allowReturnOutsideFunction: true,
	} );
}

const EXTENSIONS = [ '.js', '.jsx', '.mjs', '.ts', '.tsx' ];

export function resolveImport( file, specifier ) {
	const base = path.resolve( path.dirname( file ), specifier );
	return [
		base,
		...EXTENSIONS.map( ( extension ) => base + extension ),
		...EXTENSIONS.map( ( extension ) =>
			path.join( base, 'index' + extension )
		),
	].find(
		( candidate ) =>
			fs.existsSync( candidate ) && fs.statSync( candidate ).isFile()
	);
}

/** Authored JavaScript folders outside the typed app, which TypeScript already checks. */
export const AUTHORED_FOLDERS = [ 'features', 'ui', 'blocks' ];

/** Report relative imports in authored JavaScript that no longer resolve, without loading browser code. */
export function checkContracts( sourceRoot ) {
	const errors = [];
	let files = 0;
	for ( const folder of AUTHORED_FOLDERS ) {
		const directory = path.join( sourceRoot, folder );
		if ( ! fs.existsSync( directory ) ) {
			continue;
		}
		for ( const file of walk( directory ).filter( ( name ) =>
			/\.(m?js|jsx)$/.test( name )
		) ) {
			files++;
			for ( const statement of parseSource( file ).program.body ) {
				const specifier = statement.source?.value;
				if (
					typeof specifier === 'string' &&
					specifier.startsWith( '.' ) &&
					! resolveImport( file, specifier )
				)
					errors.push(
						`${ file }: unresolved import ${ specifier }`
					);
			}
		}
	}
	return { errors, files };
}
