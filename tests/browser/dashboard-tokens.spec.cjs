/**
 * Student dashboard: the student-chosen themes (meadow, ocean, sunset) keep their light look, and the
 * hub's dark theme retones every theme through the shared tokens while keeping each theme's accent.
 *
 * Needs PHP (OHMYLMS_PHP when not on PATH) and a browser (OHMYLMS_CHROMIUM_PATH when needed).
 */
const { test, expect } = require( '@playwright/test' );
const { spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const root = path.resolve( __dirname, '../..' );
const php = process.env.OHMYLMS_PHP || 'php';

let tokens = '';
test.beforeAll( () => {
	const run = spawnSync( php, [ path.join( __dirname, 'fixtures/learner-tokens.php' ) ], {
		encoding: 'utf8',
	} );
	test.skip( run.error || 0 !== run.status, 'php is not available' );
	tokens = run.stdout;
} );

const markup = ( mode, theme ) =>
	'<style>' + tokens + fs.readFileSync( path.join( root, 'assets/css/student-dashboard.css' ), 'utf8' ) + '</style>' +
	`<body data-theme="${ mode }"><div class="oml-student-dashboard" data-dashboard-theme="${ theme }">` +
	'<article class="oml-dashboard-card" id="card">Card</article>' +
	'<a class="oml-dashboard-button" href="#" id="btn">Go</a>' +
	'<span class="oml-xp-met" id="met">Goal met</span></div></body>';

const read = ( page, selector, prop ) =>
	page.evaluate(
		( [ s, p ] ) => getComputedStyle( document.querySelector( s ) )[ p ],
		[ selector, prop ]
	);

const probe = ( page, name ) =>
	page.evaluate( ( n ) => {
		const el = document.createElement( 'i' );
		el.style.color = `var(${ n })`;
		document.querySelector( '.oml-student-dashboard' ).appendChild( el );
		const value = getComputedStyle( el ).color;
		el.remove();
		return value;
	}, name );

test( 'light themes keep their palette', async ( { page } ) => {
	await page.emulateMedia( { colorScheme: 'dark' } );
	await page.setContent( markup( 'light', 'ocean' ) );
	expect( await read( page, '.oml-student-dashboard', 'backgroundColor' ) ).toBe( 'rgb(226, 242, 250)' );
	expect( await read( page, '#card', 'backgroundColor' ) ).toBe( 'rgb(255, 255, 255)' );
	expect( await read( page, '#btn', 'backgroundColor' ) ).toBe( 'rgb(37, 106, 161)' );
	expect( await read( page, '#btn', 'color' ) ).toBe( 'rgb(255, 255, 255)' );
} );

test( 'dark retones the dashboard through the tokens', async ( { page } ) => {
	await page.setContent( markup( 'dark', 'ocean' ) );
	expect( await read( page, '#card', 'backgroundColor' ) ).toBe( await probe( page, '--oml-surface' ) );
	expect( await read( page, '.oml-student-dashboard', 'color' ) ).toBe( await probe( page, '--oml-heading' ) );
	expect( await read( page, '.oml-student-dashboard', 'backgroundColor' ) ).not.toBe( 'rgb(226, 242, 250)' );
	// Dark text on the lightened accent.
	expect( await read( page, '#btn', 'color' ) ).toBe( await probe( page, '--oml-surface' ) );
} );

test( 'dark keeps each theme its own accent', async ( { page } ) => {
	await page.setContent( markup( 'dark', 'ocean' ) );
	const ocean = await read( page, '#btn', 'backgroundColor' );
	await page.setContent( markup( 'dark', 'sunset' ) );
	const sunset = await read( page, '#btn', 'backgroundColor' );
	expect( ocean ).not.toBe( sunset );
} );

test( 'system follows the device preference', async ( { page } ) => {
	await page.emulateMedia( { colorScheme: 'light' } );
	await page.setContent( markup( 'system', 'meadow' ) );
	expect( await read( page, '#card', 'backgroundColor' ) ).toBe( 'rgb(255, 255, 255)' );
	await page.emulateMedia( { colorScheme: 'dark' } );
	expect( await read( page, '#card', 'backgroundColor' ) ).toBe( await probe( page, '--oml-surface' ) );
} );

test( 'status colors come from the tokens', async ( { page } ) => {
	await page.setContent( markup( 'light', 'meadow' ) );
	expect( await read( page, '#met', 'color' ) ).toBe( await probe( page, '--oml-success' ) );
} );
