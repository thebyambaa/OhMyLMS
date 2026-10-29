import fs from 'node:fs';
import path from 'node:path';
import { buildAdminStyles } from './build-admin-styles.mjs';

// Ship the same admin app and SDK used during development, including add-on settings.
const root = path.resolve( import.meta.dirname, '..' );
buildAdminStyles( root, root );
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
for ( const [ source, target ] of [
	[ 'build/sdk/editor-lowlight.js', 'assets/dist/admin/editor-lowlight.js' ],
	[
		'build/sdk/editor-lowlight.js.map',
		'assets/dist/admin/editor-lowlight.js.map',
	],
	[
		'build/sdk/editor-lowlight.asset.php',
		'assets/dist/admin/editor-lowlight.asset.php',
	],
	[
		'build/assets/dist/vendors/vendors.js',
		'assets/dist/vendors/vendors.js',
	],
	[
		'build/assets/dist/vendors/vendors.js.map',
		'assets/dist/vendors/vendors.js.map',
	],
	[ 'build/assets/dist/chunks/355.js', 'assets/dist/chunks/355.js' ],
	[ 'build/assets/dist/chunks/355.js.map', 'assets/dist/chunks/355.js.map' ],
	[
		'build/sdk/course-structure.js',
		'assets/dist/admin/course-structure.js',
	],
	[
		'build/sdk/course-structure.js.map',
		'assets/dist/admin/course-structure.js.map',
	],
	[
		'build/sdk/course-structure.asset.php',
		'assets/dist/admin/course-structure.asset.php',
	],
	[
		'build/sdk/question-player-block.js',
		'assets/dist/admin/question-player-block.js',
	],
	[
		'build/sdk/question-player-block.js.map',
		'assets/dist/admin/question-player-block.js.map',
	],
	[
		'build/sdk/question-player-block.asset.php',
		'assets/dist/admin/question-player-block.asset.php',
	],
	[ 'build/assets/js/blocks.js', 'assets/js/blocks.js' ],
	[ 'build/assets/js/blocks.js.map', 'assets/js/blocks.js.map' ],
	[
		'build/sdk/quiz-player-block.js',
		'assets/dist/admin/quiz-player-block.js',
	],
	[
		'build/sdk/quiz-player-block.js.map',
		'assets/dist/admin/quiz-player-block.js.map',
	],
	[
		'build/sdk/quiz-player-block.asset.php',
		'assets/dist/admin/quiz-player-block.asset.php',
	],
	[
		'build/sdk/interactive-activity-block.js',
		'assets/dist/admin/interactive-activity-block.js',
	],
	[
		'build/sdk/interactive-activity-block.js.map',
		'assets/dist/admin/interactive-activity-block.js.map',
	],
	[
		'build/sdk/interactive-activity-block.asset.php',
		'assets/dist/admin/interactive-activity-block.asset.php',
	],
	[
		'build/sdk/interactive-activity-view.js',
		'assets/dist/admin/interactive-activity-view.js',
	],
	[
		'build/sdk/interactive-activity-view.js.map',
		'assets/dist/admin/interactive-activity-view.js.map',
	],
	[
		'build/sdk/interactive-activity-view.asset.php',
		'assets/dist/admin/interactive-activity-view.asset.php',
	],
	[
		'build/sdk/native-lesson-editor.js',
		'assets/dist/admin/native-lesson-editor.js',
	],
	[
		'build/sdk/native-lesson-editor.js.map',
		'assets/dist/admin/native-lesson-editor.js.map',
	],
	[
		'build/sdk/native-lesson-editor.asset.php',
		'assets/dist/admin/native-lesson-editor.asset.php',
	],
	[ 'build/sdk/paper-print.js', 'assets/dist/admin/paper-print.js' ],
	[ 'build/sdk/paper-print.js.map', 'assets/dist/admin/paper-print.js.map' ],
	[
		'build/sdk/paper-print.asset.php',
		'assets/dist/admin/paper-print.asset.php',
	],
	[ 'build/sdk/paper-student.js', 'assets/dist/admin/paper-student.js' ],
	[
		'build/sdk/paper-student.js.map',
		'assets/dist/admin/paper-student.js.map',
	],
	[
		'build/sdk/paper-student.asset.php',
		'assets/dist/admin/paper-student.asset.php',
	],
	[ 'build/sdk/math-runtime.js', 'assets/dist/admin/math-runtime.js' ],
	[
		'build/sdk/math-runtime.js.map',
		'assets/dist/admin/math-runtime.js.map',
	],
	[ 'build/assets/dist/admin/ohmylms.js', 'assets/dist/admin/ohmylms.js' ],
	[
		'build/assets/dist/admin/ohmylms.js.map',
		'assets/dist/admin/ohmylms.js.map',
	],
	[ 'build/sdk/extensions.js', 'assets/dist/admin/extensions.js' ],
	[ 'build/sdk/extensions.js.map', 'assets/dist/admin/extensions.js.map' ],
	[
		'build/sdk/extensions.asset.php',
		'assets/dist/admin/extensions.asset.php',
	],
] ) {
	fs.copyFileSync( path.join( root, source ), path.join( root, target ) );
}
// Remove outdated hashed SDK chunks before syncing the current build.
const sourceChunks = path.join( root, 'build/sdk/chunks' );
const targetChunks = path.join( root, 'assets/dist/admin/chunks' );
const currentChunks = new Set( fs.readdirSync( sourceChunks ) );
for ( const entry of fs.readdirSync( targetChunks, { withFileTypes: true } ) ) {
	if (
		entry.isFile() &&
		/^[\w-]+\.[a-f0-9]{12}\.js(?:\.map)?$/.test( entry.name ) &&
		! currentChunks.has( entry.name )
	) {
		fs.unlinkSync( path.join( targetChunks, entry.name ) );
	}
}
// Async imports resolve relative to extensions.js in both shipped and opt-in builds.
fs.cpSync( sourceChunks, targetChunks, { recursive: true } );
console.log( 'Updated shipped admin app and extension SDK.' );
const syntaxNotices = [
	[ 'lowlight', 'license' ],
	[ 'highlight.js', 'LICENSE' ],
	[ 'devlop', 'license' ],
]
	.map( ( [ name, license ] ) => {
		const directory = path.join( root, 'node_modules', name );
		const metadata = JSON.parse(
			fs.readFileSync( path.join( directory, 'package.json' ), 'utf8' )
		);
		return `${ name } ${ metadata.version } (${ metadata.license })\n${ fs.readFileSync( path.join( directory, license ), 'utf8' ) }`;
	} )
	.join( '\n\n' );
fs.writeFileSync(
	path.join( root, 'assets/dist/admin/editor-lowlight.LICENSES.txt' ),
	syntaxNotices
);
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
fs.copyFileSync(
	path.join( root, 'node_modules/@cortex-js/compute-engine/LICENSE' ),
	path.join( root, 'assets/dist/mathlive/COMPUTE-ENGINE-LICENSE.txt' )
);
fs.mkdirSync( path.join( root, 'assets/dist/jsxgraph' ), { recursive: true } );
fs.copyFileSync(
	path.join( root, 'node_modules/jsxgraph/distrib/jsxgraph.css' ),
	path.join( root, 'assets/dist/jsxgraph/jsxgraph.css' )
);
fs.copyFileSync(
	path.join( root, 'node_modules/jsxgraph/distrib/LICENSE.MIT' ),
	path.join( root, 'assets/dist/jsxgraph/LICENSE.MIT' )
);
