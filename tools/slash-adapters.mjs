/** Adds registered slash commands to the lesson editor's "/" menu (module 70181). */
import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';
import * as t from '@babel/types';
const traverse = traverseModule.default || traverseModule;
const GROUPS_FACTORY = '66427';
const SLASH_FACTORY = '70181';
export function adaptSlashCommands( ast ) {
	let hits = 0;
	traverse( ast, {
		ObjectProperty( p ) {
			if (
				String( p.node.key.value ?? p.node.key.name ) !== SLASH_FACTORY
			)
				return;
			// The menu's command groups module is bound to a local, e.g. `d = n(66427)`.
			let groups;
			p.traverse( {
				VariableDeclarator( q ) {
					const call = q.node.init;
					if (
						t.isCallExpression( call ) &&
						call.arguments[ 0 ]?.value === Number( GROUPS_FACTORY )
					)
						groups = q.node.id.name;
				},
			} );
			if ( ! groups ) return;
			p.traverse( {
				MemberExpression( q ) {
					if (
						! t.isIdentifier( q.node.object, { name: groups } ) ||
						q.node.property.name !== 'GROUPS'
					)
						return;
					q.replaceWith(
						parseExpression(
							`${ groups }.GROUPS.concat((window.ohmylms&&window.ohmylms.extensions&&window.ohmylms.extensions.slashGroups&&window.ohmylms.extensions.slashGroups())||[])`
						)
					);
					hits++;
					q.skip();
				},
			} );
			p.skip();
		},
	} );
	if ( hits !== 1 )
		throw new Error(
			'Slash command adapter expected one GROUPS read; found ' + hits
		);
	return { slashGroups: hits };
}
