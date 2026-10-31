import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import {
	announce,
	debounce,
	emit,
	jqueryTrigger,
	prefersReducedMotion,
	request,
	toast,
} from 'ohmylms/interactivity';
import { createVideo, installShims } from './video/store.js';

/** Provider adapters, imported only for the provider a player uses (registered by Extensions\Video). */
const ADAPTER_MODULES = {
	html5: () => import( 'ohmylms/video-html5' ),
	youtube: () => import( 'ohmylms/video-youtube' ),
	vimeo: () => import( 'ohmylms/video-vimeo' ),
	playerjs: () => import( 'ohmylms/video-playerjs' ),
	wistia: () => import( 'ohmylms/video-wistia' ),
};

let video = null;
const definitions = createVideo( {
	getContext,
	getElement,
	withSyncEvent,
	getState: () => video.state,
	loadAdapter: ( name ) => ADAPTER_MODULES[ name ](),
	request,
	emit,
	toast,
	announce,
	debounce,
	jqueryTrigger,
	prefersReducedMotion,
} );

/** Lesson and session replay videos. Markup: OhMyLMS\Extensions\Video. */
video = store( 'ohmylms/video', definitions );
installShims( window, definitions.find );
