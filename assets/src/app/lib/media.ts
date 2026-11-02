import {
	createValidators,
	invalidResponseError,
	type InvalidResponse,
} from './validation';

/** An image from the media library (`/wp/v2/media`). */
export interface MediaImage {
	id: number;
	url: string;
	/** The `thumbnail` size, or the full image when there is none. */
	thumbnail_url: string;
	/** `title.rendered` (HTML), or `''`. */
	title: string;
}

/** Raster types the image pickers accept; SVG is left out because WordPress does not sanitise it. */
export const IMAGE_MIME_TYPES: readonly string[] = [
	'image/jpeg',
	'image/png',
	'image/gif',
	'image/webp',
];

export interface MediaParseOptions {
	/** Message or error factory for an item that breaks the contract (the feature's wording). */
	invalid: InvalidResponse;
	/**
	 * Accepted `mime_type` values; an item of another type is invalid. Omit to accept any file
	 * (the item then needs no `mime_type`, for requests whose `_fields` leave it out).
	 */
	accept?: readonly string[];
	/**
	 * When `thumbnail_url` falls back to the full image. `'unusable'` (default): whenever the
	 * `thumbnail` size is missing, malformed or empty. `'missing'`: only when it is absent; a
	 * malformed `media_details`, `sizes` or `thumbnail` makes the item invalid.
	 */
	fallback?: 'unusable' | 'missing';
}

function member( value: unknown, key: string ): unknown {
	return value && 'object' === typeof value
		? ( value as Record< string, unknown > )[ key ]
		: undefined;
}

/**
 * Check one `/wp/v2/media` item.
 *
 * @param value            Decoded JSON item.
 * @param options          Parse options.
 * @param options.invalid  Message or error factory for invalid items.
 * @param options.accept   Accepted mime types; omit for any.
 * @param options.fallback When the thumbnail falls back to the full image.
 * @return The image.
 */
export function parseMediaImage(
	value: unknown,
	{ invalid, accept, fallback = 'unusable' }: MediaParseOptions
): MediaImage {
	const { record, text, positiveId } = createValidators( invalid );
	const item = record( value );
	if ( accept && ! accept.includes( text( item.mime_type ) ) ) {
		throw invalidResponseError( invalid );
	}
	const url = text( item.source_url );
	let thumbnail = url;
	if ( 'missing' === fallback ) {
		const details = item.media_details ? record( item.media_details ) : {};
		const sizes = details.sizes ? record( details.sizes ) : {};
		if ( sizes.thumbnail ) {
			thumbnail = text( record( sizes.thumbnail ).source_url );
		}
	} else {
		const source = member(
			member( member( item.media_details, 'sizes' ), 'thumbnail' ),
			'source_url'
		);
		if ( 'string' === typeof source && source ) {
			thumbnail = source;
		}
	}
	const title = item.title ? record( item.title ).rendered : '';
	return {
		id: positiveId( item.id ),
		url,
		thumbnail_url: thumbnail,
		title: 'string' === typeof title ? title : '',
	};
}
