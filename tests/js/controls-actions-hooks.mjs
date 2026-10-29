import { pathToFileURL, fileURLToPath } from 'node:url';

const stub = pathToFileURL(
	fileURLToPath( new URL( './controls-actions-stubs.mjs', import.meta.url ) )
).href;

/**
 * Resolve `@wordpress/components` and the layout group's FlexWP to stubs.
 *
 * @param {string}   specifier Import specifier.
 * @param {Object}   context   Resolution context.
 * @param {Function} next      Default resolver.
 * @return {Promise<Object>} Resolution result.
 */
export function resolve( specifier, context, next ) {
	if (
		'@wordpress/components' === specifier ||
		specifier.endsWith( '/layout/FlexWP.mjs' )
	) {
		// The FlexWP export lives in the same stub module.
		return { url: stub, shortCircuit: true };
	}
	return next( specifier, context );
}
