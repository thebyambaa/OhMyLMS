import fs from 'node:fs';
import path from 'node:path';
import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';

const traverse = traverseModule.default || traverseModule;
const modules = JSON.parse(
	fs.readFileSync(
		path.resolve(
			import.meta.dirname,
			'../assets/src/features/taxonomies/components.json'
		)
	)
);

export function adaptTaxonomies( ast ) {
	const found = new Set();
	const replacement = ( module ) => {
		found.add( module.name );
		return parseExpression(
			`window.ohmylms.extensions.taxonomyComponents.${ module.name }(()=>({${ module.dependencies.join( ',' ) }}))`
		);
	};
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
				module &&
				[ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					path.node.init?.type
				)
			) {
				path.node.init = replacement( module );
				path.skip();
			}
		},
		ObjectProperty( path ) {
			if (
				path.node.key.name !== 'element' ||
				! [ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					path.node.value?.type
				)
			)
				return;
			const object = path.parentPath;
			const route = object.node.properties.find(
				( property ) => property.key?.name === 'path'
			)?.value?.value;
			const module = modules.find(
				( item ) => item.binding === `route:${ route }`
			);
			if ( module ) {
				path.node.value = replacement( module );
				path.skip();
			}
		},
	} );
	if ( found.size !== modules.length )
		throw Error(
			'Missing taxonomy adapters: ' +
				modules
					.filter( ( module ) => ! found.has( module.name ) )
					.map( ( module ) => module.name )
		);
	return { components: found.size };
}
