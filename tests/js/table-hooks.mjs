import fs from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { transformSync } from '@babel/core';

const stub = pathToFileURL(
	fileURLToPath( new URL( './table-stubs.mjs', import.meta.url ) )
).href;

/**
 * Resolve `@wordpress/components` to a local stub.
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

/**
 * Compile `.jsx` files with the WordPress `createElement` pragma.
 *
 * @param {string}   url     Module URL.
 * @param {Object}   context Load context.
 * @param {Function} next    Default loader.
 * @return {Promise<Object>} Load result.
 */
export function load( url, context, next ) {
	if ( ! url.endsWith( '.jsx' ) ) {
		return next( url, context );
	}
	const { code } = transformSync(
		fs.readFileSync( fileURLToPath( url ), 'utf8' ),
		{
			configFile: false,
			babelrc: false,
			presets: [
				[
					'@babel/preset-react',
					{ runtime: 'classic', pragma: 'createElement' },
				],
			],
		}
	);
	return { format: 'module', source: code, shortCircuit: true };
}
