import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import {
	emit,
	plainText,
	request,
	serializeForm,
	toast,
} from 'ohmylms/interactivity';
import { createForms } from './forms/fields.js';
import { createAuth } from './forms/auth.js';

/**
 * Learner form helpers, login and sign-up. Markup is decorated by OhMyLMS\Extensions\Chrome::forms()
 * and OhMyLMS\Extensions\AuthForms::decorate().
 */
let forms = null;
const fields = createForms( { getContext, getElement, withSyncEvent } );
const auth = createAuth( {
	getContext,
	getElement,
	withSyncEvent,
	getState: () => forms.state,
	request,
	serializeForm,
	toast,
	emit,
	plainText,
} );

forms = store( 'ohmylms/forms', {
	state: Object.defineProperties(
		{},
		{
			...Object.getOwnPropertyDescriptors( fields.state ),
			...Object.getOwnPropertyDescriptors( auth.state ),
		}
	),
	actions: { ...fields.actions, ...auth.actions },
	callbacks: { ...fields.callbacks, ...auth.callbacks },
} );
