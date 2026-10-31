// @ts-check
/**
 * Store definitions of `ohmylms/video`: lesson and session-replay videos. One player is one island
 * (`data-wp-interactive="ohmylms/video"`) rendered by OhMyLMS\Extensions\Video::player(), which also
 * decorates the markup of theme overrides. The provider adapter is a separate module loaded only for
 * the provider on the page (see ADAPTERS); the progress session (session.js) reports what was
 * watched. Everything the runtime and the SDK provide is passed in, so tests/js/video.test.mjs
 * drives it in node.
 *
 * Context (per player, from Video::context()): `provider`, `videoId`, `hash`, `custom` (custom
 * controls), `lessonId`, `courseId`, `track`, `resume`, `videoDone`, `lessonDone`, `percent`,
 * `autoplay`, `loop`, `muted`, `title`, and the UI values `mounted`, `playing`, `started`, `time`,
 * `duration`, `volume`, `rate`, `rates`, `captions`, `captionsOn`, `volumeControl`, `speedOpen`,
 * `fullscreen`, `idle`, `seeking`, `error`, `indicator`. Speed menu items add `speedRate`.
 */
import {
	formatTime,
	menuIndex,
	pointerRatio,
	shortcut,
	sliderValue,
	timeText,
} from './progress.js';
import { createSession } from './session.js';

/** Adapter module (`ohmylms/video-<name>`) of each provider. */
export const ADAPTERS = Object.freeze( {
	html5: 'html5',
	youtube: 'youtube',
	vimeo: 'vimeo',
	bunny: 'playerjs',
	wistia: 'wistia',
} );

const ROOT = '[data-wp-interactive="ohmylms/video"]';
const IDLE_MS = 3000;
const INDICATOR_MS = 800;
const SEEK_THROTTLE_MS = 150;

/** @typedef {(event: Event) => void} EventHandler */

/**
 * @typedef {Object} Player
 * @property {HTMLElement}                           root           Island root.
 * @property {Object}                                context        Root context (reactive).
 * @property {import('./adapter.js').Adapter|null}   adapter        Adapter once ready.
 * @property {ReturnType<typeof createSession>|null} session        Progress session.
 * @property {boolean}                               wantPlay       Play was pressed before the adapter was ready.
 * @property {number}                                previousVolume Volume before muting.
 * @property {unknown}                               idleTimer      Controls auto-hide timer.
 * @property {unknown}                               indicatorTimer Seek/volume indicator timer.
 * @property {number}                                seekAt         Last throttled seek during a drag.
 * @property {number}                                pending        Drag target.
 * @property {DOMRect|null}                          rect           Track box during a drag.
 */

/**
 * Message for an adapter error.
 *
 * @param {Object<string, string>} messages Messages.
 * @param {?}                      error    Adapter error (`key`).
 * @return {string} Message.
 */
export function errorMessage( messages, error ) {
	const key = error && 'object' === typeof error ? error.key : '';
	return messages[ key ] || messages.loadError || 'Unable to load video.';
}

/**
 * Store definitions for `ohmylms/video`.
 *
 * @param {Object}                                                                                                   api                      Runtime.
 * @param {() => ?}                                                                                                  api.getContext           `@wordpress/interactivity` getContext.
 * @param {() => {ref: HTMLElement}}                                                                                 api.getElement           `@wordpress/interactivity` getElement.
 * @param {(callback: EventHandler) => EventHandler}                                                                 api.withSyncEvent        `@wordpress/interactivity` withSyncEvent.
 * @param {() => import('./session.js').VideoConfig & Object}                                                        api.getState             Store state.
 * @param {(name: string) => Promise<{createAdapter: (options: Object) => Promise<import('./adapter.js').Adapter>}>} api.loadAdapter          Import an adapter module.
 * @param {import('./session.js').RequestFn}                                                                         api.request              SDK `request()`.
 * @param {(root: EventTarget, name: string, detail?: Object, cancelable?: boolean) => boolean}                      api.emit                 SDK `emit()`.
 * @param {(message: string, options?: Object) => unknown}                                                           api.toast                SDK `toast()`.
 * @param {(message: string, politeness?: string) => void}                                                           api.announce             SDK `announce()`.
 * @param {import('./session.js').DebounceFn}                                                                        api.debounce             SDK `debounce()`.
 * @param {(name: string, args?: Array<unknown>, target?: EventTarget) => boolean}                                   api.jqueryTrigger        SDK `jqueryTrigger()`.
 * @param {() => boolean}                                                                                            api.prefersReducedMotion SDK `prefersReducedMotion()`.
 * @param {import('./adapter.js').ProviderWindow}                                                                    [api.win]                Window.
 * @param {Document}                                                                                                 [api.doc]                Document.
 * @param {() => number}                                                                                             [api.now]                Clock.
 * @param {(callback: () => void, ms: number) => unknown}                                                            [api.later]              `setTimeout`.
 * @param {(timer: unknown) => void}                                                                                 [api.cancel]             `clearTimeout`.
 * @param {(url: string, fields: Object) => string}                                                                  [api.beacon]             Final save transport (tests).
 * @return {{state: Object, actions: Object, callbacks: Object}} Definitions; `find( element )` (not
 *         enumerable, so the runtime does not see it) returns the player of an element.
 */
export function createVideo( {
	getContext,
	getElement,
	withSyncEvent,
	getState,
	loadAdapter,
	request,
	emit,
	toast,
	announce,
	debounce,
	jqueryTrigger,
	prefersReducedMotion,
	win = globalThis.window,
	doc = globalThis.document,
	now = () => Date.now(),
	later = ( callback, ms ) => setTimeout( callback, ms ),
	cancel = ( timer ) => clearTimeout( /** @type {?} */ ( timer ) ),
	beacon,
} ) {
	/** @type {WeakMap<Element, Player>} */
	const players = new WeakMap();
	const messages = () => getState().messages || {};
	const rootOf = ( element ) => element?.closest?.( ROOT ) || null;
	const find = ( element ) => {
		const root = rootOf( element );
		return root ? players.get( root ) || null : null;
	};
	/** @return {Player|null} Player of the element whose directive runs. */
	const current = () => find( getElement()?.ref );

	const duration = ( player ) =>
		player.adapter?.duration() || Number( player.context.duration ) || 0;

	const showIndicator = ( player, text ) => {
		player.context.indicator = text;
		cancel( player.indicatorTimer );
		player.indicatorTimer = later( () => {
			player.context.indicator = '';
		}, INDICATOR_MS );
	};

	const controlsFocused = ( player ) => {
		const active = doc?.activeElement;
		return Boolean(
			active &&
			player.root.contains( active ) &&
			active.closest?.( '.ohmylms-custom-controls' )
		);
	};

	const wake = ( player ) => {
		player.context.idle = false;
		cancel( player.idleTimer );
		if ( player.context.playing ) {
			player.idleTimer = later( () => {
				if ( player.context.playing && ! controlsFocused( player ) ) {
					player.context.idle = true;
				}
			}, IDLE_MS );
		}
	};

	const play = ( player ) => {
		if ( ! player.adapter ) {
			player.wantPlay = true;
			return;
		}
		player.context.error = '';
		player.adapter.play();
	};

	const toggle = ( player ) => {
		if ( player.context.playing ) {
			player.adapter?.pause();
		} else {
			play( player );
		}
	};

	const seekTo = ( player, seconds ) => {
		if ( ! player.adapter ) {
			return;
		}
		const end = duration( player );
		const target = Math.max( 0, end ? Math.min( end, seconds ) : seconds );
		player.context.time = target;
		if ( player.session ) {
			player.session.seek( target );
		} else {
			player.adapter.seek( target );
		}
	};

	const setVolume = ( player, volume ) => {
		const level = Math.max( 0, Math.min( 1, volume ) );
		if ( level > 0 ) {
			player.previousVolume = level;
		}
		player.adapter?.setVolume( level );
		player.context.volume = level;
		player.context.muted = 0 === level;
	};

	const toggleMute = ( player ) => {
		const { context } = player;
		if ( context.muted || 0 === context.volume ) {
			const level =
				context.volume > 0
					? context.volume
					: player.previousVolume || 1;
			player.adapter?.setMuted( false );
			setVolume( player, level );
		} else {
			player.previousVolume = context.volume;
			player.adapter?.setMuted( true );
			context.muted = true;
		}
	};

	const toggleCaptions = ( player ) => {
		if ( ! player.context.captions ) {
			return;
		}
		const on = ! player.context.captionsOn;
		player.context.captionsOn = on;
		player.adapter?.setCaptions( on );
	};

	const toggleFullscreen = ( player ) => {
		const { root } = player;
		const active =
			doc.fullscreenElement ||
			/** @type {?} */ ( doc ).webkitFullscreenElement;
		if ( active ) {
			const exit =
				doc.exitFullscreen ||
				/** @type {?} */ ( doc ).webkitExitFullscreen;
			Promise.resolve( exit?.call( doc ) ).catch( () => {} );
			return;
		}
		const enter =
			root.requestFullscreen ||
			/** @type {?} */ ( root ).webkitRequestFullscreen;
		if ( enter ) {
			Promise.resolve( enter.call( root ) ).catch( () => {} );
			return;
		}
		// iPhone Safari: only the <video> itself can go full screen.
		const media = /** @type {?} */ ( player.adapter?.media );
		media?.webkitEnterFullscreen?.();
	};

	const speedItems = ( player ) => [
		...player.root.querySelectorAll(
			'.ohmylms-speed-menu [role="menuitemradio"]'
		),
	];

	const focusSpeedItem = ( player, index = -1 ) => {
		const items = speedItems( player );
		const checked = items.findIndex(
			( item ) => 'true' === item.getAttribute( 'aria-checked' )
		);
		/** @type {HTMLElement|undefined} */ (
			items[ index >= 0 ? index : Math.max( 0, checked ) ]
		)?.focus();
	};

	const closeSpeed = ( player, focusButton = false ) => {
		player.context.speedOpen = false;
		if ( focusButton ) {
			/** @type {HTMLElement|null} */ (
				player.root.querySelector( '.ohmylms-speed-btn' )
			)?.focus();
		}
	};

	const run = ( player, action ) => {
		const { context } = player;
		const text = messages();
		switch ( action.action ) {
			case 'toggle':
				toggle( player );
				break;
			case 'seekBy': {
				seekTo( player, context.time + action.value );
				const seconds = Math.abs( action.value );
				showIndicator(
					player,
					String(
						action.value > 0
							? text.forward || '%d s >>'
							: text.back || '<< %d s'
					).replace( '%d', String( seconds ) )
				);
				break;
			}
			case 'seekTo':
				seekTo( player, action.value );
				break;
			case 'seekPercent':
				seekTo( player, ( duration( player ) * action.value ) / 100 );
				break;
			case 'volumeBy':
				setVolume(
					player,
					( context.muted ? 0 : context.volume ) + action.value
				);
				showIndicator(
					player,
					`${ Math.round( context.volume * 100 ) }%`
				);
				break;
			case 'mute':
				toggleMute( player );
				break;
			case 'fullscreen':
				toggleFullscreen( player );
				break;
			case 'captions':
				toggleCaptions( player );
				break;
		}
	};

	/**
	 * Mirror adapter events in the context.
	 *
	 * @param {Player} player Player.
	 */
	const bind = ( player ) => {
		const { adapter, context } = player;
		adapter.on( 'play', () => {
			context.playing = true;
			context.started = true;
			context.error = '';
			wake( player );
		} );
		adapter.on( 'pause', () => {
			context.playing = false;
			wake( player );
		} );
		adapter.on( 'ended', () => {
			context.playing = false;
			context.time = duration( player );
			wake( player );
		} );
		adapter.on( 'time', ( seconds ) => {
			if ( ! context.seeking ) {
				context.time = Number( seconds ) || 0;
			}
		} );
		adapter.on( 'duration', ( seconds ) => {
			if ( seconds > 0 ) {
				context.duration = seconds;
			}
		} );
		adapter.on( 'rate', ( rate ) => {
			context.rate = Number( rate ) || 1;
		} );
		adapter.on( 'volume', ( value ) => {
			context.volume = Number( value?.volume ) || 0;
			context.muted = Boolean( value?.muted );
		} );
		adapter.on( 'captions', ( value ) => {
			context.captions = Boolean( value?.available );
			context.captionsOn = Boolean( value?.on );
		} );
		adapter.on( 'error', ( error ) => {
			context.error = errorMessage( messages(), error );
			announce( context.error, 'assertive' );
		} );
		const media = adapter.media;
		if ( media ) {
			media.addEventListener( 'webkitbeginfullscreen', () => {
				context.fullscreen = true;
			} );
			media.addEventListener( 'webkitendfullscreen', () => {
				context.fullscreen = false;
			} );
		}
	};

	/**
	 * Load the provider adapter and start the session.
	 *
	 * @param {Player} player Player.
	 */
	const start = async ( player ) => {
		const { root, context } = player;
		const name = ADAPTERS[ context.provider ];
		// An embed without a player API, or a provider iframe that needs neither tracking nor autoplay
		// (its third-party API is then never loaded).
		const needed =
			context.custom ||
			'html5' === context.provider ||
			context.track ||
			context.autoplay;
		if ( ! name || ! needed ) {
			return;
		}
		try {
			const module = await loadAdapter( name );
			const adapter = await module.createAdapter( {
				video: root.querySelector( 'video' ),
				iframe: context.custom ? null : root.querySelector( 'iframe' ),
				mount: root.querySelector( '.ohmylms-video-player-container' ),
				source: {
					provider: context.provider,
					id: context.videoId,
					hash: context.hash,
				},
				custom: Boolean( context.custom ),
				config: {
					loop: Boolean( context.loop ),
					muted: Boolean( context.muted ),
					custom: Boolean( context.custom ),
					title: context.title || '',
				},
				win,
			} );
			player.adapter = adapter;
			bind( player );
			player.session = createSession( {
				adapter,
				context,
				root,
				config: getState(),
				request,
				emit,
				jqueryTrigger,
				toast,
				announce,
				debounce,
				now,
				doc,
				...( beacon ? { beacon } : {} ),
			} );
			context.duration =
				adapter.duration() || Number( context.duration ) || 0;
			context.rates = adapter.supports.rates;
			context.volumeControl = adapter.supports.volume;
			context.captions = adapter.supports.captions;
			context.mounted = true;
			emit( root, 'video-ready', {
				lessonId: Number( context.lessonId ) || 0,
				provider: context.provider,
				duration: context.duration,
			} );
			if ( player.wantPlay ) {
				player.wantPlay = false;
				play( player );
			} else if ( context.autoplay && ! prefersReducedMotion() ) {
				// Browsers only autoplay muted media; reduced motion keeps the video still.
				adapter.setMuted( true );
				context.muted = true;
				play( player );
			}
			await player.session.completeOnLoad();
		} catch ( error ) {
			context.error = errorMessage( messages(), error );
			announce( context.error, 'assertive' );
			emit( root, 'video-error', {
				lessonId: Number( context.lessonId ) || 0,
				provider: context.provider,
				message: context.error,
			} );
		}
	};

	const definitions = {
		state: {
			get progressWidth() {
				const { time, duration: length } = getContext();
				return `${ length > 0 ? Math.min( 100, ( time / length ) * 100 ) : 0 }%`;
			},
			get currentLabel() {
				return formatTime( getContext().time );
			},
			get durationLabel() {
				const length = getContext().duration;
				return length > 0 ? formatTime( length ) : '--:--';
			},
			get timeNow() {
				return Math.round( getContext().time || 0 );
			},
			get durationMax() {
				return Math.round( getContext().duration || 0 );
			},
			get timeText() {
				const { time, duration: length } = getContext();
				return timeText( time, length, messages().timeText );
			},
			get volumePercent() {
				const { muted, volume } = getContext();
				return muted
					? 0
					: Math.round( ( Number( volume ) || 0 ) * 100 );
			},
			get volumeWidth() {
				return `${ getState().volumePercent }%`;
			},
			get volumeText() {
				return `${ getState().volumePercent }%`;
			},
			get playLabel() {
				return getContext().playing
					? messages().pause || 'Pause'
					: messages().play || 'Play';
			},
			get muteLabel() {
				const { muted, volume } = getContext();
				return muted || 0 === volume
					? messages().unmute || 'Unmute'
					: messages().mute || 'Mute';
			},
			get mutePressed() {
				const { muted, volume } = getContext();
				return Boolean( muted || 0 === volume );
			},
			get fullscreenLabel() {
				return getContext().fullscreen
					? messages().exitFullscreen || 'Exit full screen'
					: messages().fullscreen || 'Full screen';
			},
			get overlayHidden() {
				const { playing, error } = getContext();
				return Boolean( playing || error );
			},
			get controlsHidden() {
				const { playing, idle, speedOpen } = getContext();
				return Boolean( playing && idle && ! speedOpen );
			},
			get rateLabel() {
				return `${ getContext().rate || 1 }x`;
			},
			get rateChecked() {
				const { speedRate, rate } = getContext();
				return Number( speedRate ) === Number( rate || 1 );
			},
			get speedHidden() {
				return ! getContext().rates;
			},
			get captionsHidden() {
				return ! getContext().captions;
			},
			get volumeHidden() {
				return ! getContext().volumeControl;
			},
			get coverHidden() {
				return Boolean( getContext().started );
			},
			get errorHidden() {
				return ! getContext().error;
			},
			get indicatorHidden() {
				return ! getContext().indicator;
			},
		},
		actions: {
			toggle() {
				const player = current();
				if ( player ) {
					toggle( player );
				}
			},
			/** Big play button and logo: play, then keep focus in the player. */
			start() {
				const player = current();
				if ( ! player ) {
					return;
				}
				play( player );
				later( () => {
					/** @type {HTMLElement|null} */ (
						player.root.querySelector( '.ohmylms-play-pause' )
					)?.focus();
				}, 0 );
			},
			/** Poster cover of the plain `<video>`: hide it, play and focus the video. */
			coverPlay() {
				const player = current();
				if ( ! player ) {
					return;
				}
				player.context.started = true;
				play( player );
				later( () => player.root.querySelector( 'video' )?.focus(), 0 );
			},
			seekKey: withSyncEvent( ( event ) => {
				const player = current();
				if ( ! player ) {
					return;
				}
				const value = sliderValue(
					/** @type {KeyboardEvent} */ ( event ).key,
					player.context.time,
					duration( player ),
					5
				);
				if ( null === value ) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				seekTo( player, value );
			} ),
			seekStart: withSyncEvent( ( event ) => {
				const player = current();
				const pointer = /** @type {PointerEvent} */ ( event );
				if (
					! player ||
					pointer.button > 0 ||
					! ( duration( player ) > 0 )
				) {
					return;
				}
				const track = /** @type {HTMLElement} */ (
					event.currentTarget || getElement().ref
				);
				track.setPointerCapture?.( pointer.pointerId );
				player.rect = track.getBoundingClientRect();
				player.context.seeking = true;
				player.seekAt = 0;
				player.pending =
					pointerRatio( pointer.clientX, player.rect ) *
					duration( player );
				player.context.time = player.pending;
				event.preventDefault();
			} ),
			seekMove: withSyncEvent( ( event ) => {
				const player = current();
				if ( ! player?.context.seeking ) {
					return;
				}
				const pointer = /** @type {PointerEvent} */ ( event );
				player.pending =
					pointerRatio( pointer.clientX, player.rect ) *
					duration( player );
				player.context.time = player.pending;
				if ( now() - player.seekAt > SEEK_THROTTLE_MS ) {
					player.seekAt = now();
					player.adapter?.seek( player.pending );
				}
				event.preventDefault();
			} ),
			seekEnd() {
				const player = current();
				if ( ! player?.context.seeking ) {
					return;
				}
				player.context.seeking = false;
				seekTo( player, player.pending );
			},
			volumeKey: withSyncEvent( ( event ) => {
				const player = current();
				if ( ! player ) {
					return;
				}
				const { context } = player;
				const value = sliderValue(
					/** @type {KeyboardEvent} */ ( event ).key,
					context.muted ? 0 : context.volume,
					1,
					0.05
				);
				if ( null === value ) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				setVolume( player, value );
			} ),
			volumeStart: withSyncEvent( ( event ) => {
				const player = current();
				const pointer = /** @type {PointerEvent} */ ( event );
				if ( ! player || pointer.button > 0 ) {
					return;
				}
				const track = /** @type {HTMLElement} */ (
					event.currentTarget || getElement().ref
				);
				track.setPointerCapture?.( pointer.pointerId );
				player.rect = track.getBoundingClientRect();
				player.context.volumeDrag = true;
				setVolume(
					player,
					pointerRatio( pointer.clientX, player.rect )
				);
				event.preventDefault();
			} ),
			volumeMove: withSyncEvent( ( event ) => {
				const player = current();
				if ( ! player?.context.volumeDrag ) {
					return;
				}
				setVolume(
					player,
					pointerRatio(
						/** @type {PointerEvent} */ ( event ).clientX,
						player.rect
					)
				);
				event.preventDefault();
			} ),
			volumeEnd() {
				const player = current();
				if ( player ) {
					player.context.volumeDrag = false;
				}
			},
			mute() {
				const player = current();
				if ( player ) {
					toggleMute( player );
				}
			},
			captions() {
				const player = current();
				if ( player ) {
					toggleCaptions( player );
				}
			},
			fullscreen() {
				const player = current();
				if ( player ) {
					toggleFullscreen( player );
				}
			},
			fullscreenChange() {
				const player = current();
				if ( player ) {
					const active =
						doc.fullscreenElement ||
						/** @type {?} */ ( doc ).webkitFullscreenElement;
					player.context.fullscreen = active === player.root;
				}
			},
			speedToggle() {
				const player = current();
				if ( ! player ) {
					return;
				}
				player.context.speedOpen = ! player.context.speedOpen;
				if ( player.context.speedOpen ) {
					later( () => focusSpeedItem( player ), 0 );
				}
			},
			speedButtonKey: withSyncEvent( ( event ) => {
				const player = current();
				const { key } = /** @type {KeyboardEvent} */ ( event );
				if (
					! player ||
					! [ 'ArrowUp', 'ArrowDown' ].includes( key )
				) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				player.context.speedOpen = true;
				later( () => focusSpeedItem( player ), 0 );
			} ),
			speedMenuKey: withSyncEvent( ( event ) => {
				const player = current();
				if ( ! player ) {
					return;
				}
				const { key } = /** @type {KeyboardEvent} */ ( event );
				if ( 'Escape' === key ) {
					event.preventDefault();
					event.stopPropagation();
					closeSpeed( player, true );
					return;
				}
				if ( 'Tab' === key ) {
					closeSpeed( player );
					return;
				}
				const items = speedItems( player );
				const index = menuIndex(
					key,
					items.indexOf( /** @type {Element} */ ( event.target ) ),
					items.length
				);
				if ( null !== index ) {
					event.preventDefault();
					event.stopPropagation();
					focusSpeedItem( player, index );
				}
			} ),
			setRate() {
				const player = current();
				if ( ! player ) {
					return;
				}
				const rate = Number( getContext().speedRate ) || 1;
				player.adapter?.setRate( rate );
				player.context.rate = rate;
				closeSpeed( player, true );
			},
			outside( event ) {
				const player = current();
				const control = player?.root.querySelector(
					'.ohmylms-speed-control'
				);
				if (
					player?.context.speedOpen &&
					control &&
					! control.contains( /** @type {Node} */ ( event.target ) )
				) {
					closeSpeed( player );
				}
			},
			keydown: withSyncEvent( ( event ) => {
				const player = current();
				if ( ! player?.adapter ) {
					return;
				}
				const keyboard = /** @type {KeyboardEvent} */ ( event );
				const target = /** @type {HTMLElement} */ ( event.target );
				if ( ! player.context.custom ) {
					// A plain <video> whose author switched its controls off: Space, Enter and K play and pause.
					const bare =
						'VIDEO' === target?.tagName &&
						! (
							/** @type {HTMLVideoElement} */ ( target ).controls
						);
					if (
						bare &&
						[ ' ', 'Enter', 'k', 'K' ].includes( keyboard.key )
					) {
						event.preventDefault();
						toggle( player );
					}
					return;
				}
				const action = shortcut( keyboard, {
					tag: target?.tagName,
					role: target?.getAttribute?.( 'role' ),
					editable: Boolean( target?.isContentEditable ),
				} );
				if ( ! action ) {
					return;
				}
				event.preventDefault();
				wake( player );
				run( player, action );
			} ),
			activity() {
				const player = current();
				if ( player ) {
					wake( player );
				}
			},
			pagehide() {
				current()?.session?.flush();
			},
			visibility() {
				if ( 'hidden' === doc.visibilityState ) {
					current()?.session?.flush();
				}
			},
		},
		callbacks: {
			mount() {
				const { ref } = getElement();
				if ( ! ref || players.has( ref ) ) {
					return;
				}
				/** @type {Player} */
				const player = {
					root: ref,
					context: getContext(),
					adapter: null,
					session: null,
					wantPlay: false,
					previousVolume: 1,
					idleTimer: null,
					indicatorTimer: null,
					seekAt: 0,
					pending: 0,
					rect: null,
				};
				players.set( ref, player );
				start( player );
			},
		},
	};
	Object.defineProperty( definitions, 'find', { value: find } );
	return definitions;
}

/**
 * `window.OhMyLMSVideoPlayer` and `window.initOhMyLMSVideoPlayers` of the removed jQuery player, kept
 * as thin shims for one release: `new OhMyLMSVideoPlayer( element )` controls the island the
 * element belongs to; players are mounted by the store, so `initOhMyLMSVideoPlayers()` does nothing.
 *
 * @param {?}                                 win  Window.
 * @param {(element: Element) => Player|null} find Player of an element.
 */
export function installShims( win, find ) {
	if ( ! win || win.OhMyLMSVideoPlayer ) {
		return;
	}
	class OhMyLMSVideoPlayer {
		/** @param {?} element Element or jQuery object. */
		constructor( element ) {
			this.element = element?.jquery ? element[ 0 ] : element;
		}
		get adapter() {
			return find( this.element )?.adapter || null;
		}
		play() {
			this.adapter?.play();
		}
		pause() {
			this.adapter?.pause();
		}
		seekTo( seconds ) {
			const player = find( this.element );
			if ( player?.session ) {
				player.session.seek( Number( seconds ) || 0 );
			} else {
				this.adapter?.seek( Number( seconds ) || 0 );
			}
		}
		setVolume( volume ) {
			this.adapter?.setVolume( Number( volume ) || 0 );
		}
		setPlaybackRate( rate ) {
			this.adapter?.setRate( Number( rate ) || 1 );
		}
		getCurrentTime() {
			return this.adapter?.time() || 0;
		}
		getDuration() {
			return this.adapter?.duration() || 0;
		}
		destroy() {}
	}
	win.OhMyLMSVideoPlayer = OhMyLMSVideoPlayer;
	win.initOhMyLMSVideoPlayers = () => {};
}
