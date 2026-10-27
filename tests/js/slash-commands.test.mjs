import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { adaptSlashCommands } from '../../tools/slash-adapters.mjs';
import { createRegistry } from '../../assets/src/extensions/registry.mjs';
import {
	registerBuiltinSlashCommands,
	slashGroups,
} from '../../assets/src/extensions/slashCommands.mjs';
const generate = generatorModule.default || generatorModule;
const factory = ( id ) =>
	fs
		.readFileSync(
			new URL(
				`../../assets/src/modules/dist/admin/ohmylms/0-${ id }.js`,
				import.meta.url
			),
			'utf8'
		)
		.trim()
		.replace( /;$/, '' );

test( 'slash commands registry requires an action, not a render component', () => {
	const registry = createRegistry();
	assert.throws(
		() =>
			registry.registerSlashCommand( 'bad', {
				label: 'Bad',
				render() {},
			} ),
		/action function/
	);
	registry.registerSlashCommand( 'ok', { label: 'Ok', action() {} } );
	assert.equal( registry.list( 'slash-command' ).length, 1 );
} );

test( 'registered commands become one Interactive group with the built-in reveal card', () => {
	const registry = createRegistry();
	assert.deepEqual( slashGroups( registry ), [] );
	registerBuiltinSlashCommands( registry );
	const [ group ] = slashGroups( registry );
	assert.equal( group.title, 'Interactive' );
	const reveal = group.commands.find(
		( command ) => command.name === 'reveal-card'
	);
	assert.ok( reveal.aliases.includes( 'reveal' ) );
	let inserted = '';
	const chain = {
		focus: () => chain,
		insertContent: ( html ) => ( ( inserted = html ), chain ),
		run: () => true,
	};
	reveal.action( { chain: () => chain } );
	assert.match(
		inserted,
		/\[ohmylms_activity type="reveal" prompt="[^"]+" answer="[^"]+"\]/
	);
} );

test( 'the slash menu reads extra groups from the SDK', () => {
	const ast = parse( `({70181:${ factory( 70181 ) }})` );
	assert.deepEqual( adaptSlashCommands( ast ), { slashGroups: 1 } );
	const code = generate( ast ).code;
	assert.match( code, /GROUPS\.concat\(/ );
	assert.match( code, /slashGroups/ );
	assert.doesNotMatch( code, /\bd\.GROUPS,/ );
} );
