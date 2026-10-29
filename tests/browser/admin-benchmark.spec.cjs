const { test, expect } = require( '@playwright/test' );
const path = require( 'node:path' );

test( 'dashboard diagnostics distinguish DNS and server wait and omit query parameters', async ( {
	page,
} ) => {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.route( 'https://benchmark.test/**', ( route ) => {
		const url = new URL( route.request().url() );
		if ( url.pathname === '/' )
			return route.fulfill( {
				contentType: 'text/html; charset=utf-8',
				body: '<!doctype html><html><head><meta charset="utf-8"><script src="/probe.js?sample=private-value"></script></head><body><main id="wpbody-content"><h1>Overview</h1></main></body></html>',
			} );
		return route.fulfill( {
			contentType: 'application/javascript; charset=utf-8',
			path: path.resolve(
				__dirname,
				'../../assets/js/admin-benchmark.js'
			),
		} );
	} );
	await page.goto( 'https://benchmark.test/' );
	const output = page.locator( '#ohmylms-benchmark-results' );
	await expect
		.poll( () => output.textContent(), { timeout: 10000 } )
		.not.toBe( '' );
	const sample = JSON.parse( await output.textContent() );
	const expected = await page.evaluate( () => {
		const navigation = performance.getEntriesByType( 'navigation' )[ 0 ];
		const rounded = ( value ) => Math.round( value * 10 ) / 10;
		return {
			ttfbMs: rounded(
				navigation.responseStart - navigation.requestStart
			),
			responseStartMs: rounded( navigation.responseStart ),
			dnsMs: rounded(
				navigation.domainLookupEnd - navigation.domainLookupStart
			),
			connectMs: rounded(
				navigation.connectEnd - navigation.connectStart
			),
		};
	} );
	expect( sample ).toMatchObject( expected );
	expect( sample.dashboardHeadingMs ).toBeGreaterThan( 0 );
	expect( sample.loadMs ).toBeGreaterThan( 0 );
	expect( sample.scripts.map( ( script ) => script.path ) ).toEqual( [
		'/probe.js',
	] );
	expect( JSON.stringify( sample ) ).not.toContain( 'private-value' );
	expect( errors ).toEqual( [] );
} );
