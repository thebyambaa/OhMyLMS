import fs from 'node:fs';
import path from 'node:path';
import { syncAdminApp } from './sync-admin-app.mjs';

// Ship the same admin app, blocks, native editors and front-end styles used during development.
const root = path.resolve( import.meta.dirname, '..' );
syncAdminApp( root );
fs.cpSync(
	path.join( root, 'build/assets/css/frontend' ),
	path.join( root, 'assets/css/frontend' ),
	{ recursive: true }
);
for ( const name of [
	'style.css',
	'style.css.map',
	'style-rtl.css',
	'style-rtl.css.map',
] ) {
	fs.copyFileSync(
		path.join( root, 'build/assets/css', name ),
		path.join( root, 'assets/css', name )
	);
}
const manifest = JSON.parse(
	fs.readFileSync( path.join( root, 'assets/src/manifest.json' ), 'utf8' )
);
for ( const retired of manifest.retiredAssets || [] ) {
	for ( const suffix of [ '', '.map' ] ) {
		const target = path.resolve( root, retired + suffix );
		if ( ! target.startsWith( root + path.sep ) ) {
			throw new Error(
				`Retired asset outside plugin directory: ${ retired }`
			);
		}
		if ( fs.existsSync( target ) ) {
			fs.unlinkSync( target );
		}
	}
}
fs.copyFileSync(
	path.join( root, 'build/assets/js/blocks.js' ),
	path.join( root, 'assets/js/blocks.js' )
);
fs.copyFileSync(
	path.join( root, 'build/assets/js/blocks.js.map' ),
	path.join( root, 'assets/js/blocks.js.map' )
);
for ( const entry of [
	'question-player-block',
	'quiz-player-block',
	'interactive-activity-block',
	'interactive-activity-view',
	'native-lesson-editor',
	'paper-print',
	'paper-student',
	'math-runtime',
	'schools',
] ) {
	for ( const suffix of [ '.js', '.js.map', '.asset.php' ] ) {
		// math-runtime is loaded by URL, so its dependency file is not shipped.
		if ( entry === 'math-runtime' && suffix === '.asset.php' ) continue;
		fs.copyFileSync(
			path.join( root, 'build/sdk', entry + suffix ),
			path.join( root, 'assets/dist/admin', entry + suffix )
		);
	}
}
// Remove outdated hashed chunks before syncing the current build.
const sourceChunks = path.join( root, 'build/sdk/chunks' );
const targetChunks = path.join( root, 'assets/dist/admin/chunks' );
const currentChunks = new Set( fs.readdirSync( sourceChunks ) );
for ( const entry of fs.readdirSync( targetChunks, { withFileTypes: true } ) ) {
	if (
		entry.isFile() &&
		/^[\w-]+\.[a-f0-9]{12}\.js(?:\.map|\.LICENSE\.txt)?$/.test(
			entry.name
		) &&
		! currentChunks.has( entry.name )
	) {
		fs.unlinkSync( path.join( targetChunks, entry.name ) );
	}
}
// Async imports resolve relative to each entry in both shipped and source builds.
fs.cpSync( sourceChunks, targetChunks, { recursive: true } );
console.log( 'Updated shipped admin app, blocks and native editors.' );
fs.mkdirSync( path.join( root, 'assets/dist/mathlive' ), { recursive: true } );
fs.cpSync(
	path.join( root, 'node_modules/mathlive/fonts' ),
	path.join( root, 'assets/dist/mathlive/fonts' ),
	{ recursive: true }
);
fs.copyFileSync(
	path.join( root, 'node_modules/mathlive/LICENSE.txt' ),
	path.join( root, 'assets/dist/mathlive/LICENSE.txt' )
);
fs.copyFileSync(
	path.join( root, 'node_modules/@cortex-js/compute-engine/LICENSE' ),
	path.join( root, 'assets/dist/mathlive/COMPUTE-ENGINE-LICENSE.txt' )
);
fs.mkdirSync( path.join( root, 'assets/dist/jsxgraph' ), { recursive: true } );
for ( const file of [
	'jsxgraphcore.js',
	'jsxgraph.css',
	'LICENSE.MIT',
	'LICENSE.LGPL',
] ) {
	fs.copyFileSync(
		path.join( root, 'node_modules/jsxgraph/distrib', file ),
		path.join( root, 'assets/dist/jsxgraph', file )
	);
}
