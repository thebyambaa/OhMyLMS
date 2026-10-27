import fs from 'node:fs';
import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';
import * as t from '@babel/types';

const traverse = traverseModule.default || traverseModule;

/** Build-time bridge for components declared directly in application factory 1841.
 * Dependencies stay lazy: sibling bindings may not be initialized at registration.
 * Route, object-member and wrapped components keep their specialized adapters.
 */
export function createComponentAdapter( {
	manifest,
	namespace,
	label,
	declarations = false,
} ) {
	const modules = JSON.parse( fs.readFileSync( manifest, 'utf8' ) );
	const byBinding = new Map();
	const names = new Set();
	if ( ! t.isValidIdentifier( namespace ) )
		throw new Error( `Invalid component namespace: ${ namespace }` );
	for ( const module of modules ) {
		if (
			! t.isValidIdentifier( module.binding ) ||
			! t.isValidIdentifier( module.name ) ||
			! Array.isArray( module.dependencies ) ||
			module.dependencies.some( ( id ) => ! t.isValidIdentifier( id ) )
		) {
			throw new Error(
				`Invalid ${ label } component contract: ${ module.name }`
			);
		}
		if ( byBinding.has( module.binding ) || names.has( module.name ) ) {
			throw new Error(
				`Duplicate ${ label } component contract: ${ module.name }`
			);
		}
		byBinding.set( module.binding, module );
		names.add( module.name );
	}

	return function adaptComponents( ast ) {
		const found = new Set();
		function ownsBinding( nodePath ) {
			const owner = nodePath.findParent( ( parent ) =>
				parent.isFunction()
			);
			return (
				owner?.parentPath.isObjectProperty() &&
				String( owner.parentPath.node.key.value ) === '1841'
			);
		}
		function replacement( module ) {
			if ( found.has( module.name ) )
				throw new Error(
					`Duplicate ${ label } adapter: ${ module.name }`
				);
			found.add( module.name );
			return parseExpression(
				`window.ohmylms.extensions.${ namespace }.${ module.name }(()=>({${ module.dependencies.join( ',' ) }}))`
			);
		}
		traverse( ast, {
			VariableDeclarator( nodePath ) {
				if ( ! ownsBinding( nodePath ) ) return;
				const module = byBinding.get( nodePath.node.id.name );
				if (
					! module ||
					! (
						t.isFunctionExpression( nodePath.node.init ) ||
						t.isArrowFunctionExpression( nodePath.node.init )
					)
				)
					return;
				nodePath.node.init = replacement( module );
				nodePath.skip();
			},
			FunctionDeclaration( nodePath ) {
				if ( ! declarations || ! ownsBinding( nodePath ) ) return;
				const module = byBinding.get( nodePath.node.id?.name );
				if ( ! module ) return;
				nodePath.replaceWith(
					t.variableDeclaration( 'var', [
						t.variableDeclarator(
							t.identifier( module.binding ),
							replacement( module )
						),
					] )
				);
				nodePath.skip();
			},
		} );
		const missing = modules.filter(
			( module ) => ! found.has( module.name )
		);
		if ( missing.length )
			throw new Error(
				`Missing ${ label } adapters: ${ missing.map( ( module ) => module.name ).join( ',' ) }`
			);
		return { components: found.size };
	};
}
