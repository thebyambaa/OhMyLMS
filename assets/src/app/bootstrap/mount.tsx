import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { App } from '../App';
import { parseBootstrap } from './config';
import { configureApiFetch } from './configure-api-fetch';

/** Id of the mount element printed by includes/Admin/AppPage.php. */
export const MOUNT_ID = 'ohmylms-app-root';

/**
 * Validate the bootstrap data, configure the API client and render the app.
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
	createRoot( container ).render( <App bootstrap={ bootstrap } /> );
	return true;
}
