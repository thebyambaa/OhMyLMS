import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';

const traverse = traverseModule.default || traverseModule;
export const retiredHighlightFactory = 38416;

/** Reject removal if another recovered component starts using the old core. */
export function auditHighlightConsumers( manifest, readSource ) {
	for ( const asset of manifest.assets ) {
		if (
			asset.source.endsWith( '.js' ) &&
			asset.output !== 'assets/dist/vendors/vendors.js' &&
			/\b38416\b/.test( readSource( asset.source ) )
		)
			throw new Error(
				`Recovered highlight.js core is still used by ${ asset.source }`
			);
		for ( const factory of asset.factories || [] ) {
			if (
				[ 7871, retiredHighlightFactory ].includes(
					Number( factory.id )
				)
			)
				continue;
			for ( const source of factory.fragments || [ factory.source ] ) {
				if ( /\b38416\b/.test( readSource( source ) ) )
					throw new Error(
						`Recovered highlight.js core is still used by ${ source }`
					);
			}
		}
	}
}

/** Preserve the recovered Lowlight export contract while sharing the npm instance. */
export function adaptEditorPackages( ast ) {
	let lowlight = 0;
	let retired = 0;
	traverse( ast, {
		ObjectProperty( factory ) {
			if (
				! [ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					factory.node.value.type
				)
			)
				return;
			if ( factory.node.key.value === 7871 ) {
				factory.node.value =
					parseExpression( `(module,exports,require)=>{
          const syntax=window.ohmylmsEditorPackages?.lowlight;
          if(!syntax)throw new Error('OhMyLMS Lowlight package must load before the editor');
          require.r(exports);
          require.d(exports,{all:()=>syntax.all,common:()=>syntax.common,createLowlight:()=>syntax.createLowlight});
        }` );
				lowlight++;
				factory.skip();
			} else if ( factory.node.key.value === retiredHighlightFactory ) {
				factory.remove();
				retired++;
			}
		},
	} );
	if ( lowlight !== 1 || retired !== 1 )
		throw new Error( 'Recovered Lowlight factory mapping changed' );
	return { lowlight, retired };
}
