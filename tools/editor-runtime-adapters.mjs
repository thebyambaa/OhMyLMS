import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';
import {
	editorRuntime,
	sharedEditorRuntime,
	retiredEditorFactories,
} from './editor-runtime-manifest.mjs';

const traverse = traverseModule.default || traverseModule;
const tables = {
	'assets/dist/vendors/editor.js': editorRuntime,
	'assets/dist/vendors/vendors.js': sharedEditorRuntime,
};

/** Fail closed on unmapped editor implementations and new retired-library consumers. */
export function auditEditorRuntime( manifest, readSource ) {
	const editor = manifest.assets.find(
		( asset ) => asset.output === 'assets/dist/vendors/editor.js'
	);
	if ( ! editor ) throw new Error( 'Recovered editor manifest is missing' );
	const supported = new Set( [
		...Object.keys( editorRuntime ).map( Number ),
		...retiredEditorFactories[ editor.output ],
	] );
	for ( const factory of editor.factories )
		if ( ! supported.has( Number( factory.id ) ) )
			throw new Error(
				`Unmapped recovered editor factory ${ factory.id }`
			);
	const approved = {
		74802: new Set( [ 45644 ] ),
		11298: new Set( [ 74802 ] ),
		98405: new Set( [ 74802 ] ),
	};
	for ( const asset of manifest.assets )
		for ( const factory of asset.factories || [] )
			for ( const source of factory.fragments || [ factory.source ] ) {
				for ( const [ id, callers ] of Object.entries( approved ) ) {
					if (
						Number( factory.id ) === Number( id ) ||
						callers.has( Number( factory.id ) )
					)
						continue;
					if (
						new RegExp( `\\b${ id }\\b` ).test(
							readSource( source )
						)
					)
						throw new Error(
							`Retired collaboration factory ${ id } is still needed by ${ source }`
						);
				}
			}
}

export function editorBridge( { group, names } ) {
	return parseExpression( `(module,exports,require)=>{
    const runtime=window.ohmylmsEditorPackages?.tiptap?.[${ JSON.stringify( group ) }];
    if(!runtime)throw new Error('OhMyLMS npm editor runtime must load before vendor modules');
    require.r(exports);
    const names=${ names ? JSON.stringify( names ) : 'Object.fromEntries(Object.keys(runtime).filter(key=>key!=="__esModule").map(key=>[key,key]))' };
    require.d(exports,Object.fromEntries(Object.entries(names).map(([key,name])=>{
      if(!(name in runtime))throw new Error('Missing npm editor export '+${ JSON.stringify( group ) }+'.'+name);
      return [key,()=>runtime[name]];
    })));
  }` );
}

/** Replace every recovered editor runtime implementation with an npm bridge. */
export function adaptEditorRuntime( ast, output ) {
	const mappings = tables[ output ];
	if ( ! mappings ) return;
	let replaced = 0;
	let retired = 0;
	const removals = retiredEditorFactories[ output ];
	traverse( ast, {
		ObjectProperty( factory ) {
			if (
				! [ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					factory.node.value.type
				)
			)
				return;
			const id = factory.node.key.value;
			if ( mappings[ id ] ) {
				factory.node.value = editorBridge( mappings[ id ] );
				replaced++;
				factory.skip();
			} else if ( removals.includes( id ) ) {
				factory.remove();
				retired++;
			}
		},
	} );
	if (
		replaced !== Object.keys( mappings ).length ||
		retired !== removals.length
	)
		throw new Error(
			`Editor runtime mapping changed in ${ output }: ${ replaced } bridges, ${ retired } retired`
		);
	return { replaced, retired };
}
