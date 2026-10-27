import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import { emit } from 'ohmylms/interactivity';

/** Reusable disclosure/drawer actions. Add-ons can use the same markup contract. */
store( 'ohmylms/ui', {
	state: {
		get display() {
			return getContext().open ? 'block' : 'none';
		},
		get sidebarDisplay() {
			return getContext().sidebarOpen ? 'block' : '';
		},
	},
	actions: {
		toggle: withSyncEvent( ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			const context = getContext();
			context.open = ! context.open;
			emit( getElement().ref, 'disclosure-changed', {
				open: context.open,
			} );
		} ),
		keyToggle: withSyncEvent( ( event ) => {
			if ( event.key !== 'Enter' && event.key !== ' ' ) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			const context = getContext();
			context.open = ! context.open;
			emit( getElement().ref, 'disclosure-changed', {
				open: context.open,
			} );
		} ),
		openSidebar: withSyncEvent( ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			getContext().sidebarOpen = true;
		} ),
		closeSidebar: withSyncEvent( ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			getContext().sidebarOpen = false;
		} ),
		sidebarKey: withSyncEvent( ( event ) => {
			if ( event.key === 'Enter' || event.key === ' ' ) {
				event.preventDefault();
				event.stopPropagation();
				getContext().sidebarOpen = true;
			}
		} ),
		outside( event ) {
			if (
				! event.target.closest(
					'.ohmylms-lesson-sidebar,.ohmylms-lesson-details-hamburger'
				)
			) {
				getContext().sidebarOpen = false;
			}
		},
		escape( event ) {
			if ( event.key === 'Escape' ) {
				getContext().sidebarOpen = false;
			}
		},
	},
} );
