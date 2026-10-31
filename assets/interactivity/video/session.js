// @ts-check
/**
 * Watch tracking of one lesson video for the `ohmylms/video` store: resumes the saved position on
 * the first play, logs the spans really played, saves them (at most every `saveInterval` while
 * playing, shortly after a pause, seek or end, and with `navigator.sendBeacon` when the page is
 * hidden), and asks for the lesson to be completed once the server counts the video as watched.
 *
 * The server is the authority: it merges the spans with what it already has, bounds them by
 * elapsed time when the lesson requires watching and applies the lesson's or the site's threshold
 * (`is_completed`). Completing the lesson goes through the one gated endpoint, `ohmylms_lesson_completed`
 * (enrolment, release, prerequisites, the watched-percentage rule): through the lesson store's
 * `ohmylms:lesson-complete-request` document event when `ohmylms/lesson` is on the page, else directly.
 * Everything is passed in, so tests/js/video.test.mjs drives it in node.
 */
import {
	createSaveClock,
	createWatchLog,
	progressFields,
	resumeAt,
} from './progress.js';

export const SAVE_ACTION = 'ohmylms_save_video_progress';
export const COMPLETE_ACTION = 'ohmylms_lesson_completed';

/** Root of the lesson store (assets/interactivity/lesson), which owns the completion checkbox. */
export const LESSON_ROOT = '[data-wp-interactive="ohmylms/lesson"]';

/** Watched share (points below the threshold) from which progress is saved every few seconds. */
export const NEAR_THRESHOLD = 10;
export const NEAR_INTERVAL = 3000;

/**
 * @typedef {Object} VideoConfig Store state printed by OhMyLMS\Extensions\Video::enqueue().
 * @property {string}                                 [ajaxUrl]      admin-ajax URL.
 * @property {{progress?: string, complete?: string}} [nonces]       `video_progress_nonce`, `lesson_completed_nonce`.
 * @property {number}                                 [saveInterval] Milliseconds between periodic saves while playing.
 * @property {Object<string, string>}                 [messages]     Translated messages.
 */

/** @typedef {(options: Object) => Promise<?>} RequestFn SDK `request()`. */

/**
 * @typedef {(callback: () => unknown, ms: number) => (() => void) & {cancel: () => void, flush: () => void}} DebounceFn SDK `debounce()`.
 */

/**
 * Send fields as a form POST that survives the page going away: `sendBeacon`, else `fetch` with
 * `keepalive`. Cookies are sent (same origin), so admin-ajax knows the learner.
 *
 * @param {string}                                          url         admin-ajax URL.
 * @param {Object<string, string|number>}                   fields      Fields.
 * @param {?}                                               [nav]       `navigator`.
 * @param {(url: string, init: Object) => Promise<unknown>} [fetchImpl] `fetch`.
 * @return {''|'beacon'|'fetch'} Transport used.
 */
export function sendFinal(
	url,
	fields,
	nav = globalThis.navigator,
	fetchImpl = globalThis.fetch
) {
	const body = new URLSearchParams();
	for ( const [ name, value ] of Object.entries( fields ) ) {
		body.set( name, String( value ) );
	}
	if (
		nav &&
		'function' === typeof nav.sendBeacon &&
		nav.sendBeacon( url, body )
	) {
		return 'beacon';
	}
	if ( 'function' === typeof fetchImpl ) {
		Promise.resolve(
			fetchImpl( url, {
				method: 'POST',
				body,
				keepalive: true,
				credentials: 'same-origin',
			} )
		).catch( () => {} );
		return 'fetch';
	}
	return '';
}

/**
 * Show a completed lesson on pages without the lesson store (a layout or override without
 * `ohmylms/lesson`): the "Mark as complete" box is checked and the "watch N %" hint removed, as
 * video-progress-tracker.js did.
 *
 * @param {Document} doc Document.
 */
export function markLessonDone( doc ) {
	const box = /** @type {HTMLInputElement|null} */ (
		doc?.getElementById( 'ohmylms-completed-lesson' )
	);
	if ( box ) {
		box.checked = true;
		box.disabled = true;
		box.removeAttribute( 'data-ohmylms-video-pending' );
		box.removeAttribute( 'aria-describedby' );
	}
	doc?.getElementById( 'ohmylms-video-completion-hint' )?.remove();
}

/**
 * Tracking session of one player.
 *
 * @param {Object}                                                                              api                 Dependencies.
 * @param {import('./adapter.js').Adapter}                                                      api.adapter         Adapter (ready).
 * @param {Object}                                                                              api.context         Player context (reactive): `lessonId`, `courseId`, `track`, `resume`, `videoDone`, `lessonDone`, `percent`, `threshold`.
 * @param {EventTarget}                                                                         api.root            Player root (events bubble from it).
 * @param {VideoConfig}                                                                         api.config          Store state.
 * @param {RequestFn}                                                                           api.request         SDK `request()`.
 * @param {(root: EventTarget, name: string, detail?: Object, cancelable?: boolean) => boolean} api.emit            SDK `emit()`.
 * @param {(name: string, args?: Array, target?: EventTarget) => boolean}                       [api.jqueryTrigger] SDK `jqueryTrigger()`.
 * @param {(message: string, options?: Object) => unknown}                                      [api.toast]         SDK `toast()`.
 * @param {(message: string, politeness?: string) => void}                                      [api.announce]      SDK `announce()`.
 * @param {DebounceFn}                                                                          api.debounce        SDK `debounce()`.
 * @param {() => number}                                                                        [api.now]           Clock in ms.
 * @param {(url: string, fields: Object) => string}                                             [api.beacon]        Final save transport.
 * @param {Document}                                                                            [api.doc]           Document (fallback completion UI, jQuery event target).
 * @return {{tracking: boolean, seek: (seconds: number) => void, save: () => Promise<void>, flush: () => string, completeOnLoad: () => Promise<void>, destroy: () => void}} Session.
 */
export function createSession( {
	adapter,
	context,
	root,
	config,
	request,
	emit,
	jqueryTrigger = () => false,
	toast = () => null,
	announce = () => {},
	debounce,
	now = () => Date.now(),
	beacon = ( url, fields ) => sendFinal( url, fields ),
	doc = globalThis.document,
} ) {
	const messages = config.messages || {};
	const tracking = Boolean(
		context.track && context.lessonId && config.nonces?.progress
	);
	const log = createWatchLog();
	const clock = createSaveClock( Number( config.saveInterval ) || 15000 );
	let playing = false;
	let resume = resumeAt(
		context.resume,
		adapter.duration(),
		context.videoDone
	);
	/** @type {Promise<void>|null} */
	let inflight = null;
	let again = false;
	let completion = Boolean( context.videoDone );
	const unsubscribe = [];

	const fields = () =>
		progressFields( {
			lessonId: Number( context.lessonId ),
			courseId: Number( context.courseId ) || 0,
			duration: adapter.duration(),
			position: adapter.time(),
			intervals: log.intervals(),
		} );

	const lessonId = () => Number( context.lessonId );
	const courseId = () => Number( context.courseId ) || 0;
	const lessonStore = () => Boolean( doc?.querySelector?.( LESSON_ROOT ) );

	/**
	 * The lesson is completed (by the lesson store, the fallback request or earlier elsewhere).
	 *
	 * @param {string} nextUrl Next content.
	 */
	const lessonCompleted = ( nextUrl = '' ) => {
		if ( context.lessonDone ) {
			return;
		}
		context.lessonDone = true;
		// Add-ons of the jQuery tracker listened to this once the lesson was completed by the video.
		jqueryTrigger(
			'ohmylms:video:completed',
			[ { lessonId: lessonId(), courseId: courseId() } ],
			doc
		);
		if ( ! lessonStore() ) {
			markLessonDone( doc );
			announce( messages.lessonCompleted || '', 'polite' );
			emit( root, 'lesson-completed', {
				lessonId: lessonId(),
				source: 'video',
				nextUrl,
			} );
		}
	};

	/** @param {Event} event `ohmylms:lesson-completed` from the lesson store. */
	const onLessonCompleted = ( event ) => {
		const detail = /** @type {CustomEvent} */ ( event ).detail || {};
		if ( Number( detail.lessonId ) === lessonId() ) {
			lessonCompleted( String( detail.nextUrl || '' ) );
		}
	};
	doc?.addEventListener?.( 'ohmylms:lesson-completed', onLessonCompleted );

	/**
	 * Ask for the lesson to be completed.
	 *
	 * @param {boolean} [done] The server already completed it: only the page is updated.
	 */
	const requestCompletion = async ( done = false ) => {
		if ( lessonStore() ) {
			// The lesson store calls the endpoint (or updates its checkbox) and answers with ohmylms:lesson-completed.
			emit( doc, 'lesson-complete-request', {
				lessonId: lessonId(),
				source: 'video',
				...( done ? { completed: true } : {} ),
			} );
			return;
		}
		if ( done ) {
			lessonCompleted();
			return;
		}
		if ( ! config.nonces?.complete ) {
			return;
		}
		const result = await request( {
			action: COMPLETE_ACTION,
			nonce: config.nonces.complete,
			ajaxUrl: config.ajaxUrl,
			data: { lesson_id: lessonId() },
		} );
		if ( result?.ok ) {
			lessonCompleted( String( result.data?.next_content_link || '' ) );
		} else if ( result?.message ) {
			toast( result.message, { type: 'warning' } );
		}
	};

	/**
	 * The server counts the video as watched.
	 *
	 * @param {{lesson_completed?: boolean}} answer Save answer.
	 */
	const videoCompleted = ( answer ) => {
		if ( completion ) {
			return;
		}
		completion = true;
		context.videoDone = true;
		emit( root, 'video-completed', {
			lessonId: lessonId(),
			courseId: courseId(),
			percent: Number( context.percent ) || 0,
		} );
		if ( ! context.lessonDone ) {
			requestCompletion( Boolean( answer?.lesson_completed ) );
		}
	};

	/** Save interval: shorter close to the threshold, so a learner who leaves right after it is counted. */
	const interval = () =>
		Number( context.threshold ) > 0 &&
		! context.videoDone &&
		Number( context.percent ) >=
			Number( context.threshold ) - NEAR_THRESHOLD
			? NEAR_INTERVAL
			: Number( config.saveInterval ) || 15000;

	const save = async () => {
		if ( ! tracking || ! ( adapter.duration() > 0 ) ) {
			return;
		}
		if ( inflight ) {
			again = true;
			return inflight;
		}
		const data = fields();
		clock.saved( now(), log.watched(), adapter.time() );
		inflight = ( async () => {
			const result = await request( {
				action: SAVE_ACTION,
				nonce: config.nonces.progress,
				ajaxUrl: config.ajaxUrl,
				data,
			} );
			const progress = result?.ok ? result.data?.progress : null;
			if ( progress ) {
				context.percent = Number( progress.watch_percentage ) || 0;
				emit( root, 'video-progress', {
					lessonId: Number( context.lessonId ),
					percent: context.percent,
					position: data.last_position,
				} );
				if ( progress.is_completed ) {
					videoCompleted( result.data );
				}
			}
		} )();
		try {
			await inflight;
		} finally {
			inflight = null;
		}
		if ( again ) {
			again = false;
			await save();
		}
	};

	const saveSoon = debounce( save, 1000 );

	const at = ( value ) =>
		Number.isFinite( Number( value ) ) ? Number( value ) : adapter.time();

	unsubscribe.push(
		adapter.on( 'play', ( value ) => {
			playing = true;
			if ( resume > 0 ) {
				const position = resume;
				resume = 0;
				adapter.seek( position );
				log.jump( position, now() );
				return;
			}
			log.jump( at( value ), now() );
		} ),
		adapter.on( 'time', ( value ) => {
			if ( ! playing ) {
				return;
			}
			log.tick( at( value ), now(), adapter.rate() );
			if (
				clock.due( now(), log.watched(), adapter.time(), interval() )
			) {
				save();
			}
		} ),
		adapter.on( 'seeked', ( value ) => log.jump( at( value ), now() ) ),
		adapter.on( 'pause', ( value ) => {
			if ( playing ) {
				log.tick( at( value ), now(), adapter.rate() );
			}
			playing = false;
			log.stop();
			saveSoon();
		} ),
		adapter.on( 'ended', ( value ) => {
			if ( playing ) {
				log.tick( at( value ), now(), adapter.rate() );
			}
			playing = false;
			log.stop();
			saveSoon.cancel();
			save();
		} )
	);

	return {
		tracking,
		/**
		 * Seek requested by the learner (custom controls): the saved position is no longer applied.
		 *
		 * @param {number} seconds Position.
		 */
		seek( seconds ) {
			resume = 0;
			adapter.seek( seconds );
			log.jump( seconds, now() );
		},
		save,
		/**
		 * Last save when the page is hidden or left; only when something changed.
		 *
		 * @return {string} Transport used, '' when nothing was sent.
		 */
		flush() {
			saveSoon.cancel();
			if (
				! tracking ||
				! ( adapter.duration() > 0 ) ||
				! config.ajaxUrl ||
				! clock.changed( log.watched(), adapter.time() )
			) {
				return '';
			}
			const data = fields();
			clock.saved( now(), log.watched(), adapter.time() );
			return beacon( config.ajaxUrl, {
				action: SAVE_ACTION,
				nonce: config.nonces.progress,
				...data,
			} );
		},
		/**
		 * The server already counts the video as watched but the lesson is not completed (the last
		 * save was a beacon the page did not wait for): ask for the completion now.
		 */
		async completeOnLoad() {
			if ( tracking && context.videoDone && ! context.lessonDone ) {
				await requestCompletion();
			}
		},
		destroy() {
			saveSoon.cancel();
			doc?.removeEventListener?.(
				'ohmylms:lesson-completed',
				onLessonCompleted
			);
			for ( const off of unsubscribe ) {
				off();
			}
		},
	};
}
