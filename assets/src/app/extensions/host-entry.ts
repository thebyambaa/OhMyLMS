/**
 * Entry `admin-app-extensions`. Loads before the scripts added through `ohmylms_enqueue_extension_scripts`
 * and before the app, so extensions can register synchronously. See includes/Assets/AdminAppAssets.php.
 */
import { installExtensionHost } from './host';

installExtensionHost( window );
