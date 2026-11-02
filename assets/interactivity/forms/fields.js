/**
 * Learner form helpers for the `ohmylms/forms` store: floating labels, password visibility,
 * keyboard toggling of checkbox labels, the sign-up terms gate and the phone filter. Submission
 * (login, sign-up, reviews) is added to the same store by batch L5.
 */

/** @typedef {(event: Event) => void} EventHandler */

/**
 * `@wordpress/interactivity` withSyncEvent, for handlers of any event or of keyboard events.
 *
 * @typedef {((callback: EventHandler) => EventHandler) & ((callback: (event: KeyboardEvent) => void) => (event: KeyboardEvent) => void)} SyncEvent
 */

const PHONE_REJECT = /[^\d+\s\-()]/g;

/**
 * Keep digits, `+`, spaces, `-` and parentheses, moving the caret back by the characters removed
 * before it.
 *
 * @param {string}      value Field value.
 * @param {number|null} caret Caret position (`selectionStart`).
 * @return {{value: string, caret: number}} Filtered value and caret.
 */
export function filterPhone( value, caret = null ) {
	const text = String( value ?? '' );
	const clean = text.replace( PHONE_REJECT, '' );
	const at = Number.isInteger( caret )
		? /** @type {number} */ ( caret )
		: text.length;
	const removedBefore =
		text.slice( 0, at ).length -
		text.slice( 0, at ).replace( PHONE_REJECT, '' ).length;
	return { value: clean, caret: Math.max( 0, at - removedBefore ) };
}

/**
 * Whether a floating label sits above its field.
 *
 * @param {string}  value   Field value.
 * @param {boolean} focused Field has focus.
 * @return {boolean} Folded.
 */
export function isFolded( value, focused ) {
	return focused || '' !== String( value ?? '' ).trim();
}

/**
 * Enter and Space activate label "buttons" (password toggle, checkbox labels).
 *
 * @param {string} key `KeyboardEvent.key`.
 * @return {boolean} Activates.
 */
export function isActivationKey( key ) {
	return 'Enter' === key || ' ' === key || 'Spacebar' === key;
}

const FIELD = '.ohmylms-input-text';

/**
 * @param {HTMLInputElement|null} field   Field (any event target).
 * @param {boolean}               focused Field has focus.
 */
function fold( field, focused ) {
	if ( ! field?.matches?.( FIELD ) ) {
		return;
	}
	const autofilled = ( () => {
		try {
			return field.matches( ':autofill' );
		} catch {
			return false;
		}
	} )();
	field
		.closest( '.ohmylms-form-row' )
		?.classList.toggle(
			'ohmylms-folded',
			autofilled || isFolded( field.value, focused )
		);
}

/** @typedef {Event & {target: HTMLInputElement|null}} FieldEvent Event of a form field. */

/**
 * Store definitions for `ohmylms/forms`.
 *
 * @param {Object}                                        api               Runtime.
 * @param {() => {revealed?: boolean, consent?: boolean}} api.getContext    `@wordpress/interactivity` getContext.
 * @param {() => {ref: HTMLElement|null}}                 api.getElement    `@wordpress/interactivity` getElement.
 * @param {SyncEvent}                                     api.withSyncEvent `@wordpress/interactivity` withSyncEvent.
 * @param {(callback: () => void, ms: number) => void}    [api.later]       `setTimeout` (tests).
 * @return {{state: Object, actions: Object, callbacks: Object}} Definitions.
 */
export function createForms( {
	getContext,
	getElement,
	withSyncEvent,
	later = ( callback, ms ) => setTimeout( callback, ms ),
} ) {
	/** @return {HTMLElement} Element of the running directive. */
	const ref = () => /** @type {HTMLElement} */ ( getElement().ref );
	const toggle = () => {
		const context = getContext();
		context.revealed = ! context.revealed;
	};
	return {
		state: {
			get passwordType() {
				return getContext().revealed ? 'text' : 'password';
			},
			get eyeOnDisplay() {
				return getContext().revealed ? 'inline' : false;
			},
			get eyeOffDisplay() {
				return getContext().revealed ? 'none' : false;
			},
			get consentMissing() {
				return ! getContext().consent;
			},
		},
		actions: {
			/** @param {FieldEvent} event `focusin`. */
			fieldFocus( event ) {
				fold( event.target, true );
			},
			/** @param {FieldEvent} event `focusout`. */
			fieldBlur( event ) {
				fold( event.target, false );
			},
			/** @param {FieldEvent} event `input`. */
			fieldInput( event ) {
				fold(
					event.target,
					event.target?.ownerDocument?.activeElement === event.target
				);
			},
			togglePassword: withSyncEvent( ( event ) => {
				event.preventDefault();
				toggle();
			} ),
			passwordKey: withSyncEvent(
				( /** @type {KeyboardEvent} */ event ) => {
					if ( isActivationKey( event.key ) ) {
						event.preventDefault();
						toggle();
					}
				}
			),
			checkboxKey: withSyncEvent(
				( /** @type {KeyboardEvent} */ event ) => {
					const label = /** @type {HTMLLabelElement} */ ( ref() );
					if (
						! isActivationKey( event.key ) ||
						event.target !== label
					) {
						return;
					}
					event.preventDefault();
					const box = /** @type {HTMLInputElement|null} */ (
						label.control ||
							label.querySelector( 'input[type="checkbox"]' )
					);
					if ( box && ! box.disabled ) {
						box.click();
					}
				}
			),
			/** @param {FieldEvent} event Terms box `change`. */
			consentChange( event ) {
				getContext().consent = Boolean( event.target?.checked );
			},
			/** @param {Event & {target: HTMLInputElement}} event Phone `input`. */
			filterPhone( event ) {
				const field = event.target;
				const filtered = filterPhone(
					field.value,
					field.selectionStart
				);
				if ( filtered.value !== field.value ) {
					field.value = filtered.value;
					field.setSelectionRange?.( filtered.caret, filtered.caret );
				}
			},
		},
		callbacks: {
			foldFilled() {
				const root = ref();
				const run = () =>
					/** @type {HTMLInputElement[]} */ ( [
						...root.querySelectorAll( FIELD ),
					] ).forEach( ( field ) =>
						fold(
							field,
							root.ownerDocument.activeElement === field
						)
					);
				run();
				// Browsers fill saved credentials after load without an input event.
				later( run, 500 );
			},
			syncConsent() {
				const box = /** @type {HTMLInputElement|null} */ (
					ref().querySelector( 'input[name="tnc-accept"]' )
				);
				if ( box ) {
					getContext().consent = box.checked;
				}
			},
		},
	};
}
