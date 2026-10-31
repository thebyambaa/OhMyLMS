import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import {
	confirmDialog,
	emit,
	request,
	toast,
	trapFocus,
} from 'ohmylms/interactivity';
import { createAccount } from './account/store.js';

let account = null;
const definitions = createAccount( {
	getContext,
	getElement,
	withSyncEvent,
	getState: () => account.state,
	request,
	toast,
	confirmDialog,
	emit,
	trapFocus,
} );

/** Learner profile, dashboard memberships and invoices. Markup: OhMyLMS\Extensions\Account. */
account = store( 'ohmylms/account', definitions );
