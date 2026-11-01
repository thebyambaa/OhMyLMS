import { emit } from 'ohmylms/interactivity';
/**
 * Shared operations for directive-driven quizzes and the dynamic practice renderer boundary.
 * @param {HTMLElement}           root    `.quiz-reorder-options` or `.quiz-matching-options`.
 * @param {{selectedId?: string}} context Question context (the selected matching term).
 */
export function createQuestionControls( root, context ) {
	/** @param {Event} event Event. */
	const targetOf = ( event ) => /** @type {Element} */ ( event.target );
	/**
	 * @param {Event} event Event.
	 * @return {HTMLElement|null} Reorder or matching item of the event.
	 */
	const itemOf = ( event ) =>
		/** @type {HTMLElement|null} */ (
			targetOf( event ).closest( '.reorder-option,.matching-option' )
		);
	/** @param {string} selector Selector. */
	const all = ( selector ) =>
		/** @type {HTMLElement[]} */ ( [
			...root.querySelectorAll( selector ),
		] );
	/**
	 * @param {Element} field Answer field or drop box.
	 * @return {HTMLElement} Its parent.
	 */
	const parentOf = ( field ) =>
		/** @type {HTMLElement} */ ( field.parentElement );
	const changed = () =>
		emit( root, 'question-answer-changed', {
			questionId: Number( root.dataset.questionId ),
		} );
	// Keep the first item's Up and the last item's Down buttons disabled.
	const syncMoveButtons = () => {
		const items = [ ...all( '.reorder-option' ) ];
		items.forEach( ( item, index ) => {
			const up = /** @type {HTMLButtonElement|null} */ (
				item.querySelector( '.reorder-move[data-direction="-1"]' )
			);
			const down = /** @type {HTMLButtonElement|null} */ (
				item.querySelector( '.reorder-move[data-direction="1"]' )
			);
			if ( up ) {
				up.disabled = index === 0;
			}
			if ( down ) {
				down.disabled = index === items.length - 1;
			}
		} );
	};
	/**
	 * @param {HTMLElement} item      Reorder item.
	 * @param {number}      direction -1 up, 1 down.
	 * @return {boolean} Moved.
	 */
	const moveItem = ( item, direction ) => {
		const sibling =
			direction < 0
				? item.previousElementSibling
				: item.nextElementSibling;
		if ( ! sibling ) {
			return false;
		}
		root.insertBefore(
			item,
			direction < 0 ? sibling : sibling.nextSibling
		);
		syncMoveButtons();
		changed();
		return true;
	};
	/** @param {HTMLElement|null} item Matching term. */
	const select = ( item ) => {
		if ( ! item || ! root.contains( item ) ) {
			return;
		}
		context.selectedId = item.dataset.optionId;
		all( '.matching-option' ).forEach( ( option ) => {
			option.classList.toggle( 'dragging', option === item );
			option.setAttribute( 'aria-pressed', String( option === item ) );
		} );
	};
	/** @param {Element|null} box Definition drop box. */
	const place = ( box ) => {
		if ( ! box || ! context.selectedId ) {
			return;
		}
		const item = [ ...all( '.matching-option' ) ].find(
			( option ) => option.dataset.optionId === context.selectedId
		);
		if ( ! item ) {
			return;
		}
		// One answer per definition, with all selectors scoped to this question instance.
		/** @type {HTMLInputElement[]} */ ( [
			...root.querySelectorAll( '.matching-answer-input' ),
		] ).forEach( ( input ) => {
			if ( input.value === context.selectedId ) {
				input.value = '';
				const oldBox = /** @type {Element} */ (
					parentOf( input ).querySelector( '.option-drop-box' )
				);
				oldBox.querySelector( '.dropped' )?.remove();
				const placeholder = /** @type {HTMLElement|null} */ (
					oldBox.querySelector( '.placeholder-text' )
				);
				if ( placeholder ) {
					placeholder.hidden = false;
				}
			}
		} );
		box.querySelector( '.dropped' )?.remove();
		const clone = /** @type {HTMLElement} */ ( item.cloneNode( true ) );
		clone.classList.remove( 'matching-option', 'dragging' );
		clone.classList.add( 'dropped' );
		clone.removeAttribute( 'role' );
		clone.removeAttribute( 'tabindex' );
		clone.removeAttribute( 'aria-pressed' );
		clone.draggable = false;
		// Clones are display-only. Never duplicate directive event handlers or answer fields.
		for ( const attribute of [ ...clone.attributes ] ) {
			if ( attribute.name.startsWith( 'data-wp-' ) ) {
				clone.removeAttribute( attribute.name );
			}
		}
		clone
			.querySelectorAll( 'input' )
			.forEach( ( input ) => input.remove() );
		box.append( clone );
		const placeholder = /** @type {HTMLElement|null} */ (
			box.querySelector( '.placeholder-text' )
		);
		if ( placeholder ) {
			placeholder.hidden = true;
		}
		/** @type {HTMLInputElement} */ (
			parentOf( box ).querySelector( '.matching-answer-input' )
		).value = context.selectedId;
		context.selectedId = '';
		all( '.matching-option' ).forEach( ( option ) => {
			option.classList.remove( 'dragging' );
			option.setAttribute( 'aria-pressed', 'false' );
		} );
		changed();
	};
	syncMoveButtons();
	return {
		/** @param {Event} event Click on an Up/Down button. */
		move( event ) {
			const button = /** @type {HTMLButtonElement|null} */ (
				targetOf( event ).closest( '.reorder-move' )
			);
			const item = /** @type {HTMLElement|null|undefined} */ (
				button?.closest( '.reorder-option' )
			);
			if (
				button &&
				item &&
				moveItem( item, Number( button.dataset.direction ) )
			) {
				// Keep focus on the same arrow, or the row when that end is reached.
				( button.disabled ? item : button ).focus();
			}
		},
		/** @param {Event} event Click. */
		select( event ) {
			select( itemOf( event ) );
		},
		/** @param {DragEvent} event Drag start. */
		dragstart( event ) {
			const item = itemOf( event );
			select( item );
			if ( item ) {
				event.dataTransfer?.setData(
					'text/plain',
					/** @type {string} */ ( item.dataset.optionId )
				);
			}
		},
		dragend() {
			root.querySelectorAll( '.dragging' ).forEach( ( item ) =>
				item.classList.remove( 'dragging' )
			);
		},
		/** @param {DragEvent} event Drag over. */
		dragover( event ) {
			event.preventDefault();
		},
		/** @param {DragEvent} event Drop. */
		drop( event ) {
			event.preventDefault();
			if ( root.classList.contains( 'quiz-matching-options' ) ) {
				place( targetOf( event ).closest( '.option-drop-box' ) );
				return;
			}
			const item = [ ...all( '.reorder-option' ) ].find(
				( option ) => option.dataset.optionId === context.selectedId
			);
			const target = itemOf( event );
			if ( ! item || ! root.contains( item ) || item === target ) {
				return;
			}
			if ( target ) {
				const after =
					event.clientY >
					target.getBoundingClientRect().top +
						target.getBoundingClientRect().height / 2;
				root.insertBefore( item, after ? target.nextSibling : target );
			} else {
				root.append( item );
			}
			syncMoveButtons();
			changed();
		},
		/** @param {Event} event Click on a drop box. */
		place( event ) {
			place( targetOf( event ).closest( '.option-drop-box' ) );
		},
		// Double-clicking a term or a definition removes its link.
		/** @param {Event} event Double click. */
		unlink( event ) {
			const item = /** @type {HTMLElement|null} */ (
				targetOf( event ).closest( '.matching-option' )
			);
			const box = targetOf( event ).closest( '.option-drop-box' );
			const input = /** @type {HTMLInputElement|null|undefined} */ (
				item
					? [
							.../** @type {Iterable<HTMLInputElement>} */ (
								root.querySelectorAll(
									'.matching-answer-input'
								)
							),
						].find(
							( field ) => field.value === item.dataset.optionId
						)
					: box &&
						parentOf( box ).querySelector(
							'.matching-answer-input'
						)
			);
			if ( ! input?.value ) {
				return;
			}
			const target = /** @type {Element} */ (
				parentOf( input ).querySelector( '.option-drop-box' )
			);
			input.value = '';
			target.querySelector( '.dropped' )?.remove();
			const placeholder = /** @type {HTMLElement|null} */ (
				target.querySelector( '.placeholder-text' )
			);
			if ( placeholder ) {
				placeholder.hidden = false;
			}
			context.selectedId = '';
			all( '.matching-option' ).forEach( ( option ) => {
				option.classList.remove( 'dragging' );
				option.setAttribute( 'aria-pressed', 'false' );
			} );
			changed();
		},
		/** @param {KeyboardEvent} event Keydown. */
		key( event ) {
			if ( targetOf( event ).closest( '.reorder-move' ) ) {
				return;
			}
			const item = itemOf( event ),
				box = targetOf( event ).closest( '.option-drop-box' );
			if (
				item?.classList.contains( 'reorder-option' ) &&
				[ 'ArrowUp', 'ArrowDown' ].includes( event.key )
			) {
				event.preventDefault();
				const sibling =
					event.key === 'ArrowUp'
						? item.previousElementSibling
						: item.nextElementSibling;
				if ( sibling ) {
					root.insertBefore(
						item,
						event.key === 'ArrowUp' ? sibling : sibling.nextSibling
					);
					item.focus();
					syncMoveButtons();
					changed();
				}
			} else if ( event.key === 'Enter' || event.key === ' ' ) {
				event.preventDefault();
				if ( box ) {
					place( box );
				} else {
					select( item );
				}
			} else if (
				box &&
				[ 'Delete', 'Backspace' ].includes( event.key )
			) {
				event.preventDefault();
				box.querySelector( '.dropped' )?.remove();
				/** @type {HTMLInputElement} */ (
					parentOf( box ).querySelector( '.matching-answer-input' )
				).value = '';
				const placeholder = /** @type {HTMLElement|null} */ (
					box.querySelector( '.placeholder-text' )
				);
				if ( placeholder ) {
					placeholder.hidden = false;
				}
				changed();
			}
		},
	};
}
