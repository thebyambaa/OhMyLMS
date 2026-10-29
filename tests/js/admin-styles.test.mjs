import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { buildAdminStyles } from '../../tools/build-admin-styles.mjs';
import { createFeatureLoader } from '../../assets/src/extensions/featureLoader.mjs';

const root = path.resolve( import.meta.dirname, '../..' );
test( 'modular CSS preserves every original rule, URL and cascade position, including RTL', () => {
	const destination = fs.mkdtempSync( path.join( os.tmpdir(), 'oml-css-' ) );
	try {
		const modules = buildAdminStyles( root, destination );
		for ( const rtl of [ false, true ] ) {
			const filename = rtl ? 'ohmylms-rtl.css' : 'ohmylms.css';
			const baseline = fs.readFileSync(
				path.join( root, 'assets/src/static/dist/css/admin', filename ),
				'utf8'
			);
			assert.equal(
				fs.readFileSync(
					path.join( destination, 'assets/dist/css/admin', filename ),
					'utf8'
				),
				baseline
			);
			const combined = modules
				.filter(
					( entry ) => entry.layer === 'main' && entry.rtl === rtl
				)
				.map( ( entry ) =>
					fs.readFileSync(
						path.join( destination, entry.path ),
						'utf8'
					)
				)
				.join( '' );
			assert.equal(
				combined,
				baseline.replace( /\/\*# sourceMappingURL=.*?\*\//g, '' )
			);
		}
		const shared = modules
			.filter(
				( entry ) =>
					entry.layer === 'main' &&
					! entry.rtl &&
					entry.group === 'shared'
			)
			.reduce( ( sum, entry ) => sum + entry.bytes, 0 );
		assert.ok(
			shared < 120000,
			`Shared admin CSS grew to ${ shared } bytes`
		);
		assert.deepEqual( buildAdminStyles( root, destination ), modules );
		assert.equal(
			modules
				.filter( ( entry ) => entry.layer === 'ui' )
				.map( ( entry ) =>
					fs.readFileSync(
						path.join( destination, entry.path ),
						'utf8'
					)
				)
				.join( '' ),
			fs.readFileSync(
				path.join( root, 'assets/css/admin-ui.css' ),
				'utf8'
			)
		);
	} finally {
		fs.rmSync( destination, { recursive: true, force: true } );
	}
} );

test( 'a feature waits for its styles before exposing its components', async () => {
	let release;
	let ready = false;
	const original = globalThis.ohmylmsStyles;
	globalThis.ohmylmsStyles = {
		ensure: ( name ) => {
			assert.equal( name, 'courseComponents' );
			return new Promise( ( resolve ) => {
				release = resolve;
			} );
		},
	};
	try {
		const load = createFeatureLoader(
			async () => ( { courseComponents: { Course: 'ready' } } ),
			'courseComponents'
		);
		const result = load().then( ( value ) => {
			ready = true;
			return value;
		} );
		await new Promise( ( resolve ) => setImmediate( resolve ) );
		assert.equal( ready, false );
		release();
		assert.deepEqual( await result, { Course: 'ready' } );
	} finally {
		globalThis.ohmylmsStyles = original;
	}
} );
