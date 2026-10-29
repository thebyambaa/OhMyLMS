/**
 * Question player, game player and answer review: their chrome resolves to the --oml-* tokens on a
 * learner page. Palettes (the answer review's playful greens and reds) and game artwork stay literal.
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

test( 'player widgets resolve to the learner tokens', async ( { page } ) => {
	const run = spawnSync( php, [ path.join( __dirname, 'fixtures/learner-tokens.php' ) ], {
		encoding: 'utf8',
	} );
	test.skip( run.error || 0 !== run.status, 'php is not available' );
	await page.setContent(
		'<style>' + run.stdout + css( 'question-player.css' ) + css( 'answer-review.css' ) + '</style>' +
			'<body class="ohmylms-page">' +
			'<div class="ohmylms-question-player" id="player">Question</div>' +
			'<div class="ohmylms-answer-review"><div class="ohmylms-answer-review-item" id="item">Review</div></div>' +
			'</body>'
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

	expect( await read( '#player', 'backgroundColor' ) ).toBe( await token( '--oml-surface' ) );
	expect( await read( '#player', 'color' ) ).toBe( await token( '--oml-heading' ) );
	expect( await read( '#player', 'borderTopColor' ) ).toBe( await token( '--oml-border' ) );
	expect( await read( '#item', 'backgroundColor' ) ).toBe( await token( '--oml-surface' ) );
	// The review's own palette is still its own: the left stripe of an item is the playful line color.
	expect( await read( '#item', 'borderInlineStartColor' ) ).toBe( 'rgb(229, 229, 229)' );
} );
