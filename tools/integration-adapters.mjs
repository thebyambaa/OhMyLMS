import fs from 'node:fs';
import path from 'node:path';
import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';
import * as t from '@babel/types';

const traverse = traverseModule.default || traverseModule;
const modules = JSON.parse(
	fs.readFileSync(
		path.resolve(
			import.meta.dirname,
			'../assets/src/features/integrations/components.json'
		)
	)
);

export function adaptIntegrations( ast ) {
	const found = new Set();
	function replacement( module ) {
		found.add( module.name );
		return parseExpression(
			`window.ohmylms.extensions.integrationComponents.${ module.name }(()=>({${ module.dependencies.join( ',' ) }}))`
		);
	}
	traverse( ast, {
		VariableDeclarator( path ) {
			const owner = path.findParent( ( parent ) => parent.isFunction() );
			if (
				! owner?.parentPath.isObjectProperty() ||
				String( owner.parentPath.node.key.value ) !== '1841'
			)
				return;
			const module = modules.find(
				( item ) => item.binding === path.node.id.name
			);
			if (
				! module ||
				! [ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					path.node.init?.type
				)
			)
				return;
			path.node.init = replacement( module );
			path.skip();
		},
		FunctionDeclaration( path ) {
			const owner = path.findParent( ( parent ) => parent.isFunction() );
			if (
				! owner?.parentPath.isObjectProperty() ||
				String( owner.parentPath.node.key.value ) !== '1841'
			)
				return;
			const module = modules.find(
				( item ) => item.binding === path.node.id.name
			);
			if ( ! module ) return;
			path.replaceWith(
				t.variableDeclaration( 'var', [
					t.variableDeclarator(
						t.identifier( module.binding ),
						replacement( module )
					),
				] )
			);
			path.skip();
		},
		ObjectProperty( path ) {
			const declaration = path.findParent( ( parent ) =>
				parent.isVariableDeclarator()
			);
			const owner = path.findParent( ( parent ) => parent.isFunction() );
			if (
				! owner?.parentPath.isObjectProperty() ||
				String( owner.parentPath.node.key.value ) !== '1841' ||
				declaration?.node.id.name !== 'j7'
			)
				return;
			const key = path.node.key.name ?? path.node.key.value;
			const module = modules.find(
				( item ) => item.binding === `j7.${ key }`
			);
			if (
				! module ||
				! [ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					path.node.value?.type
				)
			)
				return;
			path.node.value = replacement( module );
			path.skip();
		},
	} );
	if ( found.size !== modules.length )
		throw Error(
			'Missing integration adapters: ' +
				modules
					.filter( ( module ) => ! found.has( module.name ) )
					.map( ( module ) => module.name )
		);
	return { components: found.size };
}
