import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {
	corpusPath,
	loadRecoveredCore,
	modes,
	render,
} from '../../tools/easy-email-corpus.mjs';

const corpus = JSON.parse( fs.readFileSync( corpusPath, 'utf8' ) );
const core = loadRecoveredCore();

test( 'the corpus covers every block type and records both render modes', () => {
	const expected = [
		...Object.values( core.BasicType ),
		...Object.values( core.AdvancedType ),
	].filter( ( type ) => ! [ 'page', 'template' ].includes( type ) );
	const covered = new Set( corpus.map( ( entry ) => entry.name ) );
	for ( const type of expected ) {
		assert.ok( covered.has( `block: ${ type }` ), `missing ${ type }` );
	}
	for ( const entry of corpus ) {
		assert.deepEqual( Object.keys( entry.output ), modes, entry.name );
		for ( const mode of modes ) {
			assert.match( entry.output[ mode ], /<mjml>/, entry.name );
		}
	}
} );

test( 'the recovered core still renders every recorded template identically', () => {
	for ( const entry of corpus ) {
		for ( const mode of modes ) {
			assert.equal(
				render( core, entry.data, mode ),
				entry.output[ mode ],
				`${ entry.name } [${ mode }]`
			);
		}
	}
} );

test( 'rendering does not modify the saved template data', () => {
	const entry = corpus.at( -1 );
	const before = JSON.stringify( entry.data );
	render( core, entry.data, 'production' );
	assert.equal( JSON.stringify( entry.data ), before );
} );
