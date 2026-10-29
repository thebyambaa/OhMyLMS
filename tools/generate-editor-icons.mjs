/** One-time migration: preserve legacy icon names and SVGs using the npm renderer. */
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import * as npmIcons from 'lucide-react';
import { ESLint } from 'eslint';
import prettier from 'prettier';

const root = path.resolve( import.meta.dirname, '..' );
const React = {
	forwardRef: ( render ) => ( { render } ),
	createElement: ( type, props ) => ( { type, props } ),
};
const factory = vm.runInNewContext(
	fs.readFileSync(
		path.join( root, 'assets/src/modules/dist/vendors/vendors/0-6165.js' ),
		'utf8'
	)
);
const legacy = {};
const require = ( id ) => {
	if ( id !== 41594 ) throw new Error( `Unexpected icon dependency ${ id }` );
	return React;
};
require.r = ( target ) =>
	Object.defineProperty( target, '__esModule', { value: true } );
require.d = ( target, getters ) =>
	Object.entries( getters ).forEach( ( [ name, get ] ) =>
		Object.defineProperty( target, name, { enumerable: true, get } )
	);
factory( {}, legacy, require );
const names = Object.keys( legacy.icons );
const aliases = new Map(
	names.map( ( name ) => [ legacy.icons[ name ], name ] )
);
const compatibility = {};
const imports = [ 'createLucideIcon' ];
const definitions = [];
for ( const name of names ) {
	const icon = legacy.icons[ name ];
	const iconNode = icon.render( {}, null ).props.iconNode;
	const replacement = npmIcons[ name ];
	if (
		replacement?.displayName === icon.displayName &&
		JSON.stringify( replacement.render( {}, null ).props.iconNode ) ===
			JSON.stringify( iconNode )
	) {
		imports.push( name );
		definitions.push( `${ JSON.stringify( name ) }: ${ name }` );
	} else {
		compatibility[ name ] = { name: icon.displayName, iconNode };
		definitions.push(
			`${ JSON.stringify( name ) }: createLucideIcon(compatibility[${ JSON.stringify( name ) }].name,compatibility[${ JSON.stringify( name ) }].iconNode)`
		);
	}
}
const exports = [];
for ( const [ name, icon ] of Object.entries( legacy ) ) {
	if ( name === 'icons' ) exports.push( 'icons' );
	else if ( aliases.has( icon ) )
		exports.push(
			`${ JSON.stringify( name ) }: icons[${ JSON.stringify( aliases.get( icon ) ) }]`
		);
	else if ( name === 'createLucideIcon' ) exports.push( 'createLucideIcon' );
	else if ( name === 'Icon' ) {
		imports.push( 'Icon' );
		exports.push( 'Icon' );
	} else throw new Error( `Unmapped legacy icon export ${ name }` );
}
const directory = path.join( root, 'assets/src/features/editor-packages' );
const compatPath = path.join( directory, 'icon-compat.json' );
fs.writeFileSync(
	compatPath,
	await prettier.format( JSON.stringify( compatibility ), {
		...( await prettier.resolveConfig( compatPath ) ),
		filepath: compatPath,
	} )
);
const iconPath = path.join( directory, 'icons.js' );
const code = `// Generated once by tools/generate-editor-icons.mjs; preserve the legacy icon contract.\nimport {${ imports.join( ',' ) }} from 'lucide-react';\nimport compatibility from './icon-compat.json';\nexport const icons=Object.freeze({${ definitions.join( ',\n' ) }});\nexport const iconExports=Object.freeze({${ exports.join( ',\n' ) }});\n`;
const [ formatted ] = await new ESLint( { fix: true } ).lintText( code, {
	filePath: iconPath,
} );
if ( formatted.errorCount )
	throw new Error( 'Generated icons failed WordPress lint' );
fs.writeFileSync( iconPath, formatted.output || code );
console.log(
	JSON.stringify( {
		icons: names.length,
		exports: exports.length,
		preservedSvgOverrides: Object.keys( compatibility ).length,
	} )
);
