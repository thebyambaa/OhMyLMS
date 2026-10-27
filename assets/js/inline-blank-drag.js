( () => {
	let draggedRoot = null;
	const rootOf = ( target ) => target.closest( '.ohmylms-drag-blanks' );
	const tokenOf = ( root, id ) =>
		[
			...root.querySelectorAll(
				'.ohmylms-blank-token[data-blank-token]'
			),
		].find( ( token ) => token.dataset.blankToken === id );
	const markPlaced = ( token, placed ) => {
		token.hidden = false;
		token.disabled = placed;
		token.draggable = ! placed;
		token.classList.toggle( 'is-placed', placed );
		token.setAttribute( 'aria-hidden', String( placed ) );
	};
	const sync = ( root ) => {
		const used = new Set();
		root.querySelectorAll( 'input' ).forEach( ( input ) => {
			const current = tokenOf( root, input.dataset.blankToken );
			const token =
				current &&
				current.dataset.blankAnswer === input.value &&
				! used.has( current )
					? current
					: [
							...root.querySelectorAll( '.ohmylms-blank-token' ),
						].find(
							( option ) =>
								option.dataset.blankAnswer === input.value &&
								! used.has( option )
						);
			if ( token && input.value ) {
				used.add( token );
				input.dataset.blankToken = token.dataset.blankToken;
			} else {
				delete input.dataset.blankToken;
			}
		} );
		root.querySelectorAll( '.ohmylms-blank-token' ).forEach( ( token ) =>
			markPlaced( token, used.has( token ) )
		);
	};
	const place = ( root, input, id ) => {
		const token = tokenOf( root, id );
		if ( ! token ) return;
		const previous = input.dataset.blankToken;
		root.querySelectorAll( 'input' ).forEach( ( field ) => {
			if ( field.dataset.blankToken === id ) {
				field.value = '';
				delete field.dataset.blankToken;
			}
		} );
		if ( previous !== undefined ) {
			const old = tokenOf( root, previous );
			if ( old ) markPlaced( old, false );
		}
		input.value = token.dataset.blankAnswer;
		input.dataset.blankToken = id;
		markPlaced( token, true );
		delete root.dataset.selectedToken;
		root.querySelectorAll( 'button' ).forEach( ( button ) =>
			button.setAttribute( 'aria-pressed', 'false' )
		);
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};
	document.addEventListener( 'dblclick', ( event ) => {
		const token = event.target.closest( '.ohmylms-blank-token' );
		if ( ! token || token.disabled ) return;
		const root = rootOf( token );
		const input = [ ...root.querySelectorAll( 'input' ) ].find(
			( field ) => ! field.value
		);
		if ( input ) {
			place( root, input, token.dataset.blankToken );
			input.focus();
		}
	} );
	for ( const name of [ 'input', 'change' ] ) {
		document.addEventListener( name, ( event ) => {
			const root = rootOf( event.target );
			if ( root && event.target.matches( 'input' ) ) sync( root );
		} );
	}
	document.addEventListener( 'ohmylms:answer-restored', () => {
		document.querySelectorAll( '.ohmylms-drag-blanks' ).forEach( sync );
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener(
			'DOMContentLoaded',
			() => {
				document
					.querySelectorAll( '.ohmylms-drag-blanks' )
					.forEach( sync );
			},
			{ once: true }
		);
	} else {
		document.querySelectorAll( '.ohmylms-drag-blanks' ).forEach( sync );
	}
	document.addEventListener( 'dragstart', ( event ) => {
		const token = event.target.closest( '.ohmylms-blank-token' );
		if ( token && ! token.disabled ) {
			draggedRoot = rootOf( token );
			draggedRoot.dataset.selectedToken = token.dataset.blankToken;
			event.dataTransfer.setData(
				'text/plain',
				token.dataset.blankToken
			);
		}
	} );
	document.addEventListener( 'dragend', () => {
		draggedRoot = null;
	} );
	document.addEventListener( 'dragover', ( event ) => {
		if ( event.target.matches( '.ohmylms-drag-blanks input' ) )
			event.preventDefault();
	} );
	document.addEventListener( 'drop', ( event ) => {
		const root = rootOf( event.target );
		if ( ! root || ! event.target.matches( 'input' ) ) return;
		event.preventDefault();
		if ( root === draggedRoot && root.dataset.selectedToken !== undefined )
			place( root, event.target, root.dataset.selectedToken );
	} );
	document.addEventListener( 'click', ( event ) => {
		const root = rootOf( event.target );
		if ( ! root ) return;
		const token = event.target.closest( '.ohmylms-blank-token' );
		if ( token && ! token.disabled ) {
			root.dataset.selectedToken = token.dataset.blankToken;
			root.querySelectorAll( 'button' ).forEach( ( button ) =>
				button.setAttribute(
					'aria-pressed',
					String( button === token )
				)
			);
		} else if ( event.target.matches( 'input' ) ) {
			if ( root.dataset.selectedToken !== undefined )
				place( root, event.target, root.dataset.selectedToken );
			else if ( event.target.dataset.blankToken !== undefined ) {
				const previous = tokenOf(
					root,
					event.target.dataset.blankToken
				);
				if ( previous ) markPlaced( previous, false );
				event.target.value = '';
				delete event.target.dataset.blankToken;
				event.target.dispatchEvent(
					new Event( 'change', { bubbles: true } )
				);
			}
		}
	} );
	document.addEventListener( 'keydown', ( event ) => {
		const token = event.target.closest( '.ohmylms-blank-token' );
		if ( token && ! token.disabled && event.key === 'Enter' ) {
			event.preventDefault();
			const root = rootOf( token );
			const input = [ ...root.querySelectorAll( 'input' ) ].find(
				( field ) => ! field.value
			);
			if ( input ) place( root, input, token.dataset.blankToken );
			return;
		}
		if (
			event.target.matches( '.ohmylms-drag-blanks input' ) &&
			[ 'Enter', ' ' ].includes( event.key )
		) {
			event.preventDefault();
			event.target.click();
		}
	} );
} )();
