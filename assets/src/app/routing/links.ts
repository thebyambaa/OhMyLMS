/** Turns a same-tab link into its internal route; set by the query router while it is mounted. */
export type LinkResolver = ( href: string ) => string | null;

let active: LinkResolver | null = null;

/**
 * Install the resolver of the mounted router (null when it unmounts).
 *
 * @param resolver Resolver.
 */
export function setLinkResolver( resolver: LinkResolver | null ) {
	active = resolver;
}

/**
 * The internal route a link opens without leaving the document: an address on the current page and
 * tab, an old `#/route` hash, or (in a memory router) a bare route path. Unsaved-changes guards use it to
 * intercept links.
 *
 * @param href Link `href` attribute.
 * @return Route such as `/courses/12`, or null for links that load another document.
 */
export function routeFromHref( href: string ): string | null {
	if ( href.startsWith( '#/' ) ) {
		return href.slice( 1 );
	}
	if ( active ) {
		return active( href );
	}
	return href.startsWith( '/' ) && ! href.startsWith( '//' ) ? href : null;
}
