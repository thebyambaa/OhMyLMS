import { useEffect } from '@wordpress/element';

/**
 * Keep one OhMyLMS submenu entry highlighted while a screen is open. The recovered application clears
 * the highlight on every render of its own screens, so a mutation observer puts it back instead of a
 * one-off effect. `selector` finds the entry's link inside the OhMyLMS submenu, e.g. `a[href$="#/content-hub"]`.
 * @param selector
 */
export function useMenuHighlight( selector ) {
	useEffect( () => {
		const find = () =>
			document.querySelector(
				`#toplevel_page_ohmylms .wp-submenu ${ selector }`
			)?.parentElement;
		const apply = () => {
			const item = find();
			if ( ! item ) {
				return;
			}
			item.closest( '.wp-submenu' )
				?.querySelectorAll( 'li.current' )
				.forEach( ( current ) =>
					current.classList.remove( 'current' )
				);
			item.classList.add( 'current' );
		};
		apply();
		const menu = document.querySelector( '#adminmenu' );
		if ( ! menu || ! window.MutationObserver ) {
			return undefined;
		}
		const observer = new window.MutationObserver( () => {
			if ( ! find()?.classList.contains( 'current' ) ) {
				apply();
			}
		} );
		observer.observe( menu, {
			subtree: true,
			attributes: true,
			attributeFilter: [ 'class' ],
		} );
		return () => observer.disconnect();
	}, [ selector ] );
}
