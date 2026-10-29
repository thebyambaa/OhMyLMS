import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';
import { callExpression } from '@babel/types';

const traverse = traverseModule.default || traverseModule;
export const deferredEmailReferences = {
	44098: [
		'application/shared/automation-campaign-email-builder.js',
		'modules/dist/vendors/vendors/0-49050.js',
	],
	47909: [ 'application/shared/certificate-builder-classic.js' ],
	43203: [ 'application/shared/certificate-template-preview.js' ],
};

/** Preserve synchronous template conversion behind an asynchronous component boundary. */
export function adaptEmailRuntime( ast ) {
	const hits = {
		imports: 0,
		interop: 0,
		certificateImports: 0,
		components: 0,
	};
	traverse( ast, {
		ObjectProperty( entry ) {
			if ( entry.node.key.value !== 1841 ) return;
			const factory = entry.get( 'value' );
			const requireBinding = factory.scope.getBinding( 'n' );
			factory.traverse( {
				VariableDeclarator( binding ) {
					if ( binding.scope !== factory.scope ) return;
					const { id, init } = binding.node;
					if ( id.name === 'NP' ) {
						const expected = 44098;
						if (
							init?.type !== 'CallExpression' ||
							init.callee.name !== 'n' ||
							init.arguments[ 0 ]?.value !== expected
						)
							throw new Error( 'Email import mapping changed' );
						binding.node.init = parseExpression( 'null' );
						hits.imports++;
						binding.skip();
					} else if ( id.name === 'DP' ) {
						if (
							init?.callee?.object?.name !== 'n' ||
							init.callee.property.name !== 'n' ||
							init.arguments[ 0 ]?.name !== 'NP'
						)
							throw new Error( 'MJML interop mapping changed' );
						binding.node.init = parseExpression(
							'function(){return n.n(n(44098))();}'
						);
						hits.interop++;
						binding.skip();
					} else if ( [ 'lO', 'ZO' ].includes( id.name ) ) {
						if ( init?.type !== 'FunctionExpression' )
							throw new Error(
								'Email component mapping changed'
							);
						const references = binding.scope.getBinding(
							id.name
						).referencePaths;
						if (
							references.length !== 1 ||
							references[ 0 ].parentPath.node.type !==
								'CallExpression'
						)
							throw new Error(
								'Email component consumers changed'
							);
						binding.node.init = callExpression(
							parseExpression(
								'window.ohmylms.extensions.lazyEmailComponent'
							),
							[
								init,
								parseExpression(
									'function(){return n.e(355);}'
								),
							]
						);
						hits.components++;
						binding.skip();
					}
				},
				ExpressionStatement( statement ) {
					const call = statement.node.expression;
					if (
						call.type === 'CallExpression' &&
						call.callee.name === 'n' &&
						statement.scope.getBinding( 'n' ) === requireBinding &&
						[ 47909, 43203 ].includes( call.arguments[ 0 ]?.value )
					) {
						statement.remove();
						hits.certificateImports++;
					}
				},
			} );
			entry.skip();
		},
	} );
	if (
		hits.imports !== 1 ||
		hits.interop !== 1 ||
		hits.certificateImports !== 2 ||
		hits.components !== 2
	)
		throw new Error(
			`Email runtime integration changed: ${ JSON.stringify( hits ) }`
		);
	return hits;
}

/** MJML parsing is only needed by template editor actions, not model initialization. */
export function adaptEmailModel( ast ) {
	let hits = 0;
	traverse( ast, {
		ObjectProperty( entry ) {
			if ( entry.node.key.value !== 49050 ) return;
			const factory = entry.get( 'value' );
			factory.traverse( {
				VariableDeclarator( binding ) {
					if ( binding.scope !== factory.scope ) return;
					const { id, init } = binding.node;
					if (
						id.name === 'K' &&
						init?.callee?.name === 'n' &&
						init.arguments[ 0 ]?.value === 44098
					) {
						binding.node.init = parseExpression( 'null' );
						hits++;
					} else if (
						id.name === 'V' &&
						init?.callee?.object?.name === 'n' &&
						init.callee.property.name === 'n' &&
						init.arguments[ 0 ]?.name === 'K'
					) {
						binding.node.init = parseExpression(
							'function(){return n.n(n(44098))();}'
						);
						hits++;
						binding.skip();
					}
				},
			} );
			entry.skip();
		},
	} );
	if ( hits !== 2 )
		throw new Error( 'Email model MJML import mapping changed' );
}
