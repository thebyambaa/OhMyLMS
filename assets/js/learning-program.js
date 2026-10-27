( function () {
	'use strict';
	var config = window.ohmylmsLearning || {};
	document
		.querySelectorAll( '[data-complete-placement]' )
		.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var root = button.closest( '[data-course]' );
				var message = root.querySelector( '.ohmylms-learning-message' );
				button.disabled = true;
				message.textContent = config.saving;
				fetch(
					config.root +
						'courses/' +
						root.dataset.course +
						'/learning/activities/' +
						button.dataset.completePlacement +
						'/complete',
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'X-WP-Nonce': config.nonce,
							'Content-Type': 'application/json',
						},
						body: '{}',
					}
				)
					.then( function ( response ) {
						return response.json().then( function ( data ) {
							if ( ! response.ok )
								throw new Error( data.message || config.error );
							location.reload();
						} );
					} )
					.catch( function ( error ) {
						message.textContent = error.message || config.error;
						button.disabled = false;
					} );
			} );
		} );
} )();
