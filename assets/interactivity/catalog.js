import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import {
	debounce,
	emit,
	focusable,
	serializeForm,
} from 'ohmylms/interactivity';
import { createCatalog } from './catalog/catalog.js';

/**
 * Course archive and course list: search, filters, sort, curriculum buttons, load more and the
 * layout-3 card popup. Markup: templates/archive-course.php, templates/filters/*, loop/load-more.php
 * (decorated for theme overrides by includes/Extensions/Catalog.php).
 */
let router = null;
const regions = () =>
	document.querySelector( '[data-wp-router-region^="ohmylms/catalog"]' );

const catalog = createCatalog( {
	getContext,
	getElement,
	withSyncEvent,
	getState: () => state,
	emit,
	debounce,
	focusable,
	serializeForm,
	routed: () => Boolean( router ),
	async navigate( url, { replace } ) {
		// An archive-course.php override from before the router regions: load the page.
		if ( ! regions() ) {
			window.location.assign( url );
			return false;
		}
		router = router || import( '@wordpress/interactivity-router' );
		const { actions } = await router;
		await actions.navigate( url, { replace } );
		return (
			window.location.href === new URL( url, window.location.href ).href
		);
	},
} );

const { state } = store( 'ohmylms/catalog', catalog );
