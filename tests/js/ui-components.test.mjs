import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
	cardClass,
	badgeClass,
	progressClass,
	clampPercent,
} from '../../assets/src/ui/components.mjs';
import { inputClass, selectClass } from '../../assets/src/ui/controls.mjs';

const css = fs.readFileSync(
	new URL( '../../assets/css/oml-ui.css', import.meta.url ),
	'utf8'
);
const cases = {
	card: [ {}, { flat: true }, { interactive: true, className: 'extra' } ],
	badge: [
		{},
		{ tone: 'success' },
		{ tone: 'danger', className: 'extra' },
		{ tone: 'bogus' },
	],
	progress: [
		{},
		{ tone: 'success' },
		{ tone: 'bogus', className: 'extra' },
	],
	input: [
		{},
		{ size: 'sm' },
		{ size: 'lg', className: 'extra' },
		{ invalid: true },
		{ error: 'Required' },
		{ size: 'bogus' },
	],
	select: [
		{},
		{ size: 'sm' },
		{ size: 'lg' },
		{ invalid: true },
		{ error: 'Choose one', className: 'extra' },
		{ size: 'bogus' },
	],
};
const builders = {
	card: cardClass,
	badge: badgeClass,
	progress: progressClass,
	input: inputClass,
	select: selectClass,
};

test( 'component classes follow the documented modifiers', () => {
	assert.equal( cardClass(), 'oml-card' );
	assert.equal(
		cardClass( { flat: true, interactive: true } ),
		'oml-card oml-card-flat oml-card-interactive'
	);
	assert.equal( badgeClass(), 'oml-badge' );
	assert.equal(
		badgeClass( { tone: 'warning' } ),
		'oml-badge oml-badge-warning'
	);
	assert.equal( badgeClass( { tone: 'bogus' } ), 'oml-badge' );
	assert.equal(
		progressClass( { tone: 'success' } ),
		'oml-progress oml-progress-success'
	);
} );

test( 'progress values are clamped to whole percentages', () => {
	assert.deepEqual(
		[ -5, 0, 49.6, 100, 140, 'x', null ].map( clampPercent ),
		[ 0, 0, 50, 100, 100, 0, 0 ]
	);
} );

test( 'every class a component emits is styled in oml-ui.css', () => {
	for ( const [ name, list ] of Object.entries( cases ) ) {
		for ( const args of list ) {
			for ( const cls of builders[ name ]( args )
				.split( ' ' )
				.filter( ( c ) => c.startsWith( 'oml-' ) ) ) {
				assert.ok( css.includes( '.' + cls ), cls + ' has a rule' );
			}
		}
	}
	assert.ok(
		css.includes( '.oml-progress-bar' ) && css.includes( '.oml-card-title' )
	);
} );

test( 'PHP and React build identical component classes', ( t ) => {
	const php = process.env.OHMYLMS_PHP || 'php';
	if ( spawnSync( php, [ '-v' ] ).error ) {
		t.skip( 'php is not on PATH; set OHMYLMS_PHP to run the parity check' );
		return;
	}
	const out = execFileSync(
		php,
		[
			fileURLToPath(
				new URL( '../php/ui-component-classes.php', import.meta.url )
			),
			JSON.stringify( cases ),
		],
		{ encoding: 'utf8' }
	);
	const expected = Object.fromEntries(
		Object.entries( cases ).map( ( [ name, list ] ) => [
			name,
			list.map( builders[ name ] ),
		] )
	);
	assert.deepEqual( JSON.parse( out ), expected );
} );
