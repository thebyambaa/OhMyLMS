import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { createRequire } from 'node:module';

const root = path.resolve( import.meta.dirname, '../..' );
const require = createRequire( path.join( root, 'package.json' ) );
const { parse } = require( '@babel/parser' );
const applicationDir = path.join( root, 'assets/src/application' );

/**
 * Source text of the named top-level declarations of a recovered fragment.
 *
 * `var A = ..., B = ...` lists are split so each name is returned on its own as
 * `var A = ...;`. Function declarations are returned unchanged.
 *
 * @param {string}   fragment Path relative to assets/src/application.
 * @param {string[]} names    Declaration names, in evaluation order.
 * @returns {string[]} Source snippets, one per name.
 */
export function extractDeclarations( fragment, names ) {
	const code = fs.readFileSync(
		path.join( applicationDir, fragment ),
		'utf8'
	);
	const ast = parse( code, { sourceType: 'script', errorRecovery: true } );
	const found = new Map();
	for ( const node of ast.program.body ) {
		if ( 'FunctionDeclaration' === node.type ) {
			found.set( node.id.name, code.slice( node.start, node.end ) );
		} else if ( 'VariableDeclaration' === node.type ) {
			for ( const declarator of node.declarations ) {
				if ( 'Identifier' === declarator.id.type && declarator.init ) {
					found.set(
						declarator.id.name,
						'var ' +
							code.slice( declarator.start, declarator.end ) +
							';'
					);
				}
			}
		}
	}
	return names.map( ( name ) => {
		if ( ! found.has( name ) ) {
			throw new Error( `${ name } is not declared in ${ fragment }` );
		}
		return found.get( name );
	} );
}

/**
 * Evaluate recovered declarations in an isolated context.
 *
 * @param {Array<{fragment: string, names: string[]}>} sources Fragments and the declarations to take from each.
 * @param {object}                                     scope   Free variables the recovered code reads.
 * @returns {object} Map of declared name to value.
 */
export function loadRecovered( sources, scope = {} ) {
	const names = sources.flatMap( ( source ) => source.names );
	const body = sources
		.flatMap( ( source ) =>
			extractDeclarations( source.fragment, source.names )
		)
		.join( '\n' );
	const context = vm.createContext( { ...scope } );
	vm.runInContext(
		`${ body }\n__out = { ${ names.join( ', ' ) } };`,
		context,
		{ filename: 'recovered-fragment.js' }
	);
	return context.__out;
}
