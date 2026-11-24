( function () {
	if ( ! ( 'showPopover' in HTMLElement.prototype ) ) {
		return;
	}

	for ( const link of document.querySelectorAll(
		'a[data-ohmylms-preview]'
	) ) {
		link.setAttribute( 'aria-haspopup', 'dialog' );
	}

	let current;
	document.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( 'a[data-ohmylms-preview]' );
		if (
			! link ||
			event.defaultPrevented ||
			event.button !== 0 ||
			event.altKey ||
			event.ctrlKey ||
			event.metaKey ||
			event.shiftKey
		) {
			return;
		}
		const template = document.getElementById( link.dataset.ohmylmsPreview );
		if ( ! ( template instanceof HTMLTemplateElement ) ) {
			return;
		}

		event.preventDefault();
		if ( current ) {
			current.hidePopover();
		}
		const card = template.content.firstElementChild.cloneNode( true );
		const close = card.querySelector( 'button' );
		close.addEventListener( 'click', () => card.hidePopover() );
		card.addEventListener( 'toggle', ( toggle ) => {
			if ( toggle.newState === 'closed' ) {
				const restoreFocus = card.contains( document.activeElement );
				card.remove();
				if ( restoreFocus ) {
					link.focus();
				}
				if ( current === card ) {
					current = undefined;
				}
			}
		} );
		document.body.append( card );
		card.showPopover();
		current = card;
		const anchor = link.getBoundingClientRect();
		const margin = 12;
		const below = anchor.bottom + margin;
		const above = anchor.top - margin - card.offsetHeight;
		const top =
			below + card.offsetHeight <= window.innerHeight - margin
				? below
				: above;
		card.style.top =
			Math.max(
				margin,
				Math.min( top, window.innerHeight - margin - card.offsetHeight )
			) + 'px';
		card.style.left =
			Math.max(
				margin,
				Math.min(
					anchor.left,
					window.innerWidth - margin - card.offsetWidth
				)
			) + 'px';
		close.focus();
	} );
} )();
