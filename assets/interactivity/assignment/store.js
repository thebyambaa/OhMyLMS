// @ts-check
/**
 * Assignment page for the `ohmylms/assignment` store: "Start" (which starts the server's timer), the
 * countdown, the file picker and its list, and the submission. The server owns the deadline: the module
 * only counts down to the time it answered, corrected for the device clock. At zero a picked file is
 * submitted through the assignment's own action; otherwise the form is locked and the assignment's
 * `learner-state` route says why. Markup is written by `single-lesson/content-assignment.php` and
 * `content-navigation.php`, and added to theme overrides by OhMyLMS\Extensions\Lesson. Everything the
 * runtime or the SDK provides is passed in, so tests/js/assignment.test.mjs drives it in node.
 */

/** @typedef {(event: Event) => void} EventHandler */

/**
 * @typedef {Object} AssignmentFile
 * @property {string} key  Stable key for `data-wp-each`.
 * @property {string} name File name.
 * @property {string} size Size label.
 */

/**
 * @typedef {Object} AssignmentState
 * @property {number}                            assignmentId  Assignment ID.
 * @property {number}                            timeLimit     Time limit in seconds (0: none).
 * @property {number}                            deadline      Server deadline in ms since the epoch (0: not started).
 * @property {string}                            deadlineLabel Deadline as the site displays dates.
 * @property {number}                            remaining     Seconds left.
 * @property {number}                            serverNow     Server time when the page was rendered, in ms.
 * @property {number}                            maxBytes      Largest file the server accepts (0: no client check).
 * @property {boolean}                           started       The learner pressed "Start".
 * @property {boolean}                           expired       The countdown reached zero.
 * @property {boolean}                           locked        The form no longer accepts input.
 * @property {boolean}                           busy          A request is running.
 * @property {string}                            error         Message shown in the form's alert.
 * @property {AssignmentFile[]}                  files         Picked files.
 * @property {boolean}                           tooLarge      The picked file is over the limit.
 * @property {boolean}                           [submitted]   The server accepted the submission.
 * @property {{submit?: string, start?: string}} [nonces]      Nonces.
 * @property {Object<string, string>}            [messages]    Translated messages.
 */

export const SUBMIT_ACTION = 'ohmylms_save_assignment_submission_file';
export const START_ACTION = 'ohmylms_save_remaining_time';
export const TIME_EXPIRED = 'ohmylms_assignment_time_expired';
export const NAVIGATE_DELAY = 500;

const pad = ( /** @type {number} */ value ) =>
	String( value ).padStart( 2, '0' );

/**
 * Countdown label: `05m 09s`, `01h 05m 09s`, `02d 01h 05m 09s`.
 *
 * @param {number} seconds Seconds left.
 * @return {string} Label.
 */
export function formatRemaining( seconds ) {
	const total = Math.max( 0, Math.floor( Number( seconds ) || 0 ) );
	const days = Math.floor( total / 86400 );
	const hours = Math.floor( ( total % 86400 ) / 3600 );
	const minutes = Math.floor( ( total % 3600 ) / 60 );
	return (
		( days > 0 ? `${ pad( days ) }d ` : '' ) +
		( hours > 0 || days > 0 ? `${ pad( hours ) }h ` : '' ) +
		`${ pad( minutes ) }m ${ pad( total % 60 ) }s`
	);
}

/**
 * Whole seconds left until a deadline.
 *
 * @param {number} deadline Deadline in ms.
 * @param {number} now      Current (server-corrected) time in ms.
 * @return {number} Seconds, never below 0.
 */
export function remainingSeconds( deadline, now ) {
	return Math.max(
		0,
		Math.ceil( ( Number( deadline ) - Number( now ) ) / 1000 )
	);
}

/**
 * Width of the countdown bar: the share of the time limit that is left.
 *
 * @param {number} remaining Seconds left.
 * @param {number} limit     Time limit in seconds.
 * @return {string} CSS width.
 */
export function timerWidth( remaining, limit ) {
	if ( ! ( limit > 0 ) ) {
		return '100%';
	}
	const share = Math.min( 1, Math.max( 0, remaining / limit ) );
	return `${ Math.round( share * 10000 ) / 100 }%`;
}

/**
 * Deadline of a server answer (UTC ISO 8601) in ms, 0 when missing or invalid.
 *
 * @param {unknown} value Value.
 * @return {number} Milliseconds.
 */
export function parseDeadline( value ) {
	const at = typeof value === 'string' && value ? Date.parse( value ) : NaN;
	return Number.isFinite( at ) ? at : 0;
}

/**
 * Why a picked file cannot be submitted.
 *
 * @param {{size?: number}|null|undefined} file     File.
 * @param {number}                         maxBytes Limit; 0 or less skips the check.
 * @return {''|'empty'|'size'} Problem, '' when the file is fine.
 */
export function fileProblem( file, maxBytes = 0 ) {
	if ( ! file ) {
		return 'empty';
	}
	return maxBytes > 0 && Number( file.size ) > maxBytes ? 'size' : '';
}

/**
 * Size label, as the server lists submitted files.
 *
 * @param {number} bytes Bytes.
 * @return {string} Label.
 */
export function formatSize( bytes ) {
	return `${ (
		Math.max( 0, Number( bytes ) || 0 ) /
		( 1024 * 1024 )
	).toFixed( 2 ) } MB`;
}

/**
 * List entry of a picked file.
 *
 * @param {{name: string, size: number, lastModified?: number}} file File.
 * @return {AssignmentFile} Entry.
 */
export function fileEntry( file ) {
	return {
		key: `${ file.name }:${ file.size }:${ file.lastModified || 0 }`,
		name: String( file.name ),
		size: formatSize( file.size ),
	};
}

/**
 * Whether "Submit Assignment" is disabled: the server needs a file within the limit.
 *
 * @param {Partial<AssignmentState>} state State.
 * @return {boolean} Disabled.
 */
export function submitDisabled( state ) {
	return Boolean(
		state.busy || state.locked || state.tooLarge || ! state.files?.length
	);
}

/**
 * Same-origin URL or ''.
 *
 * @param {unknown} value  URL.
 * @param {string}  origin Page URL.
 * @return {string} URL.
 */
function sameOriginUrl( value, origin ) {
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
 * Error code of a `request()` result.
 *
 * @param {any} result Result.
 * @return {string} Code.
 */
const codeOf = ( result ) =>
	String( result?.error?.code || result?.data?.code || '' );

/**
 * Store definitions for `ohmylms/assignment`.
 *
 * @param {Object}                                                                              api               Runtime.
 * @param {() => any}                                                                           api.getContext    `@wordpress/interactivity` getContext.
 * @param {() => {ref: HTMLElement}}                                                            api.getElement    `@wordpress/interactivity` getElement.
 * @param {(callback: EventHandler) => EventHandler}                                            api.withSyncEvent `@wordpress/interactivity` withSyncEvent.
 * @param {() => AssignmentState}                                                               api.getState      Store state.
 * @param {(options: Object) => Promise<any>}                                                   api.request       SDK `request()`.
 * @param {(message: string, options?: {type?: string}) => unknown}                             api.toast         SDK `toast()`.
 * @param {(root: EventTarget, name: string, detail?: Object, cancelable?: boolean) => boolean} api.emit          SDK `emit()`.
 * @param {(message: string, politeness?: string) => void}                                      api.announce      SDK `announce()`.
 * @param {any}                                                                                 [api.win]         `window` (location).
 * @param {any}                                                                                 [api.doc]         `document`.
 * @param {(form: HTMLFormElement) => FormData}                                                 [api.formData]    Form fields (tests).
 * @param {() => number}                                                                        [api.now]         Device clock in ms (tests).
 * @param {(callback: () => void, ms: number) => unknown}                                       [api.later]       `setTimeout` (tests).
 * @param {(callback: () => void, ms: number) => unknown}                                       [api.every]       `setInterval` (tests).
 * @param {(handle: unknown) => void}                                                           [api.stopEvery]   `clearInterval` (tests).
 * @return {{state: Object, actions: Object, callbacks: Object, tick: () => void}} Definitions; `tick` runs one countdown step (tests).
 */
export function createAssignment( {
	getContext,
	getElement,
	withSyncEvent,
	getState,
	request,
	toast,
	emit,
	announce,
	win = typeof window === 'undefined' ? undefined : window,
	doc = typeof document === 'undefined' ? undefined : document,
	formData = ( form ) => new FormData( form ),
	now = () => Date.now(),
	later = ( callback, ms ) => setTimeout( callback, ms ),
	every = ( callback, ms ) => setInterval( callback, ms ),
	stopEvery = ( handle ) =>
		clearInterval(
			/** @type {ReturnType<typeof setInterval>} */ ( handle )
		),
} ) {
	let skew = 0;
	/** @type {unknown} */
	let ticker = null;
	const messages = () => getState()?.messages || {};
	const nonce = ( /** @type {'submit'|'start'} */ key ) =>
		getState()?.nonces?.[ key ] || '';
	const clock = () => now() + skew;
	const detail = () => ( { assignmentId: getState().assignmentId } );
	const root = () =>
		/** @type {HTMLElement|null} */ (
			doc?.querySelector( '[data-wp-interactive="ohmylms/assignment"]' )
		) || doc;
	const form = () =>
		/** @type {HTMLFormElement|null} */ (
			doc?.querySelector( '.ohmylms-assignment-form' ) || null
		);

	const stopTicking = () => {
		if ( null !== ticker ) {
			stopEvery( ticker );
			ticker = null;
		}
	};

	/**
	 * Lock the form, with the server's reason when it has one.
	 *
	 * @param {string} fallback Message when the server gives none.
	 */
	const lock = async ( fallback ) => {
		const state = getState();
		state.locked = true;
		stopTicking();
		const result = await request( {
			path: `ohmylms/v1/assignments/${ state.assignmentId }/learner-state`,
		} );
		const message =
			( result?.ok &&
				! result.data?.can_submit &&
				result.data?.message ) ||
			fallback;
		state.error = String( message || '' );
		announce( state.error, 'assertive' );
	};

	/**
	 * Submit the form to the assignment's action.
	 *
	 * @param {HTMLFormElement}    target Form.
	 * @param {'submit'|'timeout'} reason Why.
	 * @return {Promise<boolean>} Accepted.
	 */
	const submit = async ( target, reason ) => {
		const state = getState();
		if (
			state.busy ||
			state.submitted ||
			( state.locked && 'timeout' !== reason )
		) {
			return false;
		}
		if ( ! state.files.length || state.tooLarge ) {
			return false;
		}
		if (
			! emit(
				target,
				'assignment-before-submit',
				{ ...detail(), reason },
				true
			)
		) {
			return false;
		}
		state.busy = true;
		state.error = '';
		const result = await request( {
			action: SUBMIT_ACTION,
			nonce: nonce( 'submit' ),
			data: formData( target ),
		} );
		state.busy = false;
		if ( ! result?.ok ) {
			const code = codeOf( result );
			const message =
				String( result?.message || '' ).trim() ||
				messages().submitFailed ||
				'';
			state.error = message;
			if ( TIME_EXPIRED === code || state.expired ) {
				state.locked = true;
				stopTicking();
			}
			emit( target, 'assignment-error', {
				...detail(),
				operation: 'submit',
				code,
				message,
			} );
			return false;
		}
		state.submitted = true;
		state.locked = true;
		stopTicking();
		if ( result.message ) {
			toast( result.message );
		}
		emit( target, 'assignment-submitted', { ...detail(), reason } );
		const url = sameOriginUrl(
			result.data?.redirect_url,
			win?.location?.href || 'http://localhost/'
		);
		// The submissions list and the attempt count are rendered by the server.
		later( () => {
			if ( url ) {
				win?.location?.assign( url );
			} else {
				win?.location?.reload();
			}
		}, NAVIGATE_DELAY );
		return true;
	};

	/** At zero: submit a picked file, otherwise lock. */
	const expire = async () => {
		const state = getState();
		if ( state.expired || state.submitted ) {
			return;
		}
		state.expired = true;
		stopTicking();
		const page = root();
		if ( page ) {
			emit( page, 'assignment-expired', detail() );
		}
		if ( state.busy ) {
			return;
		}
		const target = form();
		if ( target && state.files.length && ! state.tooLarge ) {
			announce( messages().autoSubmit || '', 'assertive' );
			if ( await submit( target, 'timeout' ) ) {
				return;
			}
			if ( state.locked ) {
				return;
			}
		}
		await lock( messages().timeUp || '' );
	};

	const tick = () => {
		const state = getState();
		if ( ! state.deadline ) {
			return;
		}
		const left = remainingSeconds( state.deadline, clock() );
		state.remaining =
			state.timeLimit > 0 ? Math.min( left, state.timeLimit ) : left;
		if ( state.remaining <= 0 ) {
			expire();
		}
	};

	const startTicking = () => {
		if ( null === ticker ) {
			ticker = every( tick, 1000 );
		}
		tick();
	};

	const start = async ( /** @type {HTMLElement} */ button ) => {
		const state = getState();
		if ( state.started || state.busy || state.locked ) {
			return;
		}
		if ( ! emit( button, 'assignment-before-start', detail(), true ) ) {
			return;
		}
		if ( state.timeLimit > 0 ) {
			state.busy = true;
			const result = await request( {
				action: START_ACTION,
				nonce: nonce( 'start' ),
				data: { content_id: state.assignmentId, type: 'assignment' },
			} );
			state.busy = false;
			if ( ! result?.ok ) {
				const code = codeOf( result );
				const message =
					String( result?.message || '' ).trim() ||
					messages().startFailed ||
					'';
				toast( message, { type: 'danger' } );
				emit( button, 'assignment-error', {
					...detail(),
					operation: 'start',
					code,
					message,
				} );
				if ( TIME_EXPIRED === code ) {
					state.locked = true;
				}
				return;
			}
			const deadline = parseDeadline( result.data?.deadline );
			if ( deadline ) {
				state.deadline = deadline;
				state.deadlineLabel = String(
					result.data?.deadline_label || state.deadlineLabel || ''
				);
			} else if ( ! state.deadline ) {
				state.deadline = clock() + state.timeLimit * 1000;
			}
		}
		state.started = true;
		emit( button, 'assignment-started', {
			...detail(),
			deadline: state.deadline,
		} );
		if ( state.timeLimit > 0 ) {
			startTicking();
		}
		// The panel is shown on the next render; then the first field takes focus.
		later( () => {
			/** @type {HTMLElement|null} */ (
				form()?.querySelector(
					'textarea, input[type="file"]:not([disabled])'
				) || null
			)?.focus();
		}, 0 );
	};

	const clearInputs = () => {
		form()
			?.querySelectorAll( 'input[type="file"]' )
			.forEach( ( input ) => {
				/** @type {HTMLInputElement} */ ( input ).value = '';
			} );
	};

	return {
		tick,
		state: {
			get panelDisplay() {
				return getState().started ? 'block' : 'none';
			},
			get timerDisplay() {
				const state = getState();
				return state.started && state.timeLimit > 0 ? '' : 'none';
			},
			get timeLabel() {
				return formatRemaining( getState().remaining );
			},
			get timerWidth() {
				const state = getState();
				return timerWidth( state.remaining, state.timeLimit );
			},
			get hasFiles() {
				return getState().files.length > 0;
			},
			get attachedDisplay() {
				return getState().files.length > 0 ? 'block' : 'none';
			},
			get sizeAlertDisplay() {
				return getState().tooLarge ? 'block' : 'none';
			},
			get errorDisplay() {
				return getState().error ? 'flex' : 'none';
			},
			get submitDisabled() {
				return submitDisabled( getState() );
			},
			get deadlineHidden() {
				return ! getState().deadlineLabel;
			},
		},
		actions: {
			start() {
				start( getElement().ref );
			},
			submit: withSyncEvent( ( event ) => {
				event.preventDefault();
				submit(
					/** @type {HTMLFormElement} */ ( event.target ),
					'submit'
				);
			} ),
			pick( event ) {
				const input = /** @type {HTMLInputElement} */ ( event.target );
				const file = input.files?.[ 0 ] || null;
				const state = getState();
				state.error = '';
				if ( ! file ) {
					state.files = [];
					state.tooLarge = false;
					return;
				}
				state.files = [ fileEntry( file ) ];
				state.tooLarge = 'size' === fileProblem( file, state.maxBytes );
				announce(
					String( messages().selected || '%s' ).replace(
						'%s',
						file.name
					)
				);
			},
			remove: withSyncEvent( ( event ) => {
				event.preventDefault();
				const state = getState();
				const key = getContext()?.file?.key;
				state.files = state.files.filter(
					( file ) => file.key !== key
				);
				if ( ! state.files.length ) {
					clearInputs();
					state.tooLarge = false;
					later( () => {
						/** @type {HTMLElement|null} */ (
							form()?.querySelector( '.ohmylms-file-upload' ) ||
								null
						)?.focus();
					}, 0 );
				}
				announce( messages().removed || '' );
			} ),
			/** Enter or Space on the focusable drop zone opens the file dialog. */
			pickKey: withSyncEvent( ( event ) => {
				const label = getElement().ref;
				const key = /** @type {KeyboardEvent} */ ( event ).key;
				if (
					( 'Enter' !== key && ' ' !== key ) ||
					event.target !== label
				) {
					return;
				}
				event.preventDefault();
				/** @type {HTMLInputElement|null} */ (
					label.querySelector( 'input[type="file"]' )
				)?.click();
			} ),
		},
		callbacks: {
			/** The server's clock at render time against the device's. */
			mount() {
				const state = getState();
				skew = state.serverNow ? state.serverNow - now() : 0;
				return stopTicking;
			},
		},
	};
}
