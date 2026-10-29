import { fileURLToPath, pathToFileURL } from 'node:url';

const stub = pathToFileURL(
	fileURLToPath( new URL( './shared-utils-stubs.mjs', import.meta.url ) )
).href;

/**
 * Resolve `@wordpress/date` to the stand-in.
 *
 * @param {string}                                                  specifier Import specifier.
 * @param {object}                                                  context   Resolution context.
 * @param {(specifier: string, context: object) => Promise<object>} next      Default resolver.
 * @returns {Promise<object>} Resolution result.
 */
export function resolve( specifier, context, next ) {
	if ( '@wordpress/date' === specifier ) {
		return { url: stub, shortCircuit: true };
	}
	return next( specifier, context );
}
