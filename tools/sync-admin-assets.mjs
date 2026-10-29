import fs from 'node:fs';
import path from 'node:path';
import { buildAdminStyles } from './build-admin-styles.mjs';

// Ship the same admin app and SDK used during development, including add-on settings.
const root = path.resolve( import.meta.dirname, '..' );
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
buildAdminStyles( root, root );
const manifest = JSON.parse(
	fs.readFileSync( path.join( root, 'assets/src/manifest.json' ), 'utf8' )
);
for ( const suffix of [ '.js', '.js.map', '.asset.php' ] ) {
	const name = `email-packages${ suffix }`;
	fs.copyFileSync(
		path.join( root, 'build/sdk', name ),
		path.join( root, 'assets/dist/admin', name )
	);
}
for ( const packageName of [ 'charts', 'emotion' ] ) {
	for ( const suffix of [ '.js', '.js.map', '.asset.php' ] ) {
		const name = `vendor-${ packageName }${ suffix }`;
		fs.copyFileSync(
			path.join( root, 'build/sdk', name ),
			path.join( root, 'assets/dist/admin', name )
		);
	}
	const noticeName = `vendor-${ packageName }.js.LICENSE.txt`;
	const noticePath = path.join( root, 'build/sdk', noticeName );
	if ( fs.existsSync( noticePath ) )
		fs.copyFileSync(
			noticePath,
			path.join( root, 'assets/dist/admin', noticeName )
		);
	const map = JSON.parse(
		fs.readFileSync(
			path.join( root, 'build/sdk', `vendor-${ packageName }.js.map` ),
			'utf8'
		)
	);
	const packages = new Set(
		map.sources.flatMap( ( source ) => {
			const name = source.split( 'node_modules/' )[ 1 ];
			return name
				? [
						name
							.split( '/' )
							.slice( 0, name.startsWith( '@' ) ? 2 : 1 )
							.join( '/' ),
					]
				: [];
		} )
	);
	const notices = [ ...packages ].sort().map( ( name ) => {
		const directory = path.join( root, 'node_modules', name );
		const metadata = JSON.parse(
			fs.readFileSync( path.join( directory, 'package.json' ), 'utf8' )
		);
		const licenses = fs
			.readdirSync( directory )
			.filter( ( file ) => /^license(?:\.|$)/i.test( file ) );
		if ( ! licenses.length )
			throw new Error( `Missing vendor license: ${ name }` );
		return `${ name } ${ metadata.version } (${ metadata.license })\n${ licenses.map( ( file ) => fs.readFileSync( path.join( directory, file ), 'utf8' ) ).join( '\n' ) }`;
	} );
	fs.writeFileSync(
		path.join(
			root,
			'assets/dist/admin',
			`vendor-${ packageName }.LICENSES.txt`
		),
		notices.join( '\n\n' )
	);
}
for ( const packageName of [ 'chartjs', 'emotion' ] ) {
	for ( const suffix of [ '.js', '.js.map' ] ) {
		const name = packageName + suffix;
		fs.copyFileSync(
			path.join( root, 'build/assets/dist/vendors', name ),
			path.join( root, 'assets/dist/vendors', name )
		);
	}
}
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
	[ 'build/sdk/editor-tiptap.js', 'assets/dist/admin/editor-tiptap.js' ],
	[
		'build/sdk/editor-tiptap.js.LICENSE.txt',
		'assets/dist/admin/editor-tiptap.js.LICENSE.txt',
	],
	[
		'build/sdk/editor-tiptap.js.map',
		'assets/dist/admin/editor-tiptap.js.map',
	],
	[
		'build/sdk/editor-tiptap.asset.php',
		'assets/dist/admin/editor-tiptap.asset.php',
	],
	[ 'build/assets/dist/vendors/editor.js', 'assets/dist/vendors/editor.js' ],
	[
		'build/assets/dist/vendors/editor.js.map',
		'assets/dist/vendors/editor.js.map',
	],
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
		/^[\w-]+\.[a-f0-9]{12}\.js(?:\.map|\.LICENSE\.txt)?$/.test(
			entry.name
		) &&
		! currentChunks.has( entry.name )
	) {
		fs.unlinkSync( path.join( targetChunks, entry.name ) );
	}
}
// Async imports resolve relative to extensions.js in both shipped and opt-in builds.
fs.cpSync( sourceChunks, targetChunks, { recursive: true } );
// Resolve nested npm packages from the actual lazy bundle's source maps.
const emailPackageDirectories = new Set();
for ( const name of fs
	.readdirSync( sourceChunks )
	.filter( ( file ) => file.endsWith( '.js.map' ) ) ) {
	const map = JSON.parse(
		fs.readFileSync( path.join( sourceChunks, name ), 'utf8' )
	);
	if (
		! map.sources.some( ( source ) =>
			/node_modules\/(?:@arco-design\/web-react|mjml-browser)\//.test(
				source
			)
		)
	)
		continue;
	for ( const source of map.sources ) {
		const start = source.indexOf( 'node_modules/' );
		if ( start < 0 ) continue;
		const segments = source.slice( start ).split( '/' );
		const last = segments.lastIndexOf( 'node_modules' );
		const count = segments[ last + 1 ].startsWith( '@' ) ? 2 : 1;
		emailPackageDirectories.add(
			segments.slice( 0, last + 1 + count ).join( '/' )
		);
	}
}
if ( ! emailPackageDirectories.has( 'node_modules/mjml-browser' ) )
	throw new Error( 'Missing lazy npm MJML bundle' );
const emailNotices = [ ...emailPackageDirectories ]
	.sort()
	.map( ( directory ) => {
		const location = path.join( root, directory );
		const metadata = JSON.parse(
			fs.readFileSync( path.join( location, 'package.json' ), 'utf8' )
		);
		const files = fs
			.readdirSync( location )
			.filter( ( file ) => /^licen[cs]e(?:[.\-_]|$)/i.test( file ) );
		const licenses = files.map( ( file ) =>
			fs.readFileSync( path.join( location, file ), 'utf8' )
		);
		if ( ! licenses.length && metadata.name === 'mjml-browser' )
			licenses.push(
				fs.readFileSync(
					path.join( root, 'tools/licenses/mjml-MIT.txt' ),
					'utf8'
				)
			);
		// Upstream number-precision publishes only an MIT declaration in README/package.json.
		if (
			! licenses.length &&
			metadata.name === 'number-precision' &&
			metadata.license === 'MIT'
		) {
			licenses.push(
				`Upstream declares MIT in package.json and README.md. Author: ${ metadata.author }. Source: ${ metadata.homepage }. No standalone license text is distributed upstream.`
			);
		}
		if ( ! licenses.length )
			throw new Error(
				`Missing email package license: ${ metadata.name }`
			);
		return `${ metadata.name } ${ metadata.version } (${ metadata.license })\n${ licenses.join( '\n' ) }`;
	} );
fs.writeFileSync(
	path.join( root, 'assets/dist/admin/email-packages.LICENSES.txt' ),
	emailNotices.join( '\n\n' )
);
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
// Include notices for every installed package present in the editor source map.
const editorMap = JSON.parse(
	fs.readFileSync(
		path.join( root, 'build/sdk/editor-tiptap.js.map' ),
		'utf8'
	)
);
const editorPackages = new Set(
	editorMap.sources.flatMap( ( source ) => {
		const name = source.split( 'node_modules/' )[ 1 ];
		return name
			? [
					name
						.split( '/' )
						.slice( 0, name.startsWith( '@' ) ? 2 : 1 )
						.join( '/' ),
				]
			: [];
	} )
);
const editorNotices = [ ...editorPackages ].sort().map( ( name ) => {
	const directory = path.join( root, 'node_modules', name );
	const metadata = JSON.parse(
		fs.readFileSync( path.join( directory, 'package.json' ), 'utf8' )
	);
	const license = fs
		.readdirSync( directory )
		.find( ( file ) => /^license(?:\.|$)/i.test( file ) );
	const licensePath = license
		? path.join( directory, license )
		: name.startsWith( '@tiptap/' )
			? path.join( root, 'tools/licenses/tiptap-MIT.txt' )
			: null;
	if ( ! licensePath ) throw new Error( `Missing editor license: ${ name }` );
	return `${ name } ${ metadata.version } (${ metadata.license })\n${ fs.readFileSync( licensePath, 'utf8' ) }`;
} );
fs.writeFileSync(
	path.join( root, 'assets/dist/admin/editor-tiptap.LICENSES.txt' ),
	editorNotices.join( '\n\n' )
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
