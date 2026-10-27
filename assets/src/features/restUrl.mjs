/**
 * The REST URL for a path such as `/ohmylms/v1/courses?per_page=5`, resolved against WordPress's localized
 * REST root. Sites with plain permalinks have a root like `https://example.com/index.php?rest_route=/`,
 * where the path's own query string has to be joined with `&` instead of a second `?`.
 * @param root
 * @param path
 */
export function restUrl( root, path ) {
	const [ route, query ] = String( path )
		.replace( /^\//, '' )
		.split( '?', 2 );
	const url = String( root ).replace( /\/$/, '' ) + '/' + route;
	return query ? url + ( url.includes( '?' ) ? '&' : '?' ) + query : url;
}
