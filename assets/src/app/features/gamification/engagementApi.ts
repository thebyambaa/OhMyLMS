import { get, patch } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	engagementPayload,
	parseEngagement,
	parseEngagementSave,
} from './engagementModel';
import { parseBadge } from './model';
import type {
	Badge,
	EngagementDraftMap,
	EngagementGroup,
	EngagementMap,
} from './types';

const BASE = '/ohmylms/v1/engagement/settings';

/**
 * Load a streak, XP, score or mastery group.
 *
 * @param group  Group id.
 * @param signal Cancels the request.
 * @return Validated group.
 */
export async function getEngagement< G extends EngagementGroup >(
	group: G,
	signal?: AbortSignal
): Promise< EngagementMap[ G ] > {
	return parseEngagement(
		group,
		await get< unknown >( `${ BASE }/${ group }`, { signal } )
	);
}

/**
 * Save a group. The server validates the whole result and answers with the stored group.
 *
 * @param group  Group id.
 * @param draft  Validated draft.
 * @param signal Cancels the request.
 * @return The group as stored.
 */
export async function saveEngagement< G extends EngagementGroup >(
	group: G,
	draft: EngagementDraftMap[ G ],
	signal?: AbortSignal
): Promise< EngagementMap[ G ] > {
	return parseEngagementSave(
		group,
		await patch< unknown >(
			`${ BASE }/${ group }`,
			engagementPayload( group, draft ),
			{ signal }
		)
	);
}

/**
 * Badges a streak milestone can award: custom badges and the built-in streak badges.
 *
 * @param signal Cancels the request.
 */
export async function listMilestoneBadges(
	signal?: AbortSignal
): Promise< Badge[] > {
	const result = await getCollection(
		'/ohmylms/v1/gamification/badges',
		parseBadge,
		{ signal, query: { per_page: 100, source: 'all' } }
	);
	return result.items;
}
