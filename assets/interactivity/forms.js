import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import { createForms } from './forms/fields.js';

/** Learner form helpers. Markup is decorated by OhMyLMS\Extensions\Chrome::forms(). */
const forms = createForms( { getContext, getElement, withSyncEvent } );

store( 'ohmylms/forms', forms );
