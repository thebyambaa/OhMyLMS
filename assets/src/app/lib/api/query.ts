/** Query-string helpers for REST paths. */

/** A value that can appear in a query string. `null` and `undefined` are omitted. */
export type QueryValue =
	| string
	| number
	| boolean
	| null
	| undefined
	| ReadonlyArray< string | number | boolean >;

/** Query parameters keyed by name. */
export type QueryParams = Readonly< Record< string, QueryValue > >;

function encodePair( key: string, value: string | number | boolean ): string {
	return `${ encodeURIComponent( key ) }=${ encodeURIComponent(
		String( value )
	) }`;
}

/**
 * Build a query string without the leading `?`.
 *
 * Arrays use the `key[]=a&key[]=b` form WordPress accepts. Empty strings are kept; `null` and `undefined`
 * are dropped. Keys are emitted in insertion order, so the result is stable.
 *
 * @param params Query parameters.
 * @return Encoded query string, or an empty string.
 */
export function buildQueryString( params: QueryParams ): string {
	const pairs: string[] = [];
	for ( const [ key, value ] of Object.entries( params ) ) {
		if ( null === value || undefined === value ) {
			continue;
		}
		if ( Array.isArray( value ) ) {
			for ( const item of value as ReadonlyArray<
				string | number | boolean
			> ) {
				pairs.push( encodePair( `${ key }[]`, item ) );
			}
		} else {
			pairs.push( encodePair( key, value as string | number | boolean ) );
		}
	}
	return pairs.join( '&' );
}

/**
 * Append query parameters to a REST path, respecting an existing query string.
 *
 * @param path   REST path such as `/wp/v2/posts`.
 * @param params Query parameters.
 * @return Path with the query string appended.
 */
export function withQuery( path: string, params?: QueryParams ): string {
	const query = params ? buildQueryString( params ) : '';
	if ( '' === query ) {
		return path;
	}
	return `${ path }${ path.includes( '?' ) ? '&' : '?' }${ query }`;
}
