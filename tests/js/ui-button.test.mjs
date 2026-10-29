import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { buttonClass } from '../../assets/src/ui/button.mjs';

const cases = [
	{},
	{ variant: 'primary' },
	{ variant: 'danger', size: 'lg', block: true },
	{ variant: 'tertiary', size: 'sm', className: 'extra' },
	{ variant: 'bogus', size: 'huge' },
];

test( 'button classes follow the documented variants and sizes', () => {
	assert.equal( buttonClass(), 'oml-btn' );
	assert.equal( buttonClass( { variant: 'primary' } ), 'oml-btn oml-btn-primary' );
	assert.equal(
		buttonClass( { variant: 'danger', size: 'lg', block: true } ),
		'oml-btn oml-btn-danger oml-btn-lg oml-btn-block'
	);
	assert.equal( buttonClass( { variant: 'bogus', size: 'huge' } ), 'oml-btn' );
} );

test( 'every class the button emits is styled in oml-ui.css', () => {
	const css = fs.readFileSync( new URL( '../../assets/css/oml-ui.css', import.meta.url ), 'utf8' );
	for ( const args of cases ) {
		for ( const name of buttonClass( args ).split( ' ' ).filter( ( c ) => c.startsWith( 'oml-' ) ) ) {
			assert.ok( css.includes( '.' + name ), name + ' has a rule' );
		}
	}
} );

test( 'PHP and React build identical button classes', ( t ) => {
	const php = process.env.OHMYLMS_PHP || 'php';
	if ( spawnSync( php, [ '-v' ] ).error ) {
		t.skip( 'php is not on PATH; set OHMYLMS_PHP to run the parity check' );
		return;
	}
	const out = execFileSync(
		php,
		[ fileURLToPath( new URL( '../php/ui-button-classes.php', import.meta.url ) ), JSON.stringify( cases ) ],
		{ encoding: 'utf8' }
	);
	assert.deepEqual( JSON.parse( out ), cases.map( buttonClass ) );
} );

test( 'legacy learner button rules stay scoped and token-only', () => {
	const css = fs.readFileSync( new URL( '../../assets/css/oml-ui-legacy.css', import.meta.url ), 'utf8' );
	const rules = css.replace( /\/\*[\s\S]*?\*\//g, '' ).split( '}' ).filter( ( r ) => r.trim() );
	assert.ok( rules.length >= 6 );
	for ( const rule of rules ) {
		const [ selectors, body ] = rule.split( '{' );
		for ( const selector of selectors.split( ',' ) ) {
			assert.match( selector.trim(), /^\.ohmylms-page \.ohmylms-button/ );
		}
		assert.doesNotMatch( body, /#[0-9a-f]{3,6}\b|rgba?\(/i );
	}
} );
