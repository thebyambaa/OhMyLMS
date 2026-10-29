import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import postcss from 'postcss';
import selectorParser from 'postcss-selector-parser';
import { buildAdminStyles } from '../../tools/build-admin-styles.mjs';
import { createFeatureLoader } from '../../assets/src/extensions/featureLoader.mjs';

const root = path.resolve( import.meta.dirname, '../..' );
test( 'source builds include the curriculum stylesheet imports beside their modules', () => {
	const destination = fs.mkdtempSync(
		path.join( os.tmpdir(), 'oml-css-imports-' )
	);
	try {
		buildAdminStyles( root, destination );
		for ( const name of [
			'syllabus-spreadsheet.css',
			'syllabus-mindmap.css',
		] ) {
			assert.equal(
				fs.readFileSync(
					path.join( destination, 'assets/css', name ),
					'utf8'
				),
				fs.readFileSync( path.join( root, 'assets/css', name ), 'utf8' )
			);
		}
	} finally {
		fs.rmSync( destination, { recursive: true, force: true } );
	}
} );
test( 'admin modules retain layout coverage and vendor bytes while replacing the duplicate WordPress stylesheet', () => {
	const destination = fs.mkdtempSync( path.join( os.tmpdir(), 'oml-css-' ) );
	try {
		const modules = buildAdminStyles( root, destination );
		const manifest = JSON.parse(
			fs.readFileSync(
				path.join( root, 'assets/src/admin-styles/manifest.json' ),
				'utf8'
			)
		);
		assert.equal(
			modules.some( ( entry ) => entry.group === 'block-editor' ),
			false
		);
		assert.ok(
			fs
				.readFileSync(
					path.join( root, 'includes/Assets/AdminAssets.php' ),
					'utf8'
				)
				.includes( "array( 'wp-block-editor' )" )
		);
		assert.deepEqual(
			modules.map( ( entry ) => entry.group ),
			manifest.map( ( entry ) => entry.group )
		);
		const selectors = ( css ) => {
			const result = new Set();
			postcss.parse( css ).walkRules( ( rule ) => {
				if (
					rule.parent.type === 'atrule' &&
					/keyframes$/.test( rule.parent.name )
				)
					return;
				selectorParser( ( ast ) =>
					ast.each( ( selector ) =>
						result.add(
							selector
								.toString()
								.replace( /\s+/g, '' )
								.replaceAll( '"', '' )
								.replaceAll( "'", '' )
								.replace( /:{1,2}(before|after)\b/g, '::$1' )
						)
					)
				).processSync( rule.selector );
			} );
			return result;
		};
		// Layout/public markup coverage is retained; component skins and obsolete global aliases are deliberately replaced.
		for ( const rtl of [ false, true ] ) {
			const name = rtl ? 'ohmylms-rtl.css' : 'ohmylms.css';
			let original = fs.readFileSync(
				path.join( root, 'assets/src/static/dist/css/admin', name ),
				'utf8'
			);
			if ( ! rtl )
				original = original.replace(
					fs.readFileSync(
						path.join(
							root,
							'assets/src/admin-styles/main-085-block-editor.css'
						),
						'utf8'
					),
					''
				);
			const output = fs.readFileSync(
				path.join( destination, 'assets/dist/css/admin', name ),
				'utf8'
			);
			const current = selectors( output );
			for ( const selector of selectors( original ) ) {
				if (
					selector === ':root' ||
					selector === 'body' ||
					( ! rtl &&
						selector.startsWith( '.toplevel_page_ohmylms' ) &&
						selector.includes( '.components-button' ) )
				)
					continue;
				assert.ok(
					current.has( selector ),
					'Missing layout selector: ' + selector
				);
			}
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
				output.replace( /\/\*# sourceMappingURL=.*?\*\//g, '' )
			);
		}
		for ( const entry of modules.filter( ( entry ) =>
			entry.source.startsWith( 'assets/src/admin-styles/' )
		) ) {
			assert.equal(
				fs.readFileSync( path.join( destination, entry.path ), 'utf8' ),
				fs
					.readFileSync( path.join( root, entry.source ), 'utf8' )
					.replace( /\/\*# sourceMappingURL=.*?\*\//g, '' )
			);
		}
		assert.deepEqual( buildAdminStyles( root, destination ), modules );
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
