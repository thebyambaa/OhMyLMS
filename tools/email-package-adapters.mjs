import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';
import { callExpression } from '@babel/types';
const traverse = traverseModule.default || traverseModule;

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
