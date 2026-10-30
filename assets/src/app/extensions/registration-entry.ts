/**
 * Entry `admin-app-registration`: the React fields of the signup form (`[data-ohmylms-registration-fields]`).
 * A separate script rather than a chunk of the extension host, so the host carries no chunk loader;
 * includes/Extensions/SourceAssets.php enqueues it only where the form renders.
 */
import { mountRegistration } from '../../features/students/mountRegistration';

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mountRegistration, {
		once: true,
	} );
} else {
	mountRegistration();
}
