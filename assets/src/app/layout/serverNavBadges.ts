import { useEffect } from '@wordpress/element';
import { SECTIONS_ID } from '../routing/QueryRouter';
import { TABS_ID } from './ExtensionTabs';

const BADGE_CLASS = 'ohmylms-nav-badge';

/**
 * Show a marker such as "(unsaved)" after links of the tab row (`kind` `tab`, `a[data-tab]`) or the
 * section list (`section`, `a[data-section]`) that includes/Admin/AdminNavigation.php prints. The text
 * becomes part of the link's accessible name and the link gets `data-ohmylms-badge`. Markers are removed
 * when the screen unmounts. Does nothing outside the admin pages.
 *
 * @param kind   Which server navigation to mark.
 * @param badges Link id (tab or section) => marker text; empty or undefined for none.
 */
export function useServerNavBadges(
	kind: 'tab' | 'section',
	badges: Readonly< Record< string, string | undefined > >
) {
	const signature = JSON.stringify( badges );
	useEffect( () => {
		const list = document.getElementById(
			'tab' === kind ? TABS_ID : SECTIONS_ID
		);
		if ( ! list ) {
			return undefined;
		}
		const links = Array.from(
			list.querySelectorAll< HTMLAnchorElement >( `a[data-${ kind }]` )
		);
		const wanted = JSON.parse( signature ) as Record< string, string >;
		for ( const link of links ) {
			const text = wanted[ link.dataset[ kind ] ?? '' ] ?? '';
			let badge = link.querySelector< HTMLElement >(
				`.${ BADGE_CLASS }`
			);
			if ( ! text ) {
				badge?.remove();
				link.removeAttribute( 'data-ohmylms-badge' );
				continue;
			}
			if ( ! badge ) {
				badge = document.createElement( 'span' );
				badge.className = BADGE_CLASS;
				link.append( badge );
			}
			badge.textContent = ` ${ text }`;
			link.setAttribute( 'data-ohmylms-badge', text );
		}
		return () => {
			for ( const link of links ) {
				link.querySelector( `.${ BADGE_CLASS }` )?.remove();
				link.removeAttribute( 'data-ohmylms-badge' );
			}
		};
	}, [ kind, signature ] );
}
