import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import {
	RATES,
	addInterval,
	clampIntervals,
	createSaveClock,
	createWatchLog,
	formatTime,
	measure,
	menuIndex,
	mergeIntervals,
	percentOf,
	pointerRatio,
	progressFields,
	resumeAt,
	shortcut,
	sliderValue,
	timeText,
} from '../../assets/interactivity/video/progress.js';
import {
	TRACKABLE,
	detectSource,
	mimeType,
	vimeoId,
	youtubeId,
} from '../../assets/interactivity/video/sources.js';
import { createEmitter } from '../../assets/interactivity/video/adapter.js';
import * as html5 from '../../assets/interactivity/video/html5.js';
import * as youtube from '../../assets/interactivity/video/youtube.js';
import * as vimeo from '../../assets/interactivity/video/vimeo.js';
import * as playerjs from '../../assets/interactivity/video/playerjs.js';
import * as wistia from '../../assets/interactivity/video/wistia.js';
import {
	COMPLETE_ACTION,
	SAVE_ACTION,
	createSession,
	markLessonDone,
	sendFinal,
} from '../../assets/interactivity/video/session.js';
import {
	ADAPTERS,
	createVideo,
	errorMessage,
	installShims,
} from '../../assets/interactivity/video/store.js';

const tick = () => new Promise( ( resolve ) => setImmediate( resolve ) );

// --- Pure helpers ---------------------------------------------------------------------------

test( 'watched spans merge, measure and clamp', () => {
	assert.deepEqual(
		mergeIntervals( [ [ 10, 20 ], [ 0, 5 ], [ 18, 30 ], [ 5.2, 6 ] ] ),
		[
			[ 0, 6 ],
			[ 10, 30 ],
		],
		'overlaps and gaps under a quarter second are joined, the list is sorted'
	);
	assert.deepEqual(
		mergeIntervals( [ [ 0, 5 ], [ 5.3, 6 ] ] ),
		[
			[ 0, 5 ],
			[ 5.3, 6 ],
		],
		'a real gap is kept'
	);
	assert.deepEqual(
		mergeIntervals( [ [ 5, 5 ], [ 7, 3 ], 'x', [ NaN, 4 ], [ -2, 1 ], null ] ),
		[ [ 0, 1 ] ],
		'empty, reversed and invalid spans are dropped; starts below zero are clamped'
	);
	assert.deepEqual( addInterval( [ [ 0, 10 ] ], [ 9, 12 ] ), [ [ 0, 12 ] ] );
	assert.equal( measure( [ [ 0, 6 ], [ 10, 30 ] ] ), 26 );
	assert.deepEqual( clampIntervals( [ [ 0, 6 ], [ 95, 104 ] ], 100 ), [
		[ 0, 6 ],
		[ 95, 100 ],
	] );
	assert.deepEqual( clampIntervals( [ [ 0, 6 ] ], 0 ), [ [ 0, 6 ] ] );
} );

test( 'watched share and resume position', () => {
	assert.equal( percentOf( 45, 60 ), 75 );
	assert.equal( percentOf( 1, 3 ), 33.33 );
	assert.equal( percentOf( 80, 60 ), 100, 'capped at 100' );
	assert.equal( percentOf( 10, 0 ), 0, 'unknown duration' );
	assert.equal( resumeAt( 42.5, 600 ), 42.5 );
	assert.equal( resumeAt( 42.5, 0 ), 42.5, 'duration not known yet' );
	assert.equal( resumeAt( 0.8, 600 ), 0, 'the very start is not resumed' );
	assert.equal( resumeAt( 598, 600 ), 0, 'the end starts over' );
	assert.equal( resumeAt( 42.5, 600, true ), 0, 'a completed video starts over' );
	assert.equal( resumeAt( 'x', 600 ), 0 );
} );

test( 'time labels', () => {
	assert.equal( formatTime( 0 ), '0:00' );
	assert.equal( formatTime( 65.4 ), '1:05' );
	assert.equal( formatTime( 3725 ), '1:02:05' );
	assert.equal( formatTime( NaN ), '0:00' );
	assert.equal( timeText( 65, 600 ), '1:05 of 10:00' );
	assert.equal( timeText( 65, 0, '%1$s / %2$s' ), '1:05 / --:--' );
} );

test( 'the watch log counts continuous playback only', () => {
	const log = createWatchLog();
	log.jump( 0, 0 );
	assert.ok( log.tick( 1, 1000 ) );
	assert.ok( log.tick( 2, 2000 ) );
	assert.equal( log.tick( 60, 3000 ), false, 'a jump ahead is not watching' );
	assert.ok( log.tick( 61, 4000 ), 'playback after the jump counts again' );
	assert.deepEqual( log.intervals(), [
		[ 0, 2 ],
		[ 60, 61 ],
	] );
	log.jump( 1, 5000 );
	log.tick( 2, 6000 );
	assert.equal( log.watched(), 3, 'watching a part again adds nothing' );
	log.stop();
	assert.equal( log.tick( 10, 7000 ), false, 'after stop the next tick only sets the start' );
	assert.ok( log.tick( 14, 9000, 2 ), 'double speed: 4 s of media in 2 s' );
	assert.equal( log.tick( 30, 10000, 1 ), false, 'faster than the speed allows' );
	assert.equal( log.tick( Number.NaN, 11000 ), false );
} );

test( 'progress fields for the save action', () => {
	const fields = progressFields( {
		lessonId: 12,
		courseId: 3,
		duration: 100,
		position: 120,
		intervals: [
			[ 0, 10.123 ],
			[ 95, 104 ],
		],
	} );
	assert.equal( fields.lesson_id, 12 );
	assert.equal( fields.course_id, 3 );
	assert.equal( fields.total_duration, 100 );
	assert.equal( fields.last_position, 100, 'position clamped to the duration' );
	assert.equal( fields.watched_duration, 15.12 );
	assert.deepEqual( JSON.parse( fields.segments ), [
		[ 0, 10.12 ],
		[ 95, 100 ],
	] );
} );

test( 'save cadence', () => {
	const clock = createSaveClock( 15000 );
	assert.ok( clock.due( 0, 1, 1 ), 'first save' );
	clock.saved( 0, 1, 1 );
	assert.equal( clock.due( 10000, 9, 9 ), false, 'not before the interval' );
	assert.ok( clock.due( 15000, 9, 9 ), 'after the interval' );
	clock.saved( 15000, 9, 9 );
	assert.equal( clock.due( 40000, 9, 9.2 ), false, 'nothing new' );
	assert.ok( clock.changed( 9, 20 ), 'a seek is a change' );
} );

test( 'keyboard shortcuts and sliders', () => {
	const on = ( key, target = { tag: 'DIV' } ) => shortcut( { key }, target );
	assert.deepEqual( on( ' ' ), { action: 'toggle' } );
	assert.equal( on( ' ', { tag: 'BUTTON' } ), null, 'a focused button keeps Space' );
	assert.deepEqual( on( 'k', { tag: 'BUTTON' } ), { action: 'toggle' } );
	assert.deepEqual( on( 'ArrowLeft' ), { action: 'seekBy', value: -5 } );
	assert.deepEqual( on( 'ArrowRight' ), { action: 'seekBy', value: 5 } );
	assert.deepEqual( on( 'ArrowUp' ), { action: 'volumeBy', value: 0.1 } );
	assert.deepEqual( on( '7' ), { action: 'seekPercent', value: 70 } );
	assert.deepEqual( on( 'c' ), { action: 'captions' } );
	assert.deepEqual( on( 'f' ), { action: 'fullscreen' } );
	assert.deepEqual( on( 'm' ), { action: 'mute' } );
	assert.equal( on( 'ArrowLeft', { tag: 'DIV', role: 'slider' } ), null, 'sliders keep arrows' );
	assert.equal( on( 'k', { tag: 'INPUT' } ), null );
	assert.equal( on( 'k', { tag: 'DIV', editable: true } ), null );
	assert.equal( on( 'x' ), null );
	assert.equal( shortcut( { key: 'k', ctrlKey: true } ), null );

	assert.equal( sliderValue( 'ArrowRight', 10, 100, 5 ), 15 );
	assert.equal( sliderValue( 'ArrowLeft', 2, 100, 5 ), 0 );
	assert.equal( sliderValue( 'PageUp', 10, 100, 5 ), 60 );
	assert.equal( sliderValue( 'End', 10, 100, 5 ), 100 );
	assert.equal( sliderValue( 'Home', 10, 100, 5 ), 0 );
	assert.equal( sliderValue( 'a', 10, 100, 5 ), null );
	assert.equal( pointerRatio( 150, { left: 100, width: 200 } ), 0.25 );
	assert.equal( pointerRatio( 50, { left: 100, width: 200 } ), 0 );
	assert.equal( pointerRatio( 50, { left: 0, width: 0 } ), 0 );
	assert.equal( menuIndex( 'ArrowDown', 5, 6 ), 0 );
	assert.equal( menuIndex( 'ArrowUp', 0, 6 ), 5 );
	assert.equal( menuIndex( 'End', 0, 6 ), 5 );
	assert.equal( menuIndex( 'a', 0, 6 ), null );
	assert.deepEqual( RATES, [ 0.5, 0.75, 1, 1.25, 1.5, 2 ] );
} );

// The same cases are asserted for OhMyLMS\Learning\VideoSource in tests/php/video-player-integration.php.
const SOURCES = [
	[ 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10', 'youtube', 'dQw4w9WgXcQ' ],
	[ 'https://youtu.be/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ' ],
	[ 'https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0', 'youtube', 'dQw4w9WgXcQ' ],
	[ 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ' ],
	[ 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ' ],
	[ 'https://vimeo.com/76979871', 'vimeo', '76979871' ],
	[ 'https://player.vimeo.com/video/76979871?h=8272103f6e', 'vimeo', '76979871' ],
	[ 'https://vimeo.com/channels/staffpicks/76979871', 'vimeo', '76979871' ],
	[ 'https://iframe.mediadelivery.net/embed/1234/0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f', 'bunny', '0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f' ],
	[ 'https://iframe.mediadelivery.net/play/1234/0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f', 'bunny', '0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f' ],
	[ 'https://acme.wistia.com/medias/abcde12345', 'wistia', 'abcde12345' ],
	[ 'https://fast.wistia.net/embed/iframe/abcde12345?videoFoam=true', 'wistia', 'abcde12345' ],
	[ 'https://cdn.example.com/lesson.mp4', 'html5', '' ],
	[ '/wp-content/uploads/lesson.webm', 'html5', '' ],
	[ 'https://www.loom.com/embed/abc', 'html5', '' ],
	[ 'javascript:alert(1)', '', '' ],
	[ '', '', '' ],
];

test( 'provider detection', () => {
	for ( const [ url, provider, id ] of SOURCES ) {
		const source = detectSource( url );
		assert.equal( source.provider, provider, url );
		assert.equal( source.id, id, url );
	}
	assert.equal(
		detectSource( 'https://www.loom.com/embed/abc', { custom: false } ).provider,
		'embed',
		'without the custom player an unknown page is an iframe embed'
	);
	assert.equal(
		detectSource( 'https://cdn.example.com/lesson.mp4', { custom: false } ).provider,
		'html5',
		'a file is a file either way'
	);
	assert.deepEqual( vimeoId( 'https://vimeo.com/76979871/8272103f6e' ), {
		id: '76979871',
		hash: '8272103f6e',
	} );
	assert.equal(
		detectSource( 'https://iframe.mediadelivery.net/play/1234/0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f' ).embed,
		'https://iframe.mediadelivery.net/embed/1234/0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f'
	);
	assert.equal( youtubeId( 'https://www.youtube.com/watch?v=short' ), '' );
	assert.ok( ! TRACKABLE.includes( 'embed' ) );
	assert.equal( mimeType( 'https://x.test/a.webm?x=1' ), 'video/webm' );
	assert.equal( mimeType( 'https://x.test/a.MOV' ), 'video/quicktime' );
	assert.equal( mimeType( 'https://x.test/stream' ), 'video/mp4' );
	assert.deepEqual( ADAPTERS, {
		html5: 'html5',
		youtube: 'youtube',
		vimeo: 'vimeo',
		bunny: 'playerjs',
		wistia: 'wistia',
	} );
} );

// --- Adapters -----------------------------------------------------------------------------

/** A `<video>` stand-in: an EventTarget with the media properties the adapter uses. */
function fakeVideo( { readyState = 1, duration = 120, tracks = [] } = {} ) {
	const video = new EventTarget();
	Object.assign( video, {
		readyState,
		duration,
		currentTime: 0,
		paused: true,
		playbackRate: 1,
		volume: 1,
		muted: false,
		controls: true,
		error: null,
		textTracks: tracks,
		ownerDocument: { documentElement: { lang: 'fr-FR' } },
		removed: [],
		removeAttribute( name ) {
			this.removed.push( name );
		},
		querySelector: () => null,
		play() {
			this.paused = false;
			this.dispatchEvent( new Event( 'play' ) );
			return Promise.resolve();
		},
		pause() {
			this.paused = true;
			this.dispatchEvent( new Event( 'pause' ) );
		},
	} );
	return video;
}

test( 'html5 adapter: native controls give way, events and captions', async () => {
	const tracks = [
		{ kind: 'subtitles', language: 'en', mode: 'disabled' },
		{ kind: 'captions', language: 'fr', mode: 'disabled' },
		{ kind: 'chapters', language: 'en', mode: 'disabled' },
	];
	const video = fakeVideo( { tracks } );
	const adapter = await html5.createAdapter( { video, custom: true } );
	assert.equal( video.controls, false );
	assert.deepEqual( video.removed, [ 'controls' ] );
	assert.equal( adapter.duration(), 120 );
	assert.equal( adapter.supports.captions, true );
	const seen = [];
	adapter.on( 'play', () => seen.push( 'play' ) );
	adapter.on( 'time', ( value ) => seen.push( [ 'time', value ] ) );
	adapter.on( 'captions', ( value ) => seen.push( value ) );
	adapter.play();
	video.currentTime = 12;
	video.dispatchEvent( new Event( 'timeupdate' ) );
	adapter.seek( 500 );
	assert.equal( video.currentTime, 120, 'seek clamped to the duration' );
	adapter.setRate( 1.5 );
	assert.equal( video.playbackRate, 1.5 );
	adapter.setVolume( 0 );
	assert.equal( video.muted, true );
	adapter.setCaptions( true );
	assert.deepEqual(
		tracks.map( ( track ) => track.mode ),
		[ 'hidden', 'showing', 'disabled' ],
		'the page language (fr) is shown; chapters are not captions'
	);
	adapter.setCaptions( false );
	assert.deepEqual( tracks.map( ( track ) => track.mode ).slice( 0, 2 ), [ 'hidden', 'hidden' ] );
	assert.deepEqual( seen, [
		'play',
		[ 'time', 12 ],
		{ available: true, on: true },
		{ available: true, on: false },
	] );
	adapter.destroy();
	video.dispatchEvent( new Event( 'play' ) );
	assert.equal( seen.length, 4, 'no events after destroy' );

	const native = fakeVideo( { readyState: 0 } );
	const pending = html5.createAdapter( { video: native, custom: false } );
	native.dispatchEvent( new Event( 'loadedmetadata' ) );
	const plain = await pending;
	assert.equal( native.controls, true, 'native mode keeps the controls' );
	assert.equal( plain.supports.captions, false );
} );

test( 'youtube adapter: lazy API, chained callback, player vars and events', async () => {
	assert.equal( youtube.API_URL, 'https://www.youtube.com/iframe_api' );
	assert.deepEqual(
		youtube.playerVars( { id: 'abc', loop: true, muted: true, custom: true }, 'https://site.test' ),
		{
			autoplay: 0,
			controls: 0,
			disablekb: 1,
			playsinline: 1,
			modestbranding: 1,
			rel: 0,
			iv_load_policy: 3,
			enablejsapi: 1,
			cc_load_policy: 0,
			origin: 'https://site.test',
			loop: 1,
			playlist: 'abc',
			mute: 1,
		}
	);
	assert.equal(
		youtube.withJsApi( 'https://www.youtube.com/embed/abc?rel=0', 'https://site.test' ),
		'https://www.youtube.com/embed/abc?rel=0&enablejsapi=1&origin=https%3A%2F%2Fsite.test'
	);
	assert.equal(
		youtube.withJsApi( 'https://www.youtube.com/embed/abc?enablejsapi=1', 'x' ),
		'https://www.youtube.com/embed/abc?enablejsapi=1'
	);

	const dom = new JSDOM( '<div id="mount"></div>', { url: 'https://site.test/lesson/' } );
	const win = dom.window;
	const loaded = [];
	let previous = 0;
	win.onYouTubeIframeAPIReady = () => previous++;
	let created = null;
	const calls = [];
	const load = async ( src ) => {
		loaded.push( src );
		win.YT = {
			Player: class {
				constructor( target, options ) {
					created = { target, options };
					this.frame = win.document.createElement( 'iframe' );
					setImmediate( () => options.events.onReady( { target: this } ) );
				}
				getIframe() {
					return this.frame;
				}
				getDuration() {
					return 212;
				}
				getCurrentTime() {
					return 3;
				}
				getOptions() {
					return [ 'captions' ];
				}
				playVideo() {
					calls.push( 'play' );
				}
				pauseVideo() {
					calls.push( 'pause' );
				}
				seekTo( time ) {
					calls.push( [ 'seek', time ] );
				}
				loadModule( name ) {
					calls.push( [ 'load', name ] );
				}
				unloadModule( name ) {
					calls.push( [ 'unload', name ] );
				}
				setPlaybackRate( rate ) {
					calls.push( [ 'rate', rate ] );
				}
				setVolume( volume ) {
					calls.push( [ 'volume', volume ] );
				}
				mute() {}
				unMute() {}
				destroy() {
					calls.push( 'destroy' );
				}
			},
		};
		setImmediate( () => win.onYouTubeIframeAPIReady() );
	};
	const mount = win.document.getElementById( 'mount' );
	const adapter = await youtube.createAdapter( {
		mount,
		source: { id: 'dQw4w9WgXcQ' },
		config: { custom: true, title: 'Lesson' },
		win,
		load,
	} );
	assert.deepEqual( loaded, [ youtube.API_URL ], 'the API is loaded once, on demand' );
	assert.equal( previous, 1, 'a callback set by another script still runs' );
	assert.equal( created.options.videoId, 'dQw4w9WgXcQ' );
	assert.equal( created.options.playerVars.controls, 0 );
	assert.equal( created.target.parentNode, mount );
	assert.equal( adapter.duration(), 212 );
	assert.equal( created.options.events.onReady.length, 0 );
	const events = [];
	adapter.on( 'play', () => events.push( 'play' ) );
	adapter.on( 'captions', ( value ) => events.push( value ) );
	adapter.on( 'pause', () => events.push( 'pause' ) );
	assert.equal( adapter.paused(), true );
	created.options.events.onStateChange( { data: youtube.STATE.PLAYING } );
	assert.equal( adapter.paused(), false );
	assert.equal( adapter.supports.captions, true, 'captions are known once the video plays' );
	created.options.events.onStateChange( { data: youtube.STATE.PAUSED } );
	adapter.seek( 30 );
	adapter.setCaptions( true );
	adapter.setRate( 2 );
	adapter.setVolume( 0.5 );
	assert.deepEqual( events, [
		{ available: true, on: false },
		'play',
		'pause',
		{ available: true, on: true },
	] );
	assert.deepEqual( calls, [ [ 'seek', 30 ], [ 'load', 'captions' ], [ 'rate', 2 ], [ 'volume', 50 ] ] );
	adapter.destroy();
	assert.equal( calls.at( -1 ), 'destroy' );

	// A second player reuses the API without loading it again.
	await youtube.createAdapter( { mount, source: { id: 'dQw4w9WgXcQ' }, win, load } );
	assert.equal( loaded.length, 1 );
	dom.window.close();
} );

test( 'vimeo adapter: options, private hash, text tracks and events', async () => {
	assert.deepEqual( vimeo.playerOptions( { id: '76979871', hash: 'abc123def0' }, { custom: true } ).url, 'https://vimeo.com/76979871/abc123def0' );
	const options = vimeo.playerOptions( { id: '76979871' }, { custom: true, loop: true } );
	assert.equal( options.id, 76979871 );
	assert.equal( options.controls, false );
	assert.equal( options.loop, true );
	assert.equal( options.keyboard, false );
	assert.deepEqual(
		vimeo.pickTrack( [ { language: 'en', kind: 'captions' }, { language: 'de', kind: 'subtitles' } ], 'de-AT' ),
		{ language: 'de', kind: 'subtitles' }
	);
	assert.equal( vimeo.pickTrack( [], 'de' ), null );

	const dom = new JSDOM( '<div id="mount"></div>', { url: 'https://site.test/' } );
	const win = dom.window;
	const handlers = {};
	const calls = [];
	const load = async () => {
		win.Vimeo = {
			Player: class {
				constructor( host, opts ) {
					calls.push( [ 'new', opts.id ] );
					host.appendChild( win.document.createElement( 'iframe' ) );
				}
				ready() {
					return Promise.resolve();
				}
				getDuration() {
					return Promise.resolve( 90 );
				}
				getPaused() {
					return Promise.resolve( true );
				}
				getTextTracks() {
					return Promise.resolve( [ { language: 'en', kind: 'captions' } ] );
				}
				on( name, callback ) {
					handlers[ name ] = callback;
				}
				play() {
					calls.push( 'play' );
					return Promise.resolve();
				}
				pause() {
					return Promise.resolve();
				}
				setCurrentTime( time ) {
					calls.push( [ 'seek', time ] );
					return Promise.resolve( time );
				}
				setPlaybackRate() {
					return Promise.reject( new Error( 'not allowed' ) );
				}
				enableTextTrack( language ) {
					calls.push( [ 'track', language ] );
					return Promise.resolve();
				}
				disableTextTrack() {
					return Promise.resolve();
				}
			},
		};
	};
	const adapter = await vimeo.createAdapter( {
		mount: win.document.getElementById( 'mount' ),
		source: { id: '76979871' },
		config: { custom: true },
		win,
		load,
	} );
	assert.equal( adapter.duration(), 90 );
	assert.equal( adapter.supports.captions, true );
	assert.equal( win.document.querySelector( 'iframe' ).tabIndex, -1, 'the iframe is not a tab stop' );
	const seen = [];
	adapter.on( 'time', ( value ) => seen.push( [ 'time', value ] ) );
	adapter.on( 'rate', ( value ) => seen.push( [ 'rate', value ] ) );
	handlers.timeupdate( { seconds: 4, duration: 90 } );
	handlers.play( { seconds: 4 } );
	assert.equal( adapter.paused(), false );
	adapter.seek( 200 );
	adapter.setRate( 2 );
	await tick();
	adapter.setCaptions( true );
	await tick();
	assert.deepEqual( seen, [
		[ 'time', 4 ],
		[ 'time', 90 ],
		[ 'rate', 1 ],
	], 'seek is clamped; a refused speed reports the actual one' );
	assert.deepEqual( calls, [
		[ 'new', 76979871 ],
		[ 'seek', 90 ],
		[ 'track', 'en' ],
	] );
	dom.window.close();
} );

test( 'player.js adapter (Bunny): protocol messages and events', async () => {
	assert.deepEqual( JSON.parse( playerjs.message( 'setCurrentTime', 5, 'l1' ) ), {
		context: 'player.js',
		version: '0.0.11',
		method: 'setCurrentTime',
		value: 5,
		listener: 'l1',
	} );
	assert.equal( playerjs.parseMessage( '{"context":"other","event":"ready"}' ), null );
	assert.equal( playerjs.parseMessage( 'nope' ), null );
	assert.equal( playerjs.parseMessage( { context: 'player.js', event: 'play' } ).event, 'play' );

	const win = new EventTarget();
	const sent = [];
	const frameWindow = {
		postMessage( data, origin ) {
			sent.push( [ JSON.parse( data ), origin ] );
		},
	};
	const iframe = {
		src: 'https://iframe.mediadelivery.net/embed/1/0b6f1e2c-7d1a-4b0e-9f3e-1a2b3c4d5e6f',
		contentWindow: frameWindow,
	};
	const receive = ( data ) => {
		const event = new Event( 'message' );
		Object.assign( event, { data: JSON.stringify( data ), source: frameWindow } );
		win.dispatchEvent( event );
	};
	const pending = playerjs.createAdapter( { iframe, win } );
	assert.equal( sent[ 0 ][ 0 ].method, 'addEventListener' );
	assert.equal( sent[ 0 ][ 1 ], 'https://iframe.mediadelivery.net', 'messages go to the iframe origin only' );
	const stranger = new Event( 'message' );
	Object.assign( stranger, { data: JSON.stringify( { context: 'player.js', event: 'ready' } ), source: {} } );
	win.dispatchEvent( stranger );
	receive( { context: 'player.js', event: 'ready', value: {} } );
	const adapter = await pending;
	assert.deepEqual(
		sent.filter( ( [ data ] ) => 'addEventListener' === data.method ).map( ( [ data ] ) => data.value ).slice( 1 ),
		playerjs.LISTEN
	);
	const ask = sent.find( ( [ data ] ) => 'getDuration' === data.method )[ 0 ];
	receive( { context: 'player.js', event: 'getDuration', listener: ask.listener, value: 300 } );
	await tick();
	assert.equal( adapter.duration(), 300 );
	const seen = [];
	adapter.on( 'time', ( value ) => seen.push( value ) );
	adapter.on( 'ended', () => seen.push( 'ended' ) );
	receive( { context: 'player.js', event: 'timeupdate', value: { seconds: 12, duration: 300 } } );
	receive( { context: 'player.js', event: 'ended' } );
	assert.deepEqual( seen, [ 12, 'ended' ] );
	assert.equal( adapter.supports.rates, false, 'player.js has no speed control' );
	adapter.seek( 20 );
	assert.equal( sent.at( -1 )[ 0 ].method, 'setCurrentTime' );
	adapter.destroy();
} );

test( 'wistia adapter: queue handle and events', async () => {
	const bound = {};
	const handle = {
		bind( name, callback ) {
			bound[ name ] = callback;
		},
		time: ( value ) => ( undefined === value ? 7 : value ),
		duration: () => 50,
		state: () => 'playing',
		playbackRate: () => 1,
		play() {},
		pause() {},
	};
	const win = { document: {} };
	const loaded = [];
	const load = async ( src ) => {
		loaded.push( src );
		for ( const item of win._wq ) {
			if ( 'abcde12345' === item.id ) {
				item.onReady( handle );
			}
		}
	};
	const adapter = await wistia.createAdapter( { source: { id: 'abcde12345' }, win, load } );
	assert.deepEqual( loaded, [ wistia.API_URL ] );
	assert.equal( adapter.duration(), 50 );
	assert.equal( adapter.paused(), false );
	const seen = [];
	adapter.on( 'time', ( value ) => seen.push( value ) );
	adapter.on( 'ended', () => seen.push( 'ended' ) );
	bound.timechange( 9 );
	bound.end();
	assert.deepEqual( seen, [ 9, 'ended' ] );
} );

// --- Progress session ---------------------------------------------------------------------

/** A ready adapter driven by the test. */
function fakeAdapter( { duration = 100, rate = 1 } = {} ) {
	const events = createEmitter();
	const adapter = {
		provider: 'html5',
		media: null,
		supports: { rates: true, volume: true, captions: false },
		position: 0,
		length: duration,
		seeks: [],
		plays: 0,
		play() {
			this.plays++;
		},
		pause() {},
		seek( seconds ) {
			this.seeks.push( seconds );
			this.position = seconds;
		},
		time() {
			return this.position;
		},
		duration() {
			return this.length;
		},
		paused: () => true,
		rate: () => rate,
		setRate() {},
		setVolume() {},
		setMuted() {},
		setCaptions() {},
		on: events.on,
		emit: events.emit,
		destroy() {},
	};
	return adapter;
}

/** Debounce with a manual timer queue. */
function fakeDebounce( queue ) {
	return ( callback ) => {
		let pending = false;
		const run = () => {
			if ( pending ) {
				pending = false;
				callback();
			}
		};
		const debounced = () => {
			pending = true;
			queue.push( run );
		};
		debounced.cancel = () => {
			pending = false;
		};
		debounced.flush = run;
		return debounced;
	};
}

function sessionFixture( options = {} ) {
	const lesson = options.lessonStore ? 'data-wp-interactive="ohmylms/lesson"' : '';
	const dom = new JSDOM(
		`<div id="root"></div><div id="nav" ${ lesson }><label><input type="checkbox" id="ohmylms-completed-lesson" data-ohmylms-video-pending="1" aria-describedby="ohmylms-video-completion-hint" disabled></label><p id="ohmylms-video-completion-hint">Watch</p></div>`
	);
	const doc = dom.window.document;
	const root = doc.getElementById( 'root' );
	const log = { requests: [], events: [], jquery: [], toasts: [], beacons: [], announced: [], asked: [] };
	if ( options.lessonStore ) {
		// What assets/interactivity/lesson/store.js does with a request: complete, then announce it.
		doc.addEventListener( 'ohmylms:lesson-complete-request', ( event ) => {
			log.asked.push( event.detail );
			if ( 'refuse' !== options.lessonStore ) {
				doc.getElementById( 'nav' ).dispatchEvent(
					new dom.window.CustomEvent( 'ohmylms:lesson-completed', {
						bubbles: true,
						detail: { lessonId: event.detail.lessonId, source: event.detail.source, nextUrl: '/next/' },
					} )
				);
			}
		} );
	}
	const queue = [];
	let clock = 0;
	const responses = [ ...( options.responses || [] ) ];
	const context = {
		lessonId: 12,
		courseId: 3,
		track: true,
		resume: 0,
		videoDone: false,
		lessonDone: false,
		percent: 0,
		...options.context,
	};
	const adapter = fakeAdapter( options.adapter );
	const session = createSession( {
		adapter,
		context,
		root,
		config: {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonces: { progress: 'n-progress', complete: 'n-complete' },
			saveInterval: 15000,
			messages: { lessonCompleted: 'Lesson completed.' },
			...options.config,
		},
		request: async ( request ) => {
			log.requests.push( request );
			return responses.shift() || { ok: true, data: { progress: { watch_percentage: 10, is_completed: false } } };
		},
		emit: ( target, name, detail = {}, cancelable = false ) => {
			log.events.push( [ name, detail ] );
			return target.dispatchEvent(
				new dom.window.CustomEvent( `ohmylms:${ name }`, { bubbles: true, cancelable, detail } )
			);
		},
		jqueryTrigger: ( name, args, target ) => log.jquery.push( [ name, args, target === doc ] ),
		toast: ( message, toastOptions ) => log.toasts.push( [ message, toastOptions?.type ] ),
		announce: ( message ) => log.announced.push( message ),
		debounce: fakeDebounce( queue ),
		now: () => clock,
		beacon: ( url, fields ) => {
			log.beacons.push( [ url, fields ] );
			return 'beacon';
		},
		doc,
	} );
	const play = ( from, seconds, step = 1 ) => {
		adapter.position = from;
		adapter.emit( 'play', from );
		for ( let t = from + step; t <= from + seconds; t += step ) {
			clock += step * 1000;
			adapter.position = t;
			adapter.emit( 'time', t );
		}
	};
	return {
		dom,
		doc,
		root,
		log,
		queue,
		context,
		adapter,
		session,
		play,
		advance: ( ms ) => ( clock += ms ),
		flushQueue: async () => {
			while ( queue.length ) {
				queue.shift()();
			}
			await tick();
		},
	};
}

const completedAnswer = ( lessonCompleted = true ) => ( {
	ok: true,
	data: {
		progress: { watch_percentage: 91, is_completed: true },
		lesson_completed: lessonCompleted,
		next_content_link: 'https://site.test/next/',
	},
} );

test( 'session: resume on the first play, periodic and pause saves', async () => {
	const fixture = sessionFixture( { context: { resume: 42 } } );
	const { adapter, log } = fixture;
	assert.equal( fixture.session.tracking, true );
	fixture.play( 0, 0 );
	assert.deepEqual( adapter.seeks, [ 42 ], 'the saved position is applied once, on the first play' );
	fixture.play( 42, 14 );
	assert.equal( log.requests.length, 1, 'first save after the first second played' );
	const first = log.requests[ 0 ];
	assert.equal( first.action, SAVE_ACTION );
	assert.equal( first.nonce, 'n-progress' );
	assert.equal( first.ajaxUrl, '/wp-admin/admin-ajax.php' );
	assert.equal( first.data.lesson_id, 12 );
	assert.deepEqual( JSON.parse( first.data.segments ), [ [ 42, 43 ] ] );
	await tick();
	fixture.play( 56, 1 );
	assert.equal( log.requests.length, 1, 'no second periodic save 14 s later' );
	fixture.play( 57, 1 );
	assert.equal( log.requests.length, 2, 'the next one 15 s after the first' );
	await tick();
	adapter.emit( 'pause', 58 );
	assert.equal( log.requests.length, 2, 'a pause saves after a short delay' );
	await fixture.flushQueue();
	assert.equal( log.requests.length, 3 );
	assert.deepEqual( JSON.parse( log.requests[ 2 ].data.segments ), [ [ 42, 58 ] ] );
	assert.equal( fixture.context.percent, 10 );
	assert.ok( log.events.some( ( [ name ] ) => 'video-progress' === name ) );
	fixture.play( 10, 0 );
	assert.deepEqual( adapter.seeks, [ 42 ], 'the position is not applied again' );
	fixture.dom.window.close();
} );

test( 'session: watched video → the lesson store completes the lesson, once', async () => {
	const fixture = sessionFixture( {
		lessonStore: true,
		responses: [ completedAnswer( false ), completedAnswer( true ) ],
	} );
	const { log, doc } = fixture;
	fixture.play( 0, 15 );
	await tick();
	await tick();
	assert.deepEqual(
		log.events.map( ( [ name ] ) => name ),
		[ 'video-progress', 'video-completed', 'lesson-complete-request' ]
	);
	assert.deepEqual( log.events[ 1 ][ 1 ], { lessonId: 12, courseId: 3, percent: 91 } );
	assert.deepEqual( log.asked, [ { lessonId: 12, source: 'video' } ], 'a document event the lesson store takes' );
	assert.ok( ! log.requests.some( ( request ) => COMPLETE_ACTION === request.action ), 'the lesson store calls the endpoint, not the player' );
	assert.equal( fixture.context.lessonDone, true, 'its ohmylms:lesson-completed answer is seen' );
	assert.deepEqual( log.jquery, [ [ 'ohmylms:video:completed', [ { lessonId: 12, courseId: 3 } ], true ] ] );
	assert.equal( doc.getElementById( 'ohmylms-completed-lesson' ).checked, false, 'the lesson store owns the checkbox' );
	fixture.play( 16, 16 );
	await tick();
	assert.equal( log.events.filter( ( [ name ] ) => 'video-completed' === name ).length, 1, 'announced once' );
	assert.equal( log.asked.length, 1, 'asked once' );
	fixture.dom.window.close();

	const already = sessionFixture( { lessonStore: true, responses: [ completedAnswer( true ) ] } );
	already.play( 0, 15 );
	await tick();
	await tick();
	assert.deepEqual( already.log.asked, [ { lessonId: 12, source: 'video', completed: true } ], 'completed elsewhere: the page is only updated' );
	already.dom.window.close();

	const refused = sessionFixture( { lessonStore: 'refuse', responses: [ completedAnswer( false ) ] } );
	refused.play( 0, 15 );
	await tick();
	await tick();
	assert.equal( refused.context.lessonDone, false, 'a refusal (shown by the lesson store) leaves the lesson open' );
	assert.deepEqual( refused.log.jquery, [] );
	refused.dom.window.close();
} );

test( 'session: without the lesson store the player calls the endpoint and updates the box', async () => {
	const fallback = sessionFixture( {
		responses: [
			completedAnswer( false ),
			{ ok: true, data: { status: 'success', next_content_link: '/next/' } },
		],
	} );
	const { log, doc } = fallback;
	fallback.play( 0, 15 );
	await tick();
	await tick();
	await tick();
	const request = log.requests.find( ( item ) => COMPLETE_ACTION === item.action );
	assert.deepEqual( request.data, { lesson_id: 12 } );
	assert.equal( request.nonce, 'n-complete' );
	assert.ok( log.events.some( ( [ name, detail ] ) => 'lesson-completed' === name && '/next/' === detail.nextUrl ) );
	const box = doc.getElementById( 'ohmylms-completed-lesson' );
	assert.equal( box.checked, true );
	assert.equal( box.hasAttribute( 'data-ohmylms-video-pending' ), false );
	assert.equal( doc.getElementById( 'ohmylms-video-completion-hint' ), null );
	assert.deepEqual( log.announced, [ 'Lesson completed.' ] );
	assert.equal( log.jquery.length, 1 );
	fallback.dom.window.close();

	const done = sessionFixture( { responses: [ completedAnswer( true ) ] } );
	done.play( 0, 15 );
	await tick();
	await tick();
	assert.ok( ! done.log.requests.some( ( item ) => COMPLETE_ACTION === item.action ), 'already completed: no request' );
	assert.equal( done.context.lessonDone, true );
	done.dom.window.close();

	const refused = sessionFixture( {
		responses: [ completedAnswer( false ), { ok: false, message: 'Watch at least 50% of the video.' } ],
	} );
	refused.play( 0, 15 );
	await tick();
	await tick();
	await tick();
	assert.deepEqual( refused.log.toasts, [ [ 'Watch at least 50% of the video.', 'warning' ] ] );
	assert.equal( refused.context.lessonDone, false );
	refused.dom.window.close();
} );

test( 'session: close to the threshold progress is saved every few seconds', async () => {
	const near = { ok: true, data: { progress: { watch_percentage: 85, is_completed: false } } };
	const fixture = sessionFixture( { context: { threshold: 90, percent: 82 }, responses: [ near, near ] } );
	fixture.play( 0, 1 );
	await tick();
	fixture.play( 1, 3 );
	assert.equal( fixture.log.requests.length, 2, 'saved again after 3 s' );
	fixture.dom.window.close();
	const far = sessionFixture( { context: { threshold: 90, percent: 50 } } );
	far.play( 0, 1 );
	await tick();
	far.play( 1, 3 );
	assert.equal( far.log.requests.length, 1 );
	far.dom.window.close();
} );

test( 'session: the last save goes out as a beacon, only when something changed', async () => {
	const fixture = sessionFixture();
	fixture.play( 0, 5 );
	assert.equal( fixture.session.flush(), 'beacon' );
	const [ url, fields ] = fixture.log.beacons[ 0 ];
	assert.equal( url, '/wp-admin/admin-ajax.php' );
	assert.equal( fields.action, SAVE_ACTION );
	assert.equal( fields.nonce, 'n-progress' );
	assert.deepEqual( JSON.parse( fields.segments ), [ [ 0, 5 ] ] );
	assert.equal( fixture.session.flush(), '', 'nothing new since' );
	fixture.dom.window.close();
} );

test( 'session: completion on load, and no tracking without a lesson or nonce', async () => {
	const pending = sessionFixture( {
		context: { videoDone: true },
		responses: [ { ok: true, data: { next_content_link: '' } } ],
	} );
	await pending.session.completeOnLoad();
	assert.equal( pending.log.requests[ 0 ].action, COMPLETE_ACTION );
	assert.equal( pending.context.lessonDone, true );
	pending.dom.window.close();

	const done = sessionFixture( { context: { videoDone: true, lessonDone: true } } );
	await done.session.completeOnLoad();
	assert.equal( done.log.requests.length, 0 );
	done.dom.window.close();

	const guest = sessionFixture( { config: { nonces: {} } } );
	assert.equal( guest.session.tracking, false );
	guest.play( 0, 20 );
	await guest.session.save();
	assert.equal( guest.session.flush(), '' );
	assert.equal( guest.log.requests.length, 0 );
	guest.dom.window.close();

	const untracked = sessionFixture( { context: { track: false } } );
	untracked.play( 0, 20 );
	assert.equal( untracked.log.requests.length, 0 );
	untracked.dom.window.close();
} );

test( 'sendFinal: sendBeacon, else fetch with keepalive', () => {
	const beacons = [];
	assert.equal(
		sendFinal( '/ajax', { a: 1 }, { sendBeacon: ( url, body ) => beacons.push( [ url, body.toString() ] ) } ),
		'beacon'
	);
	assert.deepEqual( beacons, [ [ '/ajax', 'a=1' ] ] );
	const fetches = [];
	assert.equal(
		sendFinal( '/ajax', { a: 2 }, { sendBeacon: () => false }, ( url, init ) => {
			fetches.push( [ url, init.keepalive, init.credentials, init.body.toString() ] );
			return Promise.resolve();
		} ),
		'fetch'
	);
	assert.deepEqual( fetches, [ [ '/ajax', true, 'same-origin', 'a=2' ] ] );
	assert.equal( sendFinal( '/ajax', {}, {}, null ), '' );
} );

test( 'markLessonDone leaves pages without the box alone', () => {
	const dom = new JSDOM( '<p>no box</p>' );
	markLessonDone( dom.window.document );
	assert.equal( dom.window.document.body.innerHTML, '<p>no box</p>' );
	dom.window.close();
} );

// --- Store ----------------------------------------------------------------------------------

const PLAYER = `
<div class="ohmylms-custom-video-player" data-wp-interactive="ohmylms/video" id="root">
	<div class="ohmylms-video-player-container"><video controls></video></div>
	<button class="ohmylms-big-play-button" id="big"></button>
	<div class="ohmylms-custom-controls">
		<div class="ohmylms-progress-bar" role="slider" id="seek"></div>
		<button class="ohmylms-play-pause" id="toggle"></button>
		<div class="ohmylms-speed-control">
			<button class="ohmylms-speed-btn" id="speed"></button>
			<div class="ohmylms-speed-menu" role="menu">
				<button role="menuitemradio" aria-checked="false" id="r05"></button>
				<button role="menuitemradio" aria-checked="true" id="r1"></button>
				<button role="menuitemradio" aria-checked="false" id="r2"></button>
			</div>
		</div>
	</div>
</div>
<p id="outside">outside</p>`;

function storeFixture( options = {} ) {
	const dom = new JSDOM( PLAYER, { url: 'https://site.test/lesson/' } );
	const doc = dom.window.document;
	const root = doc.getElementById( 'root' );
	const env = { element: root, context: null };
	const timers = [];
	const log = { events: [], requests: [], announced: [], loaded: [] };
	const adapter = fakeAdapter( { duration: 200 } );
	adapter.setRate = ( rate ) => adapter.emit( 'rate', rate );
	adapter.setMuted = ( muted ) => ( adapter.muted = muted );
	adapter.setVolume = ( volume ) => ( adapter.volume = volume );
	adapter.setCaptions = ( on ) => ( adapter.captionsOn = on );
	adapter.play = () => {
		adapter.plays++;
		adapter.emit( 'play', adapter.position );
	};
	adapter.pause = () => adapter.emit( 'pause', adapter.position );
	const context = {
		provider: 'html5',
		videoId: '',
		hash: '',
		custom: true,
		lessonId: 12,
		courseId: 3,
		track: true,
		resume: 0,
		videoDone: false,
		lessonDone: false,
		percent: 0,
		autoplay: false,
		loop: false,
		muted: false,
		title: 'Lesson',
		mounted: false,
		playing: false,
		started: false,
		time: 0,
		duration: 0,
		volume: 1,
		rate: 1,
		rates: true,
		captions: false,
		captionsOn: false,
		volumeControl: true,
		speedOpen: false,
		fullscreen: false,
		idle: false,
		seeking: false,
		error: '',
		indicator: '',
		...options.context,
	};
	env.context = context;
	let state;
	const definitions = createVideo( {
		getContext: () => env.context,
		getElement: () => ( { ref: env.element } ),
		withSyncEvent: ( callback ) => callback,
		getState: () => state,
		loadAdapter: async ( name ) => {
			log.loaded.push( name );
			if ( options.failLoad ) {
				throw { key: 'embedBlocked' };
			}
			return { createAdapter: async ( args ) => ( ( log.args = args ), adapter ) };
		},
		request: async ( request ) => {
			log.requests.push( request );
			return { ok: true, data: { progress: { watch_percentage: 5, is_completed: false } } };
		},
		emit: ( target, name, detail ) => log.events.push( [ name, detail ] ) || true,
		toast: () => null,
		announce: ( message ) => log.announced.push( message ),
		debounce: fakeDebounce( [] ),
		jqueryTrigger: () => false,
		prefersReducedMotion: () => Boolean( options.reducedMotion ),
		win: dom.window,
		doc,
		later: ( callback ) => timers.push( callback ),
		cancel: () => {},
		beacon: () => 'beacon',
	} );
	state = new Proxy( definitions.state, {
		get: ( target, key ) => Reflect.get( target, key, target ),
	} );
	state.nonces = { progress: 'n-progress', complete: 'n-complete' };
	state.ajaxUrl = '/wp-admin/admin-ajax.php';
	state.messages = {
		play: 'Play',
		pause: 'Pause',
		loadError: 'Unable to load video.',
		embedBlocked: 'This video cannot be played here.',
		forward: '%d seconds forward',
		back: '%d seconds back',
		timeText: '%1$s of %2$s',
	};
	const on = ( element ) => {
		env.element = element;
	};
	const key = ( name, target = root ) => {
		let prevented = false;
		return {
			key: name,
			target,
			preventDefault: () => ( prevented = true ),
			stopPropagation() {},
			get prevented() {
				return prevented;
			},
		};
	};
	return { dom, doc, root, env, context, definitions, adapter, log, timers, on, key, state };
}

test( 'store: mounting loads only the provider adapter and shows the controls', async () => {
	const fixture = storeFixture( { context: { provider: 'youtube', videoId: 'dQw4w9WgXcQ' } } );
	const { definitions, context, log } = fixture;
	definitions.callbacks.mount();
	definitions.callbacks.mount();
	await tick();
	await tick();
	assert.deepEqual( log.loaded, [ 'youtube' ], 'one adapter, once' );
	assert.equal( log.args.source.id, 'dQw4w9WgXcQ' );
	assert.equal( log.args.custom, true );
	assert.equal( log.args.iframe, null, 'the custom player creates its own iframe' );
	assert.ok( log.args.mount.classList.contains( 'ohmylms-video-player-container' ) );
	assert.equal( context.mounted, true );
	assert.equal( context.duration, 200 );
	assert.ok( log.events.some( ( [ name ] ) => 'video-ready' === name ) );
	assert.equal( definitions.find( fixture.doc.getElementById( 'toggle' ) ).adapter, fixture.adapter );
	assert.equal( definitions.find( fixture.doc.getElementById( 'outside' ) ), null );
	fixture.dom.window.close();
} );

test( 'store: derived labels and slider values', async () => {
	const fixture = storeFixture( { context: { time: 65, duration: 200 } } );
	const { state, context } = fixture;
	assert.equal( state.progressWidth, '32.5%' );
	assert.equal( state.currentLabel, '1:05' );
	assert.equal( state.durationLabel, '3:20' );
	assert.equal( state.timeText, '1:05 of 3:20' );
	assert.equal( state.playLabel, 'Play' );
	assert.equal( state.overlayHidden, false );
	context.playing = true;
	assert.equal( state.playLabel, 'Pause' );
	assert.equal( state.overlayHidden, true );
	assert.equal( state.controlsHidden, false );
	context.idle = true;
	assert.equal( state.controlsHidden, true, 'idle while playing hides the controls' );
	context.speedOpen = true;
	assert.equal( state.controlsHidden, false, 'not while the speed menu is open' );
	context.volume = 0.42;
	assert.equal( state.volumeWidth, '42%' );
	context.muted = true;
	assert.equal( state.volumePercent, 0 );
	assert.equal( state.mutePressed, true );
	fixture.env.context = { ...context, speedRate: 1.5, rate: 1.5 };
	assert.equal( state.rateChecked, true );
	assert.equal( state.rateLabel, '1.5x' );
	fixture.dom.window.close();
} );

test( 'store: controls, shortcuts and the speed menu', async () => {
	const fixture = storeFixture();
	const { definitions, context, adapter, doc, root, timers, key } = fixture;
	definitions.callbacks.mount();
	await tick();
	await tick();

	fixture.on( doc.getElementById( 'big' ) );
	definitions.actions.start();
	assert.equal( adapter.plays, 1 );
	assert.equal( context.playing, true );
	assert.equal( context.started, true );
	timers.shift()();
	assert.equal( doc.activeElement?.id, '', 'the idle timer ran first' );

	fixture.on( root );
	const space = key( ' ' );
	definitions.actions.keydown( space );
	assert.ok( space.prevented );
	assert.equal( context.playing, false, 'Space pauses' );
	definitions.actions.keydown( key( 'ArrowRight' ) );
	assert.deepEqual( adapter.seeks, [ 5 ] );
	assert.equal( context.indicator, '5 seconds forward' );
	definitions.actions.keydown( key( '5' ) );
	assert.deepEqual( adapter.seeks, [ 5, 100 ], '5 seeks to 50 %' );
	definitions.actions.keydown( key( 'ArrowDown' ) );
	assert.equal( context.volume, 0.9 );
	definitions.actions.keydown( key( 'm' ) );
	assert.equal( context.muted, true );
	definitions.actions.keydown( key( 'm' ) );
	assert.equal( context.muted, false );
	assert.equal( context.volume, 0.9, 'unmuting restores the volume' );
	const onSlider = key( 'ArrowLeft', doc.getElementById( 'seek' ) );
	definitions.actions.keydown( onSlider );
	assert.equal( onSlider.prevented, false, 'the slider handles its own arrows' );
	fixture.on( doc.getElementById( 'seek' ) );
	const sliderKey = key( 'End', doc.getElementById( 'seek' ) );
	definitions.actions.seekKey( sliderKey );
	assert.ok( sliderKey.prevented );
	assert.equal( adapter.seeks.at( -1 ), 200 );

	fixture.on( doc.getElementById( 'speed' ) );
	definitions.actions.speedToggle();
	assert.equal( context.speedOpen, true );
	timers.splice( 0 ).forEach( ( run ) => run() );
	assert.equal( doc.activeElement.id, 'r1', 'the checked speed gets focus' );
	fixture.on( doc.getElementById( 'r1' ) );
	definitions.actions.speedMenuKey( key( 'ArrowDown', doc.getElementById( 'r1' ) ) );
	assert.equal( doc.activeElement.id, 'r2' );
	fixture.env.context = Object.assign( Object.create( context ), { speedRate: 2 } );
	fixture.on( doc.getElementById( 'r2' ) );
	definitions.actions.setRate();
	fixture.env.context = context;
	assert.equal( context.rate, 2 );
	assert.equal( context.speedOpen, false );
	assert.equal( doc.activeElement.id, 'speed', 'focus returns to the speed button' );

	definitions.actions.speedToggle();
	fixture.on( root );
	definitions.actions.outside( { target: doc.getElementById( 'outside' ) } );
	assert.equal( context.speedOpen, false, 'a click elsewhere closes the menu' );
	definitions.actions.speedToggle();
	fixture.on( doc.getElementById( 'r1' ) );
	definitions.actions.speedMenuKey( key( 'Escape', doc.getElementById( 'r1' ) ) );
	assert.equal( context.speedOpen, false );

	fixture.on( root );
	definitions.actions.keydown( key( 'c' ) );
	assert.equal( context.captionsOn, false, 'no captions available: nothing to toggle' );
	adapter.emit( 'captions', { available: true, on: false } );
	definitions.actions.keydown( key( 'c' ) );
	assert.equal( context.captionsOn, true );
	assert.equal( adapter.captionsOn, true );

	adapter.position = 30;
	adapter.emit( 'time', 30 );
	assert.equal( context.time, 30 );
	definitions.actions.pagehide();
	fixture.dom.window.close();
} );

test( 'store: autoplay waits for motion preferences; errors are announced', async () => {
	const still = storeFixture( { context: { autoplay: true }, reducedMotion: true } );
	still.definitions.callbacks.mount();
	await tick();
	await tick();
	assert.equal( still.adapter.plays, 0, 'reduced motion: no autoplay' );
	still.dom.window.close();

	const moving = storeFixture( { context: { autoplay: true } } );
	moving.definitions.callbacks.mount();
	await tick();
	await tick();
	assert.equal( moving.adapter.plays, 1 );
	assert.equal( moving.adapter.muted, true, 'autoplay starts muted' );
	moving.dom.window.close();

	const early = storeFixture();
	early.definitions.callbacks.mount();
	early.on( early.doc.getElementById( 'big' ) );
	early.definitions.actions.start();
	await tick();
	await tick();
	assert.equal( early.adapter.plays, 1, 'a play pressed before the player was ready runs once it is' );
	early.dom.window.close();

	const broken = storeFixture( { failLoad: true } );
	broken.definitions.callbacks.mount();
	await tick();
	await tick();
	assert.equal( broken.context.error, 'This video cannot be played here.' );
	assert.deepEqual( broken.log.announced, [ 'This video cannot be played here.' ] );
	assert.ok( broken.log.events.some( ( [ name ] ) => 'video-error' === name ) );
	assert.equal( errorMessage( {}, null ), 'Unable to load video.' );
	broken.dom.window.close();

	const embed = storeFixture( { context: { provider: 'embed', custom: false, track: false } } );
	embed.definitions.callbacks.mount();
	await tick();
	assert.deepEqual( embed.log.loaded, [], 'an untracked embed loads nothing' );
	embed.dom.window.close();
} );

test( 'legacy globals control the island', async () => {
	const fixture = storeFixture();
	fixture.definitions.callbacks.mount();
	await tick();
	await tick();
	const win = {};
	installShims( win, fixture.definitions.find );
	const legacy = new win.OhMyLMSVideoPlayer( { jquery: '3', 0: fixture.root } );
	legacy.play();
	assert.equal( fixture.adapter.plays, 1 );
	legacy.seekTo( 12 );
	assert.equal( fixture.adapter.seeks.at( -1 ), 12 );
	assert.equal( legacy.getDuration(), 200 );
	assert.equal( 'function', typeof win.initOhMyLMSVideoPlayers );
	const kept = { OhMyLMSVideoPlayer: 'theirs' };
	installShims( kept, fixture.definitions.find );
	assert.equal( kept.OhMyLMSVideoPlayer, 'theirs', 'an existing global is kept' );
	fixture.dom.window.close();
} );
