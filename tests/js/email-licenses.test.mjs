import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
	emailNotices,
	packageDirectories,
} from '../../tools/email-licenses.mjs';

const root = path.resolve( import.meta.dirname, '../..' );
const bootstrap = {
	sources: [
		'webpack://x/./node_modules/easy-email-core/lib/index.es.js',
		'webpack://x/./node_modules/js-beautify/js/lib/beautify-html.js',
		'webpack://x/./node_modules/react-dom/cjs/react-dom-server.browser.production.min.js',
		'webpack://x/./node_modules/uuid/dist/esm-browser/v4.js',
		'webpack://x/./assets/src/shared/easy-email/core.mjs',
	],
};
const lazy = {
	sources: [
		'webpack://x/./node_modules/@arco-design/web-react/es/index.js',
		'webpack://x/./node_modules/mjml-browser/lib/index.js',
	],
};

test( 'the notice covers the eager Easy Email packages and the lazy chunks', () => {
	const directories = packageDirectories( [ bootstrap, lazy ] );
	for ( const name of [
		'easy-email-core',
		'he',
		'js-beautify',
		'react-dom',
		'uuid',
		'@arco-design/web-react',
		'mjml-browser',
	] )
		assert.ok( directories.has( `node_modules/${ name }` ), name );
	const notices = emailNotices( root, [ bootstrap, lazy ] );
	assert.match( notices, /easy-email-core 4\.17\.1 \(MIT\)/ );
	assert.match( notices, /js-beautify 1\.15\.4 \(MIT\)/ );
	assert.match( notices, /he 1\.2\.0 \(MIT\)/ );
	assert.match( notices, /mjml-browser 4\.16\.1/ );
} );

test( 'a bundled package without a licence text fails the build', () => {
	const temporary = fs.mkdtempSync(
		path.join( os.tmpdir(), 'ohmylms-lic-' )
	);
	try {
		const directory = path.join( temporary, 'node_modules/unlicensed' );
		fs.mkdirSync( directory, { recursive: true } );
		fs.writeFileSync(
			path.join( directory, 'package.json' ),
			'{"name":"unlicensed","version":"1.0.0","license":"MIT"}'
		);
		assert.throws(
			() =>
				emailNotices( temporary, [
					{
						sources: [
							'webpack://x/./node_modules/unlicensed/index.js',
						],
					},
				] ),
			/Missing email package license: unlicensed/
		);
	} finally {
		fs.rmSync( temporary, { recursive: true, force: true } );
	}
} );
