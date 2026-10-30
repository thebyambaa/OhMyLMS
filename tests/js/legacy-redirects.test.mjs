import fs from 'node:fs';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import test from 'node:test';

const require = createRequire( import.meta.url );
const {
	resolve,
	redirectUrl,
	landingUrl,
	showUnavailable,
	start,
} = require( '../../assets/js/legacy-redirects.js' );
// Rules and cases shared with tests/php/legacy-routes-integration.php, which checks that `routes` is
// exactly LegacyRoutes::routes().
const fixture = JSON.parse(
	fs.readFileSync(
		new URL( '../fixtures/legacy-routes.json', import.meta.url ),
		'utf8'
	)
);
const config = {
	appPage: 'ohmylms-app',
	optOut: 'ohmylms_legacy',
	routes: fixture.routes,
};

test( 'the fixture holds the PHP route map', () => {
	assert.ok( fixture.routes.length > 40 );
	assert.ok( fixture.cases.length > 80 );
} );

test( 'resolves every shared case like LegacyRoutes::resolve()', () => {
	for ( const [ legacy, expected ] of fixture.cases ) {
		assert.equal( resolve( fixture.routes, legacy ), expected, legacy );
	}
} );

test( 'redirectUrl keeps other query parameters and opens the new page', () => {
	assert.equal(
		redirectUrl( config, {
			pathname: '/wp-admin/admin.php',
			search: '?page=ohmylms&xapi_notice=saved',
			hash: '#/settings/xapi-settings',
		} ),
		'/wp-admin/admin.php?page=ohmylms-app&xapi_notice=saved#/settings?tab=xapi'
	);
	assert.equal(
		redirectUrl( config, {
			pathname: '/wp-admin/admin.php',
			search: '?page=ohmylms',
			hash: '',
		} ),
		'/wp-admin/admin.php?page=ohmylms-app#/dashboard'
	);
} );

test( 'redirectUrl stays on unmapped screens and when opted out', () => {
	const location = {
		pathname: '/wp-admin/admin.php',
		search: '?page=ohmylms',
		hash: '#/content-hub/exams',
	};
	assert.equal( redirectUrl( config, location ), null );
	assert.equal(
		redirectUrl( config, {
			...location,
			search: '?page=ohmylms&ohmylms_legacy=1',
			hash: '#/orders',
		} ),
		null
	);
	assert.equal( redirectUrl( null, location ), null );
} );

test( 'start redirects on load and after the legacy router changes the hash', () => {
	const listeners = {};
	const replaced = [];
	const win = {
		ohmylmsLegacyRoutes: config,
		location: {
			pathname: '/wp-admin/admin.php',
			search: '?page=ohmylms',
			hash: '#/content-hub',
			replace: ( url ) => replaced.push( url ),
		},
		history: {
			pushState( state, title, url ) {
				win.location.hash = url;
			},
			replaceState() {},
		},
		addEventListener: ( name, callback ) => {
			listeners[ name ] = callback;
		},
	};
	start( win );
	assert.deepEqual( replaced, [], 'Content Hub stays legacy' );
	win.history.pushState( null, '', '#/memberships/orders' );
	assert.deepEqual( replaced, [
		'/wp-admin/admin.php?page=ohmylms-app#/orders',
	] );
	win.location.hash = '#/order-edit/12';
	listeners.hashchange();
	assert.equal(
		replaced[ 1 ],
		'/wp-admin/admin.php?page=ohmylms-app#/orders/12'
	);
	assert.equal( typeof listeners.popstate, 'function' );
} );

test( 'start does nothing without the localized map', () => {
	assert.doesNotThrow( () => start( null ) );
	assert.doesNotThrow( () => start( {} ) );
} );

// After the cutover the server sends admin.php?page=ohmylms to the new app with the marker, and the
// browser carries the legacy hash over (LegacyRoutes::legacy_redirect_url()).
const landing = {
	...config,
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
	assert.deepEqual( landingUrl( landing, arrived( '#/content-hub/exams' ) ), {
		url: '/wp-admin/admin.php?page=ohmylms-app#/',
		unavailable: '/content-hub/exams',
	} );
	assert.equal(
		landingUrl(
			landing,
			arrived( '#/content-hub/question-bank?action=new' )
		).unavailable,
		'/content-hub/question-bank?action=new'
	);
} );

test( 'landingUrl ignores pages not opened by the cutover redirect', () => {
	assert.equal(
		landingUrl( landing, {
			pathname: '/wp-admin/admin.php',
			search: '?page=ohmylms-app',
			hash: '#/orders',
		} ),
		null
	);
	assert.equal( landingUrl( config, arrived( '#/orders' ) ), null );
} );

/**
 * Minimal document with the notice AppPage prints.
 *
 * @param {string} readyState Document ready state.
 */
function fakeDocument( readyState ) {
	const code = { textContent: '' };
	const link = { hash: '' };
	const notice = {
		hidden: true,
		querySelector: ( selector ) =>
			'[data-ohmylms-legacy-route]' === selector ? code : link,
	};
	const listeners = {};
	return {
		code,
		link,
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

test( 'showUnavailable fills in the route and the legacy link', () => {
	const doc = fakeDocument( 'complete' );
	showUnavailable( doc, 'ohmylms-legacy-unavailable', '/communities' );
	assert.equal( doc.notice.hidden, false );
	assert.equal( doc.code.textContent, '/communities' );
	assert.equal( doc.link.hash, '/communities' );
	assert.doesNotThrow( () => showUnavailable( doc, 'missing', '/x' ) );
} );

test( 'start lands once with replaceState and shows the notice after load', () => {
	const doc = fakeDocument( 'loading' );
	const replaced = [];
	const win = {
		ohmylmsLegacyRoutes: landing,
		document: doc,
		location: { ...arrived( '#/communities' ), replace: assert.fail },
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
	assert.equal( doc.code.textContent, '/communities' );

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
} );
