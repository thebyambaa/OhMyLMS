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

export function resolveImport( file, specifier ) {
	const base = path.resolve( path.dirname( file ), specifier );
	return [
		base,
		...[ '.js', '.jsx', '.mjs' ].map( ( extension ) => base + extension ),
		...[ 'index.js', 'index.jsx', 'index.mjs' ].map( ( index ) =>
			path.join( base, index )
		),
	].find(
		( candidate ) =>
			fs.existsSync( candidate ) && fs.statSync( candidate ).isFile()
	);
}

/** Validate authored module links and conversion contracts without loading browser code. */
export function checkContracts( sourceRoot ) {
	const errors = [];
	const manifests = walk( path.join( sourceRoot, 'features' ) ).filter(
		( file ) => path.basename( file ) === 'components.json'
	);
	for ( const folder of [ 'features', 'extensions' ] ) {
		for ( const file of walk( path.join( sourceRoot, folder ) ).filter(
			( file ) => /\.(m?js|jsx)$/.test( file )
		) ) {
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
	let components = 0;
	for ( const manifest of manifests ) {
		const names = new Set();
		const bindings = new Set();
		const directory = path.dirname( manifest );
		const indexFile = path.join( directory, 'index.js' );
		if ( ! fs.existsSync( indexFile ) ) {
			errors.push( `${ manifest }: missing index.js` );
			continue;
		}
		const index = parseSource( indexFile ).program.body;
		const imports = new Map(
			index
				.filter( ( node ) => node.type === 'ImportDeclaration' )
				.flatMap( ( node ) =>
					node.specifiers.map( ( specifier ) => [
						specifier.local.name,
						{
							file: resolveImport( indexFile, node.source.value ),
							imported: specifier.imported?.name,
						},
					] )
				)
		);
		const registrations = index
			.filter( ( node ) => node.type === 'ExportNamedDeclaration' )
			.flatMap( ( node ) => node.declaration?.declarations || [] )
			.filter( ( node ) => node.init?.type === 'ObjectExpression' )
			.flatMap( ( node ) => node.init.properties );
		for ( const component of JSON.parse(
			fs.readFileSync( manifest, 'utf8' )
		) ) {
			const { name, binding, file, dependencies } = component;
			components++;
			if ( names.has( name ) || bindings.has( binding ) )
				errors.push(
					`${ manifest }: duplicate component ${ name } / ${ binding }`
				);
			names.add( name );
			bindings.add( binding );
			if (
				! Array.isArray( dependencies ) ||
				new Set( dependencies ).size !== dependencies.length
			)
				errors.push(
					`${ manifest }: invalid dependencies for ${ name }`
				);
			if ( typeof file !== 'string' || path.basename( file ) !== file ) {
				errors.push(
					`${ manifest }: invalid component file for ${ name }`
				);
				continue;
			}
			const source = path.join( directory, file );
			if ( ! fs.existsSync( source ) ) {
				errors.push( `${ manifest }: missing ${ file }` );
				continue;
			}
			const factory = `create${ name }`;
			const exported = parseSource( source ).program.body.some(
				( node ) =>
					node.type === 'ExportNamedDeclaration' &&
					node.declaration?.id?.name === factory
			);
			if ( ! exported )
				errors.push(
					`${ source }: missing exported factory ${ factory }`
				);
			const registration = registrations.find(
				( node ) => ( node.key?.name ?? node.key?.value ) === name
			);
			const imported = imports.get( registration?.value?.name );
			if ( imported?.file !== source || imported?.imported !== factory )
				errors.push(
					`${ indexFile }: ${ name } must register ${ factory } from ${ file }`
				);
		}
	}
	return { errors, features: manifests.length, components };
}
