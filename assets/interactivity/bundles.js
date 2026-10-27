import { store, withSyncEvent } from '@wordpress/interactivity';

const { state } = store( 'ohmylms/bundles', {
	state: { status: '' },
	actions: {
		// One handler on the stable root: the section inside it is replaced after every change.
		click: withSyncEvent( ( event ) => {
			const button = event.target.closest(
				'[data-bundle-follow], [data-bundle-unfollow]'
			);
			if ( ! button || button.disabled ) {
				return;
			}
			const follow = button.hasAttribute( 'data-bundle-follow' );
			const id = button.getAttribute(
				follow ? 'data-bundle-follow' : 'data-bundle-unfollow'
			);
			change( button, follow, id );
		} ),
	},
} );

// Announces through the persistent status region; cleared first so repeats are read again.
function announce( text ) {
	state.status = '';
	setTimeout( () => {
		state.status = text;
	}, 50 );
}

async function request( follow, id ) {
	const response = await fetch(
		`${ state.root }bundles/${ encodeURIComponent( id ) }/follow`,
		{
			method: follow ? 'POST' : 'DELETE',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': state.nonce,
				'Content-Type': 'application/json',
			},
			body: follow ? '{}' : undefined,
		}
	);
	const data = await response.json();
	if ( ! response.ok ) {
		throw new Error( data.message || state.messages.error );
	}
	return data;
}

async function change( button, follow, id ) {
	button.disabled = true;
	try {
		const data = await request( follow, id );
		const current = document.getElementById( 'ohmylms-bundles' );
		const holder = document.createElement( 'div' );
		holder.innerHTML = data.html || '';
		const next = holder.querySelector( '#ohmylms-bundles' );
		if ( current && next ) {
			current.replaceWith( next );
			next.focus();
		} else if ( current ) {
			current.replaceChildren();
		}
		announce( follow ? state.messages.added : state.messages.removed );
	} catch ( error ) {
		button.disabled = false;
		announce( ( error && error.message ) || state.messages.error );
	}
}
