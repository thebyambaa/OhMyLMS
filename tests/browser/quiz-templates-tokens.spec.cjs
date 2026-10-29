/**
 * Quiz player templates: the shared question widgets (answer cards, matching, reorder, paper form) take
 * their colors from the --oml-* tokens, while each template keeps its own stage palette.
 *
 * Needs PHP (OHMYLMS_PHP when not on PATH) and a browser (OHMYLMS_CHROMIUM_PATH when needed).
 */
const { test, expect } = require( '@playwright/test' );
const { spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const root = path.resolve( __dirname, '../..' );
const php = process.env.OHMYLMS_PHP || 'php';

test( 'quiz widgets follow the tokens and templates keep their palette', async ( { page } ) => {
	const run = spawnSync( php, [ path.join( __dirname, 'fixtures/learner-tokens.php' ) ], {
		encoding: 'utf8',
	} );
	test.skip( run.error || 0 !== run.status, 'php is not available' );
	const css = fs.readFileSync( path.join( root, 'assets/css/quiz-player-templates.css' ), 'utf8' );
	await page.setContent(
		'<style>' + run.stdout + css + '</style>' +
			'<body class="ohmylms-page"><div class="ohmylms-quiz" data-player-template="default">' +
			'<div class="ohmylms-extended-question" id="card">Question card</div>' +
			'<div class="quiz-matching-options"><div class="matching-definition" id="definition">Term</div></div>' +
			'<button class="reorder-move" id="move" type="button">Move</button>' +
			'</div></body>'
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

	// Chrome: light cards that follow the design settings, inside any template's stage.
	expect( await read( '#card', 'backgroundColor' ) ).toBe( await token( '--oml-surface' ) );
	expect( await read( '#card', 'color' ) ).toBe( await token( '--oml-text' ) );
	expect( await read( '#definition', 'borderTopColor' ) ).toBe( await token( '--oml-border' ) );
	expect( await read( '#definition', 'backgroundColor' ) ).toBe( await token( '--oml-surface' ) );
	expect( await read( '#move', 'color' ) ).toBe( await token( '--oml-text' ) );
	// Palette: the default template's stage keeps its own dark toolbar color, not a token.
	expect( await read( '.ohmylms-quiz', 'backgroundColor' ) ).toBe( 'rgb(11, 11, 11)' );
} );
