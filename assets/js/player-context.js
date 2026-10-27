( () => {
	const player = document.querySelector( '.oml-player' );
	if ( ! player ) {
		return;
	}
	try {
		const theme = window.localStorage.getItem( 'ohmylms-hub-theme' );
		if ( [ 'light', 'dark', 'system' ].includes( theme ) ) {
			player.dataset.theme = theme;
		}
	} catch {
		// Keep the default light appearance when storage is unavailable.
	}
	const toggle = player.querySelector( '[data-player-outline-toggle]' );
	const outline = player.querySelector( '.oml-player-outline' );
	if ( toggle && outline ) {
		const setExpanded = ( expanded ) => {
			toggle.setAttribute( 'aria-expanded', String( expanded ) );
			outline.hidden = ! expanded;
			player.classList.toggle( 'oml-player-outline-closed', ! expanded );
		};
		toggle.hidden = false;
		setExpanded( ! window.matchMedia( '(max-width: 800px)' ).matches );
		toggle.addEventListener( 'click', () => {
			setExpanded( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
		} );
		outline.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' ) {
				setExpanded( false );
				toggle.focus();
			}
		} );
	}
} )();
