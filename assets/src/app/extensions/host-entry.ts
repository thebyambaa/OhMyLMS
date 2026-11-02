/**
 * Entry `admin-app-extensions`. Loads before the scripts added through `ohmylms_enqueue_extension_scripts`
 * and before the app, so extensions can register synchronously. See includes/Assets/AdminAppAssets.php.
 * On the front end (includes/Extensions/SourceAssets.php) it also mounts the slots PHP marked up, once
 * the document has loaded and extension scripts have registered.
 */
import { mountSlots } from './dom';
import { installExtensionHost } from './host';

const host = installExtensionHost( window );
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => mountSlots( host ), {
		once: true,
	} );
} else {
	mountSlots( host );
}
