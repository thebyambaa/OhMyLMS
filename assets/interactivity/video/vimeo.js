// @ts-check
/**
 * `ohmylms/video` adapter for Vimeo, on the Vimeo Player SDK (player.vimeo.com/api/player.js,
 * loaded only when a Vimeo video is on the page). The custom player creates a player without
 * Vimeo's controls; a server-rendered iframe keeps them. Captions are Vimeo's text tracks.
 */
import { clampVolume, createEmitter, loadScript, positive } from './adapter.js';

export const API_URL = 'https://player.vimeo.com/api/player.js';

/**
 * Load the Player SDK once.
 *
 * @param {import('./adapter.js').ProviderWindow} win    Window.
 * @param {import('./adapter.js').ScriptLoader}   [load] Script loader.
 * @return {Promise<?>} `Vimeo`.
 */
export async function loadVimeo( win, load = loadScript ) {
	if ( win.Vimeo && 'function' === typeof win.Vimeo.Player ) {
		return win.Vimeo;
	}
	await load( API_URL, win.document );
	return win.Vimeo;
}

/**
 * Options of a player created by the custom controls.
 *
 * @param {{id: string, hash?: string}} source        Parsed source.
 * @param {Object}                      config        `loop`, `muted`, `custom`.
 * @param                               config.loop
 * @param                               config.muted
 * @param                               config.custom
 * @return {Object} Player options.
 */
export function playerOptions(
	source,
	{ loop = false, muted = false, custom = true } = {}
) {
	/** @type {Object<string, unknown>} */
	const options = {
		autoplay: false,
		loop: Boolean( loop ),
		muted: Boolean( muted ),
		controls: ! custom,
		title: false,
		byline: false,
		portrait: false,
		playsinline: true,
		badge: false,
		transparent: false,
		dnt: true,
		keyboard: ! custom,
	};
	if ( source.hash ) {
		options.url = `https://vimeo.com/${ source.id }/${ source.hash }`;
	} else {
		options.id = Number( source.id );
	}
	return options;
}

/**
 * Text track to show: the page language, else the first.
 *
 * @param {Array<{language: string, kind: string, mode?: string}>} tracks Tracks.
 * @param {string}                                                 lang   Page language.
 * @return {{language: string, kind: string}|null} Track.
 */
export function pickTrack( tracks, lang = '' ) {
	const list = Array.isArray( tracks ) ? tracks : [];
	const short = String( lang ).toLowerCase().split( '-' )[ 0 ];
	return (
		list.find(
			( track ) =>
				String( track.language ).toLowerCase().split( '-' )[ 0 ] ===
				short
		) ||
		list[ 0 ] ||
		null
	);
}

/**
 * Adapter for a Vimeo video.
 *
 * @param {Object}                                options          Options.
 * @param {HTMLElement}                           [options.mount]  Where the custom player creates the iframe.
 * @param {HTMLIFrameElement}                     [options.iframe] Server-rendered iframe.
 * @param {{id: string, hash?: string}}           options.source   Parsed source.
 * @param {Object}                                [options.config] `loop`, `muted`, `custom`, `title`.
 * @param {import('./adapter.js').ProviderWindow} [options.win]    Window.
 * @param {import('./adapter.js').ScriptLoader}   [options.load]   Script loader (tests).
 * @return {Promise<import('./adapter.js').Adapter>} Adapter.
 */
export async function createAdapter( {
	mount,
	iframe,
	source,
	config = {},
	win = globalThis.window,
	load = loadScript,
} ) {
	const Vimeo = await loadVimeo( win, load );
	const doc = win.document;
	const custom = Boolean( config.custom ) && ! iframe;
	const events = createEmitter();
	let time = 0;
	let duration = 0;
	let paused = true;
	let rate = 1;
	let tracks = [];

	let host = iframe;
	if ( ! host ) {
		host = doc.createElement( 'div' );
		mount.replaceChildren( host );
	}
	const player = iframe
		? new Vimeo.Player( iframe )
		: new Vimeo.Player(
				host,
				playerOptions( source, { ...config, custom } )
			);
	await player.ready();

	try {
		duration = positive( await player.getDuration() );
		paused = await player.getPaused();
		tracks = ( await player.getTextTracks() ) || [];
	} catch {
		// Private or still processing videos answer some getters with errors; the events still come.
	}
	const frame =
		host.tagName === 'IFRAME' ? host : host.querySelector( 'iframe' );
	if ( frame ) {
		if ( custom ) {
			frame.tabIndex = -1;
			frame.setAttribute( 'aria-hidden', 'true' );
		}
		if ( config.title ) {
			frame.title = String( config.title );
		}
	}

	player.on( 'play', ( data ) => {
		paused = false;
		events.emit( 'play', data?.seconds ?? time );
	} );
	player.on( 'pause', ( data ) => {
		paused = true;
		events.emit( 'pause', data?.seconds ?? time );
	} );
	player.on( 'ended', ( data ) => {
		paused = true;
		events.emit( 'ended', data?.seconds ?? time );
	} );
	player.on( 'timeupdate', ( data ) => {
		time = Number( data?.seconds ) || 0;
		const length = positive( data?.duration );
		if ( length && length !== duration ) {
			duration = length;
			events.emit( 'duration', duration );
		}
		events.emit( 'time', time );
	} );
	player.on( 'durationchange', ( data ) => {
		duration = positive( data?.duration );
		events.emit( 'duration', duration );
	} );
	player.on( 'seeked', ( data ) => {
		time = Number( data?.seconds ) || time;
		events.emit( 'seeked', time );
	} );
	player.on( 'playbackratechange', ( data ) => {
		rate = Number( data?.playbackRate ) || 1;
		events.emit( 'rate', rate );
	} );
	player.on( 'volumechange', ( data ) => {
		const volume = clampVolume( data?.volume );
		events.emit( 'volume', { volume, muted: 0 === volume } );
	} );
	player.on( 'texttrackchange', ( data ) => {
		events.emit( 'captions', {
			available: tracks.length > 0,
			on: Boolean( data?.language ),
		} );
	} );
	player.on( 'error', ( data ) =>
		events.emit( 'error', {
			key: 'loadError',
			message: data?.message || '',
		} )
	);

	/** @type {import('./adapter.js').Adapter} */
	return {
		provider: 'vimeo',
		media: null,
		supports: { rates: true, volume: true, captions: tracks.length > 0 },
		play() {
			paused = false;
			player.play().catch( () => {
				paused = true;
				events.emit( 'pause', time );
			} );
		},
		pause() {
			paused = true;
			player.pause().catch( () => {} );
		},
		seek( seconds ) {
			time = Math.max(
				0,
				duration ? Math.min( duration, seconds ) : seconds
			);
			events.emit( 'time', time );
			player.setCurrentTime( time ).catch( () => {} );
		},
		time: () => time,
		duration: () => duration,
		paused: () => paused,
		rate: () => rate,
		setRate( next ) {
			// Speed needs a Vimeo plan that allows it; a refusal keeps the actual speed.
			player
				.setPlaybackRate( next )
				.catch( () => events.emit( 'rate', rate ) );
		},
		setVolume( volume ) {
			const level = clampVolume( volume );
			player.setVolume( level ).catch( () => {} );
			player.setMuted?.( 0 === level )?.catch?.( () => {} );
		},
		setMuted( muted ) {
			player.setMuted?.( Boolean( muted ) )?.catch?.( () => {} );
			player
				.getVolume()
				.then( ( volume ) =>
					events.emit( 'volume', {
						volume: clampVolume( volume ),
						muted: Boolean( muted ),
					} )
				)
				.catch( () => {} );
		},
		setCaptions( on ) {
			const track = on
				? pickTrack( tracks, doc.documentElement?.lang || '' )
				: null;
			const done = track
				? player.enableTextTrack( track.language, track.kind )
				: player.disableTextTrack();
			Promise.resolve( done )
				.then( () =>
					events.emit( 'captions', {
						available: tracks.length > 0,
						on: Boolean( track ),
					} )
				)
				.catch( () => {} );
		},
		on: events.on,
		destroy() {
			events.clear();
			player.destroy?.().catch?.( () => {} );
		},
	};
}
