import { del, get, patch, post } from '../../lib/api';
import { getCollection, type Collection } from '../../lib/api/collection';
import {
	buildPayload,
	badgePayload,
	invalidResponse,
	levelPayload,
	parseBadge,
	parseLevel,
	parseMedia,
	parseOptions,
	parseSection,
} from './model';
import type {
	Badge,
	BadgeDraft,
	DraftMap,
	GamificationOptions,
	Level,
	LevelDraft,
	MediaImage,
	SectionId,
	SectionMap,
} from './types';

const BASE = '/ohmylms/v1/gamification';

/**
 * Load one section.
 *
 * @param section Section id.
 * @param signal  Cancels the request.
 * @return Validated section.
 */
export async function getSection< S extends SectionId >(
	section: S,
	signal?: AbortSignal
): Promise< SectionMap[ S ] > {
	return parseSection(
		section,
		await get< unknown >( `${ BASE }/settings/${ section }`, { signal } )
	);
}

/**
 * Save one section. The server changes only the fields sent and answers with the stored section.
 *
 * @param section Section id.
 * @param draft   Validated draft.
 * @param signal  Cancels the request.
 * @return The section as stored.
 */
export async function saveSection< S extends SectionId >(
	section: S,
	draft: DraftMap[ S ],
	signal?: AbortSignal
): Promise< SectionMap[ S ] > {
	return parseSection(
		section,
		await patch< unknown >(
			`${ BASE }/settings/${ section }`,
			buildPayload( section, draft ),
			{ signal }
		)
	);
}

/**
 * Allow-lists, limits and whether the Gamification add-on is on.
 *
 * @param signal Cancels the request.
 */
export async function getOptions(
	signal?: AbortSignal
): Promise< GamificationOptions > {
	return parseOptions(
		await get< unknown >( `${ BASE }/options`, { signal } )
	);
}

/**
 * All levels, lowest threshold first (the server allows at most 50).
 *
 * @param signal Cancels the request.
 */
export function listLevels(
	signal?: AbortSignal
): Promise< Collection< Level > > {
	return getCollection( `${ BASE }/levels`, parseLevel, {
		signal,
		query: { per_page: 100 },
	} );
}

export async function createLevel(
	draft: LevelDraft,
	signal?: AbortSignal
): Promise< Level > {
	return parseLevel(
		await post< unknown >( `${ BASE }/levels`, levelPayload( draft ), {
			signal,
		} )
	);
}

export async function updateLevel(
	slug: string,
	draft: LevelDraft,
	signal?: AbortSignal
): Promise< Level > {
	return parseLevel(
		await patch< unknown >(
			`${ BASE }/levels/${ encodeURIComponent( slug ) }`,
			levelPayload( draft ),
			{ signal }
		)
	);
}

export async function deleteLevel(
	slug: string,
	signal?: AbortSignal
): Promise< void > {
	const result = await del< unknown >(
		`${ BASE }/levels/${ encodeURIComponent( slug ) }`,
		{ signal }
	);
	if ( ! result || true !== ( result as { deleted?: unknown } ).deleted ) {
		throw invalidResponse();
	}
}

/**
 * Custom badges (the system streak badges are not editable and are not listed).
 *
 * @param signal Cancels the request.
 */
export function listBadges(
	signal?: AbortSignal
): Promise< Collection< Badge > > {
	return getCollection( `${ BASE }/badges`, parseBadge, {
		signal,
		query: { per_page: 100, source: 'custom' },
	} );
}

export async function createBadge(
	draft: BadgeDraft,
	signal?: AbortSignal
): Promise< Badge > {
	return parseBadge(
		await post< unknown >( `${ BASE }/badges`, badgePayload( draft ), {
			signal,
		} )
	);
}

export async function updateBadge(
	slug: string,
	draft: BadgeDraft,
	signal?: AbortSignal
): Promise< Badge > {
	return parseBadge(
		await patch< unknown >(
			`${ BASE }/badges/${ encodeURIComponent( slug ) }`,
			badgePayload( draft ),
			{ signal }
		)
	);
}

export async function deleteBadge(
	slug: string,
	signal?: AbortSignal
): Promise< void > {
	const result = await del< unknown >(
		`${ BASE }/badges/${ encodeURIComponent( slug ) }`,
		{ signal }
	);
	if ( ! result || true !== ( result as { deleted?: unknown } ).deleted ) {
		throw invalidResponse();
	}
}

/**
 * Search the media library for badge images through the core REST route.
 *
 * @param search Search text.
 * @param page   Page number.
 * @param signal Cancels the request.
 * @return Validated images and totals.
 */
export function listMedia(
	search: string,
	page: number,
	signal?: AbortSignal
): Promise< Collection< MediaImage > > {
	return getCollection( '/wp/v2/media', parseMedia, {
		signal,
		query: {
			search,
			page,
			per_page: 12,
			media_type: 'image',
			mime_type: 'image/jpeg,image/png,image/gif,image/webp',
			orderby: 'date',
			order: 'desc',
			_fields: 'id,source_url,mime_type,title,media_details',
		},
	} );
}
