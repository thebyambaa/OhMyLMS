const CURRENT_SUBMENU_CLASSES = [ 'wp-has-current-submenu', 'wp-menu-open' ];

/**
 * Selector for the submenu links that point at one OhMyLMS screen.
 *
 * The dashboard (slug `/`) is the plugin page itself, so its link is matched at the end of the
 * address or before a query string. Every other screen is a hash route and is matched anywhere in
 * the address.
 *
 * @param {string} slug Route slug, e.g. `courses`, or `/` for the dashboard.
 * @return {string} CSS selector for the links.
 */
export function getAdminMenuLinkSelector( slug ) {
	const target =
		'/' === slug
			? 'admin.php?page=ohmylms'
			: 'admin.php?page=ohmylms#/' + slug;
	if ( '/' === slug ) {
		return `li > a[href$="${ target }"], li > a[href*="${ target }?"]`;
	}
	return `li > a[href*="${ target }"]`;
}

/**
 * Mark one OhMyLMS screen as the current entry of the WordPress admin menu.
 *
 * Clears every current entry first, then marks the submenu items for `slug`. When `openTopLevel`
 * is truthy the OhMyLMS top-level item is also opened and marked current. The mobile menu is
 * closed. The admin menu must be on the page.
 *
 * @param {unknown} openTopLevel Truthy to open and mark the top-level OhMyLMS menu item.
 * @param {string}  slug         Route slug of the screen, or `/` for the dashboard.
 */
export function highlightAdminMenu( openTopLevel, slug ) {
	const menu = document.querySelector( '#adminmenu' );
	Array.from( menu.getElementsByClassName( 'current' ) ).forEach(
		( item ) => {
			item.classList.remove( 'current' );
		}
	);
	Array.from( menu.querySelectorAll( '.wp-has-current-submenu' ) ).forEach(
		( item ) => {
			item.classList.remove( 'wp-has-current-submenu' );
			item.classList.remove( 'wp-menu-open' );
			item.classList.remove( 'selected' );
			item.classList.add( 'wp-not-current-submenu' );
			item.classList.add( 'menu-top' );
		}
	);
	Array.from(
		menu.querySelectorAll( getAdminMenuLinkSelector( slug ) )
	).forEach( ( link ) => {
		link.parentElement.classList.add( 'current' );
	} );
	if ( openTopLevel ) {
		const topLevel = menu.querySelector( '#toplevel_page_ohmylms' );
		if ( topLevel ) {
			topLevel.classList.remove( 'wp-not-current-submenu' );
			CURRENT_SUBMENU_CLASSES.forEach( ( name ) => {
				topLevel.classList.add( name );
			} );
			topLevel.classList.add( 'current' );
		}
	}
	document
		.querySelector( '#wpwrap' )
		.classList.remove( 'wp-responsive-open' );
}
