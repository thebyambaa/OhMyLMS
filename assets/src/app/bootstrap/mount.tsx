import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { createRegistry } from '../extensions/registry.mjs';
import { App } from '../App';
import { extensionGroupOf } from '../extensions/adminPages';
import { registerAppQuestionEditors } from '../extensions/coreEditors';
import { isExtensionHost } from '../extensions/host';
import { features } from '../features/registry';
import type { AdminPlace } from '../routing/address';
import { hashTarget } from '../routing/QueryRouter';
import { parseBootstrap } from './config';
import { configureApiFetch } from './configure-api-fetch';

/** Id of the mount element printed by includes/Admin/AdminNavigation.php. */
export const MOUNT_ID = 'ohmylms-app-root';

/**
 * Page, tab and section the server rendered (`data-page`, `data-tab`, `data-section` on the mount
 * element), falling back to the address.
 *
 * @param container Mount element.
 * @param search    Query string of the document.
 * @return The place.
 */
export function readPlace(
	container: HTMLElement,
	search: string
): AdminPlace {
	const query = new URLSearchParams( search );
	return {
		page: container.dataset.page ?? query.get( 'page' ) ?? '',
		tab: container.dataset.tab ?? query.get( 'tab' ) ?? '',
		section: container.dataset.section ?? query.get( 'section' ) ?? '',
	};
}

/**
 * Validate the bootstrap data, configure the API client, register the built-in question editors
 * (after the extensions, which may replace them) and render the app. An old `#/route` hash that opens
 * another admin page loads that page instead.
 *
 * @param doc Document that contains the mount element.
 * @param raw Unvalidated `window.ohmylmsApp`.
 * @return True when the app was rendered.
 */
export function mountApp( doc: Document, raw: unknown ): boolean {
	const container = doc.getElementById( MOUNT_ID );
	if ( ! container ) {
		return false;
	}
	const bootstrap = parseBootstrap( raw );
	if ( ! bootstrap ) {
		const message = doc.createElement( 'p' );
		message.setAttribute( 'role', 'alert' );
		message.textContent = __(
			'The OhMyLMS admin could not start because its configuration is missing.',
			'ohmylms'
		);
		container.replaceChildren( message );
		return false;
	}
	configureApiFetch( bootstrap );
	const win = doc.defaultView;
	const host = win?.ohmylms?.extensions;
	const extensions = isExtensionHost( host ) ? host : createRegistry();
	registerAppQuestionEditors( extensions );
	const place = readPlace( container, win?.location.search ?? '' );
	if ( win ) {
		const target = hashTarget( win, features, place, {
			extensionGroup: ( id ) => extensionGroupOf( extensions, id ),
		} );
		if ( target ) {
			win.location.replace( target );
			return false;
		}
	}
	createRoot( container ).render(
		<App
			bootstrap={ bootstrap }
			place={ place }
			extensions={ extensions }
		/>
	);
	return true;
}
