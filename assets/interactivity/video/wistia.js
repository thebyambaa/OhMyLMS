// @ts-check
/**
 * `ohmylms/video` adapter for Wistia iframe embeds, on Wistia's player API (E-v1.js, loaded only when
 * a Wistia video is on the page). The server renders the iframe with the `wistia_embed` class the API
 * looks for; Wistia keeps its own (accessible) controls, captions and speed menu.
 */
import { clampVolume, createEmitter, loadScript, positive } from './adapter.js';

export const API_URL = 'https://fast.wistia.com/assets/external/E-v1.js';

/**
 * Wait for the Wistia handle of one media (`_wq` queue, which E-v1.js drains).
 *
 * @param {import('./adapter.js').ProviderWindow} win    Window.
 * @param {string}                                id     Hashed media id.
 * @param {import('./adapter.js').ScriptLoader}   [load] Script loader.
 * @param {number}                                [wait] Milliseconds before giving up.
 * @return {Promise<?>} Wistia video handle.
 */
export function wistiaVideo( win, id, load = loadScript, wait = 15000 ) {
	return new Promise( ( resolve, reject ) => {
		const timer = setTimeout( () => reject( { key: 'loadError' } ), wait );
		win._wq = win._wq || [];
		win._wq.push( {
			id,
			onReady( video ) {
				clearTimeout( timer );
				resolve( video );
			},
		} );
		load( API_URL, win.document ).catch( ( error ) => {
			clearTimeout( timer );
			reject( error );
		} );
	} );
}

/**
 * Adapter for a Wistia media.
 *
 * @param {Object}                                options        Options.
 * @param {{id: string}}                          options.source Parsed source.
 * @param {import('./adapter.js').ProviderWindow} [options.win]  Window.
 * @param {import('./adapter.js').ScriptLoader}   [options.load] Script loader (tests).
 * @return {Promise<import('./adapter.js').Adapter>} Adapter.
 */
export async function createAdapter( {
	source,
	win = globalThis.window,
	load = loadScript,
} ) {
	const video = await wistiaVideo( win, source.id, load );
	const events = createEmitter();
	const now = () => Number( video.time() ) || 0;
	video.bind( 'play', () => events.emit( 'play', now() ) );
	video.bind( 'pause', () => events.emit( 'pause', now() ) );
	video.bind( 'end', () => events.emit( 'ended', now() ) );
	video.bind( 'timechange', ( seconds ) =>
		events.emit( 'time', Number( seconds ) || 0 )
	);
	video.bind( 'seek', ( current ) =>
		events.emit( 'seeked', Number( current ) || 0 )
	);
	video.bind( 'playbackratechange', ( rate ) =>
		events.emit( 'rate', Number( rate ) || 1 )
	);
	video.bind( 'volumechange', ( volume, muted ) =>
		events.emit( 'volume', {
			volume: clampVolume( volume ),
			muted: Boolean( muted ),
		} )
	);

	/** @type {import('./adapter.js').Adapter} */
	return {
		provider: 'wistia',
		media: null,
		supports: { rates: true, volume: true, captions: false },
		play: () => video.play(),
		pause: () => video.pause(),
		seek: ( seconds ) => video.time( Math.max( 0, seconds ) ),
		time: now,
		duration: () => positive( video.duration() ),
		paused: () => 'playing' !== video.state(),
		rate: () => Number( video.playbackRate() ) || 1,
		setRate: ( rate ) => video.playbackRate( rate ),
		setVolume: ( volume ) => video.volume( clampVolume( volume ) ),
		setMuted: ( muted ) => ( muted ? video.mute() : video.unmute() ),
		setCaptions() {},
		on: events.on,
		destroy() {
			events.clear();
			video.unbind?.( 'play' );
			video.unbind?.( 'pause' );
			video.unbind?.( 'end' );
			video.unbind?.( 'timechange' );
		},
	};
}
