/**
 * Student hub: the shell follows the --oml-* tokens and data-theme (light, dark, system), while the hub's
 * own accent setting (an inline --hub-accent) still drives its buttons and tints.
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

const markup = ( theme ) =>
	'<style>' + tokens + fs.readFileSync( path.join( root, 'assets/css/student-hub.css' ), 'utf8' ) + '</style>' +
	'<body class="ohmylms-page">' +
	`<section class="ohmylms-student-hub" data-theme="${ theme }" style="--hub-accent:#4361ee">` +
	'<a class="oml-hub-button" href="#" id="cta">Go</a><main id="main">Hub</main></section></body>';

const read = ( page, selector, prop ) =>
	page.evaluate(
		( [ s, p ] ) => getComputedStyle( document.querySelector( s ) )[ p ],
		[ selector, prop ]
	);

test( 'light hub uses the light tokens', async ( { page } ) => {
	await page.emulateMedia( { colorScheme: 'dark' } );
	await page.setContent( markup( 'light' ) );
	expect( await read( page, '.ohmylms-student-hub', 'backgroundColor' ) ).toBe( 'rgb(240, 240, 241)' );
	expect( await read( page, '.ohmylms-student-hub', 'color' ) ).toBe( 'rgb(0, 13, 37)' );
} );

test( 'dark hub flips through the tokens', async ( { page } ) => {
	await page.setContent( markup( 'dark' ) );
	expect( await read( page, '.ohmylms-student-hub', 'backgroundColor' ) ).toBe( 'rgb(37, 37, 49)' );
	expect( await read( page, '.ohmylms-student-hub', 'color' ) ).toBe( 'rgb(243, 243, 247)' );
} );

test( 'system hub follows the device preference', async ( { page } ) => {
	await page.emulateMedia( { colorScheme: 'light' } );
	await page.setContent( markup( 'system' ) );
	expect( await read( page, '.ohmylms-student-hub', 'backgroundColor' ) ).toBe( 'rgb(240, 240, 241)' );
	await page.emulateMedia( { colorScheme: 'dark' } );
	expect( await read( page, '.ohmylms-student-hub', 'backgroundColor' ) ).toBe( 'rgb(37, 37, 49)' );
} );

test( 'the hub accent setting still drives the button', async ( { page } ) => {
	await page.setContent( markup( 'light' ) );
	expect( await read( page, '#cta', 'backgroundColor' ) ).toBe( 'rgb(67, 97, 238)' );
} );

const bare = ( theme, style = '' ) =>
	markup( theme ).replace( ' style="--hub-accent:#4361ee"', style ? ` style="${ style }"` : '' );

test( 'without a saved accent the hub follows the brand token and its readable text color', async ( { page } ) => {
	await page.setContent( bare( 'light' ) );
	const token = ( name ) =>
		page.evaluate( ( n ) => {
			const probe = document.createElement( 'i' );
			probe.style.color = `var(${ n })`;
			// Inside the hub, where its theme's tokens apply.
			document.querySelector( '.ohmylms-student-hub' ).appendChild( probe );
			const value = getComputedStyle( probe ).color;
			probe.remove();
			return value;
		}, name );
	expect( await read( page, '#cta', 'backgroundColor' ) ).toBe( await token( '--oml-primary' ) );
	expect( await read( page, '#cta', 'color' ) ).toBe( await token( '--oml-on-primary' ) );
	await page.setContent( bare( 'dark' ) );
	// In dark mode the accent is the lightened brand color, with the text color chosen to read on it.
	expect( await read( page, '#cta', 'backgroundColor' ) ).toBe( await token( '--oml-primary' ) );
	expect( await read( page, '#cta', 'backgroundColor' ) ).not.toBe( 'rgb(110, 66, 211)' );
	expect( await read( page, '#cta', 'color' ) ).toBe( await token( '--oml-on-primary' ) );
} );

test( 'a custom accent brings its own readable text color', async ( { page } ) => {
	await page.setContent( bare( 'light', '--hub-accent-light:#ffd400;--hub-on-accent-light:#000000' ) );
	expect( await read( page, '#cta', 'backgroundColor' ) ).toBe( 'rgb(255, 212, 0)' );
	expect( await read( page, '#cta', 'color' ) ).toBe( 'rgb(0, 0, 0)' );
} );

test( 'browsers without color-mix keep a visible selected color and button edge', async ( { page } ) => {
	// Chrome supports color-mix, so check the fallback rule itself is present and resolves.
	const css = fs.readFileSync( path.join( root, 'assets/css/student-hub.css' ), 'utf8' );
	expect( css ).toContain( '@supports not (color: color-mix(in srgb, red, blue))' );
	await page.setContent( markup( 'light' ) );
	const fallback = await page.evaluate( () => {
		const probe = document.createElement( 'i' );
		probe.style.cssText = 'background:var(--oml-primary-soft);box-shadow:0 4px 0 var(--oml-primary-active)';
		document.querySelector( '.ohmylms-student-hub' ).appendChild( probe );
		const cs = getComputedStyle( probe );
		return { bg: cs.backgroundColor, shadow: cs.boxShadow };
	} );
	expect( fallback.bg ).not.toBe( 'rgba(0, 0, 0, 0)' );
	expect( fallback.shadow ).not.toBe( 'none' );
} );

test( 'a custom accent has its own dark-theme value and text color', async ( { page } ) => {
	const style =
		'--hub-accent-light:#1a237e;--hub-on-accent-light:#ffffff;--hub-accent-dark:#8c9eff;--hub-on-accent-dark:#000000';
	await page.setContent( bare( 'light', style ) );
	expect( await read( page, '#cta', 'backgroundColor' ) ).toBe( 'rgb(26, 35, 126)' );
	expect( await read( page, '#cta', 'color' ) ).toBe( 'rgb(255, 255, 255)' );
	await page.setContent( bare( 'dark', style ) );
	expect( await read( page, '#cta', 'backgroundColor' ) ).toBe( 'rgb(140, 158, 255)' );
	expect( await read( page, '#cta', 'color' ) ).toBe( 'rgb(0, 0, 0)' );
	await page.emulateMedia( { colorScheme: 'light' } );
	await page.setContent( bare( 'system', style ) );
	expect( await read( page, '#cta', 'backgroundColor' ) ).toBe( 'rgb(26, 35, 126)' );
	await page.emulateMedia( { colorScheme: 'dark' } );
	expect( await read( page, '#cta', 'backgroundColor' ) ).toBe( 'rgb(140, 158, 255)' );
} );
