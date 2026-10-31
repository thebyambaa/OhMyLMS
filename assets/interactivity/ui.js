import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import {
	emit,
	focusable,
	prefersReducedMotion,
	request,
	takePendingToasts,
	toast,
	toastItem,
	trapFocus,
} from 'ohmylms/interactivity';
import { createChrome, installChrome } from './ui/chrome.js';

let ui = null;
const chrome = createChrome( {
	getContext,
	getElement,
	withSyncEvent,
	getState: () => ui.state,
	emit,
	focusable,
	trapFocus,
	takePendingToasts,
	reducedMotion: prefersReducedMotion,
} );

/** Reusable disclosure/drawer actions and the page chrome. Add-ons can use the same markup contract. */
ui = store( 'ohmylms/ui', {
	state: Object.defineProperties(
		{
			get display() {
				return getContext().open ? 'block' : 'none';
			},
			get sidebarDisplay() {
				return getContext().sidebarOpen ? 'block' : '';
			},
		},
		Object.getOwnPropertyDescriptors( chrome.state )
	),
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
		...chrome.actions,
	},
	callbacks: chrome.callbacks,
} );

installChrome( {
	win: window,
	chrome,
	toast,
	toastItem,
	request,
	messages: () => ui.state.messages || {},
} );
