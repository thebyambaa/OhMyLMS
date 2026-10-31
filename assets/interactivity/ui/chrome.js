/**
 * Page chrome for the `ohmylms/ui` store: the shared toast region, dropdowns, drawers, scroll to
 * top, the block-theme menu fallback and the email-verification notices. Everything the runtime
 * or the SDK provides is passed in, so tests/js/interactivity-ui.test.mjs drives it in node.
 */

const DEFAULT_TIMERS = {
	setTimeout: ( callback, ms ) => setTimeout( callback, ms ),
	clearTimeout: ( id ) => clearTimeout( id ),
	now: () => Date.now(),
};

/**
 * @typedef {Object} ToastSnapshot
 * @property {Object|null} current Toast on screen (or fading out).
 * @property {boolean}     visible Whether it is shown (false while fading out).
 * @property {number}      queued  Toasts waiting.
 * @property {boolean}     paused  Auto-hide paused (hover or focus).
 */

/**
 * @typedef {Object} ToastQueue
 * @property {(item: Object|null) => void} push     Show or queue a toast item.
 * @property {(id?: string) => void}       dismiss  Hide the current toast, or drop a queued one by id.
 * @property {() => void}                  pause    Stop the auto-hide timer.
 * @property {() => void}                  resume   Restart it with the time that was left.
 * @property {() => ToastSnapshot}         snapshot Current state.
 */

/** @typedef {(event: Event) => void} EventHandler */

/**
 * One toast at a time. A toast arriving while another is shown waits, but cuts the current
 * one's remaining time to `hurryMs`; a repeat of the current message only restarts its timer.
 *
 * @param {Object}                        options           Options.
 * @param {function(ToastSnapshot): void} options.onChange  Called after every change.
 * @param {Object}                        [options.timers]  `setTimeout`, `clearTimeout`, `now`.
 * @param {function(): number}            [options.leaveMs] Fade-out time before the next toast.
 * @param {number}                        [options.max]     Queue length; the oldest is dropped.
 * @param {number}                        [options.hurryMs] Time left for the current toast when another waits.
 * @return {ToastQueue} Queue.
 */
export function createToastQueue( {
	onChange,
	timers = DEFAULT_TIMERS,
	leaveMs = () => 300,
	max = 5,
	hurryMs = 1500,
} ) {
	const queue = [];
	let current = null;
	let visible = false;
	let paused = false;
	let timer = null;
	let leaving = null;
	let deadline = 0;
	let remaining = 0;
	const snapshot = () => ( {
		current,
		visible,
		queued: queue.length,
		paused,
	} );
	const publish = () => onChange( snapshot() );
	const same = ( a, b ) =>
		Boolean( a && b ) && a.message === b.message && a.type === b.type;
	const stop = () => {
		if ( timer ) {
			timers.clearTimeout( timer );
			timer = null;
		}
	};
	const schedule = ( ms ) => {
		stop();
		remaining = ms;
		if ( ms > 0 ) {
			deadline = timers.now() + ms;
			timer = timers.setTimeout( hide, ms );
		}
	};
	const left = () =>
		timer ? Math.max( 0, deadline - timers.now() ) : remaining;
	function show( item ) {
		current = item;
		visible = true;
		paused = false;
		schedule( item.duration );
		if ( queue.length ) {
			hurry();
		}
		publish();
	}
	function hurry() {
		if ( ! current || ! visible ) {
			return;
		}
		const time = current.duration > 0 ? left() : hurryMs;
		if ( paused ) {
			remaining = Math.min( time || hurryMs, hurryMs );
		} else if ( ! timer || time > hurryMs ) {
			schedule( hurryMs );
		}
	}
	function next() {
		leaving = null;
		current = null;
		visible = false;
		if ( queue.length ) {
			show( queue.shift() );
		} else {
			publish();
		}
	}
	function hide() {
		stop();
		if ( ! current || ! visible ) {
			return;
		}
		visible = false;
		paused = false;
		publish();
		const ms = leaveMs();
		if ( ms > 0 ) {
			leaving = timers.setTimeout( next, ms );
		} else {
			next();
		}
	}
	return {
		push( item ) {
			if ( ! item ) {
				return;
			}
			if ( visible && same( current, item ) ) {
				if ( ! paused ) {
					schedule( item.duration );
				}
				return;
			}
			if ( queue.some( ( queued ) => same( queued, item ) ) ) {
				return;
			}
			if ( ! current ) {
				show( item );
				return;
			}
			queue.push( item );
			if ( queue.length > max ) {
				queue.shift();
			}
			hurry();
			publish();
		},
		dismiss( id = '' ) {
			if ( id && current?.id !== id ) {
				const index = queue.findIndex( ( item ) => item.id === id );
				if ( index > -1 ) {
					queue.splice( index, 1 );
					publish();
				}
				return;
			}
			if ( leaving ) {
				return;
			}
			hide();
		},
		pause() {
			if ( ! current || ! visible || paused ) {
				return;
			}
			paused = true;
			remaining = left();
			stop();
			publish();
		},
		resume() {
			if ( ! paused ) {
				return;
			}
			paused = false;
			if ( current.duration > 0 || queue.length ) {
				schedule( Math.max( remaining, 1000 ) );
			}
			publish();
		},
		snapshot,
	};
}

const VERIFICATION_PARAMS = [
	'ohmylms_email_verified',
	'ohmylms_verify_sent',
	'ohmylms_verify_error',
];

/**
 * The notice for the email-verification redirect parameters, and the query string without them
 * (so a reload does not repeat it).
 *
 * @param {string} search `location.search`.
 * @return {{key: string, type: string, query: string}|null} Notice: `key` names a message in `state.messages`.
 */
export function verificationNotice( search ) {
	const params = new URLSearchParams( search );
	let key = '';
	let type = 'success';
	if ( params.get( 'ohmylms_email_verified' ) ) {
		key = 'emailVerified';
	} else if ( params.get( 'ohmylms_verify_sent' ) ) {
		key = 'verifySent';
	} else if ( params.get( 'ohmylms_verify_error' ) ) {
		key =
			'expired' === params.get( 'ohmylms_verify_error' )
				? 'verifyExpired'
				: 'verifyInvalid';
		type = 'danger';
	}
	if ( ! key ) {
		return null;
	}
	VERIFICATION_PARAMS.forEach( ( name ) => params.delete( name ) );
	const query = params.toString();
	return { key, type, query: query ? `?${ query }` : '' };
}

/**
 * What the dropdown trigger does with a key: ArrowDown opens and focuses the first item,
 * Space toggles (Enter already clicks a link or button), Escape closes an open menu.
 *
 * @param {string}  key  `KeyboardEvent.key`.
 * @param {boolean} open Whether the menu is open.
 * @return {'open-first'|'toggle'|'close'|null} Intent.
 */
export function dropdownKey( key, open ) {
	if ( 'ArrowDown' === key ) {
		return 'open-first';
	}
	if ( ' ' === key || 'Spacebar' === key ) {
		return 'toggle';
	}
	return 'Escape' === key && open ? 'close' : null;
}

/**
 * The block-theme menu fallback acts only when core's `core/navigation` store did not react to
 * the click within a frame.
 *
 * @param {'open'|'close'} intent Button pressed.
 * @param {boolean}        before Menu open before the click.
 * @param {boolean}        after  Menu open one frame later.
 * @return {boolean|null} Open state to apply, or null to leave it to core.
 */
export function navigationFallback( intent, before, after ) {
	if ( before !== after ) {
		return null;
	}
	const open = 'open' === intent;
	return open === after ? null : open;
}

const NAV_THEMES = [
	'theme-twentytwentytwo',
	'theme-twentytwentythree',
	'theme-twentytwentyfive',
];
const NAV_OPEN = 'wp-block-navigation__responsive-container-open';
const NAV_CLOSE = 'wp-block-navigation__responsive-container-close';
const NAV_CONTAINER = 'wp-block-navigation__responsive-container';

/**
 * Store definitions for the chrome. Merge them into `ohmylms/ui`.
 *
 * @param {Object}                                                          api                     Runtime and SDK.
 * @param {() => Object}                                                    api.getContext          `@wordpress/interactivity` getContext.
 * @param {() => {ref: HTMLElement}}                                        api.getElement          `@wordpress/interactivity` getElement.
 * @param {(callback: EventHandler) => EventHandler}                        api.withSyncEvent       `@wordpress/interactivity` withSyncEvent.
 * @param {() => Object}                                                    api.getState            Returns the live `ohmylms/ui` state.
 * @param {(target: EventTarget, name: string, detail?: Object) => boolean} api.emit                SDK `emit`.
 * @param {(container: Element) => HTMLElement[]}                           [api.focusable]         SDK `focusable`.
 * @param {(container: HTMLElement, options?: Object) => (() => void)}      [api.trapFocus]         SDK `trapFocus`.
 * @param {() => Object[]}                                                  [api.takePendingToasts] SDK `takePendingToasts`.
 * @param {() => boolean}                                                   [api.reducedMotion]     SDK `prefersReducedMotion`.
 * @param {Object}                                                          [api.timers]            Timer functions (tests).
 * @param {(callback: () => void) => void}                                  [api.frame]             `requestAnimationFrame`.
 * @param {Window}                                                          [api.win]               Window.
 * @return {{state: Object, actions: Object, callbacks: Object, toasts: {push: ToastQueue['push'], dismiss: ToastQueue['dismiss']}}} Definitions.
 */
export function createChrome( api ) {
	const {
		getContext,
		getElement,
		withSyncEvent,
		getState,
		emit,
		focusable = () => [],
		trapFocus,
		takePendingToasts = () => [],
		reducedMotion = () => false,
		timers = DEFAULT_TIMERS,
		frame = ( callback ) => requestAnimationFrame( callback ),
	} = api;
	const win = () => api.win || globalThis.window;
	const activeElement = ( node ) => node?.ownerDocument?.activeElement;

	let region = null;
	let returnTo = null;
	let shownId = '';
	const announceChange = ( current, visible ) => {
		const id = current && visible ? current.id : '';
		if ( id === shownId ) {
			return;
		}
		const target = region || win()?.document;
		if ( target && shownId ) {
			emit( target, 'toast-hidden', { id: shownId } );
		}
		if ( target && id ) {
			emit( target, 'toast-shown', {
				id,
				type: current.type,
				message: current.message,
			} );
		}
		shownId = id;
	};
	const queue = createToastQueue( {
		timers,
		leaveMs: () => ( reducedMotion() ? 0 : 300 ),
		onChange( { current, visible } ) {
			const toast = getState().toast;
			toast.id = current ? current.id : '';
			toast.message = current ? current.message : '';
			toast.type = current ? current.type : 'success';
			toast.visible = visible;
			toast.shown = Boolean( current );
			announceChange( current, visible );
		},
	} );
	const dismissCurrent = () => {
		if ( region && region.contains( activeElement( region ) ) ) {
			if ( returnTo?.isConnected ) {
				returnTo.focus( { preventScroll: true } );
			} else {
				activeElement( region )?.blur?.();
			}
		}
		queue.dismiss();
	};

	const drawers = new WeakMap();
	const setDrawer = ( context, root, open, toggle = null ) => {
		context.drawerOpen = open;
		const entry = drawers.get( root ) || {};
		entry.release?.();
		entry.release = null;
		if ( toggle ) {
			entry.toggle = toggle;
		}
		drawers.set( root, entry );
		emit( root, 'drawer-changed', { open } );
		if ( ! open ) {
			return;
		}
		const panel = context.drawerPanel
			? root.querySelector( context.drawerPanel )
			: null;
		frame( () => {
			if (
				! context.drawerOpen ||
				! panel ||
				! trapFocus ||
				! panel.getClientRects().length
			) {
				return;
			}
			entry.release = trapFocus( panel, {
				onEscape: () => setDrawer( context, root, false ),
			} );
		} );
	};
	const drawerRoot = ( element ) =>
		element.closest( '[data-wp-context]' ) || element;
	const toggleKey = ( key ) =>
		'Enter' === key || ' ' === key || 'Spacebar' === key;

	const menuOf = ( trigger ) =>
		trigger.ownerDocument.getElementById(
			trigger.getAttribute( 'aria-controls' ) || ''
		) || trigger.nextElementSibling;

	return {
		state: {
			// Defaults for pages that load `ohmylms/ui` without Chrome::ui_state().
			toast: {
				id: '',
				message: '',
				type: 'success',
				visible: false,
				shown: false,
			},
			messages: {},
			get toastSuccess() {
				return 'success' === getState().toast.type;
			},
			get toastWarning() {
				return 'warning' === getState().toast.type;
			},
			get toastDanger() {
				return 'danger' === getState().toast.type;
			},
			get toastHidden() {
				return ! getState().toast.shown;
			},
		},
		actions: {
			dismissToast: withSyncEvent( ( event ) => {
				event.preventDefault();
				dismissCurrent();
			} ),
			dismissToastKey: withSyncEvent( ( event ) => {
				if ( toggleKey( event.key ) ) {
					event.preventDefault();
					dismissCurrent();
				}
			} ),
			toastKeydown: withSyncEvent( ( event ) => {
				if ( 'Escape' === event.key ) {
					event.preventDefault();
					dismissCurrent();
				}
			} ),
			pauseToast( event ) {
				if (
					'focusin' === event.type &&
					region &&
					! region.contains( event.relatedTarget )
				) {
					returnTo = event.relatedTarget || null;
				}
				queue.pause();
			},
			resumeToast( event ) {
				if ( region ) {
					const inside =
						'focusout' === event.type
							? region.contains( event.relatedTarget )
							: region.contains( activeElement( region ) ) ||
								region.matches?.( ':hover' );
					if ( inside ) {
						return;
					}
				}
				queue.resume();
			},

			dropdownToggle: withSyncEvent( ( event ) => {
				event.preventDefault();
				const context = getContext();
				context.open = ! context.open;
				emit( getElement().ref, 'dropdown-changed', {
					open: context.open,
				} );
			} ),
			dropdownKeydown: withSyncEvent( ( event ) => {
				const context = getContext();
				const intent = dropdownKey( event.key, context.open );
				if ( ! intent || 'close' === intent ) {
					return;
				}
				event.preventDefault();
				const trigger = getElement().ref;
				context.open = 'open-first' === intent ? true : ! context.open;
				emit( trigger, 'dropdown-changed', { open: context.open } );
				if ( 'open-first' === intent ) {
					frame( () => focusable( menuOf( trigger ) )[ 0 ]?.focus() );
				}
			} ),
			dropdownOutside( event ) {
				const context = getContext();
				if (
					context.open &&
					! getElement().ref.contains( event.target )
				) {
					context.open = false;
				}
			},
			dropdownEscape( event ) {
				const context = getContext();
				if ( 'Escape' !== event.key || ! context.open ) {
					return;
				}
				context.open = false;
				const root = getElement().ref;
				if ( root.contains( activeElement( root ) ) ) {
					root.querySelector( '[aria-expanded]' )?.focus();
				}
			},
			dropdownFocusout( event ) {
				const root = getElement().ref;
				if (
					event.relatedTarget &&
					! root.contains( event.relatedTarget )
				) {
					getContext().open = false;
				}
			},

			drawerToggle: withSyncEvent( ( event ) => {
				event.preventDefault();
				const context = getContext();
				const toggle = getElement().ref;
				setDrawer(
					context,
					drawerRoot( toggle ),
					! context.drawerOpen,
					toggle
				);
			} ),
			drawerKey: withSyncEvent( ( event ) => {
				if ( ! toggleKey( event.key ) ) {
					return;
				}
				event.preventDefault();
				const context = getContext();
				const toggle = getElement().ref;
				setDrawer(
					context,
					drawerRoot( toggle ),
					! context.drawerOpen,
					toggle
				);
			} ),
			drawerOutside( event ) {
				const context = getContext();
				if (
					! context.drawerOpen ||
					( context.drawerInside &&
						event.target?.closest?.( context.drawerInside ) )
				) {
					return;
				}
				setDrawer( context, getElement().ref, false );
			},
			drawerEscape( event ) {
				const context = getContext();
				if ( 'Escape' !== event.key || ! context.drawerOpen ) {
					return;
				}
				const root = getElement().ref;
				setDrawer( context, root, false );
				drawers.get( root )?.toggle?.focus();
			},

			scrollToTop: withSyncEvent( ( event ) => {
				event.preventDefault();
				win().scrollTo( {
					top: 0,
					behavior: reducedMotion() ? 'auto' : 'smooth',
				} );
			} ),
		},
		callbacks: {
			toastRegion() {
				region = getElement().ref;
				takePendingToasts().forEach( ( item ) => queue.push( item ) );
			},
			trackScroll() {
				const context = getContext();
				context.scrolled =
					( win().scrollY || 0 ) >
					( Number( context.threshold ) || 600 );
			},
		},
		toasts: {
			push: ( item ) => queue.push( item ),
			dismiss: ( id ) => queue.dismiss( id ),
		},
	};
}

/**
 * Document-level behaviour that has no server-rendered root: toast events, the resend
 * verification link, the verification redirect notice and the block-theme menu fallback.
 *
 * @param {Object}                                                          api           Dependencies.
 * @param {Window}                                                          api.win       Window.
 * @param {Object}                                                          api.chrome    `createChrome()` result.
 * @param {(message: string, options?: Object) => Object|null}              api.toast     SDK `toast`.
 * @param {(message: Object, options?: Object, id?: string) => Object|null} api.toastItem SDK `toastItem`.
 * @param {(options: Object) => Promise<Object>}                            api.request   SDK `request`.
 * @param {() => Object<string, string>}                                    api.messages  Returns `state.messages`.
 * @param {(callback: () => void) => void}                                  [api.frame]   `requestAnimationFrame`.
 */
export function installChrome( {
	win,
	chrome,
	toast,
	toastItem,
	request,
	messages,
	frame = ( callback ) => requestAnimationFrame( callback ),
} ) {
	const doc = win.document;
	let legacyCount = 0;
	win.addEventListener( 'ohmylms:toast', ( event ) => {
		if ( event.defaultPrevented || ! event.detail ) {
			return;
		}
		event.detail.accepted = true;
		chrome.toasts.push(
			toastItem(
				event.detail,
				{},
				`ohmylms-toast-event-${ ++legacyCount }`
			)
		);
	} );
	win.addEventListener( 'ohmylms:toast-dismiss', ( event ) =>
		chrome.toasts.dismiss( event.detail?.id || '' )
	);

	doc.addEventListener( 'click', ( event ) => {
		const link = event.target?.closest?.(
			'a[href*="ohmylms_resend_verification"]'
		);
		if (
			! link ||
			event.defaultPrevented ||
			event.button > 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}
		event.preventDefault();
		if ( 'true' === link.getAttribute( 'aria-busy' ) ) {
			return;
		}
		link.setAttribute( 'aria-busy', 'true' );
		const url = new URL( link.href, win.location.href );
		url.searchParams.set( 'format', 'json' );
		request( { url: url.toString() } )
			.then( ( result ) =>
				toast( result.message || messages().error, {
					type: result.ok ? 'success' : 'danger',
					duration: 5000,
				} )
			)
			.finally( () => link.removeAttribute( 'aria-busy' ) );
	} );

	doc.addEventListener( 'click', ( event ) => {
		const button = event.target?.closest?.(
			`.${ NAV_OPEN }, .${ NAV_CLOSE }`
		);
		if (
			! button ||
			! doc.body.classList.contains( 'ohmylms-page' ) ||
			! NAV_THEMES.some( ( name ) => doc.body.classList.contains( name ) )
		) {
			return;
		}
		const intent = button.classList.contains( NAV_OPEN ) ? 'open' : 'close';
		const container =
			'open' === intent
				? button.parentElement?.querySelector( `.${ NAV_CONTAINER }` )
				: button.closest( `.${ NAV_CONTAINER }` );
		if ( ! container ) {
			return;
		}
		const before = container.classList.contains( 'is-menu-open' );
		frame( () => {
			const open = navigationFallback(
				intent,
				before,
				container.classList.contains( 'is-menu-open' )
			);
			if ( null === open ) {
				return;
			}
			container.classList.toggle( 'is-menu-open', open );
			container.classList.toggle( 'has-modal-open', open );
			doc.documentElement.classList.toggle( 'has-modal-open', open );
		} );
	} );

	const notice = verificationNotice( win.location.search );
	if ( notice ) {
		win.history?.replaceState(
			win.history.state,
			'',
			win.location.pathname + notice.query + win.location.hash
		);
		toast( messages()[ notice.key ] || '', {
			type: notice.type,
			duration: 5000,
		} );
	}
}
