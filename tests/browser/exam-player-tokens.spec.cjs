/**
 * The exam player's chrome follows the shared --oml-* tokens: its neutrals, status chips and button
 * text resolve to token values on a learner page, so changing the brand or Design settings restyles it.
 *
 * Needs PHP (OHMYLMS_PHP when not on PATH) and a browser (OHMYLMS_CHROMIUM_PATH when needed).
 */
const { test, expect } = require( '@playwright/test' );
const { spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const root = path.resolve( __dirname, '../..' );
const php = process.env.OHMYLMS_PHP || 'php';
const css = ( file ) => fs.readFileSync( path.join( root, 'assets/css', file ), 'utf8' );

test( 'exam player chrome resolves to the learner tokens', async ( { page } ) => {
	const run = spawnSync( php, [ path.join( __dirname, 'fixtures/learner-tokens.php' ) ], {
		encoding: 'utf8',
	} );
	test.skip( run.error || 0 !== run.status, 'php is not available' );
	await page.setContent(
		'<style>' + run.stdout + css( 'exam-player-template.css' ) + '</style>' +
			'<body class="ohmylms-page"><div class="ohmylms-quiz ohmylms-player-template-exam">' +
			'<div class="ohmylms-exam-bar"><button class="ohmylms-exam-review" type="button">Review</button></div>' +
			'<ul>' +
			'<li><button class="ohmylms-exam-palette-item" id="plain">1</button></li>' +
			'<li><button class="ohmylms-exam-palette-item is-visited" id="visited">2</button></li>' +
			'<li><button class="ohmylms-exam-palette-item is-answered" id="answered">3</button></li>' +
			'<li><button class="ohmylms-exam-palette-item is-marked" id="marked">4</button></li>' +
			'</ul></div></body>'
	);
	const read = ( selector, prop ) =>
		page.evaluate(
			( [ s, p ] ) => getComputedStyle( document.querySelector( s ) )[ p ],
			[ selector, prop ]
		);
	const token = ( name ) =>
		page.evaluate( ( n ) => {
			const probe = document.createElement( 'i' );
			probe.style.color = `var(${ n })`;
			document.body.appendChild( probe );
			const value = getComputedStyle( probe ).color;
			probe.remove();
			return value;
		}, name );

	expect( await read( '.ohmylms-quiz', 'backgroundColor' ) ).toBe( await token( '--oml-background' ) );
	expect( await read( '.ohmylms-exam-bar', 'backgroundColor' ) ).toBe( await token( '--oml-surface' ) );
	expect( await read( '.ohmylms-exam-bar', 'borderBottomColor' ) ).toBe( await token( '--oml-border' ) );
	expect( await read( '#plain', 'color' ) ).toBe( await token( '--oml-heading' ) );
	expect( await read( '#visited', 'backgroundColor' ) ).toBe( await token( '--oml-warning-soft' ) );
	expect( await read( '#visited', 'borderTopColor' ) ).toBe( await token( '--oml-warning' ) );
	expect( await read( '#answered', 'backgroundColor' ) ).toBe( await token( '--oml-success-soft' ) );
	expect( await read( '#answered', 'borderTopColor' ) ).toBe( await token( '--oml-success' ) );
	expect( await read( '#marked', 'backgroundColor' ) ).toBe( await token( '--oml-primary-soft' ) );
	expect( await read( '#marked', 'color' ) ).toBe( await token( '--oml-primary' ) );
	// The review button keeps the quiz accent (default: the brand color) with light text.
	expect( await read( '.ohmylms-exam-review', 'backgroundColor' ) ).toBe( await token( '--oml-primary' ) );
	expect( await read( '.ohmylms-exam-review', 'color' ) ).toBe( await token( '--oml-surface' ) );
} );
