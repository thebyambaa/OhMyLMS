import { get, post } from '../../lib/api';
import { getCollection, type Collection } from '../../lib/api/collection';
import { asArray, asNumber, asRecord, asString, isRecord } from './model';

const PATH = '/ohmylms/v1/hub-icons';

/** One slot of the learner hub: an activity kind or a rail logo. */
export interface HubIconSlot {
	key: string;
	group: 'activity' | 'logo';
	label: string;
	/** Attachment id; 0 uses the built-in icon. */
	id: number;
	url: string;
}

export interface ImageOption {
	id: number;
	title: string;
	thumbnail: string;
}

export type HubIconsDraft = Record< string, { id: number; url: string } >;

export function parseHubIcons( raw: unknown ): HubIconSlot[] {
	return asArray( asRecord( raw ).kinds ).map( ( item ) => {
		const row = asRecord( item );
		return {
			key: asString( row.key ),
			group: 'logo' === row.group ? 'logo' : 'activity',
			label: asString( row.label ),
			id: asNumber( row.id ),
			url: asString( row.url ),
		};
	} );
}

export async function getHubIcons(
	signal?: AbortSignal
): Promise< HubIconSlot[] > {
	return parseHubIcons( await get< unknown >( PATH, { signal } ) );
}

/**
 * Save every slot; a slot left at 0 (or not an image) uses the built-in icon.
 *
 * @param draft  Slot key to chosen image.
 * @param signal Cancels the request.
 * @return The slots as stored.
 */
export async function saveHubIcons(
	draft: HubIconsDraft,
	signal?: AbortSignal
): Promise< HubIconSlot[] > {
	const icons: Record< string, number > = {};
	for ( const [ key, choice ] of Object.entries( draft ) ) {
		icons[ key ] = choice.id;
	}
	return parseHubIcons(
		await post< unknown >( PATH, { icons }, { signal } )
	);
}

export function hubIconsDraft( slots: readonly HubIconSlot[] ): HubIconsDraft {
	const draft: HubIconsDraft = {};
	for ( const slot of slots ) {
		draft[ slot.key ] = { id: slot.id, url: slot.url };
	}
	return draft;
}

export function hubIconsDirty(
	slots: readonly HubIconSlot[],
	draft: HubIconsDraft
): boolean {
	return slots.some( ( slot ) => draft[ slot.key ]?.id !== slot.id );
}

function parseImage( raw: unknown ): ImageOption {
	const row = asRecord( raw );
	const url = asString( row.source_url );
	const sizes =
		isRecord( row.media_details ) && isRecord( row.media_details.sizes )
			? row.media_details.sizes
			: {};
	const small = isRecord( sizes.thumbnail ) ? sizes.thumbnail : {};
	const rendered = isRecord( row.title )
		? asString( row.title.rendered )
		: '';
	return {
		id: asNumber( row.id ),
		// `title.rendered` is HTML; keep its text only.
		title:
			new DOMParser().parseFromString( rendered, 'text/html' ).body
				.textContent ?? '',
		thumbnail:
			'string' === typeof small.source_url && small.source_url
				? small.source_url
				: url,
	};
}

export function listImages(
	search: string,
	page: number,
	signal?: AbortSignal
): Promise< Collection< ImageOption > > {
	return getCollection( '/wp/v2/media', parseImage, {
		signal,
		query: {
			search,
			page,
			per_page: 12,
			media_type: 'image',
			orderby: 'date',
			order: 'desc',
			_fields: 'id,source_url,title,media_details',
		},
	} );
}
