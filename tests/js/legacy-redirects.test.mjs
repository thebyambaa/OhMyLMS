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

// The server sends admin.php?page=ohmylms to the new app with the marker, and the browser carries the
// legacy hash over (LegacyRoutes::legacy_redirect_url()); LegacyRoutes::url() builds the same address
// for unmapped routes. LegacyRoutes::script_data() is the config.
const landing = {
	routes: fixture.routes,
	landing: {
		param: 'ohmylms_from_legacy',
		home: '/',
		noticeId: 'ohmylms-legacy-unavailable',
	},
};
const arrived = ( hash, search = '' ) => ( {
	pathname: '/wp-admin/admin.php',
	search: '?page=ohmylms-app&ohmylms_from_legacy=1' + search,
	hash,
} );

test( 'landingUrl maps a carried-over legacy hash and drops the marker', () => {
	assert.deepEqual( landingUrl( landing, arrived( '#/order-edit/12' ) ), {
		url: '/wp-admin/admin.php?page=ohmylms-app#/orders/12',
		unavailable: null,
	} );
	assert.deepEqual(
		landingUrl(
			landing,
			arrived( '#/settings/xapi-settings', '&xapi_notice=saved' )
		),
		{
			url: '/wp-admin/admin.php?page=ohmylms-app&xapi_notice=saved#/settings?tab=xapi',
			unavailable: null,
		}
	);
	assert.equal(
		landingUrl( landing, arrived( '' ) ).url,
		'/wp-admin/admin.php?page=ohmylms-app#/dashboard',
		'the bare legacy page opens the dashboard'
	);
} );

test( 'landingUrl sends unmapped legacy routes home and names them', () => {
	assert.deepEqual(
		landingUrl( landing, arrived( '#/extensions/unknown-addon-page' ) ),
		{
			url: '/wp-admin/admin.php?page=ohmylms-app#/',
			unavailable: '/extensions/unknown-addon-page',
		}
	);
	assert.equal(
		landingUrl( landing, arrived( '#/quizzes?action=bulk' ) ).unavailable,
		'/quizzes?action=bulk'
	);
} );

test( 'landingUrl ignores pages not opened from a legacy address', () => {
	assert.equal(
		landingUrl( landing, {
			pathname: '/wp-admin/admin.php',
			search: '?page=ohmylms-app',
			hash: '#/orders',
		} ),
		null
	);
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
		addEventListener: assert.fail,
	};
	start( win );
	assert.deepEqual( replaced, [ '/wp-admin/admin.php?page=ohmylms-app#/' ] );
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
		'/wp-admin/admin.php?page=ohmylms-app#/memberships',
	] );
	assert.equal( mapped.notice.hidden, true );

	replaced.length = 0;
	start( {
		...win,
		location: {
			pathname: '/wp-admin/admin.php',
			search: '?page=ohmylms-app',
			hash: '#/orders',
			replace: assert.fail,
		},
	} );
	assert.deepEqual( replaced, [], 'a normal app visit keeps its address' );
} );
