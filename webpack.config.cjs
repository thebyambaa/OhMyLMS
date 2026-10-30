const path = require( 'node:path' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
module.exports = {
	entry: {
		// New typed admin app (see docs/ARCHITECTURE.md). Self-contained; it imports nothing from the recovered app.
		'admin-app': path.resolve( __dirname, 'assets/src/app/main.tsx' ),
		// Publishes window.ohmylms.extensions before extension scripts and the app load.
		'admin-app-extensions': path.resolve(
			__dirname,
			'assets/src/app/extensions/host-entry.ts'
		),
		// The signup form's React fields; enqueued only where the form renders.
		'admin-app-registration': path.resolve(
			__dirname,
			'assets/src/app/extensions/registration-entry.ts'
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
	resolve: {
		extensions: [ '.js', '.jsx', '.mjs', '.ts', '.tsx' ],
	},
	module: {
		rules: [
			{
				// Typed admin app: TypeScript and the automatic JSX runtime (react/jsx-runtime is a WordPress external).
				test: /\.tsx?$/,
				include: path.resolve( __dirname, 'assets/src/app' ),
				use: {
					loader: 'babel-loader',
					options: {
						presets: [
							'@babel/preset-typescript',
							[ '@babel/preset-react', { runtime: 'automatic' } ],
						],
					},
				},
			},
			{
				// The admin app's stylesheet is extracted to build/sdk/admin-app.css.
				test: /\.css$/,
				include: path.resolve( __dirname, 'assets/src/app' ),
				use: [ MiniCssExtractPlugin.loader, 'css-loader' ],
			},
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
		new MiniCssExtractPlugin( {
			filename: '[name].css',
			chunkFilename: 'chunks/[name].[contenthash:12].css',
		} ),
		new DependencyExtractionWebpackPlugin( {
			// MathLive registers the global <math-field> element, so only math-runtime bundles it;
			// other entries import `ohmylms/mathlive` and depend on its script handle.
			requestToExternal: ( request ) =>
				( {
					jsxgraph: 'JXG',
					'ohmylms/mathlive': [ 'OhMyLMSMath', 'MathLive' ],
				} )[ request ],
			requestToHandle: ( request ) =>
				( {
					jsxgraph: 'ohmylms-jsxgraph',
					'ohmylms/mathlive': 'ohmylms-mathlive',
				} )[ request ],
		} ),
	],
};
