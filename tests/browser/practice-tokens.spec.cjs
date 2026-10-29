/** Practice chrome follows shared tokens; mastery levels and medals retain their palettes. */
const { test, expect } = require( '@playwright/test' );
const { spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const root = path.resolve( __dirname, '../..' );
let tokens;

test.beforeAll( () => {
	const run = spawnSync(
		process.env.OHMYLMS_PHP || 'php',
		[ path.join( __dirname, 'fixtures/learner-tokens.php' ) ],
		{ encoding: 'utf8' }
	);
	expect( run.error, 'The token fixture needs PHP.' ).toBeUndefined();
	expect( run.status, run.stderr ).toBe( 0 );
	tokens = run.stdout;
} );

const markup = ( theme ) => `<!doctype html><html><head><style>${ tokens }
${ fs.readFileSync( path.join( root, 'assets/css/oml-ui.css' ), 'utf8' ) }
${ fs.readFileSync( path.join( root, 'assets/css/oml-legacy-buttons.css' ), 'utf8' ) }
${ fs.readFileSync( path.join( root, 'assets/css/practice.css' ), 'utf8' ) }
body{margin:24px;background:var(--oml-background);font-family:system-ui}main{max-width:760px;margin:auto}
</style></head><body data-theme="${ theme }"><main>
<section class="ohmylms-practice"><h2>Practice: fractions</h2><p class="ohmylms-practice-progress">Question 2 of 5</p>
<div class="ohmylms-practice-bar"><ul class="ohmylms-practice-dots"><li class="is-right"></li><li class="is-current"></li><li class="is-wrong"></li><li class="is-help"></li></ul><button class="ohmylms-practice-restart oml-btn oml-btn-tertiary oml-btn-sm">Start over</button></div>
<form class="ohmylms-practice-question"><p class="the-question">What is one half of eight?</p><label class="ohmylms-practice-option"><input type="radio" name="answer">Four</label><label class="ohmylms-practice-option ohmylms-inline-answer"><input type="radio" name="answer">Two</label><div class="ohmylms-practice-inputs"><input class="ohmylms-text-input" aria-label="Explain your answer"></div><div class="ohmylms-practice-controls"><button class="ohmylms-button oml-btn oml-btn-primary">Check answer</button><button class="ohmylms-button outline oml-btn">Skip</button></div></form>
<div class="ohmylms-practice-feedback is-correct">Correct! <div class="ohmylms-solution">The whole is split into two equal parts.</div></div><div class="ohmylms-practice-feedback is-wrong">Try again.</div>
<div class="ohmylms-replay-banner">Review a missed question</div><div class="ohmylms-ai-reply">Consider the two equal groups.</div>
</section>
<section class="ohmylms-guidance"><article class="ohmylms-guidance-card"><h3>Your next step</h3><p>Keep building confidence.</p><div class="ohmylms-recommendation ohmylms-recommendation-help"><a href="#">Review fractions</a></div><div class="ohmylms-guidance-meter"><span style="width:60%"></span></div></article></section>
<section class="ohmylms-skill-map"><div class="ohmylms-skill-map-scroll"><table class="ohmylms-skill-map-table"><thead><tr><th>Skill</th><th>Level</th></tr></thead><tbody><tr><th>Fractions</th><td><span class="ohmylms-map-chip ohmylms-map-proficient">Proficient</span></td></tr></tbody></table></div><span class="ohmylms-skill-level ohmylms-skill-mastered">Mastered</span></section>
<section class="ohmylms-reward"><strong class="ohmylms-reward-gain">+10 XP</strong><span class="ohmylms-reward-medal is-80">Bronze</span><span class="ohmylms-reward-medal is-90">Silver</span><span class="ohmylms-reward-medal is-100">Gold</span><span class="ohmylms-reward-goal">Daily goal met</span></section>
</main></body></html>`;

const contracts = [
	[ '.ohmylms-practice', 'color', '--oml-text' ],
	[ '.ohmylms-practice', 'backgroundColor', '--oml-background' ],
	[ '.ohmylms-practice h2', 'color', '--oml-heading' ],
	[ '.ohmylms-practice-progress', 'color', '--oml-muted' ],
	[ '.ohmylms-practice-question', 'backgroundColor', '--oml-surface' ],
	[ '.ohmylms-practice-question', 'borderTopColor', '--oml-border' ],
	[ '.ohmylms-practice-question .the-question', 'color', '--oml-heading' ],
	[ '.ohmylms-text-input', 'color', '--oml-text' ],
	[ '.ohmylms-text-input', 'backgroundColor', '--oml-surface' ],
	[ '.ohmylms-practice-controls', 'backgroundColor', '--oml-surface' ],
	[
		'.ohmylms-practice-controls .oml-btn-primary',
		'backgroundColor',
		'--oml-primary',
	],
	[
		'.ohmylms-practice-controls .oml-btn-primary',
		'color',
		'--oml-on-primary',
	],
	[
		'.ohmylms-practice-controls .oml-btn:not(.oml-btn-primary)',
		'backgroundColor',
		'--oml-surface',
	],
	[ '.ohmylms-practice-restart', 'color', '--oml-link' ],
	[
		'.ohmylms-practice-feedback.is-correct',
		'backgroundColor',
		'--oml-success-soft',
	],
	[ '.ohmylms-practice-feedback.is-correct', 'color', '--oml-success' ],
	[
		'.ohmylms-practice-feedback.is-wrong',
		'backgroundColor',
		'--oml-danger-soft',
	],
	[ '.ohmylms-practice-feedback.is-wrong', 'borderTopColor', '--oml-danger' ],
	[ '.ohmylms-practice-feedback.is-wrong', 'color', '--oml-danger' ],
	[ '.ohmylms-solution', 'backgroundColor', '--oml-subtle' ],
	[ '.ohmylms-inline-answer', 'backgroundColor', '--oml-success-soft' ],
	[ '.ohmylms-guidance-card', 'backgroundColor', '--oml-surface' ],
	[ '.ohmylms-recommendation', 'backgroundColor', '--oml-subtle' ],
	[ '.ohmylms-recommendation-help', 'borderLeftColor', '--oml-warning' ],
	[ '.ohmylms-guidance-meter', 'backgroundColor', '--oml-subtle' ],
	[ '.ohmylms-guidance-meter span', 'backgroundColor', '--oml-primary' ],
	[ '.ohmylms-skill-map-table tbody th', 'backgroundColor', '--oml-surface' ],
	[ '.ohmylms-reward', 'backgroundColor', '--oml-warning-soft' ],
	[ '.ohmylms-reward-gain', 'color', '--oml-warning' ],
	[ '.ohmylms-reward-goal', 'color', '--oml-success' ],
];

async function assertTokens( page ) {
	// Shared buttons transition between theme colors; inspect their settled values.
	await page.waitForTimeout( 700 );
	const values = await page.evaluate(
		( checks ) =>
			checks.map( ( [ selector, prop, name ] ) => {
				const target = document.querySelector( selector );
				const probe = document.createElement( 'i' );
				probe.style.color = `var(${ name })`;
				target.parentNode.appendChild( probe );
				const expected = getComputedStyle( probe ).color;
				const actual = getComputedStyle( target )[ prop ];
				probe.remove();
				return { selector, prop, expected, actual };
			} ),
		contracts
	);
	for ( const value of values )
		expect( value.actual, `${ value.selector }: ${ value.prop }` ).toBe(
			value.expected
		);
}

for ( const [ theme, device ] of [
	[ 'light', 'dark' ],
	[ 'dark', 'light' ],
	[ 'system', 'light' ],
	[ 'system', 'dark' ],
] ) {
	test( `${ theme } practice on a ${ device } device uses token colors`, async ( {
		page,
	}, testInfo ) => {
		await page.setViewportSize( { width: 900, height: 1100 } );
		await page.emulateMedia( { colorScheme: device } );
		await page.setContent( markup( theme ) );
		await assertTokens( page );
		await page.locator( '.ohmylms-text-input' ).focus();
		const focus = await page
			.locator( '.ohmylms-text-input' )
			.evaluate( ( input ) => {
				const cs = getComputedStyle( input );
				const probe = document.createElement( 'i' );
				probe.style.color = 'var(--oml-focus)';
				input.parentNode.appendChild( probe );
				const color = getComputedStyle( probe ).color;
				probe.remove();
				return {
					actual: cs.outlineColor,
					expected: color,
					style: cs.outlineStyle,
				};
			} );
		expect( focus.actual ).toBe( focus.expected );
		expect( focus.style ).toBe( 'solid' );
		await expect( page.locator( '.ohmylms-map-proficient' ) ).toHaveCSS(
			'background-color',
			'rgb(219, 234, 254)'
		);
		await expect( page.locator( '.ohmylms-map-proficient' ) ).toHaveCSS(
			'color',
			'rgb(30, 64, 175)'
		);
		await expect( page.locator( '.ohmylms-skill-mastered' ) ).toHaveCSS(
			'color',
			'rgb(22, 101, 52)'
		);
		await expect(
			page.locator( '.ohmylms-reward-medal.is-100' )
		).toHaveCSS( 'background-color', 'rgb(253, 230, 138)' );
		await page.screenshot( {
			path: testInfo.outputPath( `practice-${ theme }-${ device }.png` ),
			fullPage: true,
		} );
	} );
}

test( 'system preference can change without reloading practice', async ( {
	page,
} ) => {
	await page.emulateMedia( { colorScheme: 'light' } );
	await page.setContent( markup( 'system' ) );
	await assertTokens( page );
	const light = await page
		.locator( '.ohmylms-practice-question' )
		.evaluate( ( el ) => getComputedStyle( el ).backgroundColor );
	await page.emulateMedia( { colorScheme: 'dark' } );
	await assertTokens( page );
	expect(
		await page
			.locator( '.ohmylms-practice-question' )
			.evaluate( ( el ) => getComputedStyle( el ).backgroundColor )
	).not.toBe( light );
} );
