import { createComponentAdapter } from './component-adapter.mjs';
import traverseModule from '@babel/traverse';

const traverse = traverseModule.default || traverseModule;
const adaptComponents = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/commerce/components.json',
		import.meta.url
	),
	namespace: 'commerceComponents',
	label: 'commerce',
	declarations: true,
} );

/** Route recovered commerce clients to the OhMyLMS-owned REST namespace. */
export function adaptCommerceRoutes( ast ) {
	let routes = 0;
	traverse( ast, {
		StringLiteral( nodePath ) {
			const value = nodePath.node.value;
			if ( ! /^\/?cx-ecommerce\/v1(?:\/|$)/.test( value ) ) {
				return;
			}
			nodePath.node.value = value.replace(
				'cx-ecommerce/v1',
				'ohmylms-commerce/v1'
			);
			routes++;
		},
	} );
	return routes;
}

export function adaptCommerce( ast ) {
	return { ...adaptComponents( ast ), routes: adaptCommerceRoutes( ast ) };
}
