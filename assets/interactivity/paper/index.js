import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import { createPaperView } from './view.js';

const paper = store(
	'ohmylms/paper',
	createPaperView( {
		getContext,
		getElement,
		withSyncEvent,
		state: () => paper.state,
		storage: window.sessionStorage,
		// A native dialog blocks until answered, which is what a final submit needs.
		// eslint-disable-next-line no-alert
		confirm: ( message ) => window.confirm( message ),
	} )
);
