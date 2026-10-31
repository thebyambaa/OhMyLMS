import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import { announce, emit, request, toast } from 'ohmylms/interactivity';
import { createAssignment } from './assignment/store.js';

let assignment = null;
const definitions = createAssignment( {
	getContext,
	getElement,
	withSyncEvent,
	getState: () => assignment.state,
	request,
	toast,
	emit,
	announce,
} );

/** Assignment start, timer, files and submission. Markup: OhMyLMS\Extensions\Lesson. */
assignment = store( 'ohmylms/assignment', {
	state: definitions.state,
	actions: definitions.actions,
	callbacks: definitions.callbacks,
} );
