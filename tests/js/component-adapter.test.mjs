import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { createComponentAdapter } from '../../tools/component-adapter.mjs';

const generate = generatorModule.default || generatorModule;
const row = {
	binding: 'View',
	name: 'Example',
	dependencies: [ 'dependency' ],
};
function adapter( t, rows = [ row ], options = {} ) {
	const directory = fs.mkdtempSync(
		path.join( os.tmpdir(), 'ohmylms-adapter-' )
	);
	t.after( () => fs.rmSync( directory, { recursive: true, force: true } ) );
	const manifest = path.join( directory, 'components.json' );
	fs.writeFileSync( manifest, JSON.stringify( rows ) );
	return createComponentAdapter( {
		manifest,
		namespace: 'exampleComponents',
		label: 'example',
		...options,
	} );
}

test( 'component adapters preserve lazy dependencies and ignore other factory scopes', ( t ) => {
	const ast = parse(
		'({1841:function(){var View=()=>"old";var dependency="ready";return View();},7:function(){var View=()=>"other";return View();}})'
	);
	assert.deepEqual( adapter( t )( ast ), { components: 1 } );
	const context = {
		window: {
			ohmylms: {
				extensions: {
					exampleComponents: {
						Example: ( read ) => () => read().dependency,
					},
				},
			},
		},
	};
	const factories = vm.runInNewContext( generate( ast ).code, context );
	assert.equal( factories[ 1841 ](), 'ready' );
	assert.equal( factories[ 7 ](), 'other' );
} );

test( 'function declarations are adapted only when explicitly enabled', ( t ) => {
	const source = '({1841:function(){function View(){} var dependency=1;}})';
	assert.throws(
		() => adapter( t )( parse( source ) ),
		/Missing example adapters: Example/
	);
	assert.deepEqual(
		adapter( t, [ row ], { declarations: true } )( parse( source ) ),
		{ components: 1 }
	);
} );

test( 'nested functions cannot accidentally satisfy a missing application binding', ( t ) => {
	assert.throws(
		() =>
			adapter( t )(
				parse(
					'({1841:function(){function nested(){var View=()=>1;}}})'
				)
			),
		/Missing example adapters/
	);
} );

test( 'ambiguous component registrations fail instead of silently replacing twice', ( t ) => {
	assert.throws(
		() => adapter( t, [ row, row ] ),
		/Duplicate example component contract/
	);
	assert.throws(
		() =>
			adapter( t )(
				parse( '({1841:function(){var View=()=>1;var View=()=>2;}})' )
			),
		/Duplicate example adapter/
	);
} );

test( 'invalid dependency expressions fail before constructing an adapter', ( t ) => {
	assert.throws(
		() =>
			adapter( t, [
				{ ...row, dependencies: [ 'invalid.expression' ] },
			] ),
		/Invalid example component contract/
	);
} );
