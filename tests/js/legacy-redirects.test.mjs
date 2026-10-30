import fs from 'node:fs';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import test from 'node:test';

const require = createRequire( import.meta.url );
const {
	resolve,
	redirectUrl,
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
		hash: '#/quiz-edit/5',
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
