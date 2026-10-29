import { fileURLToPath, pathToFileURL } from 'node:url';

const stub = pathToFileURL(
	fileURLToPath( new URL( './controls-forms-stubs.mjs', import.meta.url ) )
).href;

/**
 * Resolve `@wordpress/components` to the probe components.
 *
 * @param {string}   specifier Import specifier.
 * @param {Object}   context   Resolution context.
 * @param {Function} next      Default resolver.
 * @return {Promise<Object>} Resolution result.
 */
export function resolve( specifier, context, next ) {
	if ( '@wordpress/components' === specifier ) {
		return { url: stub, shortCircuit: true };
	}
	return next( specifier, context );
}
