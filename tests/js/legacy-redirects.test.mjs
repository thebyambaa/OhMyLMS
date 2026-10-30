import fs from 'node:fs';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import test from 'node:test';

const require = createRequire( import.meta.url );
const legacyRedirects = require( '../../assets/js/legacy-redirects.js' );
const { resolve, landingUrl, showUnavailable, start } = legacyRedirects;
// Rules and cases shared with tests/php/legacy-routes-integration.php, which checks that `routes` is
// exactly LegacyRoutes::routes().
const fixture = JSON.parse(
	fs.readFileSync(
		new URL( '../fixtures/legacy-routes.json', import.meta.url ),
		'utf8'
	)
);

test( 'the fixture holds the PHP route map', () => {
	assert.ok( fixture.routes.length > 40 );
	assert.ok( fixture.cases.length > 80 );
	assert.ok( fixture.addresses.length > 30 );
} );

// The address each legacy route ends on is checked in PHP (LegacyRoutes::url()) and, through the app's
// address format, in assets/src/app/routing/navigation-contract.test.ts; here only the first hop.
test( 'every legacy route of the address cases has an app route or none, as listed', () => {
	for ( const [ legacy, address ] of fixture.addresses ) {
		assert.equal(
			null === resolve( fixture.routes, legacy || '/' ),
			null === address,
			legacy
		);
	}
} );

test( 'resolves every shared case like LegacyRoutes::resolve()', () => {
	for ( const [ legacy, expected ] of fixture.cases ) {
		assert.equal( resolve( fixture.routes, legacy ), expected, legacy );
	}
} );

test( 'the legacy-page redirect mode is gone', () => {
	assert.deepEqual( Object.keys( legacyRedirects ).sort(), [
		'landingUrl',
		'resolve',
		'showUnavailable',
		'start',
	] );
} );

test( 'start does nothing without the localized map', () => {
	assert.doesNotThrow( () => start( null ) );
	assert.doesNotThrow( () => start( {} ) );
} );

// Old addresses arrive on the Dashboard (admin.php?page=ohmylms): legacy bookmarks directly, the
// retired app page through LegacyRoutes::redirect_app_page() with `ohmylms_from_app=1`, older unmapped
// links with `ohmylms_from_legacy=1`; the browser keeps the hash. LegacyRoutes::script_data() is the
// config. The app's router then opens the app route in the hash at its address.
const landing = {
	routes: fixture.routes,
	landing: {
		param: 'ohmylms_from_legacy',
		appParam: 'ohmylms_from_app',
		home: '/',
		noticeId: 'ohmylms-legacy-unavailable',
	},
};
const arrived = ( hash, search = '' ) => ( {
	pathname: '/wp-admin/admin.php',
	search: '?page=ohmylms' + search,
	hash,
} );

test( 'landingUrl maps a legacy hash on the Dashboard to its app route', () => {
	assert.deepEqual( landingUrl( landing, arrived( '#/order-edit/12' ) ), {
		url: '/wp-admin/admin.php?page=ohmylms#/orders/12',
		unavailable: null,
	} );
	assert.deepEqual(
		landingUrl(
			landing,
			arrived(
				'#/settings/xapi-settings',
				'&ohmylms_from_legacy=1&xapi_notice=saved'
			)
		),
		{
			url: '/wp-admin/admin.php?page=ohmylms&xapi_notice=saved#/settings?tab=xapi',
			unavailable: null,
		},
		'older links with the legacy marker'
	);
	assert.equal(
		landingUrl( landing, arrived( '', '&ohmylms_from_legacy=1' ) ).url,
		'/wp-admin/admin.php?page=ohmylms#/dashboard',
		'the bare legacy page opens the dashboard'
	);
} );

test( 'landingUrl keeps the app route the retired app page carried over', () => {
	assert.deepEqual(
		landingUrl( landing, arrived( '#/orders/12', '&ohmylms_from_app=1' ) ),
		{
			url: '/wp-admin/admin.php?page=ohmylms#/orders/12',
			unavailable: null,
		},
		'an app route is not read as a legacy one (legacy /orders/12 is page 12 of the orders)'
	);
	assert.deepEqual(
		landingUrl( landing, arrived( '', '&ohmylms_from_app=1' ) ),
		{ url: '/wp-admin/admin.php?page=ohmylms', unavailable: null },
		'the bare app page is the Dashboard'
	);
	assert.equal(
		landingUrl(
			landing,
			arrived(
				'#/extensions/unknown-addon-page',
				'&ohmylms_from_legacy=1&ohmylms_from_app=1'
			)
		).unavailable,
		'/extensions/unknown-addon-page',
		'the legacy marker wins'
	);
} );

test( 'landingUrl sends unmapped legacy routes to the Dashboard and names them', () => {
	assert.deepEqual(
		landingUrl( landing, arrived( '#/extensions/unknown-addon-page' ) ),
		{
			url: '/wp-admin/admin.php?page=ohmylms',
			unavailable: '/extensions/unknown-addon-page',
		}
	);
	assert.equal(
		landingUrl( landing, arrived( '#/quizzes?action=bulk' ) ).unavailable,
		'/quizzes?action=bulk'
	);
} );

test( 'landingUrl ignores visits without a route or a marker', () => {
	assert.equal( landingUrl( landing, arrived( '' ) ), null );
	assert.equal( landingUrl( landing, arrived( '#wpbody' ) ), null );
	assert.equal(
		landingUrl( { routes: fixture.routes }, arrived( '#/orders' ) ),
		null
	);
} );

/**
 * Minimal document with the notice AppPage prints.
 *
 * @param {string} readyState Document ready state.
 */
function fakeDocument( readyState ) {
	const code = { textContent: '' };
	const notice = {
		hidden: true,
		querySelector: ( selector ) =>
			'[data-ohmylms-legacy-route]' === selector ? code : null,
	};
	const listeners = {};
	return {
		code,
		notice,
		listeners,
		readyState,
		getElementById: ( id ) =>
			'ohmylms-legacy-unavailable' === id ? notice : null,
		addEventListener: ( name, callback ) => {
			listeners[ name ] = callback;
		},
	};
}

test( 'showUnavailable fills in the route', () => {
	const doc = fakeDocument( 'complete' );
	showUnavailable(
		doc,
		'ohmylms-legacy-unavailable',
		'/extensions/unknown-addon-page'
	);
	assert.equal( doc.notice.hidden, false );
	assert.equal( doc.code.textContent, '/extensions/unknown-addon-page' );
	assert.doesNotThrow( () => showUnavailable( doc, 'missing', '/x' ) );
} );

test( 'start lands once with replaceState and shows the notice after load', () => {
	const doc = fakeDocument( 'loading' );
	const replaced = [];
	const win = {
		ohmylmsLegacyRoutes: landing,
		document: doc,
		location: {
			...arrived( '#/extensions/unknown-addon-page' ),
			replace: assert.fail,
		},
		history: {
			state: null,
			replaceState: ( state, title, url ) => replaced.push( url ),
			pushState: assert.fail,
		},
		addEventListener: ( name ) =>
			assert.equal( name, 'hashchange', 'only listens for hash changes' ),
	};
	start( win );
	assert.deepEqual( replaced, [ '/wp-admin/admin.php?page=ohmylms' ] );
	assert.equal( doc.notice.hidden, true, 'waits for the notice markup' );
	doc.listeners.DOMContentLoaded();
	assert.equal( doc.notice.hidden, false );
	assert.equal( doc.code.textContent, '/extensions/unknown-addon-page' );

	const mapped = fakeDocument( 'complete' );
	replaced.length = 0;
	start( {
		...win,
		document: mapped,
		location: { ...arrived( '#/memberships' ), replace: assert.fail },
	} );
	assert.deepEqual( replaced, [
		'/wp-admin/admin.php?page=ohmylms#/memberships',
	] );
	assert.equal( mapped.notice.hidden, true );

	replaced.length = 0;
	start( {
		...win,
		location: {
			pathname: '/wp-admin/admin.php',
			search: '?page=ohmylms',
			hash: '',
			replace: assert.fail,
		},
	} );
	assert.deepEqual(
		replaced,
		[],
		'a normal Dashboard visit keeps its address'
	);

	// A legacy link clicked on the Dashboard only changes the hash.
	replaced.length = 0;
	const location = {
		pathname: '/wp-admin/admin.php',
		search: '?page=ohmylms',
		hash: '',
		replace: assert.fail,
	};
	const listeners = {};
	start( {
		...win,
		document: fakeDocument( 'complete' ),
		location,
		addEventListener: ( name, callback ) => {
			listeners[ name ] = callback;
		},
	} );
	location.hash = '#/order-edit/12';
	listeners.hashchange();
	assert.deepEqual( replaced, [
		'/wp-admin/admin.php?page=ohmylms#/orders/12',
	] );
} );
