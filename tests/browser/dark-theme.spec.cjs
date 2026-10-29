/**
 * Dark theme: the learner player follows data-theme="light|dark|system" through the --oml-* tokens,
 * with no palette of its own, and the quiz canvas stays light in every theme.
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
	'<style>' + tokens +
		fs.readFileSync( path.join( root, 'assets/css/player-context.css' ), 'utf8' ) +
		'</style><body class="ohmylms-page">' +
		`<div class="oml-player" data-theme="${ theme }">` +
		'<div class="oml-player-context" id="bar"><a class="oml-player-exit">Exit</a></div>' +
		'<div class="oml-player-main" id="main">Lesson</div></div></body>';

const read = ( page, selector, prop ) =>
	page.evaluate(
		( [ s, p ] ) => getComputedStyle( document.querySelector( s ) )[ p ],
		[ selector, prop ]
	);

const LIGHT_SURFACE = 'rgb(255, 255, 255)';
const DARK_SURFACE = 'rgb(21, 21, 33)';
const DARK_PAGE = 'rgb(37, 37, 49)';

test( 'light theme uses the light tokens', async ( { page } ) => {
	await page.emulateMedia( { colorScheme: 'dark' } );
	await page.setContent( markup( 'light' ) );
	expect( await read( page, '#bar', 'backgroundColor' ) ).toBe( LIGHT_SURFACE );
	expect( await read( page, '.oml-player', 'backgroundColor' ) ).not.toBe( DARK_PAGE );
} );

test( 'dark theme flips the same components to the dark tokens', async ( { page } ) => {
	await page.setContent( markup( 'dark' ) );
	expect( await read( page, '#bar', 'backgroundColor' ) ).toBe( DARK_SURFACE );
	expect( await read( page, '.oml-player', 'backgroundColor' ) ).toBe( DARK_PAGE );
	expect( await read( page, '#bar', 'color' ) ).toBe( 'rgb(243, 243, 247)' );
} );

test( 'system theme follows the device preference', async ( { page } ) => {
	await page.emulateMedia( { colorScheme: 'light' } );
	await page.setContent( markup( 'system' ) );
	expect( await read( page, '#bar', 'backgroundColor' ) ).toBe( LIGHT_SURFACE );
	await page.emulateMedia( { colorScheme: 'dark' } );
	expect( await read( page, '#bar', 'backgroundColor' ) ).toBe( DARK_SURFACE );
} );

test( 'the quiz canvas stays light in the dark theme', async ( { page } ) => {
	await page.setContent(
		markup( 'dark' ).replace( 'class="oml-player"', 'class="oml-player oml-player-taking-quiz"' )
	);
	expect( await read( page, '#main', 'backgroundColor' ) ).toBe( LIGHT_SURFACE );
} );

test( 'the page behind the player follows the theme too', async ( { page } ) => {
	await page.setContent( markup( 'dark' ) );
	expect( await read( page, 'body', 'backgroundColor' ) ).toBe( DARK_PAGE );
	await page.setContent( markup( 'light' ) );
	expect( await read( page, 'body', 'backgroundColor' ) ).not.toBe( DARK_PAGE );
} );
