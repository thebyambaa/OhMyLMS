import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import {
	adaptConnectionStatus,
	adaptVendors,
} from '../../tools/vendor-adapters.mjs';
const generate = generatorModule.default || generatorModule;
const factory = ( folder, id ) =>
	fs
		.readFileSync(
			new URL(
				`../../assets/src/modules/dist/${ folder }/0-${ id }.js`,
				import.meta.url
			),
			'utf8'
		)
		.trim()
		.replace( /;$/, '' );

test( 'connection labels preserve behavior without importing collaboration or Yjs', () => {
	const ast = parse( `({45644:${ factory( 'admin/ohmylms', 45644 ) }})` );
	adaptConnectionStatus( ast );
	const modules = vm.runInNewContext( generate( ast ).code );
	const exports = {};
	modules[ 45644 ]( {}, exports, () => {
		throw new Error( 'Unexpected provider import' );
	} );
	for ( const [ value, label ] of [
		[ 'connected', 'Connected' ],
		[ 'connecting', 'Connecting...' ],
		[ 'disconnected', 'Disconnected' ],
		[ 'unknown', 'Connecting...' ],
	] ) {
		assert.equal( exports.getConnectionText( value ), label );
	}
} );

test( 'shortcuts share the native WordPress store and hooks', () => {
	const ast = parse(
		`({${ [ 42845, 55578, 87240, 58903, 38262 ].map( ( id ) => `${ id }:${ factory( 'vendors/vendors', id ) }` ).join( ',' ) }})`
	);
	adaptVendors( ast );
	const shortcuts = {
		store: { name: 'core/keyboard-shortcuts' },
		ShortcutProvider() {},
		useShortcut() {},
	};
	const modules = vm.runInNewContext( generate( ast ).code, {
		window: { wp: { keyboardShortcuts: shortcuts } },
	} );
	const exports = {};
	modules[ 55578 ]( {}, exports, {
		d( target, getters ) {
			for ( const [ name, get ] of Object.entries( getters ) )
				Object.defineProperty( target, name, { get } );
		},
	} );
	assert.equal( exports.M_, shortcuts.store );
	assert.equal( exports.Ee, shortcuts.ShortcutProvider );
	assert.equal( exports.wk, shortcuts.useShortcut );
	const preferencesCode = generate(
		ast.program.body[ 0 ].expression.properties.find(
			( p ) => p.key.value === 87240
		).value
	).code;
	assert.match( preferencesCode, /window\.wp\.preferences\.store/ );
	assert.doesNotMatch( preferencesCode, /register\)\(x\)/ );
} );

test( 'each ProseMirror package resolves to a single CJS copy under the original export names', () => {
	const pairs = [
		[ 42845, 37820 ],
		[ 58903, 77712 ],
		[ 38262, 36553 ],
	];
	const ast = parse(
		`({${ [ 42845, 55578, 87240, 58903, 38262 ].map( ( id ) => `${ id }:${ factory( 'vendors/vendors', id ) }` ).join( ',' ) }})`
	);
	adaptVendors( ast );
	const modules = vm.runInNewContext( generate( ast ).code, {
		window: { wp: { keyboardShortcuts: {}, preferences: {} } },
	} );
	for ( const [ esmId, cjsId ] of pairs ) {
		// Every name the ESM consumers import must still be exported.
		const original = factory( 'vendors/vendors', esmId ).match(
			/n\.d\(t, \{([^}]*)\}\)/
		)[ 1 ];
		const names = [ ...original.matchAll( /^\s*([\w$]+):/gm ) ].map(
			( m ) => m[ 1 ]
		);
		assert.ok( names.length > 5 );
		// A Proxy hands out one unique sentinel per CJS export, so identity proves no second copy is used.
		const cjs = new Proxy( {}, { get: ( o, key ) => ( o[ key ] ??= {} ) } );
		const required = [];
		const exports = {};
		const req = ( id ) => {
			required.push( id );
			return cjs;
		};
		req.d = ( target, getters ) => {
			for ( const [ name, get ] of Object.entries( getters ) )
				Object.defineProperty( target, name, {
					get,
					enumerable: true,
				} );
		};
		modules[ esmId ]( {}, exports, req );
		assert.deepEqual( required, [ cjsId ] );
		assert.deepEqual( Object.keys( exports ).sort(), names.sort() );
		for ( const name of names ) assert.ok( exports[ name ] );
	}
} );
