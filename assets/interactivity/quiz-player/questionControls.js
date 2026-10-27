import { emit } from 'ohmylms/interactivity';
/**
 * Shared operations for directive-driven quizzes and the dynamic practice renderer boundary.
 * @param root
 * @param context
 */
export function createQuestionControls( root, context ) {
	const itemOf = ( event ) =>
		event.target.closest( '.reorder-option,.matching-option' );
	const changed = () =>
		emit( root, 'question-answer-changed', {
			questionId: Number( root.dataset.questionId ),
		} );
	// Keep the first item's Up and the last item's Down buttons disabled.
	const syncMoveButtons = () => {
		const items = [ ...root.querySelectorAll( '.reorder-option' ) ];
		items.forEach( ( item, index ) => {
			const up = item.querySelector(
				'.reorder-move[data-direction="-1"]'
			);
			const down = item.querySelector(
				'.reorder-move[data-direction="1"]'
			);
			if ( up ) {
				up.disabled = index === 0;
			}
			if ( down ) {
				down.disabled = index === items.length - 1;
			}
		} );
	};
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
	const select = ( item ) => {
		if ( ! item || ! root.contains( item ) ) {
			return;
		}
		context.selectedId = item.dataset.optionId;
		root.querySelectorAll( '.matching-option' ).forEach( ( option ) => {
			option.classList.toggle( 'dragging', option === item );
			option.setAttribute( 'aria-pressed', String( option === item ) );
		} );
	};
	const place = ( box ) => {
		if ( ! box || ! context.selectedId ) {
			return;
		}
		const item = [ ...root.querySelectorAll( '.matching-option' ) ].find(
			( option ) => option.dataset.optionId === context.selectedId
		);
		if ( ! item ) {
			return;
		}
		// One answer per definition, with all selectors scoped to this question instance.
		root.querySelectorAll( '.matching-answer-input' ).forEach(
			( input ) => {
				if ( input.value === context.selectedId ) {
					input.value = '';
					const oldBox =
						input.parentElement.querySelector( '.option-drop-box' );
					oldBox.querySelector( '.dropped' )?.remove();
					const placeholder =
						oldBox.querySelector( '.placeholder-text' );
					if ( placeholder ) {
						placeholder.hidden = false;
					}
				}
			}
		);
		box.querySelector( '.dropped' )?.remove();
		const clone = item.cloneNode( true );
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
		const placeholder = box.querySelector( '.placeholder-text' );
		if ( placeholder ) {
			placeholder.hidden = true;
		}
		box.parentElement.querySelector( '.matching-answer-input' ).value =
			context.selectedId;
		context.selectedId = '';
		root.querySelectorAll( '.matching-option' ).forEach( ( option ) => {
			option.classList.remove( 'dragging' );
			option.setAttribute( 'aria-pressed', 'false' );
		} );
		changed();
	};
	syncMoveButtons();
	return {
		move( event ) {
			const button = event.target.closest( '.reorder-move' );
			const item = button?.closest( '.reorder-option' );
			if (
				item &&
				moveItem( item, Number( button.dataset.direction ) )
			) {
				// Keep focus on the same arrow, or the row when that end is reached.
				( button.disabled ? item : button ).focus();
			}
		},
		select( event ) {
			select( itemOf( event ) );
		},
		dragstart( event ) {
			const item = itemOf( event );
			select( item );
			if ( item ) {
				event.dataTransfer?.setData(
					'text/plain',
					item.dataset.optionId
				);
			}
		},
		dragend() {
			root.querySelectorAll( '.dragging' ).forEach( ( item ) =>
				item.classList.remove( 'dragging' )
			);
		},
		dragover( event ) {
			event.preventDefault();
		},
		drop( event ) {
			event.preventDefault();
			if ( root.classList.contains( 'quiz-matching-options' ) ) {
				place( event.target.closest( '.option-drop-box' ) );
				return;
			}
			const item = [ ...root.querySelectorAll( '.reorder-option' ) ].find(
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
		place( event ) {
			place( event.target.closest( '.option-drop-box' ) );
		},
		// Double-clicking a term or a definition removes its link.
		unlink( event ) {
			const item = event.target.closest( '.matching-option' );
			const box = event.target.closest( '.option-drop-box' );
			const input = item
				? [ ...root.querySelectorAll( '.matching-answer-input' ) ].find(
						( field ) => field.value === item.dataset.optionId
					)
				: box?.parentElement.querySelector( '.matching-answer-input' );
			if ( ! input?.value ) {
				return;
			}
			const target =
				input.parentElement.querySelector( '.option-drop-box' );
			input.value = '';
			target.querySelector( '.dropped' )?.remove();
			const placeholder = target.querySelector( '.placeholder-text' );
			if ( placeholder ) {
				placeholder.hidden = false;
			}
			context.selectedId = '';
			root.querySelectorAll( '.matching-option' ).forEach( ( option ) => {
				option.classList.remove( 'dragging' );
				option.setAttribute( 'aria-pressed', 'false' );
			} );
			changed();
		},
		key( event ) {
			if ( event.target.closest( '.reorder-move' ) ) {
				return;
			}
			const item = itemOf( event ),
				box = event.target.closest( '.option-drop-box' );
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
				box.parentElement.querySelector(
					'.matching-answer-input'
				).value = '';
				const placeholder = box.querySelector( '.placeholder-text' );
				if ( placeholder ) {
					placeholder.hidden = false;
				}
				changed();
			}
		},
	};
}
