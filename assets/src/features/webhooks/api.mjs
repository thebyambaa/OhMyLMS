import { getRestClient } from '../../shared/api/index.mjs';

/** Collection route of the webhooks REST controller (`OhMyLMS\Rest\V1\WebhookController`). */
export const WEBHOOKS_PATH = '/ohmylms/v1/webhooks';

/**
 * REST access for webhooks. Each function builds exactly the request the recovered store
 * (factory 86169) sends, so the server contract and the recorded request shapes stay identical.
 *
 * Every function takes the client as its last argument; the default is the shared client, which
 * sends requests through `wp.apiFetch`.
 */

/**
 * List webhooks. The response is requested unparsed because the totals are in the
 * `X-WP-Total` and `X-WP-TotalPages` headers.
 *
 * @param {Object} params Query parameters: page, per_page, search, status, orderby, order.
 * @param {Object} client Client from `createRestClient`.
 * @return {Promise<Response>} Unparsed response.
 */
export const listWebhooks = ( params = {}, client = getRestClient() ) =>
	client.request( {
		path: `${ WEBHOOKS_PATH }?${ new URLSearchParams( params ).toString() }`,
		parse: false,
	} );

/**
 * Create a webhook.
 *
 * @param {Object} data   Webhook fields.
 * @param {Object} client Client from `createRestClient`.
 * @return {Promise<Object>} The created webhook.
 */
export const createWebhook = ( data, client = getRestClient() ) =>
	client.request( { path: WEBHOOKS_PATH, method: 'POST', data } );

/**
 * Update a webhook.
 *
 * @param {number} id     Webhook id.
 * @param {Object} data   Changed fields.
 * @param {Object} client Client from `createRestClient`.
 * @return {Promise<Object>} The saved webhook.
 */
export const updateWebhook = ( id, data, client = getRestClient() ) =>
	client.request( {
		path: `${ WEBHOOKS_PATH }/${ id }`,
		method: 'PUT',
		data,
	} );

/**
 * Delete a webhook.
 *
 * @param {number} id     Webhook id.
 * @param {Object} client Client from `createRestClient`.
 * @return {Promise<Object>} Server response.
 */
export const deleteWebhook = ( id, client = getRestClient() ) =>
	client.request( { path: `${ WEBHOOKS_PATH }/${ id }`, method: 'DELETE' } );

/**
 * Apply one action to several webhooks. The server accepts `delete`, `active` and `inactive`.
 *
 * @param {string}   action One of the accepted actions.
 * @param {number[]} ids    Webhook ids.
 * @param {Object}   client Client from `createRestClient`.
 * @return {Promise<Object>} Per-id results.
 */
export const bulkWebhooks = ( action, ids, client = getRestClient() ) =>
	client.request( {
		path: `${ WEBHOOKS_PATH }/bulk`,
		method: 'POST',
		data: { action, ids },
	} );

/**
 * The trigger events a webhook can listen to.
 *
 * @param {Object} client Client from `createRestClient`.
 * @return {Promise<Object>} Triggers keyed by event name.
 */
export const loadWebhookTriggers = ( client = getRestClient() ) =>
	client.request( { path: `${ WEBHOOKS_PATH }/triggers` } );

/**
 * Send a test delivery. The recovered store requests `POST /ohmylms/v1/webhooks/test`, but no
 * controller in `includes/` registers that route (see docs/STATE-API-MODULARIZATION.md), so on
 * the current server the request is rejected as an unknown route. The request is kept as is until
 * the server route is added or the button is retired.
 *
 * @param {Object} data   Webhook fields to send.
 * @param {Object} client Client from `createRestClient`.
 * @return {Promise<Object>} Server response.
 */
export const testWebhook = ( data, client = getRestClient() ) =>
	client.request( {
		path: `${ WEBHOOKS_PATH }/test`,
		method: 'POST',
		data,
	} );

/**
 * Bind the webhooks API to one client.
 *
 * @param {Object} client Client from `createRestClient`; defaults to the shared client.
 * @return {Object} The seven requests, without the client argument.
 */
export function createWebhooksApi( client = getRestClient() ) {
	return {
		list: ( params ) => listWebhooks( params, client ),
		create: ( data ) => createWebhook( data, client ),
		update: ( id, data ) => updateWebhook( id, data, client ),
		remove: ( id ) => deleteWebhook( id, client ),
		bulk: ( action, ids ) => bulkWebhooks( action, ids, client ),
		triggers: () => loadWebhookTriggers( client ),
		test: ( data ) => testWebhook( data, client ),
	};
}
