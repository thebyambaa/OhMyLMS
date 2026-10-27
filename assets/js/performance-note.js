/**
 * Asks for the learner's AI-written study note on the "My skills" screen. The server writes it from
 * skill names and numbers only; nothing here changes a level.
 */
( function () {
	'use strict';
	document.querySelectorAll( '[data-ohmylms-note]' ).forEach( function ( box ) {
		var button = box.querySelector( 'button' );
		var text = box.querySelector( '.ohmylms-ai-note-text' );
		if ( ! button || ! text ) {
			return;
		}
		button.addEventListener( 'click', function () {
			button.disabled = true;
			text.textContent = '';
			fetch( box.getAttribute( 'data-url' ), {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': box.getAttribute( 'data-nonce' ),
				},
				body: JSON.stringify( { audience: 'student' } ),
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					text.textContent = result.ok
						? result.data.text
						: result.data.message || '';
				} )
				.catch( function () {
					text.textContent = '';
				} )
				.finally( function () {
					button.disabled = false;
				} );
		} );
	} );
} )();
