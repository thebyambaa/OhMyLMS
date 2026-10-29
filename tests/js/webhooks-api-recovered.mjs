/**
 * Evaluate the webhooks section of the recovered application store (factory 86169) in a vm sandbox.
 *
 * The factory is minified, so nothing here relies on a minified name for the webhooks code itself:
 * the action and selector namespaces are found by their export keys, the reducer by the `webhooks`
 * property of the root reducer, and the notification creators by their export keys. The code that
 * those roots need (helpers, regenerator runtime, constants) is copied unchanged, in source order,
 * by following scope-resolved references. The only replaced binding is the `apiFetch` accessor.
 */
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';

const traverse = traverseModule.default || traverseModule;
const FACTORY = path.resolve(
	import.meta.dirname,
	'../../assets/src/modules/dist/admin/ohmylms/0-86169.js'
);

/**
 * @param {Array}  calls `n.d( target, { key: () => binding } )` calls.
 * @param {string} name  Export key to look for.
 * @return {Object|undefined} Matching call.
 */
const findExports = ( calls, name ) =>
	calls.find( ( call ) => call.keys.has( name ) );

/**
 * Parse the factory and return the recovered webhooks code as a function source.
 *
 * @return {{ source: string, roots: Object, factoryPath: string }} Evaluable source and the names it exports.
 */
export function extractRecoveredWebhooks() {
	const code = fs.readFileSync( FACTORY, 'utf8' );
	const ast = parse( code, {
		sourceType: 'script',
		allowReturnOutsideFunction: true,
	} );
	let factory = null;
	traverse( ast, {
		ArrowFunctionExpression( p ) {
			if ( ! factory && p.parentPath.isExpressionStatement() ) {
				factory = p;
				p.stop();
			}
		},
	} );
	if ( ! factory ) {
		throw new Error( 'Factory 86169 shape changed: no arrow factory.' );
	}
	const body = factory.get( 'body' );
	const units = new Map(); // binding name -> declarator or function declaration path
	for ( const statement of body.get( 'body' ) ) {
		if ( statement.isVariableDeclaration() ) {
			for ( const declarator of statement.get( 'declarations' ) ) {
				if ( declarator.node.id.type === 'Identifier' ) {
					units.set( declarator.node.id.name, declarator );
				}
			}
		} else if ( statement.isFunctionDeclaration() ) {
			units.set( statement.node.id.name, statement );
		}
	}

	// n.d( target, { key: () => binding, ... } ) calls.
	const exportCalls = [];
	for ( const statement of body.get( 'body' ) ) {
		statement.traverse( {
			CallExpression( p ) {
				const callee = p.node.callee;
				if (
					callee.type === 'MemberExpression' &&
					callee.property.name === 'd' &&
					p.node.arguments[ 1 ]?.type === 'ObjectExpression'
				) {
					const entries = new Map();
					for ( const prop of p.node.arguments[ 1 ].properties ) {
						const key = prop.key.name || prop.key.value;
						const value = prop.value.body;
						if ( value?.type === 'Identifier' ) {
							entries.set( key, value.name );
						}
					}
					exportCalls.push( { keys: entries } );
				}
			},
		} );
	}
	const actionExports = findExports( exportCalls, 'bulkWebhookAction' );
	const selectorExports = findExports( exportCalls, 'selectWebhookFormData' );
	const notificationExports = findExports( exportCalls, 'showNotification' );
	if ( ! actionExports || ! selectorExports || ! notificationExports ) {
		throw new Error( 'Factory 86169 shape changed: export groups moved.' );
	}

	// The reducer is the call assigned to the `webhooks` key of the root reducer.
	let reducerName = null;
	body.traverse( {
		ObjectProperty( p ) {
			const key = p.node.key.name || p.node.key.value;
			if (
				'webhooks' === key &&
				p.node.value.type === 'CallExpression' &&
				p.node.value.callee.type === 'Identifier' &&
				p.node.value.arguments[ 0 ]?.type === 'MemberExpression' &&
				p.node.value.arguments[ 0 ].property.name === 'webhooks'
			) {
				reducerName = p.node.value.callee.name;
			}
		},
	} );
	if ( ! reducerName ) {
		throw new Error( 'Factory 86169 shape changed: no webhooks reducer.' );
	}

	const actions = Object.fromEntries( actionExports.keys );
	const selectors = Object.fromEntries( selectorExports.keys );
	const notifications = {
		showNotification: notificationExports.keys.get( 'showNotification' ),
		hideNotification: notificationExports.keys.get( 'hideNotification' ),
	};
	let initialStateName = null;
	units.get( reducerName ).traverse( {
		ConditionalExpression( p ) {
			if (
				! initialStateName &&
				'Identifier' === p.node.alternate.type
			) {
				initialStateName = p.node.alternate.name;
			}
		},
	} );
	if ( ! initialStateName ) {
		throw new Error( 'Factory 86169 shape changed: no initial state.' );
	}

	const roots = [
		reducerName,
		initialStateName,
		...Object.values( actions ),
		...Object.values( selectors ),
		...Object.values( notifications ),
	];

	// Follow scope-resolved references from the roots to the factory-level declarations they need.
	const needed = new Set();
	let accessor = null;
	const queue = [ ...roots ];
	while ( queue.length ) {
		const name = queue.pop();
		if ( needed.has( name ) || name === accessor ) {
			continue;
		}
		const unit = units.get( name );
		if ( ! unit ) {
			throw new Error(
				'Webhooks code needs an undeclared name: ' + name
			);
		}
		const init = unit.isVariableDeclarator() ? unit.node.init : null;
		const initSource = init ? code.slice( init.start, init.end ) : '';
		if ( /^n\.n\(\s*[\w$]+\s*\)$/.test( initSource ) ) {
			// `pe = n.n( me )` wraps wp.apiFetch; it is replaced by the host's function.
			if ( accessor && accessor !== name ) {
				throw new Error(
					'Webhooks code needs a second required module: ' + name
				);
			}
			accessor = name;
			continue;
		}
		if ( /^n\(/.test( initSource ) ) {
			throw new Error(
				'Webhooks code now needs a required module: ' + name
			);
		}
		needed.add( name );
		unit.traverse( {
			Identifier( p ) {
				if ( ! p.isReferencedIdentifier() ) {
					return;
				}
				const binding = p.scope.getBinding( p.node.name );
				if ( binding && binding.scope.block === factory.node ) {
					if ( 'param' === binding.kind ) {
						throw new Error(
							'Webhooks code uses a factory parameter: ' +
								p.node.name
						);
					}
					queue.push( p.node.name );
				}
			},
		} );
	}
	if ( ! accessor ) {
		throw new Error( 'Factory 86169 shape changed: no apiFetch accessor.' );
	}

	const statements = [ ...needed ]
		.map( ( name ) => ( { name, unit: units.get( name ) } ) )
		.sort( ( x, y ) => x.unit.node.start - y.unit.node.start )
		.map( ( { name, unit } ) =>
			unit.isFunctionDeclaration()
				? code.slice( unit.node.start, unit.node.end )
				: `var ${ name }${
						unit.node.init
							? ' = ' +
								code.slice(
									unit.node.init.start,
									unit.node.init.end
								)
							: ''
					};`
		);
	const pick = ( map ) =>
		'{' +
		Object.entries( map )
			.map(
				( [ key, binding ] ) =>
					`${ JSON.stringify( key ) }: ${ binding }`
			)
			.join( ',' ) +
		'}';
	const source = `(function (${ accessor }) {
"use strict";
${ statements.join( '\n' ) }
return {
	reducer: ${ reducerName },
	initialState: ${ initialStateName },
	actions: ${ pick( actions ) },
	selectors: ${ pick( selectors ) },
	notifications: ${ pick( notifications ) }
};
})`;
	return {
		source,
		roots: { reducerName, initialStateName, accessor },
		factoryPath: FACTORY,
	};
}

/**
 * Run the extracted code.
 *
 * @param {Object}   host            Values the sandbox may use.
 * @param {Function} host.apiFetch   The request function behind the recovered `apiFetch` accessor.
 * @param {Function} host.setTimeout Timer function used by the recovered notification thunk.
 * @return {Object} `{ reducer, initialState, actions, selectors, notifications, names }`.
 */
export function loadRecoveredWebhooks( { apiFetch, setTimeout } ) {
	const { source, roots } = extractRecoveredWebhooks();
	const context = vm.createContext( {
		setTimeout,
		URLSearchParams,
		console: { error() {} },
	} );
	const factory = vm.runInContext( source, context, {
		filename: 'recovered-webhooks.vm.js',
	} );
	// `pe()` returns the apiFetch function in the recovered code.
	const loaded = factory( () => apiFetch );
	return { ...loaded, names: roots };
}

/**
 * Copy a value into plain main-realm objects so `deepStrictEqual` can compare values made inside
 * the sandbox (which has its own `Object` and `Array`). `undefined` and `NaN` are preserved.
 *
 * @param {*} value Any value.
 * @return {*} Plain copy.
 */
export function plain( value ) {
	if ( Array.isArray( value ) ) {
		return Array.from( value, ( item ) => plain( item ) );
	}
	if ( value && 'object' === typeof value ) {
		const copy = {};
		for ( const key of Object.keys( value ) ) {
			copy[ key ] = plain( value[ key ] );
		}
		return copy;
	}
	return value;
}
