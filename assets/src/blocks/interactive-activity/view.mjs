import { mountFunctionGraph } from './function-graph.mjs';

/** Activity types the page can mount. New types register here and in the editor. */
const ACTIVITIES = { 'function-graph': mountFunctionGraph };

/**
 * Mount every server-rendered activity on the page. The configuration is data, never script.
 *
 * @param {Document|Element} root Region to scan.
 */
function mountAll( root ) {
	root.querySelectorAll( '[data-ohmylms-activity]' ).forEach( ( host ) => {
		if ( host.dataset.mounted ) {
			return;
		}
		const mount = ACTIVITIES[ host.dataset.ohmylmsActivity ];
		if ( ! mount ) {
			return;
		}
		let config;
		let labels;
		try {
			config = JSON.parse( host.dataset.config );
			labels = JSON.parse( host.dataset.labels || '{}' );
		} catch {
			return;
		}
		host.dataset.mounted = '1';
		mount( host, config, { labels, fonts: host.dataset.fonts } );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => mountAll( document ) );
} else {
	mountAll( document );
}
