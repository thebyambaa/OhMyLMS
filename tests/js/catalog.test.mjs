import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {
	PARAMS,
	catalogUrl,
	clampPage,
	clearFilters,
	createCatalog,
	emptyFilters,
	historyMode,
	keyOf,
	nextPage,
	popupPosition,
	readFilters,
	readUrl,
	sameFilters,
	setListValue,
	toParams,
} from '../../assets/interactivity/catalog/catalog.js';

const BASE = 'http://800.local/course/';

test( 'field and parameter names map to filter keys', () => {
	assert.equal( keyOf( 'course_level' ), 'level' );
	assert.equal( keyOf( 'course_level[]' ), 'level' );
	assert.equal( keyOf( 'course_level[3]' ), 'level' );
	assert.equal( keyOf( 'course_search' ), 'search' );
	assert.equal( keyOf( 'paged' ), 'page' );
	assert.equal( keyOf( 'page_id' ), null );
	assert.equal( keyOf( 'course_category[]' ), null, 'taxonomy query vars are not catalogue parameters' );
	assert.equal( keyOf( 'ohmylms-filter-checkbox' ), null );
} );

test( 'form fields become filters; junk and duplicates are dropped', () => {
	const filters = readFilters(
		'page_id=7&course_search=+algebra+&course_curriculum%5B%5D=c27&course_curriculum%5B%5D=c27&course_level%5B%5D=beginner&course_price%5B%5D=free&course_sort=nonsense&paged=3&course_bundle%5B%5D='
	);
	assert.deepEqual( filters, {
		search: 'algebra',
		curriculum: [ 'c27' ],
		bundle: [],
		level: [ 'beginner' ],
		price: [ 'free' ],
		sort: '',
		page: 3,
	} );
	assert.equal( readFilters( 'course_search=' + 'x'.repeat( 300 ) ).search.length, 100 );
	assert.deepEqual( readFilters( '' ), emptyFilters() );
} );

test( 'pages are clamped and read from /page/N/ too', () => {
	assert.equal( clampPage( '0' ), 1 );
	assert.equal( clampPage( 'x' ), 1 );
	assert.equal( clampPage( 7 ), 7 );
	assert.equal( clampPage( 900 ), 50 );
	assert.equal( readUrl( `${ BASE }page/4/?course_level%5B%5D=expert` ).page, 4 );
	assert.equal( readUrl( `${ BASE }page/4/?paged=2` ).page, 2, 'an explicit paged wins' );
} );

test( 'filters build one canonical query string (the PHP side builds the same)', () => {
	const filters = {
		...emptyFilters(),
		search: 'linear equations',
		curriculum: [ 'c27', 'c150' ],
		level: [ 'expert', 'beginner' ],
		price: [ 'paid' ],
		sort: 'rating',
		page: 2,
	};
	// Same string as Catalog::query_string() in tests/php/catalog-integration.php.
	assert.equal(
		toParams( filters ).toString(),
		'course_search=linear+equations&course_curriculum%5B%5D=c150&course_curriculum%5B%5D=c27&course_level%5B%5D=beginner&course_level%5B%5D=expert&course_price%5B%5D=paid&course_sort=rating&paged=2'
	);
	assert.equal( toParams( { ...emptyFilters(), sort: 'date' } ).toString(), '', 'the default sort and page 1 are left out' );
	assert.equal(
		toParams( { ...emptyFilters(), search: 'a b*c~d&e' } ).toString(),
		'course_search=a+b*c%7Ed%26e',
		'form encoding, as Catalog::query_string()'
	);
	assert.ok( sameFilters( { ...filters, level: [ 'beginner', 'expert' ] }, filters ), 'order does not matter' );
	assert.ok( ! sameFilters( { ...filters, page: 3 }, filters ) );
} );

test( 'catalogue URLs keep other parameters and drop stale catalogue ones', () => {
	const url = catalogUrl(
		`${ BASE }page/3/?page_id=7&course_level%5B%5D=expert&paged=3&utm=x#top`,
		{ ...emptyFilters(), price: [ 'free' ] }
	);
	assert.equal( url, `${ BASE }?page_id=7&utm=x&course_price%5B%5D=free` );
	assert.equal( catalogUrl( `${ BASE }?course_search=a`, emptyFilters() ), BASE );
	assert.deepEqual( readUrl( url ), { ...emptyFilters(), price: [ 'free' ] }, 'a built URL reads back to its filters' );
} );

test( 'changing a filter resets the page; clear keeps search and sort', () => {
	const start = { ...emptyFilters(), search: 'a', sort: 'review', level: [ 'beginner' ], page: 4 };
	const on = setListValue( start, 'level', 'expert', true );
	assert.deepEqual( on.level, [ 'beginner', 'expert' ] );
	assert.equal( on.page, 1 );
	assert.deepEqual( setListValue( on, 'level', 'beginner', false ).level, [ 'expert' ] );
	assert.deepEqual( setListValue( on, 'level', 'expert', true ).level, [ 'beginner', 'expert' ], 'no duplicates' );
	const cleared = clearFilters( { ...on, curriculum: [ 'c1' ], price: [ 'free' ] } );
	assert.deepEqual( cleared, { ...emptyFilters(), search: 'a', sort: 'review' } );
} );

test( 'load more stops on the last page', () => {
	const filters = { ...emptyFilters(), page: 2 };
	assert.equal( nextPage( filters, 3 ).page, 3 );
	assert.equal( nextPage( { ...filters, page: 3 }, 3 ), null );
	assert.equal( nextPage( emptyFilters(), 1 ), null );
	assert.equal( nextPage( { ...filters, page: 50 }, 80 ), null, 'capped at MAX_PAGE' );
} );

test( 'typing refines one history entry, everything else pushes', () => {
	assert.equal( historyMode( '', 'search' ), 'push' );
	assert.equal( historyMode( 'search', 'search' ), 'replace' );
	assert.equal( historyMode( 'search', 'filter' ), 'push' );
	assert.equal( historyMode( 'filter', 'more' ), 'push' );
} );

test( 'the layout-3 popup opens right of the card, or left when it would overflow', () => {
	const parent = { left: 40, top: 100 };
	assert.deepEqual( popupPosition( { left: 100, top: 300, width: 200, height: 120 }, parent, 300, 1200 ), {
		left: 280,
		top: 260,
		toLeft: false,
	} );
	assert.deepEqual( popupPosition( { left: 900, top: 300, width: 200, height: 120 }, parent, 300, 1200 ), {
		left: 540,
		top: 260,
		toLeft: true,
	} );
} );

// ---- Store factory -----------------------------------------------------------------------------

function fakeDom( { search = '', boxes = [], sort = 'date' } = {} ) {
	const listeners = [];
	const make = ( props ) => ( {
		...props,
		dataset: props.dataset || {},
		focus: props.focus || ( () => {} ),
		closest: props.closest || ( () => null ),
		querySelector: () => null,
		querySelectorAll: () => [],
	} );
	const form = make( { id: 'ohmylms-catalog-form', tagName: 'FORM' } );
	const fields = [
		make( { name: 'course_search', type: 'search', value: search, form } ),
		...boxes.map( ( [ name, value, checked ] ) =>
			make( { name, type: 'checkbox', value, checked, form } )
		),
		make( { name: 'course_sort', type: 'select-one', value: sort, form } ),
	];
	form.elements = fields;
	const heading = make( { id: 'ohmylms-catalog-heading', focused: 0 } );
	heading.focus = () => heading.focused++;
	const doc = {
		getElementById: ( id ) => ( { [ form.id ]: form, [ heading.id ]: heading }[ id ] || null ),
		querySelectorAll: () => [],
	};
	const root = make( {
		ownerDocument: doc,
		dispatchEvent: ( event ) => {
			listeners.push( event );
			return true;
		},
	} );
	root.closest = ( selector ) => ( '.ohmylms-catalog' === selector ? root : null );
	for ( const node of [ form, heading, ...fields ] ) {
		node.ownerDocument = doc;
	}
	return { root, form, fields, heading, listeners };
}

function harness( dom, { href = BASE, filters = emptyFilters(), ref = dom.root } = {} ) {
	const state = { loading: false, filters };
	const context = { form: 'ohmylms-catalog-form', heading: 'ohmylms-catalog-heading' };
	const win = { location: { href }, innerWidth: 1200, document: dom.root.ownerDocument };
	const navigations = [];
	const pending = [];
	const catalog = createCatalog( {
		getContext: () => context,
		getElement: () => ( { ref } ),
		withSyncEvent: ( callback ) => callback,
		getState: () => state,
		emit: ( target, name, detail, cancelable ) =>
			target.dispatchEvent( { type: `ohmylms:${ name }`, detail, cancelable } ),
		serializeForm: ( form ) => {
			const params = new URLSearchParams();
			for ( const field of form.elements ) {
				if ( 'checkbox' !== field.type || field.checked ) {
					params.append( field.name, field.value );
				}
			}
			return params;
		},
		debounce: ( callback ) => {
			const run = ( ...args ) => pending.push( () => callback( ...args ) );
			run.flush = () => pending.splice( 0 ).forEach( ( fn ) => fn() );
			run.cancel = () => pending.splice( 0 );
			return run;
		},
		navigate: async ( url, options ) => {
			navigations.push( { url, ...options, loading: state.loading } );
			win.location.href = url;
			return true;
		},
		frame: ( callback ) => callback(),
		timers: { setTimeout: ( callback ) => callback(), clearTimeout: () => {} },
		win,
	} );
	return { catalog, state, navigations, pending, win };
}

const tick = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

test( 'a checkbox change navigates to the filtered URL with the loading flag', async () => {
	const dom = fakeDom( { boxes: [ [ 'course_level[]', 'beginner', true ], [ 'course_price[]', 'free', false ] ] } );
	const { catalog, state, navigations } = harness( dom );
	catalog.actions.change( { target: dom.fields[ 1 ] } );
	await tick();
	assert.equal( navigations.length, 1 );
	assert.equal( navigations[ 0 ].url, `${ BASE }?course_level%5B%5D=beginner` );
	assert.equal( navigations[ 0 ].replace, false );
	assert.equal( navigations[ 0 ].loading, true, 'loading is set while the router fetches' );
	assert.equal( state.loading, false );
	assert.deepEqual( state.filters.level, [ 'beginner' ] );
	assert.deepEqual(
		dom.listeners.map( ( event ) => event.type ),
		[ 'ohmylms:catalog-before-navigate', 'ohmylms:catalog-navigated' ]
	);
	assert.equal( dom.heading.focused, 0, 'focus stays on the checkbox' );
} );

test( 'typing is debounced and refines one history entry; submit moves focus to the results', async () => {
	const dom = fakeDom( { search: 'alg' } );
	const { catalog, navigations, pending } = harness( dom );
	catalog.actions.input( { target: dom.fields[ 0 ] } );
	catalog.actions.input( { target: dom.fields[ 0 ] } );
	assert.equal( navigations.length, 0, 'nothing happens until the debounce fires' );
	pending.splice( 0 ).at( -1 )();
	await tick();
	dom.fields[ 0 ].value = 'algebra';
	catalog.actions.input( { target: dom.fields[ 0 ] } );
	pending.splice( 0 ).at( -1 )();
	await tick();
	assert.deepEqual(
		navigations.map( ( { url, replace } ) => [ url, replace ] ),
		[
			[ `${ BASE }?course_search=alg`, false ],
			[ `${ BASE }?course_search=algebra`, true ],
		]
	);
	let prevented = false;
	dom.fields[ 0 ].value = 'algebra 2';
	catalog.actions.submit( { target: dom.form, preventDefault: () => ( prevented = true ) } );
	await tick();
	assert.ok( prevented );
	assert.equal( navigations.at( -1 ).url, `${ BASE }?course_search=algebra+2` );
	assert.equal( dom.heading.focused, 1 );
} );

test( 'clear unticks the boxes, keeps the search, and the same URL is not fetched twice', async () => {
	const dom = fakeDom( { search: 'a', boxes: [ [ 'course_price[]', 'free', true ] ] } );
	const { catalog, navigations } = harness( dom, { href: `${ BASE }?course_search=a&course_price%5B%5D=free` } );
	catalog.actions.clear( { preventDefault() {} } );
	await tick();
	assert.equal( dom.fields[ 1 ].checked, false );
	assert.equal( navigations[ 0 ].url, `${ BASE }?course_search=a` );
	catalog.actions.change( { target: dom.fields[ 2 ] } );
	await tick();
	assert.equal( navigations.length, 1, 'sort "date" is the URL already shown' );
} );

test( 'load more asks for the next page and stops at the last one', async () => {
	const dom = fakeDom();
	const button = {
		dataset: { paged: '1', total: '2' },
		closest: () => dom.root,
		ownerDocument: dom.root.ownerDocument,
	};
	const { catalog, navigations } = harness( dom, { ref: button } );
	catalog.actions.loadMore( { preventDefault() {} } );
	await tick();
	assert.equal( navigations[ 0 ].url, `${ BASE }?paged=2` );
	assert.equal( navigations[ 0 ].replace, false, 'Back returns to fewer cards' );
	button.dataset.paged = '2';
	catalog.actions.loadMore( { preventDefault() {} } );
	await tick();
	assert.equal( navigations.length, 1 );
} );

test( 'Back/Forward syncs the controls and focuses the heading without scrolling', () => {
	const dom = fakeDom( { search: 'x', boxes: [ [ 'course_level[]', 'expert', true ] ] } );
	const { catalog, state, win } = harness( dom, { filters: { ...emptyFilters(), search: 'x', level: [ 'expert' ] } } );
	win.location.href = `${ BASE }?course_level%5B%5D=beginner&course_sort=rating`;
	catalog.actions.popstate();
	assert.deepEqual( state.filters.level, [ 'beginner' ] );
	assert.equal( dom.fields[ 0 ].value, '' );
	assert.equal( dom.fields[ 1 ].checked, false );
	assert.equal( dom.fields[ 2 ].value, 'rating' );
	assert.equal( dom.heading.focused, 1 );
} );

test( 'a cancelled before-navigate event keeps the page', async () => {
	const dom = fakeDom( { boxes: [ [ 'course_price[]', 'paid', true ] ] } );
	dom.root.dispatchEvent = () => false;
	const { catalog, navigations, state } = harness( dom );
	catalog.actions.change( { target: dom.fields[ 1 ] } );
	await tick();
	assert.equal( navigations.length, 0 );
	assert.deepEqual( state.filters, emptyFilters() );
} );

test( 'curriculum buttons are active from state', () => {
	const contexts = [ { slug: 'all' }, { slug: 'c27' } ];
	let current = contexts[ 0 ];
	const state = { filters: { ...emptyFilters(), curriculum: [ 'c27' ] } };
	const catalog = createCatalog( {
		getContext: () => current,
		getElement: () => ( { ref: null } ),
		withSyncEvent: ( callback ) => callback,
		getState: () => state,
		emit: () => true,
		debounce: ( callback ) => callback,
	} );
	Object.defineProperty( state, 'categoryActive', {
		get: () => Object.getOwnPropertyDescriptor( catalog.state, 'categoryActive' ).get(),
	} );
	assert.equal( state.categoryActive, false );
	current = contexts[ 1 ];
	assert.equal( state.categoryActive, true );
	assert.equal( Object.getOwnPropertyDescriptor( catalog.state, 'categoryCurrent' ).get(), 'true' );
} );

test( 'the store module wires the factory to the router and the SDK', () => {
	const source = fs.readFileSync( new URL( '../../assets/interactivity/catalog.js', import.meta.url ), 'utf8' );
	assert.match( source, /from 'ohmylms\/interactivity'/, 'SDK by its bare specifier' );
	assert.match( source, /import\( '@wordpress\/interactivity-router' \)/, 'router loaded on demand' );
	assert.match( source, /store\( 'ohmylms\/catalog'/ );
	assert.ok( ! /jQuery|\$\(/.test( source ), 'no jQuery' );
	assert.deepEqual( Object.values( PARAMS ).sort(), [ 'course_bundle', 'course_curriculum', 'course_level', 'course_price', 'course_search', 'course_sort', 'paged' ] );
} );
