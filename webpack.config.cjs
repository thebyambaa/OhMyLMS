const path = require( 'node:path' );
const fs = require( 'node:fs' );
const pmNames = [
	'state',
	'model',
	'view',
	'transform',
	'commands',
	'keymap',
	'schema-list',
	'tables',
	'history',
	'gapcursor',
	'dropcursor',
	'inputrules',
	'changeset',
	'collab',
	'menu',
	'schema-basic',
	'markdown',
];
const pmAliases = Object.fromEntries(
	pmNames.map( ( name ) => {
		const id = 'prosemirror-' + name;
		const target = path.join(
			path.dirname( require.resolve( id ) ),
			'index.js'
		);
		if ( ! fs.existsSync( target ) )
			throw new Error( 'Missing shared ESM runtime ' + id );
		return [ id.concat( '$' ), target ];
	} )
);
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
module.exports = {
	entry: {
		'email-packages': path.resolve(
			__dirname,
			'assets/src/features/vendor-packages/email-loader.js'
		),
		'vendor-charts': path.resolve(
			__dirname,
			'assets/src/features/vendor-packages/charts.js'
		),
		'vendor-emotion': path.resolve(
			__dirname,
			'assets/src/features/vendor-packages/emotion.js'
		),
		'editor-tiptap': path.resolve(
			__dirname,
			'assets/src/features/editor-packages/tiptap.js'
		),
		'editor-lowlight': path.resolve(
			__dirname,
			'assets/src/features/editor-packages/lowlight.js'
		),
		'course-structure': path.resolve(
			__dirname,
			'assets/src/features/courses/structureClient.js'
		),
		extensions: path.resolve(
			__dirname,
			'assets/src/extensions/index.jsx'
		),
		schools: path.resolve(
			__dirname,
			'assets/src/features/schools/index.jsx'
		),
		'math-runtime': path.resolve(
			__dirname,
			'assets/src/features/math/runtime.mjs'
		),
		'native-lesson-editor': path.resolve(
			__dirname,
			'assets/src/features/learning/NativeLessonSettings.jsx'
		),
		'paper-print': path.resolve(
			__dirname,
			'assets/src/features/paper/print.jsx'
		),
		'paper-student': path.resolve(
			__dirname,
			'assets/src/features/paper/student.jsx'
		),
		'quiz-player-block': path.resolve(
			__dirname,
			'assets/src/blocks/quiz-player/index.jsx'
		),
		'question-player-block': path.resolve(
			__dirname,
			'assets/src/blocks/question-player/index.jsx'
		),
		'interactive-activity-block': path.resolve(
			__dirname,
			'assets/src/blocks/interactive-activity/index.jsx'
		),
		'interactive-activity-view': path.resolve(
			__dirname,
			'assets/src/blocks/interactive-activity/view.mjs'
		),
	},
	output: {
		path: path.resolve( __dirname, 'build/sdk' ),
		filename: '[name].js',
		chunkFilename: 'chunks/[name].[contenthash:12].js',
		publicPath: 'auto',
		uniqueName: 'ohmylmsExtensionSdk',
		clean: true,
	},
	devtool: 'source-map',
	externals: [
		( { context, request }, callback ) => {
			if (
				request === 'highlight.js/lib/core' &&
				context.includes( '@tiptap' )
			)
				return callback(
					null,
					[ 'ohmylmsEditorPackages', 'highlight' ],
					'window'
				);
			callback();
		},
	],
	resolve: { alias: pmAliases, extensions: [ '.js', '.jsx', '.mjs' ] },
	module: {
		rules: [
			{
				test: /\.[cm]?jsx?$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: [
							[
								'@babel/preset-react',
								{ runtime: 'classic', pragma: 'createElement' },
							],
						],
					},
				},
			},
		],
	},
	plugins: [
		new DependencyExtractionWebpackPlugin( {
			requestToExternal: ( request ) =>
				'jsxgraph' === request ? 'JXG' : undefined,
			requestToHandle: ( request ) =>
				'jsxgraph' === request ? 'ohmylms-jsxgraph' : undefined,
		} ),
	],
};
