import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';
import { callExpression } from '@babel/types';
import { EASY_EMAIL_CORE_EXPORTS } from '../assets/src/shared/easy-email/export-names.mjs';
const traverse = traverseModule.default || traverseModule;

/**
 * Replace the recovered Easy Email core (factory 49050) with the authored wrapper around the
 * npm package. Admin startup requires this factory synchronously, so the bridge cannot wait
 * for a lazy chunk: the bootstrap entry defines `createEasyEmailCore`, and the factory calls
 * it when the first consumer asks. It refuses to run unless the recovered factory exports
 * exactly the names the wrapper provides.
 */
export function bridgeEasyEmailCore( ast ) {
	let bridges = 0;
	traverse( ast, {
		ObjectProperty( factory ) {
			if (
				49050 !== factory.node.key.value ||
				! [ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					factory.node.value.type
				)
			)
				return;
			const recovered = recoveredExports( factory.get( 'value' ) );
			if (
				JSON.stringify( [ ...recovered ].sort() ) !==
				JSON.stringify( [ ...EASY_EMAIL_CORE_EXPORTS ].sort() )
			)
				throw new Error(
					'Easy Email core exports differ from the authored wrapper'
				);
			factory.node.value = parseExpression( `(module,exports,require)=>{
				const create=window.ohmylmsEmailPackages?.createEasyEmailCore;
				if(typeof create!=='function')throw new Error('OhMyLMS npm Easy Email core must load before its consumers');
				const core=create();
				require.r(exports);
				require.d(exports,Object.fromEntries(${ JSON.stringify(
					EASY_EMAIL_CORE_EXPORTS
				) }.map(name=>{
					if(!(name in core))throw new Error('Missing npm Easy Email export '+name);
					return [name,()=>core[name]];
				})));
			}` );
			bridges++;
			factory.skip();
		},
	} );
	if ( 1 !== bridges )
		throw new Error(
			`Expected one Easy Email core factory; found ${ bridges }`
		);
}

/** Names a webpack factory declares with `require.d( exports, { name: () => value } )`. */
function recoveredExports( factory ) {
	const names = [];
	factory.traverse( {
		CallExpression( call ) {
			const { callee, arguments: args } = call.node;
			if (
				callee.type !== 'MemberExpression' ||
				callee.property.name !== 'd' ||
				args[ 1 ]?.type !== 'ObjectExpression'
			)
				return;
			for ( const property of args[ 1 ].properties )
				names.push( property.key.name ?? property.key.value );
			call.skip();
		},
	} );
	return names;
}

/** Gate all email chunk consumers on the npm package chunk, preserving sync rendering afterwards. */
export function adaptEmailPackages( ast, output ) {
	if ( output === 'assets/dist/admin/ohmylms.js' ) {
		let loaders = 0;
		traverse( ast, {
			AssignmentExpression( assignment ) {
				const { left, right } = assignment.node;
				if (
					left.type !== 'MemberExpression' ||
					left.property.name !== 'e' ||
					right.type !== 'ArrowFunctionExpression' ||
					right.body.callee?.object?.name !== 'Promise' ||
					right.body.callee.property.name !== 'all'
				)
					return;
				assignment.node.right = callExpression(
					parseExpression( `(load=>id=>{
					if(id!==355)return load(id);
					if(typeof window.ohmylmsLoadEmailPackages!=='function')return Promise.reject(new Error('OhMyLMS email package loader is missing'));
					return Promise.all([load(id),window.ohmylmsLoadEmailPackages()]);
				})` ),
					[ right ]
				);
				loaders++;
				assignment.skip();
			},
		} );
		if ( loaders !== 1 )
			throw new Error(
				`Expected one email chunk loader; found ${ loaders }`
			);
	}
	if ( output === 'assets/dist/vendors/vendors.js' )
		bridgeEasyEmailCore( ast );
	if ( output !== 'assets/dist/chunks/355.js' ) return;
	let bridges = 0;
	traverse( ast, {
		ObjectProperty( factory ) {
			if (
				! [ 3270, 44098 ].includes( factory.node.key.value ) ||
				! [ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					factory.node.value.type
				)
			)
				return;
			factory.node.value = parseExpression(
				factory.node.key.value === 44098
					? `(module)=>{const render=window.ohmylmsEmailPackages?.mjml;if(!render)throw new Error('OhMyLMS npm MJML package is not ready');module.exports=render;}`
					: `(module,exports,require)=>{const arco=window.ohmylmsEmailPackages?.arco;if(!arco)throw new Error('OhMyLMS npm Arco package is not ready');require.r(exports);require.d(exports,Object.fromEntries(Object.keys(arco).map(name=>[name,()=>arco[name]])));}`
			);
			bridges++;
			factory.skip();
		},
	} );
	if ( bridges !== 2 )
		throw new Error( `Expected two npm email bridges; found ${ bridges }` );
}
