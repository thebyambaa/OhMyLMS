import { defineConfig } from 'vitest/config';

// Component and unit tests for the typed admin app only (assets/src/app).
// The recovered application keeps its node:test suite (`npm test`).
export default defineConfig( {
	// Keep the cache out of assets/src/app (root-relative path).
	cacheDir: '../../../node_modules/.vite',
	test: {
		root: 'assets/src/app',
		environment: 'jsdom',
		globals: true,
		setupFiles: [ './test/setup.ts' ],
		include: [ '**/*.test.{ts,tsx}' ],
		css: false,
		restoreMocks: true,
	},
} );
