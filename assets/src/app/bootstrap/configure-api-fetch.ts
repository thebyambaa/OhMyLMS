import apiFetch from '@wordpress/api-fetch';
import type { AppBootstrap } from './config';

/**
 * Point `@wordpress/api-fetch` at this site's REST API and authenticate with the REST nonce.
 *
 * WordPress core registers the same middleware for `wp-api-fetch`; registering it from the bootstrap data
 * keeps the app independent of that default and testable. Call once, before the first request.
 *
 * @param bootstrap Validated bootstrap data.
 */
export function configureApiFetch( bootstrap: AppBootstrap ): void {
	apiFetch.use( apiFetch.createRootURLMiddleware( bootstrap.restRoot ) );
	apiFetch.use( apiFetch.createNonceMiddleware( bootstrap.restNonce ) );
}
