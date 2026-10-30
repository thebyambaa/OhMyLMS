import { get, patch, post } from '../../lib/api';
import {
	parseCodeNames,
	parseCurrencies,
	parseDeleted,
	parseGroupResponse,
	parsePages,
	parseRestore,
} from './model';
import type {
	CodeName,
	CurrencyOption,
	GroupId,
	GroupResponse,
	PageOption,
	RestorePagesResult,
	SearchPagesParams,
	SettingValue,
} from './types';

const BASE = '/ohmylms/v1/settings';
const PAGE_LIMIT = 20;

/**
 * Load the current values of one group.
 *
 * @param group  Group id.
 * @param signal Cancels the request.
 * @return Validated response.
 */
export async function getSettings< G extends GroupId >(
	group: G,
	signal?: AbortSignal
): Promise< GroupResponse< G > > {
	return parseGroupResponse(
		group,
		await get< unknown >( `${ BASE }/${ group }`, { signal } )
	);
}

/**
 * Save changed fields of one group. Fields that are not sent are left untouched by the server.
 *
 * @param group   Group id.
 * @param changes Changed fields only (see `buildPatch`).
 * @param signal  Cancels the request.
 * @return The group as stored, with the names that actually changed.
 */
export async function saveSettings< G extends GroupId >(
	group: G,
	changes: Record< string, SettingValue >,
	signal?: AbortSignal
): Promise< GroupResponse< G > > {
	return parseGroupResponse(
		group,
		await patch< unknown >( `${ BASE }/${ group }`, changes, { signal } )
	);
}

/**
 * Search published pages for a page picker (at most 20 rows).
 *
 * @param params Search text, or ids to label saved choices.
 * @param signal Cancels the request.
 * @return Matching pages.
 */
export async function searchPages(
	params: SearchPagesParams = {},
	signal?: AbortSignal
): Promise< PageOption[] > {
	const include = params.include?.filter( ( id ) => id > 0 );
	return parsePages(
		await get< unknown >( `${ BASE }/pages`, {
			query: include?.length
				? { include }
				: {
						search: params.search?.trim() || undefined,
						per_page: Math.min(
							params.perPage ?? PAGE_LIMIT,
							PAGE_LIMIT
						),
					},
			signal,
		} )
	);
}

export async function listCurrencies(
	signal?: AbortSignal
): Promise< CurrencyOption[] > {
	return parseCurrencies(
		await get< unknown >( `${ BASE }/currencies`, { signal } )
	);
}

export async function listCountries(
	signal?: AbortSignal
): Promise< CodeName[] > {
	return parseCodeNames(
		await get< unknown >( `${ BASE }/countries`, { signal } )
	);
}

/**
 * List the states of a country.
 *
 * @param country Two-letter country code.
 * @param signal  Cancels the request.
 * @return States (empty for countries without a state list).
 */
export async function listStates(
	country: string,
	signal?: AbortSignal
): Promise< CodeName[] > {
	return parseCodeNames(
		await get< unknown >(
			`${ BASE }/countries/${ encodeURIComponent( country ) }/states`,
			{ signal }
		)
	);
}

/**
 * Re-create default LMS pages whose settings point at nothing published.
 *
 * @param signal Cancels the request.
 * @return The pages created and the general values after the change.
 */
export async function restoreDefaultPages(
	signal?: AbortSignal
): Promise< RestorePagesResult > {
	return parseRestore(
		await post< unknown >( `${ BASE }/general/restore-pages`, undefined, {
			signal,
		} )
	);
}

/**
 * Delete OhMyLMS transient cache.
 *
 * @param signal Cancels the request.
 * @return Number of cache rows removed.
 */
export async function clearCache( signal?: AbortSignal ): Promise< number > {
	return parseDeleted(
		await post< unknown >( `${ BASE }/advanced/clear-cache`, undefined, {
			signal,
		} )
	);
}
