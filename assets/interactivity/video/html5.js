// @ts-check
/**
 * `ohmylms/video` adapter for a `<video>` element (uploaded or self-hosted file). The server renders
 * the element with native controls, so it plays without JavaScript; the custom player removes them
 * once its own controls are mounted. Captions are the element's `<track>`s.
 */
import { clampVolume, createEmitter, positive } from './adapter.js';

/** Media events forwarded to the adapter events. */
const FORWARD = Object.freeze( {
	play: 'play',
	pause: 'pause',
	ended: 'ended',
	timeupdate: 'time',
	durationchange: 'duration',
	loadedmetadata: 'duration',
	seeked: 'seeked',
	ratechange: 'rate',
	volumechange: 'volume',
} );

/**
 * Caption tracks of a video (`subtitles` and `captions` kinds).
 *
 * @param {HTMLMediaElement} video Video.
 * @return {TextTrack[]} Tracks.
 */
export function captionTracks( video ) {
	const tracks = video?.textTracks ? Array.from( video.textTracks ) : [];
	return tracks.filter( ( track ) =>
		[ 'subtitles', 'captions' ].includes( track.kind )
	);
}

/**
 * Show one caption track or none: the default one, else the one in the page language, else the first.
 *
 * @param {TextTrack[]} tracks Tracks.
 * @param {boolean}     on     Show captions.
 * @param {string}      [lang] Page language, e.g. `en-US`.
 * @return {TextTrack|null} Track shown.
 */
export function showCaptions( tracks, on, lang = '' ) {
	const short = String( lang ).toLowerCase().split( '-' )[ 0 ];
	const chosen = on
		? tracks.find( ( track ) => 'showing' === track.mode ) ||
			tracks.find(
				( track ) =>
					String( track.language ).toLowerCase().split( '-' )[ 0 ] ===
					short
			) ||
			tracks[ 0 ] ||
			null
		: null;
	for ( const track of tracks ) {
		track.mode = track === chosen ? 'showing' : 'hidden';
	}
	return chosen;
}

/**
 * Adapter for a `<video>`.
 *
 * @param {Object}           options          Options.
 * @param {HTMLMediaElement} options.video    The element.
 * @param {boolean}          [options.custom] Custom controls replace the native ones.
 * @return {Promise<import('./adapter.js').Adapter>} Adapter, resolved once metadata is known (or after 2 s).
 */
export function createAdapter( { video, custom = false } ) {
	const events = createEmitter();
	const doc = video.ownerDocument;
	if ( custom ) {
		video.controls = false;
		video.removeAttribute( 'controls' );
	}
	/** @type {Array<[string, (event: Event) => void]>} */
	const bound = [];
	for ( const [ type, name ] of Object.entries( FORWARD ) ) {
		const listener = () => {
			if ( 'time' === name ) {
				events.emit( 'time', video.currentTime );
			} else if ( 'duration' === name ) {
				events.emit( 'duration', positive( video.duration ) );
			} else if ( 'rate' === name ) {
				events.emit( 'rate', video.playbackRate );
			} else if ( 'volume' === name ) {
				events.emit( 'volume', {
					volume: video.volume,
					muted: video.muted,
				} );
			} else {
				events.emit( name, video.currentTime );
			}
		};
		video.addEventListener( type, listener );
		bound.push( [ type, listener ] );
	}
	const failed = () =>
		events.emit( 'error', {
			message: video.error?.message || '',
			code: video.error?.code || 0,
		} );
	video.addEventListener( 'error', failed );
	bound.push( [ 'error', failed ] );
	const lastSource = video.querySelector( 'source:last-of-type' );
	if ( lastSource ) {
		lastSource.addEventListener( 'error', failed );
	}

	const tracks = () => captionTracks( video );
	/** @type {import('./adapter.js').Adapter} */
	const adapter = {
		provider: 'html5',
		media: video,
		supports: { rates: true, volume: true, captions: tracks().length > 0 },
		play() {
			const started = video.play();
			if ( started && 'function' === typeof started.catch ) {
				started.catch( ( error ) => {
					if ( 'AbortError' !== error?.name ) {
						events.emit( 'pause', video.currentTime );
					}
				} );
			}
		},
		pause: () => video.pause(),
		seek( seconds ) {
			const end = positive( video.duration );
			video.currentTime = Math.max(
				0,
				end ? Math.min( end, seconds ) : seconds
			);
		},
		time: () =>
			Number.isFinite( video.currentTime ) ? video.currentTime : 0,
		duration: () => positive( video.duration ),
		paused: () => video.paused,
		rate: () => video.playbackRate || 1,
		setRate( rate ) {
			video.playbackRate = rate;
		},
		setVolume( volume ) {
			video.volume = clampVolume( volume );
			video.muted = 0 === video.volume;
		},
		setMuted( muted ) {
			video.muted = Boolean( muted );
		},
		setCaptions( on ) {
			const shown = showCaptions(
				tracks(),
				on,
				doc?.documentElement?.lang || ''
			);
			events.emit( 'captions', {
				available: tracks().length > 0,
				on: Boolean( shown ),
			} );
		},
		on: events.on,
		destroy() {
			for ( const [ type, listener ] of bound ) {
				video.removeEventListener( type, listener );
			}
			lastSource?.removeEventListener( 'error', failed );
			events.clear();
		},
	};

	return new Promise( ( resolve ) => {
		let done = false;
		const ready = () => {
			if ( done ) {
				return;
			}
			done = true;
			video.removeEventListener( 'loadedmetadata', ready );
			resolve( adapter );
		};
		if ( video.readyState >= 1 ) {
			ready();
			return;
		}
		video.addEventListener( 'loadedmetadata', ready );
		// Some servers answer metadata slowly (or not for range requests): the controls work anyway.
		setTimeout( ready, 2000 );
	} );
}
