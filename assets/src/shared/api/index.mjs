import { createRestClient } from './client.mjs';

export { createRestClient } from './client.mjs';
export {
	RestError,
	hasMessage,
	isResponse,
	normalizeRestError,
} from './errors.mjs';
export { REST_NAMESPACE, buildPath, buildQuery, restUrl } from './url.mjs';

let defaultClient = null;

/**
 * The shared client for the `/ohmylms/v1` namespace, created on first use. It sends requests
 * through `wp.apiFetch`, so WordPress's REST root and nonce middleware apply.
 *
 * @return {Object} Client from `createRestClient`.
 */
export function getRestClient() {
	if ( ! defaultClient ) {
		defaultClient = createRestClient();
	}
	return defaultClient;
}
