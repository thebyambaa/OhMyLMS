// @ts-check
/**
 * Content protection for `ohmylms/protection`, loaded only on lesson, quiz, assignment and session pages
 * when the `ohmylms_content_protection` option is on (OhMyLMS\Extensions\Lesson). A deterrent, not
 * security: the content is in the page. It blocks the context menu, copy and cut and the usual
 * "print / save / view source / developer tools / screenshot" shortcuts, and blurs the page while the
 * window is in the background or a screen recorder extension is present. Printing from the browser menu
 * prints nothing through `@media print` (body class `ohmylms-content-protected`, set by the server).
 */

/** Class on `<body>` that blurs the page (assets/interactivity/frontend.css). */
export const BLUR_CLASS = 'ohmylms-protection-blur';

/** How long a blocked screenshot or tools shortcut keeps the page blurred. */
export const BLUR_MS = 2000;

/** Element the Loom recorder extension adds to the page. */
export const RECORDER_SELECTOR = '#loom-extension-mv3-id';

/** Function keys: F1 help, F2, F3 find, F4, F6 address bar, F7 caret browsing; F12 is developer tools. */
const BLOCKED_FUNCTION_KEYS = [ 'F1', 'F2', 'F3', 'F4', 'F6', 'F7' ];

/** Ctrl/Cmd + Shift + letter: inspector, console, element picker, network, performance, memory, sources. */
const TOOLS_WITH_SHIFT = [ 'i', 'j', 'c', 'k', 'e', 'p', 'm', 's' ];

/** Cmd + Option + letter (macOS developer tools). */
const TOOLS_WITH_ALT = [ 'i', 'j', 'c' ];

/**
 * What to do with a key press.
 *
 * @param {{key?: string, ctrlKey?: boolean, metaKey?: boolean, shiftKey?: boolean, altKey?: boolean}} event Key event.
 * @return {''|'block'|'blur'} `blur` blocks it and blurs the page, `block` only blocks it, '' lets it through.
 */
export function shortcutAction( event ) {
	const key = String( event.key || '' );
	if ( 'F12' === key || 'PrintScreen' === key ) {
		return 'blur';
	}
	if ( BLOCKED_FUNCTION_KEYS.includes( key ) || 'ContextMenu' === key ) {
		return 'block';
	}
	if ( event.metaKey && event.shiftKey && /^[3-6]$/.test( key ) ) {
		return 'blur';
	}
	const letter = 1 === key.length ? key.toLowerCase() : '';
	const command = Boolean( event.ctrlKey || event.metaKey );
	if ( command && event.shiftKey ) {
		if ( TOOLS_WITH_SHIFT.includes( letter ) || 'Delete' === key ) {
			return 'blur';
		}
		if ( 'a' === letter ) {
			return 'block';
		}
	}
	if ( command && event.altKey && TOOLS_WITH_ALT.includes( letter ) ) {
		return 'blur';
	}
	if ( command && ! event.shiftKey && [ 'u', 's', 'p' ].includes( letter ) ) {
		return 'blur';
	}
	if ( event.metaKey && ',' === key ) {
		return 'block';
	}
	return '';
}

/**
 * Whether losing window focus should blur the page. Focus that moved into an embedded player (an
 * iframe) keeps the page readable, as does a lesson page whose body still has focus.
 *
 * @param {Element|null} active `document.activeElement`.
 * @return {boolean} Blur.
 */
export function blurOnLeave( active ) {
	if ( ! active ) {
		return true;
	}
	if ( [ 'IFRAME', 'WEBVIEW' ].includes( active.tagName ) ) {
		return false;
	}
	return ! active.classList?.contains( 'single-ohmylms-lesson' );
}

/**
 * Install the protection listeners.
 *
 * @param {Object}                                        api             Environment.
 * @param {any}                                           api.win         `window`.
 * @param {Document}                                      api.doc         `document`.
 * @param {(callback: () => void, ms: number) => unknown} [api.later]     `setTimeout`.
 * @param {(callback: () => void, ms: number) => unknown} [api.every]     `setInterval`.
 * @param {(handle: unknown) => void}                     [api.stopEvery] `clearInterval`.
 * @return {() => void} Removes everything.
 */
export function installProtection( {
	win,
	doc,
	later = ( callback, ms ) => setTimeout( callback, ms ),
	every = ( callback, ms ) => setInterval( callback, ms ),
	stopEvery = ( handle ) =>
		clearInterval(
			/** @type {ReturnType<typeof setInterval>} */ ( handle )
		),
} ) {
	const body = () => doc.body;
	let shortcutBlur = false;
	let away = false;
	let recorder = false;
	const update = () =>
		body()?.classList.toggle(
			BLUR_CLASS,
			shortcutBlur || away || recorder
		);

	const prevent = ( /** @type {Event} */ event ) => event.preventDefault();
	const keydown = ( /** @type {KeyboardEvent} */ event ) => {
		const action = shortcutAction( event );
		if ( ! action ) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		if ( 'blur' === action ) {
			shortcutBlur = true;
			update();
			later( () => {
				shortcutBlur = false;
				update();
			}, BLUR_MS );
		}
	};
	const leave = () => {
		away = blurOnLeave( doc.activeElement );
		update();
	};
	const back = () => {
		away = false;
		update();
	};
	const watch = () => {
		recorder = Boolean( doc.querySelector( RECORDER_SELECTOR ) );
		update();
	};

	doc.addEventListener( 'contextmenu', prevent );
	doc.addEventListener( 'copy', prevent );
	doc.addEventListener( 'cut', prevent );
	doc.addEventListener( 'keydown', keydown, true );
	win.addEventListener( 'blur', leave );
	win.addEventListener( 'focus', back );
	watch();
	const poll = every( watch, 1000 );

	return () => {
		doc.removeEventListener( 'contextmenu', prevent );
		doc.removeEventListener( 'copy', prevent );
		doc.removeEventListener( 'cut', prevent );
		doc.removeEventListener( 'keydown', keydown, true );
		win.removeEventListener( 'blur', leave );
		win.removeEventListener( 'focus', back );
		stopEvery( poll );
		body()?.classList.remove( BLUR_CLASS );
	};
}
