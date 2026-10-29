import { fileURLToPath, pathToFileURL } from 'node:url';

const react = pathToFileURL(
	fileURLToPath( new URL( './controls-selects-react.mjs', import.meta.url ) )
).href;

/**
 * Point `@wordpress/element` at real React so the authored selects render with the
 * installed react-select.
 *
 * @param {string}   specifier Import specifier.
 * @param {Object}   context   Resolution context.
 * @param {Function} next      Default resolver.
 * @return {Promise<Object>} Resolution result.
 */
export function resolve( specifier, context, next ) {
	if ( '@wordpress/element' === specifier ) {
		return { url: react, shortCircuit: true };
	}
	return next( specifier, context );
}
