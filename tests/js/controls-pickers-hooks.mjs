import { fileURLToPath, pathToFileURL } from 'node:url';

const here = ( name ) =>
	pathToFileURL( fileURLToPath( new URL( name, import.meta.url ) ) ).href;
const stubs = here( './controls-pickers-stubs.mjs' );
const harness = here( './controls-pickers-harness.mjs' );

/**
 * Point the picker modules at test doubles: `@wordpress/components` and the
 * sibling controls become probes, and `@wordpress/element` the test hooks.
 *
 * @param {string}   specifier Import specifier.
 * @param {Object}   context   Resolution context.
 * @param {Function} next      Default resolver.
 * @return {Promise<Object>} Resolution result.
 */
export function resolve( specifier, context, next ) {
	if ( '@wordpress/components' === specifier ) {
		return { url: stubs, shortCircuit: true };
	}
	if ( '@wordpress/element' === specifier ) {
		return { url: harness, shortCircuit: true };
	}
	if ( /^\.\.\/(actions|layout)\/\w+WP\.mjs$/.test( specifier ) ) {
		return { url: stubs, shortCircuit: true };
	}
	return next( specifier, context );
}
