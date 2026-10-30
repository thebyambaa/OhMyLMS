import { createPortal } from '@wordpress/element';
import { adminPageGroup } from '../extensions/adminPages';
import { useExtensions } from '../extensions/context';
import { addressHref, GROUP_PAGES } from '../routing/address';
import { useAdminPlace } from '../routing/place';

/** Id of the `nav-tab-wrapper` includes/Admin/AdminNavigation.php prints. */
export const TABS_ID = 'ohmylms-admin-tabs';

/**
 * Tabs of the extension admin pages registered for the current page (`registerAdminPage( id, { group } )`),
 * added at the end of the server's tab row as `ext-<id>` links. Extensions register in the browser, so
 * the server cannot print them.
 */
export function ExtensionTabs() {
	const place = useAdminPlace();
	const pages = useExtensions( 'admin-page' );
	const wrapper = place ? document.getElementById( TABS_ID ) : null;
	if ( ! place || ! wrapper ) {
		return null;
	}
	const own = pages.filter(
		( page ) => GROUP_PAGES[ adminPageGroup( page ) ] === place.page
	);
	if ( ! own.length ) {
		return null;
	}
	return createPortal(
		<>
			{ own.map( ( page ) => {
				const tab = `ext-${ page.id }`;
				const active = tab === place.tab;
				return (
					<a
						key={ page.id }
						className={ `nav-tab${ active ? ' nav-tab-active' : '' }` }
						href={ addressHref( [
							[ 'page', place.page ],
							[ 'tab', tab ],
						] ) }
						aria-current={ active ? 'page' : undefined }
					>
						{ page.label }
					</a>
				);
			} ) }
		</>,
		wrapper
	);
}
