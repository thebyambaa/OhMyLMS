/**
 * Course catalogue for the `ohmylms/catalog` store: search, filters, sort, curriculum buttons,
 * "load more" and the layout-3 card popup. Every list is a GET URL that the server renders
 * (includes/Extensions/Catalog.php); the store only builds those URLs and asks the router to swap
 * the result regions. Everything the runtime or the SDK provides is passed in, so
 * tests/js/catalog.test.mjs drives it in node.
 */

/** Query parameters of the URL contract, by filter key. Lists are sent as `name[]`. */
export const PARAMS = Object.freeze( {
	search: 'course_search',
	curriculum: 'course_curriculum',
	bundle: 'course_bundle',
	level: 'course_level',
	price: 'course_price',
	sort: 'course_sort',
	page: 'paged',
} );

/** Multi-value filters, in URL order. */
export const LISTS = Object.freeze( [
	'curriculum',
	'bundle',
	'level',
	'price',
] );

/** Sort orders the server knows; `date` is the default and is left out of URLs. */
export const SORTS = Object.freeze( [
	'date',
	'max_price',
	'min_price',
	'rating',
	'review',
] );

export const DEFAULT_SORT = 'date';

/** Highest "load more" page; mirrors Catalog::MAX_PAGES. */
export const MAX_PAGE = 50;

/** Search terms are cut to this length; mirrors Catalog::MAX_SEARCH. */
export const MAX_SEARCH = 100;

/** Cards that open a popup (course archive layout 3). */
export const POPUP_CARD =
	'.grid-style3 .ohmylms-course-cards-carousel .course-card';

const KEYS = Object.fromEntries(
	Object.entries( PARAMS ).map( ( [ key, name ] ) => [ name, key ] )
);

/**
 * @typedef {Object} Filters
 * @property {string}   search     Search term.
 * @property {string[]} curriculum Curriculum item slugs (`c<id>`).
 * @property {string[]} bundle     Course Bundle slugs (`t<id>`).
 * @property {string[]} level      Difficulty levels (`all` means any).
 * @property {string[]} price      `paid`, `free`.
 * @property {string}   sort       One of SORTS, or '' for the default order.
 * @property {number}   page       Pages shown (1 + "load more" clicks).
 */

/** @return {Filters} No filters. */
export function emptyFilters() {
	return {
		search: '',
		curriculum: [],
		bundle: [],
		level: [],
		price: [],
		sort: '',
		page: 1,
	};
}

/**
 * Filter key of a field name: `course_level`, `course_level[]` and `course_level[0]` are all `level`.
 *
 * @param {string} name Field or query parameter name.
 * @return {string|null} Filter key, or null for any other parameter.
 */
export function keyOf( name ) {
	const match = /^([a-z_]+)(?:\[\d*\])?$/.exec( String( name ) );
	return match && KEYS[ match[ 1 ] ] ? KEYS[ match[ 1 ] ] : null;
}

/**
 * Page number, clamped to 1..MAX_PAGE.
 *
 * @param {string|number} value Raw value.
 * @return {number} Page.
 */
export function clampPage( value ) {
	const page = parseInt( value, 10 );
	return Number.isFinite( page ) && page > 1 ? Math.min( page, MAX_PAGE ) : 1;
}

/**
 * Filters from query parameters or form fields (what `serializeForm( form, { as: 'params' } )` gives).
 *
 * @param {URLSearchParams|string|Iterable<[string, string]>} query Parameters.
 * @return {Filters} Filters.
 */
export function readFilters( query ) {
	const params =
		query instanceof URLSearchParams
			? query
			: new URLSearchParams( query || '' );
	const filters = emptyFilters();
	for ( const [ name, raw ] of params ) {
		const key = keyOf( name );
		const value = String( raw ).trim();
		if ( ! key || ! value ) {
			continue;
		}
		if ( LISTS.includes( key ) ) {
			if ( ! filters[ key ].includes( value ) ) {
				filters[ key ].push( value );
			}
		} else if ( 'search' === key ) {
			filters.search = value.slice( 0, MAX_SEARCH );
		} else if ( 'sort' === key ) {
			filters.sort = SORTS.includes( value ) ? value : '';
		} else {
			filters.page = clampPage( value );
		}
	}
	return filters;
}

/**
 * Filters of a page URL, including the `/page/N/` form WordPress redirects `paged` to.
 *
 * @param {string} href URL.
 * @return {Filters} Filters.
 */
export function readUrl( href ) {
	const url = new URL( href, 'http://localhost/' );
	const filters = readFilters( url.searchParams );
	const pretty = /\/page\/(\d+)\/?$/.exec( url.pathname );
	if ( pretty && 1 === filters.page ) {
		filters.page = clampPage( pretty[ 1 ] );
	}
	return filters;
}

/**
 * Canonical query parameters: fixed order, sorted lists, no empty or default values. The PHP
 * side (Catalog::query_string()) builds the same string, so server links and client URLs match.
 *
 * @param {Filters} filters Filters.
 * @return {URLSearchParams} Parameters.
 */
export function toParams( filters ) {
	const params = new URLSearchParams();
	if ( filters.search ) {
		params.append( PARAMS.search, filters.search );
	}
	for ( const key of LISTS ) {
		for ( const value of [ ...( filters[ key ] || [] ) ].sort() ) {
			params.append( `${ PARAMS[ key ] }[]`, value );
		}
	}
	if ( filters.sort && DEFAULT_SORT !== filters.sort ) {
		params.append( PARAMS.sort, filters.sort );
	}
	if ( filters.page > 1 ) {
		params.append( PARAMS.page, String( filters.page ) );
	}
	return params;
}

/**
 * URL of a filtered list: the page's own URL without catalogue parameters (other parameters such
 * as `page_id` stay), plus the filters.
 *
 * @param {string}  href    Current URL.
 * @param {Filters} filters Filters.
 * @return {string} URL.
 */
export function catalogUrl( href, filters ) {
	const url = new URL( href );
	for ( const name of [ ...url.searchParams.keys() ] ) {
		if ( keyOf( name ) ) {
			url.searchParams.delete( name );
		}
	}
	url.pathname = url.pathname.replace( /\/page\/\d+\/?$/, '/' );
	for ( const [ name, value ] of toParams( filters ) ) {
		url.searchParams.append( name, value );
	}
	url.hash = '';
	return url.toString();
}

/**
 * Whether two filter sets describe the same list.
 *
 * @param {Filters} a First.
 * @param {Filters} b Second.
 * @return {boolean} Same.
 */
export function sameFilters( a, b ) {
	return toParams( a ).toString() === toParams( b ).toString();
}

/**
 * Turn one list value on or off. Any change starts again from the first page.
 *
 * @param {Filters} filters Filters.
 * @param {string}  key     List key.
 * @param {string}  value   Value.
 * @param {boolean} on      Selected.
 * @return {Filters} New filters.
 */
export function setListValue( filters, key, value, on ) {
	const list = ( filters[ key ] || [] ).filter( ( item ) => item !== value );
	return {
		...filters,
		[ key ]: on ? [ ...list, value ] : list,
		page: 1,
	};
}

/**
 * "Clear all" in the filter sidebar: the checkboxes go, the search term and sort stay (as before).
 *
 * @param {Filters} filters Filters.
 * @return {Filters} New filters.
 */
export function clearFilters( filters ) {
	return {
		...filters,
		curriculum: [],
		bundle: [],
		level: [],
		price: [],
		page: 1,
	};
}

/**
 * Filters for the next "load more" page, or null on the last page.
 *
 * @param {Filters} filters Filters.
 * @param {number}  total   Number of pages.
 * @return {Filters|null} Next filters.
 */
export function nextPage( filters, total ) {
	const page = Math.max( 1, filters.page || 1 );
	if ( page >= Math.min( total, MAX_PAGE ) ) {
		return null;
	}
	return { ...filters, page: page + 1 };
}

/**
 * Browser history for a navigation: typing in the search box refines one history entry,
 * everything else adds an entry so Back undoes it.
 *
 * @param {string} previous Reason of the previous navigation.
 * @param {string} reason   `search`, `filter`, `sort`, `category`, `clear`, `submit`, `more`.
 * @return {'push'|'replace'} History mode.
 */
export function historyMode( previous, reason ) {
	return 'search' === reason && 'search' === previous ? 'replace' : 'push';
}

/**
 * Where the layout-3 popup goes: right of the card, or left of it when it would leave the viewport.
 *
 * @param {{left: number, top: number, width: number, height: number}} card          Card rectangle (viewport).
 * @param {{left: number, top: number}}                                parent        Positioned container rectangle (viewport).
 * @param {number}                                                     popupWidth    Popup width.
 * @param {number}                                                     viewportWidth Viewport width.
 * @param {number}                                                     [gap]         Space between card and popup.
 * @return {{left: number, top: number, toLeft: boolean}} Position relative to the container.
 */
export function popupPosition(
	card,
	parent,
	popupWidth,
	viewportWidth,
	gap = 20
) {
	const top = card.top - parent.top + card.height / 2;
	const toLeft = card.left + card.width + gap + popupWidth > viewportWidth;
	const left = toLeft
		? card.left - parent.left - popupWidth - gap
		: card.left - parent.left + card.width + gap;
	return { left, top, toLeft };
}

/**
 * Store definitions.
 *
 * @param {Object}                                                                                api               Runtime and SDK.
 * @param {() => Object}                                                                          api.getContext    `@wordpress/interactivity` getContext.
 * @param {() => {ref: HTMLElement}}                                                              api.getElement    `@wordpress/interactivity` getElement.
 * @param {(callback: (event: Event) => void) => (event: Event) => void}                          api.withSyncEvent `@wordpress/interactivity` withSyncEvent.
 * @param {() => Object}                                                                          api.getState      Returns the live `ohmylms/catalog` state.
 * @param {(url: string, options: {replace: boolean}) => Promise<boolean>}                        api.navigate      Swaps the regions; resolves false when the page was loaded in full instead.
 * @param {() => boolean}                                                                         api.routed        Whether a client navigation happened (popstate then belongs to the router).
 * @param {(form: HTMLFormElement, options?: Object) => URLSearchParams}                          api.serializeForm SDK `serializeForm`.
 * @param {(callback: (...args: unknown[]) => void, ms: number) => (...args: unknown[]) => void}  api.debounce      SDK `debounce`.
 * @param {(target: EventTarget, name: string, detail?: Object, cancelable?: boolean) => boolean} api.emit          SDK `emit`.
 * @param {(container: Element) => HTMLElement[]}                                                 [api.focusable]   SDK `focusable`.
 * @param {(callback: () => void) => void}                                                        [api.frame]       `requestAnimationFrame`.
 * @param {Object}                                                                                [api.timers]      `setTimeout`, `clearTimeout` (tests).
 * @param {number}                                                                                [api.searchDelay] Debounce for typing, in ms.
 * @param {Window}                                                                                [api.win]         Window.
 * @return {{state: Object, actions: Object, callbacks: Object}} Definitions.
 */
export function createCatalog( api ) {
	const {
		getContext,
		getElement,
		withSyncEvent,
		getState,
		navigate,
		routed = () => true,
		serializeForm,
		debounce,
		emit,
		focusable = () => [],
		frame = ( callback ) => requestAnimationFrame( callback ),
		timers = {
			setTimeout: ( callback, ms ) => setTimeout( callback, ms ),
			clearTimeout: ( id ) => clearTimeout( id ),
		},
		searchDelay = 350,
	} = api;
	const win = () => api.win || globalThis.window;

	let previousReason = '';
	let sequence = 0;

	/**
	 * Catalogue root of an element: the archive container, or a decorated template island.
	 *
	 * @param {Element} element Element inside the catalogue.
	 * @return {Element} Root.
	 */
	const rootOf = ( element ) =>
		element?.closest?.( '.ohmylms-catalog' ) ||
		element?.closest?.( '[data-wp-interactive="ohmylms/catalog"]' ) ||
		element;

	const formOf = ( context, element ) => {
		const doc = element?.ownerDocument || win().document;
		const form = context?.form ? doc.getElementById( context.form ) : null;
		return 'FORM' === form?.tagName ? form : null;
	};

	/**
	 * Catalogue fields: those of the form, or (pre-L3 archive overrides) every named control.
	 *
	 * @param {HTMLFormElement|null} form Filter form.
	 * @param {Element}              root Catalogue root.
	 * @return {Array<HTMLInputElement|HTMLSelectElement>} Fields.
	 */
	const controlsOf = ( form, root ) => {
		if ( form ) {
			return [ ...form.elements ];
		}
		const doc = root?.ownerDocument || win().document;
		return [
			...doc.querySelectorAll( 'input[name], select[name]' ),
		].filter( ( field ) => keyOf( field.name ) && ! field.form );
	};

	const collect = ( form, root ) => {
		if ( form ) {
			return readFilters( serializeForm( form, { as: 'params' } ) );
		}
		const params = new URLSearchParams();
		for ( const field of controlsOf( null, root ) ) {
			if (
				( 'checkbox' !== field.type && 'radio' !== field.type ) ||
				field.checked
			) {
				params.append( field.name, field.value );
			}
		}
		return readFilters( params );
	};

	/**
	 * Put the controls back in line with the URL (Back/Forward, "Clear all").
	 *
	 * @param {HTMLFormElement|null} form    Filter form.
	 * @param {Element}              root    Catalogue root.
	 * @param {Filters}              filters Filters to show.
	 */
	const syncControls = ( form, root, filters ) => {
		for ( const field of controlsOf( form, root ) ) {
			const key = keyOf( field.name );
			if ( ! key || 'page' === key ) {
				continue;
			}
			if ( LISTS.includes( key ) ) {
				field.checked = filters[ key ].includes( field.value );
			} else if ( 'search' === key && field.value !== filters.search ) {
				field.value = filters.search;
			} else if ( 'sort' === key ) {
				field.value = filters.sort || DEFAULT_SORT;
			}
		}
	};

	const focusHeading = ( context, root, preventScroll = false ) => {
		const doc = root?.ownerDocument || win().document;
		const heading =
			( context?.heading && doc.getElementById( context.heading ) ) ||
			root?.querySelector?.( '.ohmylms-catalog-heading' );
		heading?.focus?.( { preventScroll } );
	};

	const hidePopups = ( root ) => {
		root?.querySelectorAll?.( '.ohmylms-course-card-popup.active' ).forEach(
			( popup ) => popup.classList.remove( 'active' )
		);
	};

	/**
	 * Show a filtered list.
	 *
	 * @param {Object}  context Root context (`form`, `heading`).
	 * @param {Element} root    Catalogue root.
	 * @param {Filters} filters Filters.
	 * @param {string}  reason  Why (see historyMode()).
	 * @param {Object}  [after] `focus`: 'heading' or a function run with the root.
	 * @return {Promise<void>} Settles after the regions are swapped.
	 */
	const go = async ( context, root, filters, reason, after = {} ) => {
		const href = win().location.href;
		const url = catalogUrl( href, filters );
		if (
			'submit' !== reason &&
			url === catalogUrl( href, readUrl( href ) )
		) {
			return;
		}
		if (
			! emit(
				root,
				'catalog-before-navigate',
				{ url, filters, reason },
				true
			)
		) {
			return;
		}
		const replace = 'replace' === historyMode( previousReason, reason );
		previousReason = reason;
		const run = ++sequence;
		const state = getState();
		state.filters = filters;
		state.loading = true;
		hidePopups( root );
		let swapped = false;
		try {
			swapped = await navigate( url, { replace } );
		} finally {
			if ( run === sequence ) {
				state.loading = false;
			}
		}
		if ( ! swapped || run !== sequence ) {
			return;
		}
		emit( root, 'catalog-navigated', { url, filters, reason } );
		if ( 'heading' === after.focus ) {
			focusHeading( context, root );
		} else if ( 'function' === typeof after.focus ) {
			after.focus( root );
		}
	};

	const typed = debounce( ( context, root, form ) => {
		go( context, root, collect( form, root ), 'search' );
	}, searchDelay );

	let popupTimer = 0;
	const popupFor = ( root, card ) => {
		if ( ! card?.id ) {
			return null;
		}
		const id = globalThis.CSS?.escape ? CSS.escape( card.id ) : card.id;
		return root.querySelector(
			`.ohmylms-course-card-popup[data-item-id="${ id }"]`
		);
	};
	const placePopup = ( card, popup ) => {
		const parent = card.closest( '.ohmylms-course-outer' );
		if ( ! parent ) {
			return;
		}
		const rect = card.getBoundingClientRect();
		const position = popupPosition(
			rect,
			parent.getBoundingClientRect(),
			popup.getBoundingClientRect().width,
			win().innerWidth
		);
		popup.classList.toggle( 'to-left', position.toLeft );
		popup.style.left = `${ position.left }px`;
		popup.style.top = `${ position.top }px`;
		popup.style.transform = 'translateY(-50%)';
	};

	return {
		state: {
			loading: false,
			get categoryActive() {
				const { slug } = getContext();
				const { curriculum = [] } = getState().filters || {};
				return 'all' === slug
					? ! curriculum.length
					: curriculum.includes( slug );
			},
			get categoryCurrent() {
				return getState().categoryActive ? 'true' : false;
			},
		},
		actions: {
			/**
			 * Checkbox and sort changes (delegated from the root: the boxes sit in `ohmylms/ui` accordions).
			 *
			 * @param {Event} event `change` event.
			 */
			change( event ) {
				const field = event.target;
				const key = keyOf( field?.name );
				if ( ! key || 'page' === key ) {
					return;
				}
				const context = getContext();
				const root = rootOf( getElement().ref );
				const form = formOf( context, root );
				if ( form && field.form !== form ) {
					return;
				}
				if ( 'search' === key ) {
					typed.flush?.();
					return;
				}
				typed.cancel?.();
				go(
					context,
					root,
					collect( form, root ),
					'sort' === key ? 'sort' : 'filter'
				);
			},
			/**
			 * Typing in the search box (debounced).
			 *
			 * @param {Event} event `input` event.
			 */
			input( event ) {
				if ( 'search' !== keyOf( event.target?.name ) ) {
					return;
				}
				const context = getContext();
				const root = rootOf( getElement().ref );
				const form = formOf( context, root );
				if ( form && event.target.form !== form ) {
					return;
				}
				typed( context, root, form );
			},
			/** Enter in the search box, or the no-JavaScript "Apply" button. */
			submit: withSyncEvent( ( event ) => {
				const context = getContext();
				const root = rootOf( getElement().ref );
				const form = formOf( context, root );
				if ( ! form || event.target !== form ) {
					return;
				}
				event.preventDefault();
				typed.cancel?.();
				go( context, root, collect( form, root ), 'submit', {
					focus: 'heading',
				} );
			} ),
			/** Enter/Space on the styled box next to a filter checkbox. */
			keydown: withSyncEvent( ( event ) => {
				const box = event.target?.closest?.( '.checkedbox' );
				if ( ! box || ( 'Enter' !== event.key && ' ' !== event.key ) ) {
					return;
				}
				const input = box.ownerDocument.getElementById(
					box.dataset.target || ''
				);
				if ( input ) {
					event.preventDefault();
					input.click();
				}
			} ),
			/** "Clear all" in the filter sidebar. */
			clear: withSyncEvent( ( event ) => {
				event.preventDefault();
				const context = getContext();
				const root = rootOf( getElement().ref );
				const form = formOf( context, root );
				const filters = clearFilters( collect( form, root ) );
				syncControls( form, root, filters );
				go( context, root, filters, 'clear', { focus: 'heading' } );
			} ),
			/** Curriculum buttons (layouts 3 and 4): real links, followed by the router. */
			category: withSyncEvent( ( event ) => {
				if (
					event.button > 0 ||
					event.metaKey ||
					event.ctrlKey ||
					event.shiftKey ||
					event.altKey
				) {
					return;
				}
				event.preventDefault();
				const { slug } = getContext();
				const root = rootOf( getElement().ref );
				const current = getState().filters || emptyFilters();
				go(
					getContext(),
					root,
					{
						...current,
						curriculum: ! slug || 'all' === slug ? [] : [ slug ],
						page: 1,
					},
					'category',
					{ focus: 'heading' }
				);
			} ),
			/** "Load more": the server renders pages 1..N for `paged=N`, so the cards already shown stay. */
			loadMore: withSyncEvent( ( event ) => {
				event.preventDefault();
				const button = getElement().ref;
				const next = nextPage(
					{
						...( getState().filters || emptyFilters() ),
						page: Math.max(
							1,
							parseInt( button.dataset.paged, 10 ) || 1
						),
					},
					parseInt( button.dataset.total, 10 ) || 1
				);
				if ( ! next || getState().loading ) {
					return;
				}
				const context = getContext();
				const root = rootOf( button );
				const shown = root.querySelectorAll(
					'.ohmylms-course-cards .course-card'
				).length;
				go( context, root, next, 'more', {
					focus: ( container ) => {
						const card = container.querySelectorAll(
							'.ohmylms-course-cards .course-card'
						)[ shown ];
						focusable( card )[ 0 ]?.focus();
					},
				} );
			} ),
			/** Close button inside the mobile filter drawer: the toggle closes it and keeps focus. */
			closeFilters: withSyncEvent( ( event ) => {
				event.preventDefault();
				rootOf( getElement().ref )
					?.querySelector( '.filter-hamburger' )
					?.click();
			} ),
			/** Back/Forward between catalogue URLs: the router swaps the regions, this syncs the controls. */
			popstate() {
				if ( ! routed() ) {
					return;
				}
				const state = getState();
				const filters = readUrl( win().location.href );
				if ( sameFilters( filters, state.filters || emptyFilters() ) ) {
					return;
				}
				const context = getContext();
				const root = rootOf( getElement().ref );
				state.filters = filters;
				syncControls( formOf( context, root ), root, filters );
				hidePopups( root );
				timers.setTimeout(
					() => frame( () => focusHeading( context, root, true ) ),
					0
				);
			},
			/**
			 * Layout-3 card popup (delegated, so cards from later navigations work too).
			 *
			 * @param {MouseEvent} event `mouseover` event.
			 */
			cardOver( event ) {
				const card = event.target?.closest?.( POPUP_CARD );
				if ( ! card || card.contains( event.relatedTarget ) ) {
					return;
				}
				const root = rootOf( getElement().ref );
				const popup = popupFor( root, card );
				if ( ! popup ) {
					return;
				}
				timers.clearTimeout( popupTimer );
				root.querySelectorAll(
					'.ohmylms-course-card-popup.active'
				).forEach(
					( other ) =>
						other !== popup && other.classList.remove( 'active' )
				);
				popup.classList.add( 'active' );
				placePopup( card, popup );
			},
			cardOut( event ) {
				const from =
					event.target?.closest?.( POPUP_CARD ) ||
					event.target?.closest?.( '.ohmylms-course-card-popup' );
				if ( ! from || from.contains( event.relatedTarget ) ) {
					return;
				}
				const root = rootOf( getElement().ref );
				const popup = from.matches( '.ohmylms-course-card-popup' )
					? from
					: popupFor( root, from );
				const card = popup
					? root.ownerDocument.getElementById(
							popup.dataset.itemId || ''
						)
					: null;
				timers.clearTimeout( popupTimer );
				popupTimer = timers.setTimeout( () => {
					if (
						popup &&
						! popup.matches( ':hover' ) &&
						! card?.matches( ':hover' )
					) {
						popup.classList.remove( 'active' );
					}
				}, 100 );
			},
		},
		callbacks: {
			/** Keep an open popup next to its card when the window is resized. */
			reposition() {
				const root = rootOf( getElement().ref );
				const popup = root?.querySelector?.(
					'.ohmylms-course-card-popup.active'
				);
				const card = popup
					? root.ownerDocument.getElementById(
							popup.dataset.itemId || ''
						)
					: null;
				if ( popup && card ) {
					placePopup( card, popup );
				}
			},
		},
	};
}
