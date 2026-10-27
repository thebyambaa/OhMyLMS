import { store, getElement, withSyncEvent } from '@wordpress/interactivity';
import { emit } from 'ohmylms/interactivity';

const { state, actions } = store( 'ohmylms/gamification', {
	state: { message: '', visible: false },
	actions: {
		dismiss: withSyncEvent( () => {
			state.visible = false;
		} ),
		show( message ) {
			state.message = String( message );
			state.visible = true;
		},
	},
	callbacks: {
		mount() {
			const root = getElement().ref;
			let timeout;
			const show = ( event ) => {
				actions.show( event.detail.message );
				clearTimeout( timeout );
				timeout = setTimeout( () => {
					state.visible = false;
				}, 3500 );
				if (
					! window.matchMedia( '(prefers-reduced-motion: reduce)' )
						.matches
				) {
					window.OhMyLMSCelebrate?.startConfetti();
				}
				emit( root, 'reward-presented', { message: state.message } );
			};
			document.addEventListener( 'ohmylms:celebrate', show );
			for ( const message of window.ohmylmsCelebrationQueue || [] ) {
				show( { detail: { message } } );
			}
			window.ohmylmsCelebrationQueue = [];
			window.ohmylmsCelebrationReady = true;
			return () => {
				clearTimeout( timeout );
				document.removeEventListener( 'ohmylms:celebrate', show );
				window.ohmylmsCelebrationReady = false;
				const animation = window.OhMyLMSCelebrate;
				if ( animation ) {
					cancelAnimationFrame( animation.animationFrameId );
					animation.confettiPieces = [];
				}
			};
		},
	},
} );
