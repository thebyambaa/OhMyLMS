import { get } from '../../lib/api';
import { parseOverview } from './model';

/** Recent items the overview shows. */
export const RECENT_ITEMS = 8;

/**
 * Read the Content Hub overview: counts of every area the user may open and recently changed items.
 *
 * @param signal Cancels the request.
 * @return The validated overview.
 */
export async function getOverview( signal?: AbortSignal ) {
	return parseOverview(
		await get< unknown >( '/ohmylms/v1/content-hub/overview', {
			signal,
			query: { recent: RECENT_ITEMS },
		} )
	);
}
