// @ts-check
/**
 * "Mark as complete" for the `ohmylms/lesson` store: the completion checkbox of
 * `single-lesson/content-navigation.php`, its keyboard label, the video hint, and the
 * `ohmylms:lesson-complete-request` document event that the video player uses. Markup is written by
 * the template and added to theme overrides by OhMyLMS\Extensions\Lesson. Everything the runtime or
 * the SDK provides is passed in, so tests/js/lesson.test.mjs drives it in node.
 */

/** @typedef {(event: Event) => void} EventHandler */

/**
 * @typedef {Object} LessonContext
 * @property {number}  lessonId     Lesson, quiz, assignment or session ID.
 * @property {boolean} completed    The learner completed it.
 * @property {boolean} busy         A completion request is running.
 * @property {boolean} videoPending The video must be watched first (server rule).
 * @property {string}  nextUrl      Next content, '' on the last one.
 */

/**
 * @typedef {Object} LessonState
 * @property {{complete?: string}}                                          [nonces]   `lesson_completed_nonce`.
 * @property {{markComplete?: string, completed?: string, failed?: string}} [messages] Translated labels.
 */

/**
 * @typedef {Object} CompleteRequest
 * @property {number}  [lessonId]  Lesson the request is for (default: the page's lesson).
 * @property {string}  [source]    Who asks: `video`, or any add-on name (default `event`).
 * @property {boolean} [completed] The server already completed it (for example the video tracker's answer): update the page only.
 * @property {boolean} [navigate]  Go to the next content afterwards, as the checkbox does (default false).
 */

export const COMPLETE_ACTION = 'ohmylms_lesson_completed';

/** Document event other modules dispatch to complete the page's lesson. */
export const COMPLETE_REQUEST_EVENT = 'ohmylms:lesson-complete-request';

/** jQuery event of the legacy video tracker (`$( document ).trigger( 'ohmylms:video:completed' )`). */
export const LEGACY_VIDEO_EVENT = 'ohmylms:video:completed';

/** Pause before leaving the page, so the toast is seen. */
export const NAVIGATE_DELAY = 500;

/**
 * An absolute URL on the page's origin, or '' (the server's next link is never followed off site).
 *
 * @param {unknown} value  URL from the server.
 * @param {string}  origin Page URL.
 * @return {string} URL or ''.
 */
export function sameOriginUrl( value, origin ) {
	if ( typeof value !== 'string' || ! value.trim() ) {
		return '';
	}
	try {
		const target = new URL( value, origin );
		return target.origin === new URL( origin ).origin ? target.href : '';
	} catch {
		return '';
	}
}

/**
 * What to do after a completion answer: go to the next content, reload, or show the error.
 *
 * @param {{ok: boolean, data?: any, message?: string, error?: {code?: string}|null}} result   `request()` result.
 * @param {string}                                                                    origin   Page URL.
 * @param {string}                                                                    fallback Message when the server sent none.
 * @return {{type: 'navigate', url: string}|{type: 'reload'}|{type: 'error', code: string, message: string}} Outcome.
 */
export function completionOutcome( result, origin, fallback = '' ) {
	if ( ! result?.ok ) {
		return {
			type: 'error',
			code: String( result?.error?.code || result?.data?.code || '' ),
			message: String( result?.message || '' ).trim() || fallback,
		};
	}
	const url = sameOriginUrl( result.data?.next_content_link, origin );
	return url ? { type: 'navigate', url } : { type: 'reload' };
}

/**
 * Whether the checkbox is disabled.
 *
 * @param {Partial<LessonContext>} context Root context.
 * @return {boolean} Disabled.
 */
export function completeDisabled( context ) {
	return Boolean(
		context?.completed || context?.busy || context?.videoPending
	);
}

/**
 * Whether a complete request is for this lesson.
 *
 * @param {CompleteRequest|null|undefined} detail  Event detail.
 * @param {Partial<LessonContext>}         context Root context.
 * @return {boolean} For this lesson.
 */
export function requestMatches( detail, context ) {
	const wanted = Number( detail?.lessonId || 0 );
	return ! wanted || wanted === Number( context?.lessonId );
}

/**
 * Store definitions for `ohmylms/lesson`.
 *
 * @param {Object}                                                                              api               Runtime.
 * @param {() => LessonContext}                                                                 api.getContext    `@wordpress/interactivity` getContext.
 * @param {() => {ref: HTMLElement}}                                                            api.getElement    `@wordpress/interactivity` getElement.
 * @param {(callback: EventHandler) => EventHandler}                                            api.withSyncEvent `@wordpress/interactivity` withSyncEvent.
 * @param {() => LessonState}                                                                   api.getState      Store state (server values).
 * @param {(options: Object) => Promise<any>}                                                   api.request       SDK `request()`.
 * @param {(message: string, options?: {type?: string}) => unknown}                             api.toast         SDK `toast()`.
 * @param {(root: EventTarget, name: string, detail?: Object, cancelable?: boolean) => boolean} api.emit          SDK `emit()`.
 * @param {any}                                                                                 [api.win]         `window` (location, jQuery).
 * @param {(callback: () => void, ms: number) => unknown}                                       [api.later]       `setTimeout` (tests).
 * @return {{state: Object, actions: Object, callbacks: Object}} Definitions.
 */
export function createLesson( {
	getContext,
	getElement,
	withSyncEvent,
	getState,
	request,
	toast,
	emit,
	win = typeof window === 'undefined' ? undefined : window,
	later = ( callback, ms ) => setTimeout( callback, ms ),
} ) {
	const messages = () => getState()?.messages || {};

	/**
	 * The page now shows the lesson as completed.
	 *
	 * @param {LessonContext} context Root context.
	 */
	const markCompleted = ( context ) => {
		context.completed = true;
		context.videoPending = false;
	};

	/**
	 * Complete the lesson on the server.
	 *
	 * @param {LessonContext}         context          Root context.
	 * @param {HTMLElement}           root             Navigation root (events).
	 * @param {Object}                options          Options.
	 * @param {string}                options.source   `button`, `video`, ...
	 * @param {boolean}               options.navigate Follow the next link afterwards.
	 * @param {HTMLInputElement|null} [options.input]  The checkbox, unticked again on failure.
	 * @return {Promise<boolean>} Completed.
	 */
	const complete = async (
		context,
		root,
		{ source, navigate, input = null }
	) => {
		const untick = () => {
			if ( input ) {
				input.checked = false;
			}
		};
		if ( context.busy || context.completed ) {
			return Boolean( context.completed );
		}
		const detail = { lessonId: context.lessonId, source };
		if ( ! emit( root, 'lesson-before-complete', detail, true ) ) {
			untick();
			return false;
		}
		context.busy = true;
		const result = await request( {
			action: COMPLETE_ACTION,
			nonce: getState()?.nonces?.complete || '',
			data: { lesson_id: context.lessonId },
		} );
		context.busy = false;
		const outcome = completionOutcome(
			result,
			win?.location?.href || 'http://localhost/',
			messages().failed
		);
		if ( 'error' === outcome.type ) {
			untick();
			if ( 'ohmylms_lesson_video_not_watched' === outcome.code ) {
				context.videoPending = true;
			}
			toast( outcome.message, { type: 'danger' } );
			emit( root, 'lesson-complete-error', {
				...detail,
				code: outcome.code,
				message: outcome.message,
			} );
			return false;
		}
		markCompleted( context );
		if ( result.message ) {
			toast( result.message );
		}
		emit( root, 'lesson-completed', {
			...detail,
			nextUrl: 'navigate' === outcome.type ? outcome.url : '',
		} );
		if ( navigate ) {
			// Progress, the sidebar ticks and the course rate all change: show the server's next page.
			later( () => {
				if ( 'navigate' === outcome.type ) {
					win?.location?.assign( outcome.url );
				} else {
					win?.location?.reload();
				}
			}, NAVIGATE_DELAY );
		}
		return true;
	};

	/**
	 * Handle a complete request from another module.
	 *
	 * @param {LessonContext}                  context Root context.
	 * @param {HTMLElement}                    root    Navigation root.
	 * @param {CompleteRequest|null|undefined} detail  Request.
	 */
	const requested = ( context, root, detail ) => {
		if ( ! requestMatches( detail, context ) ) {
			return;
		}
		const source = String( detail?.source || 'event' );
		if ( detail?.completed ) {
			if ( ! context.completed ) {
				markCompleted( context );
				emit( root, 'lesson-completed', {
					lessonId: context.lessonId,
					source,
					nextUrl: '',
				} );
			}
			return;
		}
		complete( context, root, {
			source,
			navigate: Boolean( detail?.navigate ),
		} );
	};

	return {
		state: {
			get completeDisabled() {
				return completeDisabled( getContext() );
			},
			get completeLabel() {
				return getContext().completed
					? messages().completed || ''
					: messages().markComplete || '';
			},
		},
		actions: {
			complete( event ) {
				const input = /** @type {HTMLInputElement} */ ( event.target );
				if ( ! input?.checked ) {
					return;
				}
				const context = getContext();
				const root = /** @type {HTMLElement} */ (
					input.closest?.(
						'[data-wp-interactive="ohmylms/lesson"]'
					) || input
				);
				complete( context, root, {
					source: 'button',
					navigate: true,
					input,
				} );
			},
			/** Enter or Space on the focusable label ticks the box, as a click would. */
			checkboxKey: withSyncEvent( ( event ) => {
				const label = getElement().ref;
				const key = /** @type {KeyboardEvent} */ ( event ).key;
				if (
					( 'Enter' !== key && ' ' !== key ) ||
					event.target !== label
				) {
					return;
				}
				event.preventDefault();
				const input = /** @type {HTMLInputElement|null} */ (
					label.querySelector( 'input[type="checkbox"]' )
				);
				if ( input && ! input.disabled ) {
					input.click();
				}
			} ),
		},
		callbacks: {
			/** Listen for complete requests (and the legacy tracker's jQuery event) while the lesson is shown. */
			listen() {
				const context = getContext();
				const root = getElement().ref;
				const doc = root.ownerDocument;
				const onRequest = ( /** @type {Event} */ event ) =>
					requested(
						context,
						root,
						/** @type {CustomEvent} */ ( event ).detail
					);
				doc.addEventListener( COMPLETE_REQUEST_EVENT, onRequest );
				const $ = win?.jQuery;
				const onLegacy = (
					/** @type {unknown} */ _event,
					/** @type {{lessonId?: number}} */ data
				) =>
					requested( context, root, {
						lessonId: data?.lessonId,
						source: 'video',
						completed: true,
					} );
				if ( $ ) {
					$( doc ).on( LEGACY_VIDEO_EVENT, onLegacy );
				}
				return () => {
					doc.removeEventListener(
						COMPLETE_REQUEST_EVENT,
						onRequest
					);
					if ( $ ) {
						$( doc ).off( LEGACY_VIDEO_EVENT, onLegacy );
					}
				};
			},
		},
	};
}
