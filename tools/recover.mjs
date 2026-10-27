import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';
import generatorModule from '@babel/generator';
import * as t from '@babel/types';
const traverse = traverseModule.default || traverseModule;
const generate = generatorModule.default || generatorModule;
const root = path.resolve( import.meta.dirname, '..' );
const target = path.join( root, 'assets/src' );
if ( fs.existsSync( path.join( target, 'manifest.json' ) ) )
	throw new Error(
		'Source already recovered; refusing to overwrite editable modules.'
	);
const walk = ( dir ) =>
	fs
		.readdirSync( dir, { withFileTypes: true } )
		.flatMap( ( e ) =>
			e.isDirectory()
				? walk( path.join( dir, e.name ) )
				: [ path.join( dir, e.name ) ]
		);
const write = ( name, data ) => {
	const dest = path.join( target, name );
	fs.mkdirSync( path.dirname( dest ), { recursive: true } );
	fs.writeFileSync( dest, data );
};
const files = [
	...new Set(
		[ 'dist', 'js', 'blocks/js', 'extensions', 'css' ].flatMap( ( dir ) =>
			fs.existsSync( path.join( root, 'assets', dir ) )
				? walk( path.join( root, 'assets', dir ) )
				: []
		)
	),
].sort();
const manifest = {
	schema: 1,
	description:
		'Reconstructed from installed outputs. Original names, JSX and comments are not recoverable.',
	assets: [],
	modules: [],
};
for ( const file of files ) {
	const output = path.relative( root, file ).replaceAll( '\\', '/' );
	const original = fs.readFileSync( file );
	const record = {
		output,
		sha256: crypto
			.createHash( 'sha256' )
			.update( original )
			.digest( 'hex' ),
		bytes: original.length,
	};
	if ( ! file.endsWith( '.js' ) ) {
		record.source =
			'static/' +
			path
				.relative( path.join( root, 'assets' ), file )
				.replaceAll( '\\', '/' );
		write( record.source, original );
		manifest.assets.push( record );
		continue;
	}
	const source = original
		.toString( 'utf8' )
		.replace( /\/\/[#@] sourceMappingURL=.*$/gm, '' );
	const ast = parse( source, {
		sourceType: 'unambiguous',
		allowReturnOutsideFunction: true,
	} );
	const bundle = output.replace( /^assets\//, '' ).replace( /\.js$/, '' );
	const substitutions = [];
	traverse( ast, {
		ObjectExpression( p ) {
			const props = p.node.properties;
			if (
				props.length < 2 ||
				! props.every(
					( v ) =>
						t.isObjectProperty( v ) &&
						( t.isNumericLiteral( v.key ) ||
							( t.isStringLiteral( v.key ) &&
								/^\d+$/.test( v.key.value ) ) ) &&
						( t.isFunctionExpression( v.value ) ||
							t.isArrowFunctionExpression( v.value ) )
				)
			)
				return;
			const table = substitutions.length;
			for ( const prop of props ) {
				const id = String( prop.key.value );
				const modulePath = `modules/${ bundle }/${ table }-${ id }.js`;
				const text = generate( prop.value, {
					comments: true,
					compact: false,
				} ).code;
				write(
					modulePath,
					`// Reconstructed Webpack factory ${ id }; arguments retain original semantics.\n(${ text });\n`
				);
				const marker = `__OHMYLMS_FACTORY_${ table }_${ id }__`;
				substitutions.push( { marker, source: modulePath, id } );
				manifest.modules.push( {
					bundle,
					id,
					source: modulePath,
					kind: bundle.startsWith( 'dist/vendors/' )
						? 'third-party'
						: 'unclassified',
					bytes: text.length,
				} );
				prop.value = t.identifier( marker );
			}
			p.skip();
		},
	} );
	record.source = `runtimes/${ bundle }.js`;
	record.factories = substitutions;
	write(
		record.source,
		generate( ast, { comments: true, compact: false } ).code + '\n'
	);
	manifest.assets.push( record );
}
write( 'manifest.json', JSON.stringify( manifest, null, 2 ) + '\n' );
console.log(
	`Recovered ${ manifest.modules.length } module factories and ${ manifest.assets.length } assets. Production files unchanged.`
);
