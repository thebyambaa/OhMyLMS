document
	.querySelectorAll( '[data-ohmylms-streak-timezone]' )
	.forEach( ( form ) => {
		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const button = form.querySelector( 'button' );
			const message = form.querySelector( '[data-streak-message]' );
			if ( button.disabled ) return;
			button.disabled = true;
			try {
				const response = await fetch( form.dataset.endpoint, {
					method: 'PUT',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': form.dataset.nonce,
					},
					body: JSON.stringify( {
						timezone: new FormData( form ).get( 'timezone' ),
					} ),
				} );
				const result = await response.json();
				if ( ! response.ok || result !== true )
					throw Error( result?.message || form.dataset.error );
				message.textContent = form.dataset.success;
			} catch ( error ) {
				message.textContent = error.message || form.dataset.error;
			} finally {
				button.disabled = false;
			}
		} );
	} );
