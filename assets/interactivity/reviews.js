import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import { emit } from 'ohmylms/interactivity';
import { createReviews } from './reviews/rating.js';

/** Course review form. Markup is decorated by OhMyLMS\Extensions\Reviews. */
store(
	'ohmylms/reviews',
	createReviews( { getContext, getElement, withSyncEvent, emit } )
);
