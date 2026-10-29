/**
 * REST path and URL helpers shared by every feature API module.
 *
 * Kept free of WordPress globals so the behaviour can be tested directly.
 */

/** The REST namespace served by the `OhMyLMS\Rest\V1` controllers. */
export const REST_NAMESPACE = '/ohmylms/v1';

/**
 * The REST URL for a path such as `/ohmylms/v1/courses?per_page=5`, resolved against WordPress's
 * localized REST root. Sites with plain permalinks have a root like
 * `https://example.com/index.php?rest_route=/`, where the path's own query string has to be joined
 * with `&` instead of a second `?`.
 *
 * This is the same convention as `assets/src/features/restUrl.mjs`; the shared test keeps the two
 * identical until that file re-exports this one.
 *
 * @param {string} root REST root, for example `wpApiSettings.root`.
 * @param {string} path Route path, with or without a leading slash.
 * @return {string} Absolute URL.
 */
export function restUrl( root, path ) {
	const [ route, query ] = String( path )
		.replace( /^\//, '' )
		.split( '?', 2 );
	const url = String( root ).replace( /\/$/, '' ) + '/' + route;
	return query ? url + ( url.includes( '?' ) ? '&' : '?' ) + query : url;
}

/**
 * Serialize query parameters, leaving out `undefined`, `null` and empty strings.
 *
 * @param {Object} params Query parameters.
 * @return {string} Query string with a leading `?`, or an empty string.
 */
export function buildQuery( params ) {
	const search = new URLSearchParams();
	for ( const [ key, value ] of Object.entries( params || {} ) ) {
		if ( undefined !== value && null !== value && '' !== value ) {
			search.set( key, String( value ) );
		}
	}
	const text = search.toString();
	return text ? '?' + text : '';
}

/**
 * Join a namespace, a route and optional query parameters into an `apiFetch` path.
 *
 * @param {string} namespace Namespace such as `/ohmylms/v1`.
 * @param {string} route     Route such as `/courses/12`; a leading slash is added when missing.
 * @param {Object} query     Optional query parameters, see `buildQuery`.
 * @return {string} Path for `apiFetch`.
 */
export function buildPath( namespace, route, query ) {
	const base = String( namespace ).replace( /\/$/, '' );
	const tail = String( route ).replace( /^\/?/, '/' );
	return base + tail + buildQuery( query );
}
