// @ts-check
/**
 * Which provider plays a lesson video URL. Mirrors OhMyLMS\Learning\VideoSource::parse(), which
 * decides the markup on the server; the client uses it for markup written by theme overrides and
 * for the adapters. Pure, tested by tests/js/video.test.mjs with the same cases as the PHP suite.
 */

/**
 * @typedef {Object} VideoSource
 * @property {''|'html5'|'youtube'|'vimeo'|'bunny'|'wistia'|'embed'} provider Provider ('' for no usable URL).
 * @property {string}                                                id       Provider video id ('' for html5 and embed).
 * @property {string}                                                hash     Vimeo privacy hash of an unlisted video.
 * @property {string}                                                embed    Iframe URL for iframe providers, the file URL for html5.
 */

/** Providers whose progress can be tracked (an `embed` is any other iframe). */
export const TRACKABLE = Object.freeze( [
	'html5',
	'youtube',
	'vimeo',
	'bunny',
	'wistia',
] );

/** Providers the custom controls can drive; the others keep their own controls. */
export const CUSTOM = Object.freeze( [ 'html5', 'youtube', 'vimeo' ] );

const FILE = /\.(mp4|webm|ogg|ogv|mov|m4v|avi|flv|mkv)(\?.*)?$/i;

const MIME = Object.freeze( {
	mp4: 'video/mp4',
	m4v: 'video/mp4',
	webm: 'video/webm',
	ogg: 'video/ogg',
	ogv: 'video/ogg',
	mov: 'video/quicktime',
	avi: 'video/x-msvideo',
	flv: 'video/x-flv',
	mkv: 'video/x-matroska',
} );

/**
 * Parse a URL without throwing.
 *
 * @param {string} url URL.
 * @return {URL|null} Parsed URL (absolute http/https only).
 */
function parse( url ) {
	try {
		const parsed = new URL( url );
		return /^https?:$/.test( parsed.protocol ) ? parsed : null;
	} catch {
		return null;
	}
}

/**
 * YouTube video id (11 characters) of a watch, short, embed, shorts or live URL.
 *
 * @param {string} url URL.
 * @return {string} Id, '' when none.
 */
export function youtubeId( url ) {
	const parsed = parse( url );
	if ( ! parsed ) {
		return '';
	}
	const host = parsed.hostname.replace( /^(www\.|m\.)/, '' );
	let id = '';
	if ( 'youtu.be' === host ) {
		id = parsed.pathname.slice( 1 ).split( '/' )[ 0 ];
	} else if (
		[ 'youtube.com', 'youtube-nocookie.com', 'music.youtube.com' ].includes(
			host
		)
	) {
		const match = parsed.pathname.match(
			/^\/(?:embed|v|e|shorts|live)\/([^/?#]+)/
		);
		id = match ? match[ 1 ] : parsed.searchParams.get( 'v' ) || '';
	}
	return /^[A-Za-z0-9_-]{11}$/.test( id ) ? id : '';
}

/**
 * Vimeo id and privacy hash of a vimeo.com or player.vimeo.com URL.
 *
 * @param {string} url URL.
 * @return {{id: string, hash: string}} Id ('' when none) and hash.
 */
export function vimeoId( url ) {
	const parsed = parse( url );
	if ( ! parsed || ! /(^|\.)vimeo\.com$/.test( parsed.hostname ) ) {
		return { id: '', hash: '' };
	}
	const match = parsed.pathname.match(
		/\/(\d{3,})(?:\/([0-9a-f]{6,}))?\/?$/i
	);
	if ( ! match ) {
		return { id: '', hash: '' };
	}
	return {
		id: match[ 1 ],
		hash: match[ 2 ] || parsed.searchParams.get( 'h' ) || '',
	};
}

/**
 * Bunny Stream library and video ids (`/embed/<library>/<video>` or `/play/…`).
 *
 * @param {string} url URL.
 * @return {{library: string, id: string}} Ids ('' when not a Bunny URL).
 */
export function bunnyId( url ) {
	const parsed = parse( url );
	const none = { library: '', id: '' };
	if (
		! parsed ||
		! /(^|\.)(mediadelivery\.net|bunnycdn\.com)$/.test( parsed.hostname )
	) {
		return none;
	}
	const match = parsed.pathname.match(
		/^\/(?:embed|play)\/(\d+)\/([0-9a-f-]{36})\/?$/i
	);
	return match ? { library: match[ 1 ], id: match[ 2 ] } : none;
}

/**
 * Wistia media id of a media page, embed or iframe URL.
 *
 * @param {string} url URL.
 * @return {string} Id, '' when none.
 */
export function wistiaId( url ) {
	const parsed = parse( url );
	if (
		! parsed ||
		! /(^|\.)(wistia\.com|wistia\.net|wi\.st)$/.test( parsed.hostname )
	) {
		return '';
	}
	const match = parsed.pathname.match(
		/\/(?:medias|embed\/iframe|embed\/medias|embed)\/([a-z0-9]{10})(?:\.jsonp)?\/?$/i
	);
	return match ? match[ 1 ] : '';
}

/**
 * Provider of a video URL.
 *
 * @param {string}  url           Video URL.
 * @param {Object}  [opts]        Options.
 * @param {boolean} [opts.custom] Whether the site's custom player is on: then any other http(s) URL is
 *                                treated as a video file (as the old player did), else it is an embed.
 * @return {VideoSource} Source.
 */
export function detectSource( url, { custom = true } = {} ) {
	const value = String( url || '' ).trim();
	const none = { provider: '', id: '', hash: '', embed: '' };
	if ( ! value ) {
		return /** @type {VideoSource} */ ( none );
	}
	const youtube = youtubeId( value );
	if ( youtube ) {
		return {
			provider: 'youtube',
			id: youtube,
			hash: '',
			embed: `https://www.youtube.com/embed/${ youtube }`,
		};
	}
	const vimeo = vimeoId( value );
	if ( vimeo.id ) {
		return {
			provider: 'vimeo',
			id: vimeo.id,
			hash: vimeo.hash,
			embed: `https://player.vimeo.com/video/${ vimeo.id }`,
		};
	}
	const bunny = bunnyId( value );
	if ( bunny.id ) {
		return {
			provider: 'bunny',
			id: bunny.id,
			hash: '',
			embed: `https://iframe.mediadelivery.net/embed/${ bunny.library }/${ bunny.id }`,
		};
	}
	const wistia = wistiaId( value );
	if ( wistia ) {
		return {
			provider: 'wistia',
			id: wistia,
			hash: '',
			embed: `https://fast.wistia.net/embed/iframe/${ wistia }`,
		};
	}
	const web = parse( value );
	const relative = value.startsWith( '/' ) && ! value.startsWith( '//' );
	if ( ! web && ! relative ) {
		return /** @type {VideoSource} */ ( none );
	}
	const file = FILE.test( web ? web.pathname : value );
	if ( file || custom ) {
		return { provider: 'html5', id: '', hash: '', embed: value };
	}
	return { provider: 'embed', id: '', hash: '', embed: value };
}

/**
 * MIME type for a `<source>` of a video file (MP4 when unknown, as before).
 *
 * @param {string} url File URL.
 * @return {string} Type.
 */
export function mimeType( url ) {
	const path = String( url || '' )
		.split( /[?#]/ )[ 0 ]
		.toLowerCase();
	const extension = path.includes( '.' ) ? path.split( '.' ).pop() : '';
	return (
		MIME[ /** @type {keyof typeof MIME} */ ( extension ) ] || 'video/mp4'
	);
}
