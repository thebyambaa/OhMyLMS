const wordpress = require( '@wordpress/eslint-plugin' );

module.exports = [
	{
		ignores: [
			'node_modules/**',
			'vendor/**',
			'build/**',
			'.wp-dev/**',
			'tools/coding-standards/vendor/**',
			'assets/dist/**',
			'assets/src/runtimes/**',
			'assets/src/static/**',
		],
	},
	...wordpress.configs[ 'recommended' ].map( ( config ) => ( {
		...config,
		files: [
			'assets/src/features/**/*.{js,jsx,mjs}',
			'assets/src/ui/**/*.{js,jsx,mjs}',
			'assets/src/blocks/**/*.{js,jsx,mjs}',
			'assets/interactivity/**/*.js',
			'assets/js/student-hub.js',
			'assets/js/player-context.js',
			'assets/js/ui.js',
		],
	} ) ),
	...wordpress.configs[ 'recommended' ].map( ( config ) => ( {
		...config,
		files: [ 'assets/src/app/**/*.{ts,tsx}' ],
	} ) ),
	{
		// Typed admin app (docs/ARCHITECTURE.md). @wordpress/* packages resolve from node_modules for types
		// and tests; webpack externalizes them to the WordPress script handles at build time.
		files: [ 'assets/src/app/**/*.{ts,tsx}' ],
		settings: { 'import/resolver': { typescript: { project: 'assets/src/app/tsconfig.json' } } },
	},
	{
		files: [
			'assets/src/features/**/*.{js,jsx,mjs}',
			'assets/src/ui/**/*.{js,jsx,mjs}',
			'assets/src/blocks/**/*.{js,jsx,mjs}',
			'assets/interactivity/**/*.js',
		],
		settings: {
			// webpack.config.cjs compiles JSX with the WordPress createElement pragma.
			react: { pragma: 'createElement' },
			// These modules are supplied by WordPress/dependency extraction, not bundled from node_modules.
			'import/core-modules': [
				'@wordpress/api-fetch',
				'@wordpress/block-editor',
				'@wordpress/block-library',
				'@wordpress/blocks',
				'@wordpress/components',
				'@wordpress/compose',
				'@wordpress/data',
				'@wordpress/element',
				'@wordpress/hooks',
				'@wordpress/html-entities',
				'@wordpress/i18n',
				'@wordpress/media-utils',
				'@wordpress/primitives',
				'ohmylms/interactivity',
				'ohmylms/mathlive',
			],
		},
		rules: {
			// ViewAsModal's public `role` prop selects an account type; it is not a DOM ARIA role.
			'jsx-a11y/aria-role': [ 'error', { ignoreNonDOM: true } ],
		},
	},
];
