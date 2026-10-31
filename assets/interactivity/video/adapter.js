// @ts-check
/**
 * Shared pieces of the `ohmylms/video` provider adapters (html5.js, youtube.js, vimeo.js,
 * playerjs.js, wistia.js). Each adapter turns one provider's player into the same small interface,
 * so the store and the progress session never branch on the provider.
 */

/**
 * @typedef {'play'|'pause'|'ended'|'time'|'duration'|'seeked'|'rate'|'volume'|'captions'|'error'} AdapterEvent
 *
 * - `time` carries the position in seconds; `duration` the duration; `rate` the speed;
 *   `volume` `{volume, muted}`; `captions` `{available, on}`; `error` `{message}`.
 * - Every `createAdapter()` resolves once the player is ready, so there is no ready event.
 */

/**
 * @typedef {Object} Adapter
 * @property {string}                                                 provider    Provider name.
 * @property {() => void}                                             play        Start playback.
 * @property {() => void}                                             pause       Pause.
 * @property {(seconds: number) => void}                              seek        Seek.
 * @property {() => number}                                           time        Position (cached, synchronous).
 * @property {() => number}                                           duration    Duration, 0 when unknown.
 * @property {() => boolean}                                          paused      Whether paused.
 * @property {() => number}                                           rate        Current speed.
 * @property {(rate: number) => void}                                 setRate     Change speed.
 * @property {(volume: number) => void}                               setVolume   Volume 0 to 1.
 * @property {(muted: boolean) => void}                               setMuted    Mute or unmute.
 * @property {(on: boolean) => void}                                  setCaptions Show or hide captions.
 * @property {{rates: boolean, volume: boolean, captions: boolean}}   supports    What the custom controls may offer.
 * @property {HTMLMediaElement|null}                                  media       The `<video>` (fullscreen on iPhone).
 * @property {(name: AdapterEvent, callback: Listener) => () => void} on          Subscribe; returns unsubscribe.
 * @property {() => void}                                             destroy     Remove listeners and timers.
 */

/** @typedef {(value?: ?) => void} Listener Adapter event listener (the value depends on the event). */

/** @typedef {(src: string, doc?: Document) => Promise<void>} ScriptLoader Loads a third-party script once. */

/**
 * @typedef {Window & Object<string, ?>} ProviderWindow Window with the provider globals (`YT`, `Vimeo`, `_wq`).
 */

/**
 * Minimal event emitter.
 *
 * @return {{on: (name: string, callback: Listener) => () => void, emit: (name: string, value?: unknown) => void, clear: () => void}} Emitter.
 */
export function createEmitter() {
	/** @type {Map<string, Set<Listener>>} */
	const listeners = new Map();
	return {
		on( name, callback ) {
			if ( ! listeners.has( name ) ) {
				listeners.set( name, new Set() );
			}
			listeners.get( name ).add( callback );
			return () => listeners.get( name )?.delete( callback );
		},
		emit( name, value ) {
			for ( const callback of [ ...( listeners.get( name ) || [] ) ] ) {
				try {
					callback( value );
				} catch ( error ) {
					// A failing listener must not stop the others or the player.
					globalThis.console?.error?.( error );
				}
			}
		},
		clear() {
			listeners.clear();
		},
	};
}

/** @type {Map<string, Promise<void>>} */
const scripts = new Map();

/**
 * Load a third-party script once per page (the provider API), resolving when it ran.
 *
 * @param {string}   src   Script URL.
 * @param {Document} [doc] Document.
 * @return {Promise<void>} Loaded.
 */
export function loadScript( src, doc = globalThis.document ) {
	if ( scripts.has( src ) ) {
		return scripts.get( src );
	}
	const loading = new Promise( ( resolve, reject ) => {
		const existing = doc.querySelector( `script[src="${ src }"]` );
		const script = existing || doc.createElement( 'script' );
		script.addEventListener( 'load', () => resolve(), { once: true } );
		script.addEventListener(
			'error',
			() => {
				scripts.delete( src );
				reject( new Error( `Could not load ${ src }` ) );
			},
			{ once: true }
		);
		if ( ! existing ) {
			script.src = src;
			script.async = true;
			doc.head.appendChild( script );
		}
	} );
	scripts.set( src, loading );
	return loading;
}

/**
 * Clamp a volume to 0..1.
 *
 * @param {number} volume Volume.
 * @return {number} Clamped volume.
 */
export const clampVolume = ( volume ) =>
	Math.max( 0, Math.min( 1, Number( volume ) || 0 ) );

/**
 * A number when finite and positive, else 0.
 *
 * @param {unknown} value Value.
 * @return {number} Number.
 */
export const positive = ( value ) => {
	const number = Number( value );
	return Number.isFinite( number ) && number > 0 ? number : 0;
};
