import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import { emit, request, toast } from 'ohmylms/interactivity';
import { createLesson } from './lesson/store.js';

let lesson = null;
const definitions = createLesson( {
	getContext,
	getElement,
	withSyncEvent,
	getState: () => lesson.state,
	request,
	toast,
	emit,
} );

/** "Mark as complete" on lesson pages. Markup: OhMyLMS\Extensions\Lesson. */
lesson = store( 'ohmylms/lesson', definitions );
