/**
 * Close the Explore menu with Escape or a click outside it; the menu itself is a plain details element.
 * A full-width menu is fixed to the browser edges, so it is placed just under its button while open.
 */
( function () {
	function openMenus() {
		return document.querySelectorAll( '.oml-explore-menu[open]' );
	}
	function place( menu ) {
		const summary = menu.querySelector( 'summary' );
		if ( summary && menu.closest( '.oml-explore.is-full' ) ) {
			menu.style.setProperty(
				'--oml-explore-top',
				summary.getBoundingClientRect().bottom + 'px'
			);
		}
	}
	document.addEventListener(
		'toggle',
		function ( event ) {
			if ( event.target.matches( '.oml-explore-menu[open]' ) ) {
				place( event.target );
			}
		},
		true
	);
	window.addEventListener( 'scroll', function () {
		openMenus().forEach( place );
	} );
	window.addEventListener( 'resize', function () {
		openMenus().forEach( place );
	} );
	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key !== 'Escape' ) {
			return;
		}
		openMenus().forEach( function ( menu ) {
			menu.removeAttribute( 'open' );
			const summary = menu.querySelector( 'summary' );
			if ( summary ) {
				summary.focus();
			}
		} );
	} );
	document.addEventListener( 'click', function ( event ) {
		openMenus().forEach( function ( menu ) {
			if ( ! menu.contains( event.target ) ) {
				menu.removeAttribute( 'open' );
			}
		} );
	} );
} )();
