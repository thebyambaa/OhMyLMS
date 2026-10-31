// @ts-check
/**
 * `ohmylms/video` adapter for iframes that speak the player.js protocol (Bunny Stream, and any other
 * player.js receiver). It talks to the iframe with `postMessage` directly, so no third-party script
 * is loaded. The provider keeps its own controls (speed, captions, keyboard): player.js has no speed
 * or caption methods, so the custom controls are not offered for it.
 */
import { clampVolume, createEmitter, positive } from './adapter.js';

export const CONTEXT = 'player.js';
export const VERSION = '0.0.11';

/** Receiver events the adapter listens to. */
export const LISTEN = Object.freeze( [
	'play',
	'pause',
	'ended',
	'timeupdate',
	'seeked',
	'error',
] );

/**
 * A player.js message.
 *
 * @param {string}  method     Method.
 * @param {unknown} [value]    Value.
 * @param {string}  [listener] Listener id for answers.
 * @return {string} JSON.
 */
export function message( method, value, listener ) {
	/** @type {Object<string, unknown>} */
	const data = { context: CONTEXT, version: VERSION, method };
	if ( undefined !== value ) {
		data.value = value;
	}
	if ( listener ) {
		data.listener = listener;
	}
	return JSON.stringify( data );
}

/**
 * Parse a message from a receiver; null when it is not a player.js message.
 *
 * @param {unknown} data Message data (string or object).
 * @return {{event: string, value?: ?, listener?: string}|null} Message.
 */
export function parseMessage( data ) {
	let parsed = data;
	if ( 'string' === typeof data ) {
		try {
			parsed = JSON.parse( data );
		} catch {
			return null;
		}
	}
	if (
		! parsed ||
		'object' !== typeof parsed ||
		CONTEXT !== parsed.context ||
		'string' !== typeof parsed.event
	) {
		return null;
	}
	return parsed;
}

/**
 * Adapter for a player.js iframe.
 *
 * @param {Object}            options        Options.
 * @param {HTMLIFrameElement} options.iframe The iframe.
 * @param {Window}            [options.win]  Window.
 * @param {number}            [options.wait] Milliseconds to wait for the receiver.
 * @return {Promise<import('./adapter.js').Adapter>} Adapter.
 */
export function createAdapter( {
	iframe,
	win = globalThis.window,
	wait = 10000,
} ) {
	const events = createEmitter();
	let origin = '*';
	try {
		origin = new URL( iframe.src ).origin;
	} catch {
		// Keep '*': the receiver is still the iframe's own window.
	}
	let time = 0;
	let duration = 0;
	let paused = true;
	let counter = 0;
	/** @type {Map<string, (value: ?) => void>} */
	const answers = new Map();
	const send = ( method, value, listener ) =>
		iframe.contentWindow?.postMessage(
			message( method, value, listener ),
			origin
		);
	const ask = ( method ) =>
		new Promise( ( resolve ) => {
			const id = `ohmylms-${ ++counter }`;
			answers.set( id, resolve );
			send( method, undefined, id );
		} );

	return new Promise( ( resolve, reject ) => {
		let ready = false;
		let pings = null;
		const started = Date.now();
		/** @type {import('./adapter.js').Adapter} */
		const adapter = {
			provider: 'playerjs',
			media: null,
			supports: { rates: false, volume: true, captions: false },
			play() {
				paused = false;
				send( 'play' );
			},
			pause() {
				paused = true;
				send( 'pause' );
			},
			seek( seconds ) {
				time = Math.max( 0, seconds );
				send( 'setCurrentTime', time );
				events.emit( 'time', time );
			},
			time: () => time,
			duration: () => duration,
			paused: () => paused,
			rate: () => 1,
			setRate() {},
			setVolume( volume ) {
				const level = clampVolume( volume );
				send( 'setVolume', Math.round( level * 100 ) );
				send( 0 === level ? 'mute' : 'unmute' );
				events.emit( 'volume', { volume: level, muted: 0 === level } );
			},
			setMuted( muted ) {
				send( muted ? 'mute' : 'unmute' );
			},
			setCaptions() {},
			on: events.on,
			destroy() {
				clearInterval( pings );
				win.removeEventListener( 'message', receive );
				events.clear();
			},
		};
		const becomeReady = () => {
			if ( ready ) {
				return;
			}
			ready = true;
			clearInterval( pings );
			for ( const name of LISTEN ) {
				send( 'addEventListener', name );
			}
			ask( 'getDuration' ).then( ( value ) => {
				duration = positive( value );
				if ( duration ) {
					events.emit( 'duration', duration );
				}
			} );
			resolve( adapter );
		};
		/** @param {MessageEvent} event Message. */
		function receive( event ) {
			if ( event.source !== iframe.contentWindow ) {
				return;
			}
			const data = parseMessage( event.data );
			if ( ! data ) {
				return;
			}
			if ( data.listener && answers.has( data.listener ) ) {
				answers.get( data.listener )( data.value );
				answers.delete( data.listener );
			}
			becomeReady();
			switch ( data.event ) {
				case 'timeupdate': {
					time = Number( data.value?.seconds ) || 0;
					const length = positive( data.value?.duration );
					if ( length && length !== duration ) {
						duration = length;
						events.emit( 'duration', duration );
					}
					events.emit( 'time', time );
					break;
				}
				case 'play':
					paused = false;
					events.emit( 'play', time );
					break;
				case 'pause':
					paused = true;
					events.emit( 'pause', time );
					break;
				case 'ended':
					paused = true;
					events.emit( 'ended', time );
					break;
				case 'seeked':
					events.emit( 'seeked', time );
					break;
				case 'error':
					events.emit( 'error', { key: 'loadError' } );
					break;
			}
		}
		win.addEventListener( 'message', receive );
		// The receiver announces `ready` once; if the iframe loaded before this module, ask again.
		const ping = () => {
			if ( Date.now() - started > wait ) {
				clearInterval( pings );
				win.removeEventListener( 'message', receive );
				reject( { key: 'loadError' } );
				return;
			}
			send( 'addEventListener', 'ready' );
		};
		ping();
		pings = setInterval( ping, 500 );
	} );
}
