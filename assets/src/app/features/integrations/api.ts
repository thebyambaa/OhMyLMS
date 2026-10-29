import { get, patch } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	integrationId,
	invalidResponse,
	parseIntegration,
	parseSettings,
} from './model';
import type {
	Integration,
	IntegrationSettings,
	SettingsChanges,
} from './types';

const BASE = '/ohmylms/v1/integrations';
const itemPath = ( id: string ) => `${ BASE }/${ integrationId( id ) }`;

/**
 * Load every integration (the catalogue is small and not paginated; the screen filters locally).
 *
 * @param signal Cancels the request.
 * @return Validated integrations in server order.
 */
export async function listIntegrations(
	signal?: AbortSignal
): Promise< Integration[] > {
	const { items } = await getCollection( BASE, parseIntegration, { signal } );
	return items;
}

/**
 * Switch an integration on or off.
 *
 * @param id      Integration id.
 * @param enabled Desired state.
 * @param signal  Cancels the request.
 * @return The updated integration; `requires_reload` is true when the admin menu changes.
 */
export async function setIntegrationEnabled(
	id: string,
	enabled: boolean,
	signal?: AbortSignal
): Promise< Integration > {
	const updated = parseIntegration(
		await patch< unknown >( itemPath( id ), { enabled }, { signal } )
	);
	if ( updated.id !== id || updated.enabled !== enabled ) {
		throw invalidResponse();
	}
	return updated;
}

/**
 * Load an integration's credential settings. Secrets are never included.
 *
 * @param id     Integration id.
 * @param signal Cancels the request.
 * @return Validated settings.
 */
export async function getIntegrationSettings(
	id: string,
	signal?: AbortSignal
): Promise< IntegrationSettings > {
	const settings = parseSettings(
		await get< unknown >( `${ itemPath( id ) }/settings`, { signal } )
	);
	if ( settings.integration !== id ) {
		throw invalidResponse();
	}
	return settings;
}

/**
 * Save changed credential fields.
 *
 * @param id      Integration id.
 * @param changes Fields to change; omitted fields (and secrets) are kept by the server.
 * @param signal  Cancels the request.
 * @return The settings as stored, with secrets reduced to `configured` and `hint`.
 */
export async function saveIntegrationSettings(
	id: string,
	changes: SettingsChanges,
	signal?: AbortSignal
): Promise< IntegrationSettings > {
	const settings = parseSettings(
		await patch< unknown >( `${ itemPath( id ) }/settings`, changes, {
			signal,
		} )
	);
	if ( settings.integration !== id ) {
		throw invalidResponse();
	}
	return settings;
}
