// @ts-check
/**
 * Learner account pages for the `ohmylms/account` store: cover and profile photo upload, the
 * profile form's repeatable skill and link rows, the membership "View plan" dialog, membership
 * cancellation and invoice printing. Markup is written by the profile templates and added to theme
 * overrides by OhMyLMS\Extensions\Account. Everything the runtime or the SDK provides is passed in,
 * so tests/js/account.test.mjs drives it in node.
 */

/** @typedef {(event: Event) => void} EventHandler */

/**
 * @typedef {Object} AccountMessages
 * @property {string} [imageType]     The picked file is not an image.
 * @property {string} [imageSize]     The picked file is over the upload limit.
 * @property {string} [uploadFailed]  Upload failed without a server message.
 * @property {string} [coverSaved]    Cover uploaded.
 * @property {string} [avatarSaved]   Profile photo uploaded.
 * @property {string} [coverDeleted]  Cover removed.
 * @property {string} [deleteFailed]  Removing the cover failed without a server message.
 * @property {string} [cancelTitle]   Cancel confirmation heading.
 * @property {string} [cancelMessage] Cancel confirmation text.
 * @property {string} [cancelConfirm] Confirm button.
 * @property {string} [cancelKeep]    Keep button.
 * @property {string} [cancelled]     Cancelled.
 * @property {string} [cancelFailed]  Cancelling failed without a server message.
 */

/**
 * @typedef {Object} AccountState
 * @property {Object<string, string>} [nonces]    `coverUpload`, `coverDelete`, `avatarUpload`, `cancelMembership`.
 * @property {AccountMessages}        [messages]  Translated messages.
 * @property {number}                 [maxUpload] Upload limit in bytes (0: no client check).
 */

/** Admin-ajax actions and the state nonce each one posts. */
export const IMAGE_ACTIONS = Object.freeze( {
	cover: {
		action: 'ohmylms_student_cover_image_upload',
		nonce: 'coverUpload',
	},
	avatar: {
		action: 'ohmylms_student_profile_image_upload',
		nonce: 'avatarUpload',
	},
	deleteCover: {
		action: 'ohmylms_student_cover_image_delete',
		nonce: 'coverDelete',
	},
} );

export const CANCEL_ACTION = 'ohmylms_cancel_membership';

/**
 * Why a picked file cannot be uploaded as a cover or profile photo.
 *
 * @param {{type?: string, size?: number}|null|undefined} file     Picked file.
 * @param {number}                                        maxBytes Limit; 0 or less skips the size check.
 * @return {''|'empty'|'type'|'size'} Problem, '' when the file is fine.
 */
export function imageProblem( file, maxBytes = 0 ) {
	if ( ! file ) {
		return 'empty';
	}
	if ( ! /^image\//.test( String( file.type || '' ) ) ) {
		return 'type';
	}
	if ( maxBytes > 0 && Number( file.size ) > maxBytes ) {
		return 'size';
	}
	return '';
}

/**
 * The POST fields for `ohmylms_cancel_membership`. Only the subscription (and its order) is
 * sent: the server derives the learner, plan and order from the subscription and refuses
 * anyone else's.
 *
 * @param {Object|null|undefined}                 context   Link context (`subscriptionId`, `orderId`).
 * @param {Object<string, string|undefined>|null} [dataset] Fallback `data-*` values (pre-L4 overrides).
 * @return {{subscription_id: number, order_id?: number}|null} Fields, null without a subscription.
 */
export function cancelFields( context, dataset = null ) {
	const pick = ( contextKey, dataKey ) => {
		const value = Number( context?.[ contextKey ] || dataset?.[ dataKey ] );
		return Number.isInteger( value ) && value > 0 ? value : 0;
	};
	const subscription = pick( 'subscriptionId', 'subscriptionId' );
	if ( ! subscription ) {
		return null;
	}
	const order = pick( 'orderId', 'orderId' );
	return order
		? { subscription_id: subscription, order_id: order }
		: { subscription_id: subscription };
}

/**
 * Message to show for a request result.
 *
 * @param {{ok: boolean, message?: string}} result   `request()` result.
 * @param {string}                          fallback Message when the server sent none.
 * @return {string} Message.
 */
export function resultMessage( result, fallback = '' ) {
	return String( result?.message || '' ).trim() || fallback;
}

/**
 * Markup of a repeatable row template: `<template>` content, or the text of a pre-L4
 * `<script type="text/template">`.
 *
 * @param {Element|null} template Template element.
 * @return {DocumentFragment|null} Fresh copy of the row, null when missing.
 */
export function rowFragment( template ) {
	if ( ! template ) {
		return null;
	}
	const doc = template.ownerDocument;
	if ( 'content' in template && template.content ) {
		return /** @type {DocumentFragment} */ (
			/** @type {HTMLTemplateElement} */ ( template ).content.cloneNode(
				true
			)
		);
	}
	const holder = doc.createElement( 'template' );
	holder.innerHTML = template.innerHTML.trim();
	return holder.content;
}

/**
 * Store definitions for `ohmylms/account`.
 *
 * @param {Object}                                                                              api               Runtime.
 * @param {() => Object}                                                                        api.getContext    `@wordpress/interactivity` getContext.
 * @param {() => {ref: HTMLElement}}                                                            api.getElement    `@wordpress/interactivity` getElement.
 * @param {(callback: EventHandler) => EventHandler}                                            api.withSyncEvent `@wordpress/interactivity` withSyncEvent.
 * @param {() => AccountState}                                                                  api.getState      Store state (server values).
 * @param {(options: Object) => Promise<{ok: boolean, data?: unknown, message?: string}>}       api.request       SDK `request()`.
 * @param {(message: string, options?: {type?: string}) => unknown}                             api.toast         SDK `toast()`.
 * @param {(options: Object) => Promise<boolean>}                                               api.confirmDialog SDK `confirmDialog()`.
 * @param {(root: EventTarget, name: string, detail?: Object, cancelable?: boolean) => boolean} api.emit          SDK `emit()`.
 * @param {(container: Element, options?: Object) => () => void}                                api.trapFocus     SDK `trapFocus()`.
 * @param {Object}                                                                              [api.win]         `window` (print, reload, `afterprint`).
 * @param {(callback: () => void, ms: number) => unknown}                                       [api.later]       `setTimeout` (tests).
 * @return {{state: Object, actions: Object, callbacks: Object}} Definitions.
 */
export function createAccount( {
	getContext,
	getElement,
	withSyncEvent,
	getState,
	request,
	toast,
	confirmDialog,
	emit,
	trapFocus,
	win = typeof window === 'undefined' ? undefined : window,
	later = ( callback, ms ) => setTimeout( callback, ms ),
} ) {
	const messages = () => getState()?.messages || {};
	const nonce = ( key ) => getState()?.nonces?.[ key ] || '';
	const fail = ( root, operation, message ) => {
		toast( message, { type: 'danger' } );
		emit( root, 'account-error', { operation, message } );
	};

	/**
	 * Pre-L4 profile templates print the initials instead of an `<img>` when there is no photo.
	 *
	 * @param {HTMLElement} root Account root.
	 * @param {string}      url  Photo URL.
	 */
	const showAvatar = ( root, url ) => {
		const figure = root.querySelector( '.ohmylms-profile-avatar figure' );
		if ( ! figure || figure.querySelector( 'img' ) ) {
			return;
		}
		const img = figure.ownerDocument.createElement( 'img' );
		img.className = 'student-profile-photo';
		img.id = 'student-profile-photo';
		img.alt = '';
		img.src = url;
		figure.replaceChildren( img );
	};

	/**
	 * @param {'cover'|'avatar'} kind    Image.
	 * @param {HTMLInputElement} input   File input.
	 * @param {Object}           context Root context.
	 * @param {HTMLElement}      root    Account root.
	 */
	const upload = async ( kind, input, context, root ) => {
		const file = input.files?.[ 0 ];
		if ( ! file || context.busy ) {
			return;
		}
		const problem = imageProblem( file, Number( getState()?.maxUpload ) );
		if ( problem ) {
			input.value = '';
			fail(
				root,
				`upload-${ kind }`,
				'size' === problem
					? messages().imageSize || ''
					: messages().imageType || ''
			);
			return;
		}
		if ( ! emit( root, 'account-before-upload', { kind }, true ) ) {
			input.value = '';
			return;
		}
		context.busy = kind;
		const result = await request( {
			action: IMAGE_ACTIONS[ kind ].action,
			nonce: nonce( IMAGE_ACTIONS[ kind ].nonce ),
			data: { file },
		} );
		context.busy = '';
		input.value = '';
		const url = result.ok ? String( result.data?.url || '' ) : '';
		if ( ! url ) {
			fail(
				root,
				`upload-${ kind }`,
				resultMessage(
					result.ok ? { ok: false } : result,
					messages().uploadFailed
				)
			);
			return;
		}
		if ( 'cover' === kind ) {
			context.cover = url;
			context.hasCover = true;
		} else {
			context.avatar = url;
			showAvatar( root, url );
		}
		emit( root, 'account-image-changed', { kind, url } );
		const saved =
			'cover' === kind ? messages().coverSaved : messages().avatarSaved;
		if ( saved ) {
			toast( saved );
		}
	};

	const deleteCover = async ( context, root ) => {
		if ( context.busy ) {
			return;
		}
		context.busy = 'cover';
		const result = await request( {
			action: IMAGE_ACTIONS.deleteCover.action,
			nonce: nonce( IMAGE_ACTIONS.deleteCover.nonce ),
		} );
		context.busy = '';
		if ( ! result.ok ) {
			fail(
				root,
				'delete-cover',
				resultMessage( result, messages().deleteFailed )
			);
			return;
		}
		if ( result.data?.url ) {
			context.cover = String( result.data.url );
		}
		context.hasCover = false;
		emit( root, 'account-image-changed', {
			kind: 'cover',
			url: context.cover || '',
		} );
		if ( messages().coverDeleted ) {
			toast( messages().coverDeleted );
		}
	};

	const cancel = async ( link, context ) => {
		const fields = cancelFields(
			context,
			link.parentElement?.querySelector( '.ohmylms-cancel-membership' )
				?.dataset || link.dataset
		);
		if ( ! fields || context.busy ) {
			return;
		}
		const detail = { subscriptionId: fields.subscription_id };
		if ( ! emit( link, 'membership-before-cancel', detail, true ) ) {
			return;
		}
		const confirmed = await confirmDialog( {
			title: messages().cancelTitle,
			message: messages().cancelMessage,
			confirmLabel: messages().cancelConfirm,
			cancelLabel: messages().cancelKeep,
			tone: 'danger',
		} );
		if ( ! confirmed ) {
			return;
		}
		context.busy = true;
		const result = await request( {
			action: CANCEL_ACTION,
			nonce: nonce( 'cancelMembership' ),
			data: fields,
		} );
		if ( ! result.ok ) {
			context.busy = false;
			const message = resultMessage( result, messages().cancelFailed );
			toast( message, { type: 'danger' } );
			emit( link, 'membership-cancel-error', { ...detail, message } );
			return;
		}
		emit( link, 'membership-cancelled', detail );
		toast( messages().cancelled || resultMessage( result ) );
		// The membership table, the dashboard cards and access all change: show the server's view.
		later( () => win?.location?.reload(), 600 );
	};

	const print = ( button ) => {
		const area =
			button.closest( '.membership-invoice-printable-area' ) ||
			button.closest( '.ohmylms-membership-invoice' );
		const html = button.ownerDocument.documentElement;
		if ( ! area || ! win?.print ) {
			return;
		}
		const done = () => {
			html.classList.remove( 'ohmylms-printing' );
			area.classList.remove( 'ohmylms-print-area' );
		};
		// The classes only matter to `@media print`; `afterprint` fires when the dialog closes, also
		// in browsers whose print() returns at once.
		html.classList.add( 'ohmylms-printing' );
		area.classList.add( 'ohmylms-print-area' );
		win.addEventListener?.( 'afterprint', done, { once: true } );
		win.print();
	};

	const pickKey = withSyncEvent( ( event ) => {
		const label = /** @type {HTMLLabelElement} */ ( getElement().ref );
		const key = /** @type {KeyboardEvent} */ ( event ).key;
		if ( ( 'Enter' !== key && ' ' !== key ) || event.target !== label ) {
			return;
		}
		event.preventDefault();
		/** @type {HTMLInputElement|null} */ (
			label.control || label.querySelector( 'input[type="file"]' )
		)?.click();
	} );

	const imageHandler = ( kind ) => ( event ) => {
		const input = /** @type {HTMLInputElement} */ ( event.target );
		const root = /** @type {HTMLElement} */ (
			input.closest( '[data-wp-interactive="ohmylms/account"]' )
		);
		upload( kind, input, getContext(), root || input );
	};

	return {
		state: {
			get coverDeleteDisplay() {
				return getContext().hasCover ? 'flex' : 'none';
			},
			get avatarHidden() {
				return ! getContext().avatar;
			},
			get initialsHidden() {
				return Boolean( getContext().avatar );
			},
			get isBusy() {
				return Boolean( getContext().busy );
			},
			get planDisplay() {
				return getContext().planOpen ? 'block' : false;
			},
		},
		actions: {
			uploadCover: imageHandler( 'cover' ),
			uploadAvatar: imageHandler( 'avatar' ),
			deleteCover: withSyncEvent( ( event ) => {
				event.preventDefault();
				const button = getElement().ref;
				deleteCover(
					getContext(),
					/** @type {HTMLElement} */ (
						button.closest(
							'[data-wp-interactive="ohmylms/account"]'
						)
					) || button
				);
			} ),
			pickKey,
			addRow: withSyncEvent( ( event ) => {
				event.preventDefault();
				const button = getElement().ref;
				const doc = button.ownerDocument;
				const list = doc.getElementById( button.dataset.target || '' );
				const row = rowFragment(
					doc.getElementById( button.dataset.template || '' )
				);
				if ( ! list || ! row ) {
					return;
				}
				const first = row.querySelector( 'input, textarea, select' );
				list.append( row );
				/** @type {HTMLElement|null} */ ( first )?.focus();
			} ),
			removeRow: withSyncEvent( ( event ) => {
				const target = /** @type {Element} */ ( event.target );
				const remove = target?.closest?.( '.ohmylms-remove-field' );
				const row = remove?.closest( '.ohmylms-dynamic-field-row' );
				const list = row?.parentElement;
				if ( ! row || ! list ) {
					return;
				}
				event.preventDefault();
				if ( list.children.length > 1 ) {
					const rows = [ ...list.children ];
					const next =
						rows[ rows.indexOf( row ) - 1 ] ||
						rows[ rows.indexOf( row ) + 1 ];
					row.remove();
					/** @type {HTMLElement|null} */ (
						next?.querySelector( 'input, textarea, select' )
					)?.focus();
				} else {
					row.querySelectorAll( 'input' ).forEach( ( input ) => {
						input.value = '';
					} );
					/** @type {HTMLElement|null} */ (
						row.querySelector( 'input' )
					)?.focus();
				}
			} ),
			openPlan: withSyncEvent( ( event ) => {
				event.preventDefault();
				getContext().planOpen = true;
			} ),
			closePlan: withSyncEvent( ( event ) => {
				event.preventDefault();
				getContext().planOpen = false;
			} ),
			planBackdrop( event ) {
				if (
					! event.target?.closest?.(
						'.ohmylms-membership-modal-inner'
					)
				) {
					getContext().planOpen = false;
				}
			},
			cancelMembership: withSyncEvent( ( event ) => {
				event.preventDefault();
				cancel( getElement().ref, getContext() );
			} ),
			printInvoice: withSyncEvent( ( event ) => {
				event.preventDefault();
				print( getElement().ref );
			} ),
		},
		callbacks: {
			/** Trap focus in the open plan dialog; Escape closes it and focus returns to "View plan". */
			planFocus() {
				const context = getContext();
				if ( ! context.planOpen ) {
					return undefined;
				}
				const modal = getElement().ref;
				const dialog =
					modal.querySelector( '.ohmylms-membership-modal-inner' ) ||
					modal;
				return trapFocus( dialog, {
					onEscape: () => {
						context.planOpen = false;
					},
				} );
			},
		},
	};
}
