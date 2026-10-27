import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import { emit } from 'ohmylms/interactivity';
function select( button ) {
	const root = button.closest( '[data-wp-interactive="ohmylms/tabs"]' );
	const key = button.dataset.target.slice( 1 );
	if (
		! emit(
			root,
			'tab-before-change',
			{
				tab: key,
			},
			true
		)
	) {
		return;
	}
	const context = getContext();
	context.activeTab = key;
	emit( root, 'tab-changed', {
		tab: key,
	} );
}
store( 'ohmylms/tabs', {
	state: {
		get selected() {
			const c = getContext();
			return c.activeTab === c.tabKey;
		},
		get tabIndex() {
			const c = getContext();
			return c.activeTab === c.tabKey ? '0' : '-1';
		},
	},
	actions: {
		select: withSyncEvent( ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			select( getElement().ref );
		} ),
		key: withSyncEvent( ( event ) => {
			if (
				! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes(
					event.key
				)
			) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			const button = getElement().ref;
			const root = button.closest(
				'[data-wp-interactive="ohmylms/tabs"]'
			);
			const buttons = [ ...root.querySelectorAll( '[role=tab]' ) ];
			const rtl = getComputedStyle( root ).direction === 'rtl';
			const step =
				( event.key === 'ArrowRight' ? 1 : -1 ) * ( rtl ? -1 : 1 );
			const index = ( () => {
				if ( event.key === 'Home' ) {
					return 0;
				}
				if ( event.key === 'End' ) {
					return buttons.length - 1;
				}
				return (
					( buttons.indexOf( button ) + step + buttons.length ) %
					buttons.length
				);
			} )();
			select( buttons[ index ] );
			buttons[ index ].focus();
		} ),
	},
} );
