// @ts-check
/**
 * `ohmylms/video` adapter for YouTube, on the IFrame Player API. The API script is loaded only
 * when a YouTube video is on the page (the module is a dynamic import of ohmylms/video). With the
 * custom player it creates the player in the mount point without YouTube's controls; for a
 * server-rendered `<iframe>` (custom player off) it attaches to it and keeps YouTube's controls.
 */
import { clampVolume, createEmitter, loadScript, positive } from './adapter.js';

export const API_URL = 'https://www.youtube.com/iframe_api';

/** `YT.PlayerState` values. */
export const STATE = Object.freeze( {
	UNSTARTED: -1,
	ENDED: 0,
	PLAYING: 1,
	PAUSED: 2,
	BUFFERING: 3,
	CUED: 5,
} );

/** IFrame API error codes and the message key of the store for each. */
export const ERRORS = Object.freeze( {
	2: 'videoInvalid',
	5: 'loadError',
	100: 'videoMissing',
	101: 'embedBlocked',
	150: 'embedBlocked',
} );

/** @type {WeakMap<object, Promise<?>>} */
const loading = new WeakMap();

/**
 * Load the IFrame API once, keeping a callback another script set on `onYouTubeIframeAPIReady`.
 *
 * @param {import('./adapter.js').ProviderWindow} win    Window.
 * @param {import('./adapter.js').ScriptLoader}   [load] Script loader.
 * @return {Promise<?>} `YT`.
 */
export function loadYouTube( win, load = loadScript ) {
	if ( win.YT && 'function' === typeof win.YT.Player ) {
		return Promise.resolve( win.YT );
	}
	if ( loading.has( win ) ) {
		return loading.get( win );
	}
	const ready = new Promise( ( resolve, reject ) => {
		const previous = win.onYouTubeIframeAPIReady;
		win.onYouTubeIframeAPIReady = ( ...args ) => {
			if ( 'function' === typeof previous ) {
				previous( ...args );
			}
			resolve( win.YT );
		};
		if ( win.YT && 'function' === typeof win.YT.ready ) {
			win.YT.ready( () => resolve( win.YT ) );
		}
		load( API_URL, win.document ).catch( ( error ) => {
			loading.delete( win );
			reject( error );
		} );
	} );
	loading.set( win, ready );
	return ready;
}

/**
 * Player parameters for a player created by the custom controls.
 *
 * @param {Object}  config          Player settings.
 * @param {string}  config.id       Video id.
 * @param {boolean} [config.loop]   Loop.
 * @param {boolean} [config.muted]  Start muted.
 * @param {boolean} [config.custom] Custom controls (YouTube's are hidden and its keyboard off).
 * @param {string}  origin          Page origin.
 * @return {Object<string, string|number>} `playerVars`.
 */
export function playerVars(
	{ id, loop = false, muted = false, custom = true },
	origin
) {
	/** @type {Object<string, string|number>} */
	const vars = {
		autoplay: 0,
		controls: custom ? 0 : 1,
		disablekb: custom ? 1 : 0,
		playsinline: 1,
		modestbranding: 1,
		rel: 0,
		iv_load_policy: 3,
		enablejsapi: 1,
		cc_load_policy: 0,
		origin,
	};
	if ( loop ) {
		vars.loop = 1;
		vars.playlist = id;
	}
	if ( muted ) {
		vars.mute = 1;
	}
	return vars;
}

/**
 * An iframe URL with the JS API switched on (needed to attach to a server-rendered embed).
 *
 * @param {string} src    Iframe src.
 * @param {string} origin Page origin.
 * @return {string} URL, unchanged when it already enables the API.
 */
export function withJsApi( src, origin ) {
	try {
		const url = new URL( src );
		if ( '1' === url.searchParams.get( 'enablejsapi' ) ) {
			return src;
		}
		url.searchParams.set( 'enablejsapi', '1' );
		url.searchParams.set( 'origin', origin );
		return url.toString();
	} catch {
		return src;
	}
}

/**
 * Adapter for a YouTube video.
 *
 * @param {Object}                                options          Options.
 * @param {HTMLElement}                           [options.mount]  Where the custom player creates the iframe.
 * @param {HTMLIFrameElement}                     [options.iframe] Server-rendered iframe to attach to.
 * @param {{id: string}}                          options.source   Parsed source.
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
	const YT = await loadYouTube( win, load );
	const doc = win.document;
	const origin = win.location?.origin || '';
	const custom = Boolean( config.custom ) && ! iframe;
	const events = createEmitter();
	let state = STATE.UNSTARTED;
	let time = 0;
	let duration = 0;
	let timer = null;
	let captionsOn = false;
	let ready = false;

	const poll = () => {
		const now = Number( player.getCurrentTime?.() ) || 0;
		const length = positive( player.getDuration?.() );
		if ( length && length !== duration ) {
			duration = length;
			events.emit( 'duration', duration );
		}
		if ( now !== time ) {
			time = now;
			events.emit( 'time', time );
		}
	};
	const watch = ( on ) => {
		clearInterval( timer );
		timer = on ? setInterval( poll, 250 ) : null;
	};

	let target;
	if ( iframe ) {
		const src = withJsApi( iframe.src, origin );
		if ( src !== iframe.src ) {
			iframe.src = src;
		}
		target = iframe;
	} else {
		target = doc.createElement( 'div' );
		mount.replaceChildren( target );
	}

	/** @type {?} */
	let player;
	/** @type {import('./adapter.js').Adapter} */
	const adapter = {
		provider: 'youtube',
		media: null,
		supports: { rates: true, volume: true, captions: false },
		play: () => player.playVideo(),
		pause: () => player.pauseVideo(),
		seek( seconds ) {
			time = Math.max( 0, seconds );
			player.seekTo( time, true );
			events.emit( 'time', time );
			events.emit( 'seeked', time );
		},
		time: () => time,
		duration: () => duration,
		paused: () => STATE.PLAYING !== state && STATE.BUFFERING !== state,
		rate: () => Number( player.getPlaybackRate?.() ) || 1,
		setRate: ( rate ) => player.setPlaybackRate( rate ),
		setVolume( volume ) {
			const level = clampVolume( volume );
			player.setVolume( Math.round( level * 100 ) );
			if ( level > 0 ) {
				player.unMute();
			} else {
				player.mute();
			}
			events.emit( 'volume', { volume: level, muted: 0 === level } );
		},
		setMuted( muted ) {
			if ( muted ) {
				player.mute();
			} else {
				player.unMute();
			}
			events.emit( 'volume', {
				volume: ( Number( player.getVolume?.() ) || 0 ) / 100,
				muted: Boolean( muted ),
			} );
		},
		setCaptions( on ) {
			captionsOn = Boolean( on );
			if ( captionsOn ) {
				player.loadModule?.( 'captions' );
			} else {
				player.unloadModule?.( 'captions' );
			}
			events.emit( 'captions', {
				available: adapter.supports.captions,
				on: captionsOn,
			} );
		},
		on: events.on,
		destroy() {
			watch( false );
			events.clear();
			player?.destroy?.();
		},
	};

	return new Promise( ( resolve, reject ) => {
		const handlers = {
			onReady() {
				ready = true;
				const frame = player.getIframe?.();
				if ( frame ) {
					frame.classList.add( 'player-ready' );
					if ( custom ) {
						// The custom controls are the keyboard interface; YouTube's iframe is not a tab stop.
						frame.tabIndex = -1;
						frame.setAttribute( 'aria-hidden', 'true' );
					}
					if ( config.title ) {
						frame.title = String( config.title );
					}
				}
				duration = positive( player.getDuration?.() );
				resolve( adapter );
				if ( duration ) {
					events.emit( 'duration', duration );
				}
			},
			onStateChange( event ) {
				state = event.data;
				poll();
				if ( STATE.PLAYING === state ) {
					watch( true );
					if ( ! adapter.supports.captions ) {
						const options = player.getOptions?.() || [];
						if ( options.includes?.( 'captions' ) ) {
							adapter.supports.captions = true;
							events.emit( 'captions', {
								available: true,
								on: captionsOn,
							} );
						}
					}
					events.emit( 'play', time );
				} else if ( STATE.PAUSED === state ) {
					watch( false );
					events.emit( 'pause', time );
				} else if ( STATE.ENDED === state ) {
					watch( false );
					events.emit( 'ended', time );
				}
			},
			onPlaybackRateChange( event ) {
				events.emit( 'rate', Number( event.data ) || 1 );
			},
			onError( event ) {
				const error = {
					code: event.data,
					key: ERRORS[ event.data ] || 'loadError',
				};
				if ( ready ) {
					events.emit( 'error', error );
				} else {
					reject( error );
				}
			},
		};
		player = iframe
			? new YT.Player( target, { events: handlers } )
			: new YT.Player( target, {
					videoId: source.id,
					playerVars: playerVars(
						{ id: source.id, ...config, custom },
						origin
					),
					events: handlers,
				} );
	} );
}
