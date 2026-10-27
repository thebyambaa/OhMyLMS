import fs from 'node:fs';
import path from 'node:path';
import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';

const traverse = traverseModule.default || traverseModule;
const modules = JSON.parse(
	fs.readFileSync(
		path.resolve(
			import.meta.dirname,
			'../assets/src/features/webhooks/components.json'
		)
	)
);

export function adaptWebhooks( ast ) {
	const found = new Set();
	function replacement( module ) {
		found.add( module.name );
		return parseExpression(
			`window.ohmylms.extensions.webhookComponents.${ module.name }(()=>({${ module.dependencies.join( ',' ) }}))`
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
			if ( ! module ) return;
			if (
				[ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					path.node.init?.type
				)
			)
				path.node.init = replacement( module );
			else if (
				path.node.init?.type === 'CallExpression' &&
				[ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					path.node.init.arguments[ 0 ]?.type
				)
			)
				path.node.init.arguments[ 0 ] = replacement( module );
			else return;
			path.skip();
		},
	} );
	if ( found.size !== modules.length )
		throw Error(
			'Missing webhook adapters: ' +
				modules
					.filter( ( module ) => ! found.has( module.name ) )
					.map( ( module ) => module.name )
		);
	return { components: found.size };
}
