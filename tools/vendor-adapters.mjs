import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';
const traverse = traverseModule.default || traverseModule;
/** The installed vendor bundle contains CJS and ESM copies of prosemirror-state, -model and -transform.
 * Each ESM factory is replaced by a thin re-export of its CJS twin under the ESM minified export names,
 * so exactly one instance of every package (and one PluginKey generator) is loaded. Mixing copies makes
 * `instanceof` and schema checks fail ("multiple versions of prosemirror-model were loaded").
 * The untouched recovered factories remain available to the parity build.
 */
const proseMirrorCopies = {
	// ESM factory id -> [CJS factory id, { ESM minified export: CJS export }]
	42845: [
		37820,
		{
			$t: 'EditorState',
			LN: 'Selection',
			U3: 'TextSelection',
			hs: 'PluginKey',
			i5: 'AllSelection',
			k_: 'Plugin',
			nh: 'NodeSelection',
		},
	],
	58903: [
		77712,
		{
			S4: 'DOMParser',
			ZF: 'DOMSerializer',
			FK: 'Fragment',
			CU: 'Mark',
			sX: 'MarkType',
			bP: 'Node',
			u$: 'NodeRange',
			vI: 'ReplaceError',
			Sj: 'Schema',
			Ji: 'Slice',
		},
	],
	38262: [
		36553,
		{
			$L: 'replaceStep',
			Ln: 'ReplaceStep',
			N0: 'joinPoint',
			Um: 'insertPoint',
			Wg: 'ReplaceAroundStep',
			X9: 'Mapping',
			dL: 'Transform',
			jP: 'liftTarget',
			n9: 'canJoin',
			oM: 'findWrapping',
			zy: 'canSplit',
		},
	],
};
function proseMirrorFactory( cjsId, names ) {
	const exports = Object.entries( names )
		.map( ( [ esm, cjs ] ) => `${ esm }:()=>cjs.${ cjs }` )
		.join( ',' );
	return `(module,exports,require)=>{const cjs=require(${ cjsId });require.d(exports,{${ exports }});}`;
}

export function adaptVendors( ast ) {
	let hits = 0;
	let shortcuts = 0,
		preferences = 0,
		registrations = 0;
	traverse( ast, {
		ObjectProperty( p ) {
			if ( String( p.node.key.value ) === '55578' ) {
				p.node.value = parseExpression( `(module,exports,require)=>{
          const shortcuts=window.wp.keyboardShortcuts;
          require.d(exports,{Ee:()=>shortcuts.ShortcutProvider,M_:()=>shortcuts.store,wk:()=>shortcuts.useShortcut});
        }` );
				shortcuts++;
				p.skip();
				return;
			}
			if ( String( p.node.key.value ) === '87240' ) {
				p.traverse( {
					VariableDeclarator( q ) {
						if (
							q.node.id.name === 'x' &&
							q.node.init?.type === 'CallExpression' &&
							q.node.init.arguments[ 0 ]?.value ===
								'core/preferences'
						) {
							q.node.init = parseExpression(
								'window.wp.preferences.store'
							);
							preferences++;
						}
					},
					ExpressionStatement( q ) {
						const call = q.node.expression;
						if (
							call.type === 'CallExpression' &&
							call.arguments.length === 1 &&
							call.arguments[ 0 ].name === 'x' &&
							call.callee.type === 'SequenceExpression' &&
							call.callee.expressions.at( -1 )?.property?.name ===
								'register'
						) {
							q.remove();
							registrations++;
						}
					},
				} );
			}
			const copy = proseMirrorCopies[ String( p.node.key.value ) ];
			if (
				! copy ||
				! [ 'ArrowFunctionExpression', 'FunctionExpression' ].includes(
					p.node.value.type
				)
			)
				return;
			p.node.value = parseExpression(
				proseMirrorFactory( copy[ 0 ], copy[ 1 ] )
			);
			hits++;
			p.skip();
		},
	} );
	if ( hits !== Object.keys( proseMirrorCopies ).length )
		throw new Error(
			'ProseMirror adapter expected one factory per package; found ' +
				hits
		);
	if ( shortcuts !== 1 || preferences !== 1 || registrations !== 1 )
		throw new Error(
			'WordPress store adapters did not match expected factories'
		);
	return { proseMirror: hits, shortcuts, preferences };
}

// This utility only needs status strings, not the collaboration provider and Yjs.
export function adaptConnectionStatus( ast ) {
	let hits = 0;
	traverse( ast, {
		ObjectProperty( p ) {
			if ( String( p.node.key.value ) !== '45644' ) return;
			p.traverse( {
				CallExpression( q ) {
					if (
						q.node.callee.name === 'n' &&
						q.node.arguments[ 0 ]?.value === 74802
					) {
						q.replaceWith(
							parseExpression(
								'({WebSocketStatus:{Connected:"connected",Connecting:"connecting",Disconnected:"disconnected"}})'
							)
						);
						hits++;
					}
				},
			} );
		},
	} );
	if ( hits !== 1 )
		throw new Error(
			'Connection status adapter expected one provider import; found ' +
				hits
		);
	return { connectionStatus: hits };
}
