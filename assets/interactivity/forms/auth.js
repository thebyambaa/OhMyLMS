// @ts-check
/**
 * Login and sign-up for the `ohmylms/forms` store (batch L5): submission through the
 * `ohmylms_login` / `ohmylms_signup` admin-ajax actions, inline errors, the pending email
 * verification notice and the switch between the login and sign-up forms. Without JavaScript the
 * same forms post to the page, where `OhMyLMS\Extensions\AuthForms::handle_post()` runs the same
 * server code.
 */

/** @typedef {'login'|'signup'} AuthKind */
/** @typedef {(event: Event) => void} EventHandler */

/**
 * @typedef {Object} AuthOutcome
 * @property {'redirect'|'pending'|'error'} type    What to do.
 * @property {string}                       [url]   Same-origin URL for `redirect`.
 * @property {string}                       message Server message, may contain markup.
 */

export const MIN_PASSWORD_LENGTH = 8;

/** @type {Record<string, AuthKind>} */
const KINDS = { ohmylms_login: 'login', ohmylms_signup: 'signup' };

/** @type {Record<AuthKind, string>} */
export const AUTH_ACTIONS = {
	login: 'ohmylms_login',
	signup: 'ohmylms_signup',
};

/**
 * Which auth form this is, from its hidden `action` field.
 *
 * @param {HTMLFormElement|null|undefined} form Form.
 * @return {AuthKind|''} Kind, '' for any other form.
 */
export function formKind( form ) {
	const field = /** @type {HTMLInputElement|null|undefined} */ (
		form?.elements?.namedItem?.( 'action' )
	);
	return KINDS[ String( field?.value ?? '' ) ] || '';
}

/**
 * Client checks before the request. The server repeats every check.
 *
 * @param {AuthKind|''}             kind     Form.
 * @param {Object<string, unknown>} values   Submitted fields (`serializeForm( form, { as: 'object' } )`).
 * @param {Object<string, string>}  messages Translated messages.
 * @return {string} Error message, '' when the form may be sent.
 */
export function validateAuth( kind, values, messages = {} ) {
	if ( 'login' === kind && '' === String( values.username ?? '' ).trim() ) {
		return messages.usernameRequired || 'Username is required.';
	}
	if ( 'signup' === kind ) {
		if ( String( values.password ?? '' ).length < MIN_PASSWORD_LENGTH ) {
			return (
				messages.passwordShort ||
				'Password must be at least 8 characters long.'
			);
		}
		if ( ! values[ 'tnc-accept' ] ) {
			return messages.consent || 'Please accept the Privacy Policy.';
		}
	}
	return '';
}

/**
 * A redirect target on the page's own origin, or null.
 *
 * @param {unknown} value  URL from the server.
 * @param {string}  origin Current page URL.
 * @return {string|null} Absolute URL.
 */
export function safeRedirect( value, origin ) {
	if ( typeof value !== 'string' || ! value.trim() ) {
		return null;
	}
	try {
		const target = new URL( value, origin );
		return target.origin === new URL( origin ).origin ? target.href : null;
	} catch {
		return null;
	}
}

/**
 * Map a `request()` result for `Ajax::login()` / `Ajax::signup()` (`{ status, message,
 * redirect_url }`) to the next step. A success without a usable redirect reloads the page, which
 * now renders for the signed-in user.
 *
 * @param {{ok: boolean, data?: unknown, message?: string}} result   `request()` result.
 * @param {string}                                          here     Current page URL.
 * @param {Object<string, string>}                          messages Translated fallbacks.
 * @return {AuthOutcome} Outcome.
 */
export function authOutcome( result, here, messages = {} ) {
	const body =
		/** @type {{status?: string, message?: string, redirect_url?: string}} */ (
			result?.data && typeof result.data === 'object' ? result.data : {}
		);
	if ( result?.ok && 'success' === body.status ) {
		return {
			type: 'redirect',
			url: safeRedirect( body.redirect_url, here ) || here,
			message: String( body.message ?? '' ),
		};
	}
	if ( result?.ok && 'pending_verification' === body.status ) {
		return {
			type: 'pending',
			message: String( body.message || messages.pending || '' ),
		};
	}
	return {
		type: 'error',
		message: String( result?.message || messages.error || '' ),
	};
}

/**
 * Display of an auth form: the server's initial visibility until a link switches the view,
 * hidden after a sign-up that waits for email verification.
 *
 * @param {{authForm?: string, authHidden?: boolean}}    context Form context.
 * @param {{authView?: string, pendingMessage?: string}} state   Store state.
 * @return {string|false} CSS display, false to leave the stylesheet in charge.
 */
export function authDisplay( context, state ) {
	if ( 'signup' === context.authForm && state.pendingMessage ) {
		return 'none';
	}
	if ( ! state.authView ) {
		return context.authHidden ? 'none' : false;
	}
	return state.authView === context.authForm ? 'block' : 'none';
}

/**
 * Store definitions for login and sign-up, merged into `ohmylms/forms` by forms.js.
 *
 * @param {Object}                                                                                api               Runtime and SDK.
 * @param {() => Object}                                                                          api.getContext    `@wordpress/interactivity` getContext.
 * @param {() => {ref: HTMLElement}}                                                              api.getElement    `@wordpress/interactivity` getElement.
 * @param {(callback: EventHandler) => EventHandler}                                              api.withSyncEvent `@wordpress/interactivity` withSyncEvent.
 * @param {() => Object}                                                                          api.getState      Store state (`authView`, `pendingMessage`, `messages`).
 * @param {(options: Object) => Promise<{ok: boolean, data?: unknown, message?: string}>}         api.request       SDK `request()`.
 * @param {(form: HTMLFormElement, options?: {as?: string}) => Object}                            api.serializeForm SDK `serializeForm()`.
 * @param {(message: string, options?: Object) => unknown}                                        api.toast         SDK `toast()`.
 * @param {(target: EventTarget, name: string, detail?: Object, cancelable?: boolean) => boolean} api.emit          SDK `emit()`.
 * @param {(value: unknown) => string}                                                            api.plainText     SDK `plainText()`.
 * @param {(url: string) => void}                                                                 [api.navigate]    Page navigation (tests).
 * @param {() => string}                                                                          [api.here]        Current URL (tests).
 * @param {Document}                                                                              [api.doc]         Document (tests).
 * @param {(callback: () => void) => void}                                                        [api.later]       Deferred focus (tests).
 * @return {{state: Object, actions: Object, callbacks: Object}} Definitions.
 */
export function createAuth( {
	getContext,
	getElement,
	withSyncEvent,
	getState,
	request,
	serializeForm,
	toast,
	emit,
	plainText,
	navigate = ( url ) => window.location.assign( url ),
	here = () => window.location.href,
	doc = typeof document === 'undefined' ? undefined : document,
	later = ( callback ) => setTimeout( callback, 50 ),
} ) {
	const messages = () => getState().messages || {};

	/**
	 * Show an error in the form's `aria-live` region, or as a toast for a form without one
	 * (theme override copied before L5).
	 *
	 * @param {Object}          context Form context.
	 * @param {HTMLFormElement} form    Form.
	 * @param {string}          message Message.
	 */
	const fail = ( context, form, message ) => {
		const text = plainText( message ) || messages().error || '';
		context.error = text;
		if ( ! form.querySelector?.( '.ohmylms-form-error' ) ) {
			toast( text, { type: 'danger' } );
		}
	};

	/**
	 * @param {AuthKind}    view   Form to show.
	 * @param {HTMLElement} source Link that asked.
	 */
	const show = ( view, source ) => {
		const state = getState();
		state.authView = view;
		if ( 'signup' === view ) {
			const checkout = doc?.getElementById( 'ohmylms-checkout-form' );
			if ( checkout ) {
				checkout.style.display = 'none';
			}
		}
		emit( source, 'auth-view-changed', { view } );
		later( () => {
			/** @type {HTMLElement|null|undefined} */ (
				doc?.querySelector(
					`.ohmylms-form-${ view } input:not([type="hidden"])`
				)
			)?.focus();
		} );
	};

	/**
	 * @param {Object}          context Form context.
	 * @param {HTMLFormElement} form    Form.
	 * @param {AuthKind}        kind    Form kind.
	 */
	const send = async ( context, form, kind ) => {
		const result = await request( {
			action: AUTH_ACTIONS[ kind ],
			data: serializeForm( form ),
		} );
		const outcome = authOutcome( result, here(), messages() );
		if ( 'redirect' === outcome.type ) {
			emit( form, 'auth-complete', {
				form: kind,
				status: 'success',
				redirect: outcome.url,
			} );
			navigate( /** @type {string} */ ( outcome.url ) );
			return;
		}
		context.busy = false;
		if ( 'pending' === outcome.type ) {
			const text = plainText( outcome.message );
			getState().pendingMessage = text;
			if ( ! doc?.querySelector( '.ohmylms-email-pending-notice' ) ) {
				toast( text, { type: 'success', duration: 0 } );
			}
			emit( form, 'auth-complete', {
				form: kind,
				status: 'pending_verification',
				message: text,
			} );
			return;
		}
		fail( context, form, outcome.message );
		emit( form, 'auth-error', {
			form: kind,
			message: context.error,
		} );
	};

	return {
		state: {
			get authDisplay() {
				return authDisplay( getContext(), getState() );
			},
			get submitDisabled() {
				const context = getContext();
				return Boolean(
					context.busy ||
					( 'signup' === context.authForm && ! context.consent )
				);
			},
			get loaderDisplay() {
				return getContext().busy ? 'inline-block' : false;
			},
			get errorHidden() {
				return ! getContext().error;
			},
			get pendingHidden() {
				return ! getState().pendingMessage;
			},
		},
		actions: {
			showSignup: withSyncEvent( ( event ) => {
				event.preventDefault();
				show( 'signup', getElement().ref );
			} ),
			showLogin: withSyncEvent( ( event ) => {
				event.preventDefault();
				show( 'login', getElement().ref );
			} ),
			submit: withSyncEvent( ( event ) => {
				const form = /** @type {HTMLFormElement} */ (
					getElement().ref
				);
				const kind = formKind( form );
				// The opt-in React sign-up fields (source assets) submit the form themselves.
				if (
					! kind ||
					event.defaultPrevented ||
					'react' === form.dataset?.ohmylmsRegistration
				) {
					return;
				}
				event.preventDefault();
				const context = getContext();
				if ( context.busy ) {
					return;
				}
				const error = validateAuth(
					kind,
					serializeForm( form, { as: 'object' } ),
					messages()
				);
				if ( error ) {
					fail( context, form, error );
					return;
				}
				if (
					! emit( form, 'auth-before-submit', { form: kind }, true )
				) {
					return;
				}
				context.error = '';
				context.busy = true;
				return send( context, form, kind );
			} ),
		},
		callbacks: {},
	};
}
