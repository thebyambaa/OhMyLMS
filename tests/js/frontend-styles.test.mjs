import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { buildFrontendStyles } from '../../tools/build-frontend-styles.mjs';

const root = path.resolve( import.meta.dirname, '../..' );

test( 'frontend page modules have portable maps, deterministic versions and no competing button skin', () => {
	const destination = fs.mkdtempSync(
		path.join( os.tmpdir(), 'oml-frontend-' )
	);
	try {
		const modules = buildFrontendStyles( root, destination );
		assert.deepEqual(
			modules.map( ( entry ) => entry.name ),
			[
				'core',
				'catalog',
				'course',
				'lesson',
				'checkout',
				'thankyou',
				'account',
				'membership',
				'rtl',
			]
		);
		for ( const entry of modules ) {
			const file = path.join( destination, entry.path );
			const css = fs.readFileSync( file, 'utf8' );
			assert.equal( Buffer.byteLength( css ), entry.bytes );
			assert.doesNotMatch( css, /fonts\.googleapis/ );
			const map = JSON.parse( fs.readFileSync( file + '.map', 'utf8' ) );
			assert.ok( map.sources.length );
			assert.ok(
				map.sources.every( ( source ) =>
					source.startsWith( 'ohmylms-source:///' )
				)
			);
			assert.equal( map.sources.length, map.sourcesContent.length );
		}
		const core = modules.find( ( entry ) => entry.name === 'core' );
		for ( const route of modules.filter(
			( entry ) => ! [ 'core', 'rtl' ].includes( entry.name )
		) ) {
			assert.ok(
				core.bytes + route.bytes < 450000,
				`${ route.name } loads too much CSS`
			);
		}
		assert.deepEqual( buildFrontendStyles( root, destination ), modules );
	} finally {
		fs.rmSync( destination, { recursive: true, force: true } );
	}
} );
