/** Question chrome is themed; paper, illustration and quiz palettes retain their contracts. */
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
	expect( run.error ).toBeUndefined();
	expect( run.status, run.stderr ).toBe( 0 );
	tokens = run.stdout;
} );
const styles = [
	'oml-ui',
	'extended-questions',
	'interactive',
	'paper',
	'quiz-overview',
	'question-stage',
]
	.map( ( name ) =>
		fs.readFileSync( path.join( root, `assets/css/${ name }.css` ), 'utf8' )
	)
	.join( '\n' );
const markup = (
	theme
) => `<!doctype html><html><head><style>${ tokens }${ styles }
body{margin:16px;background:var(--oml-background);color:var(--oml-text);font-family:system-ui}section{margin-bottom:24px}#stage{min-height:0}#stage .ohmylms-shared-answers{display:grid}canvas{width:240px;height:70px}
</style></head><body data-theme="${ theme }">
<section class="ohmylms-extended-question" id="extended"><div class="ohmylms-passage">Read the passage and explore the example.</div><label class="ohmylms-extended-field">Answer<input aria-label="Extended answer"></label><div class="ohmylms-activity"><button class="ohmylms-activity-button">Start activity</button><button class="ohmylms-flashcard">Flip card</button><div class="ohmylms-gameshow-choice"><span class="ohmylms-gameshow-key">A</span>Choose a response</div></div><canvas aria-label="Drawing canvas"></canvas><div class="ohmylms-spin-wheel"></div><div class="ohmylms-activity is-whack-a-mole"><button class="ohmylms-pick-target">Mole</button></div></section>
<section class="ohmylms-interactive ohmylms-build-expression" id="interactive"><label>Complete the sentence <select class="ohmylms-inline-select"><option>Choose an answer</option><option>Four</option></select></label><div class="ohmylms-build-answer"><button class="ohmylms-tile" aria-pressed="true">4</button><button class="ohmylms-tile">+</button></div><button class="ohmylms-build-clear">Clear answer</button><button class="ohmylms-key">+</button><svg class="ohmylms-vis-svg" tabindex="0" viewBox="0 0 300 70"><path class="ohmylms-vis-axis" d="M20 40H280"/><text class="ohmylms-vis-text" x="20" y="25">Number line</text><circle class="ohmylms-vis-point" cx="150" cy="40" r="8"/></svg><div class="ohmylms-money-tray"><button class="ohmylms-bill">10</button></div><p class="ohmylms-vis-hint">Drag to explore.</p><details class="ohmylms-template-panel"><summary>Editor settings</summary></details></section>
<section class="ohmylms-print-page" id="paper" style="--ohmylms-paper-width:210mm;--ohmylms-paper-margin:20mm;--ohmylms-paper-font-size:11pt"><div class="ohmylms-print-toolbar"><a class="ohmylms-print-back" href="#">Back to the exam</a><strong class="ohmylms-print-toolbar-title">Fractions paper</strong><span class="ohmylms-print-status">Ready</span><button class="oml-btn oml-btn-primary ohmylms-print-button">Print</button></div><main class="ohmylms-print-sheet"><header class="ohmylms-paper-header"><h2>Fractions paper</h2></header><div class="ohmylms-print-question"><p>1. What is one half of eight?</p><ol class="ohmylms-print-options"><li>Four</li><li>Two</li></ol><div class="ohmylms-print-key">Answer: four</div><div class="ohmylms-print-lines"></div></div><div class="ohmylms-print-note">Editor note</div></main></section>
<section class="ohmylms-quiz-overview" id="overview"><div><p class="ohmylms-overview-metadata">Question overview</p><article class="ohmylms-overview-card"><button class="ohmylms-overview-prompt">What is one half of eight?</button><ul class="ohmylms-overview-answers"><li><span class="ohmylms-overview-answer-mark is-correct">✓</span>Four</li><li>Two</li></ul><div class="ohmylms-quick-question-prompt"><div role="textbox" contenteditable="true">Edit prompt</div></div></article></div><aside class="ohmylms-overview-find"><h2>Find a question</h2><p>Search your library</p></aside></section>
<section class="ohmylms-question-authoring is-stage-editor" id="stage"><header class="ohmylms-form-workspace-actions"><button class="components-button is-primary">Save</button><span>Question editor toolbar</span></header><div class="ohmylms-form-description"><div role="textbox">Question prompt</div></div><div class="ohmylms-form-response-fields"><div class="ohmylms-shared-answers is-single-choice"><div class="ohmylms-shared-answer-row">First answer</div><div class="ohmylms-shared-answer-row">Second answer</div></div><div class="ohmylms-interactive-editor">Generic editor panel</div><span class="ohmylms-blank-answer-chip">Blank answer</span></div></section>
</body></html>`;
const contracts = {
	'extended-questions': [
		[ '#extended', 'backgroundColor', '--oml-surface' ],
		[ '#extended', 'color', '--oml-text' ],
		[ '.ohmylms-passage', 'backgroundColor', '--oml-subtle' ],
		[ '.ohmylms-activity-button', 'backgroundColor', '--oml-primary' ],
		[ '.ohmylms-activity-button', 'color', '--oml-on-primary' ],
		[ '.ohmylms-flashcard', 'backgroundColor', '--oml-surface' ],
		[ '#extended input', 'backgroundColor', '--oml-surface' ],
	],
	interactive: [
		[ '.ohmylms-build-clear', 'color', '--oml-text' ],
		[ '.ohmylms-key', 'color', '--oml-text' ],
		[ '.ohmylms-key', 'backgroundColor', '--oml-surface' ],
		[ '#interactive', 'backgroundColor', '--oml-surface' ],
		[ '.ohmylms-inline-select', 'backgroundColor', '--oml-surface' ],
		[ '.ohmylms-inline-select', 'color', '--oml-text' ],
		[
			'.ohmylms-tile[aria-pressed="true"]',
			'backgroundColor',
			'--oml-primary-soft',
		],
		[
			'.ohmylms-tile[aria-pressed="true"]',
			'borderTopColor',
			'--oml-primary',
		],
		[ '.ohmylms-vis-hint', 'color', '--oml-muted' ],
		[ '.ohmylms-template-panel', 'backgroundColor', '--oml-subtle' ],
	],
	paper: [
		[ '#paper', 'backgroundColor', '--oml-background' ],
		[ '.ohmylms-print-toolbar', 'backgroundColor', '--oml-surface' ],
		[ '.ohmylms-print-toolbar', 'color', '--oml-text' ],
		[ '.ohmylms-print-status', 'color', '--oml-muted' ],
		[ '.ohmylms-print-back', 'color', '--oml-link' ],
		[ '.ohmylms-print-button', 'backgroundColor', '--oml-primary' ],
		[ '.ohmylms-print-button', 'color', '--oml-on-primary' ],
	],
	'quiz-overview': [
		[ '#overview', 'backgroundColor', '--oml-surface' ],
		[ '#overview', 'color', '--oml-text' ],
		[ '.ohmylms-overview-card', 'borderTopColor', '--oml-border' ],
		[ '.ohmylms-overview-prompt', 'color', '--oml-heading' ],
		[ '.ohmylms-overview-metadata', 'color', '--oml-muted' ],
		[
			'.ohmylms-overview-answer-mark.is-correct',
			'backgroundColor',
			'--oml-success-soft',
		],
		[
			'.ohmylms-overview-answer-mark.is-correct',
			'color',
			'--oml-success',
		],
	],
	'question-stage': [
		[
			'.ohmylms-form-workspace-actions',
			'backgroundColor',
			'--oml-surface',
		],
		[ '.ohmylms-form-workspace-actions', 'color', '--oml-text' ],
		[
			'.ohmylms-form-workspace-actions .is-primary',
			'backgroundColor',
			'--oml-primary',
		],
		[
			'.ohmylms-form-workspace-actions .is-primary',
			'color',
			'--oml-on-primary',
		],
		[ '.ohmylms-interactive-editor', 'backgroundColor', '--oml-surface' ],
		[ '.ohmylms-blank-answer-chip', 'backgroundColor', '--oml-subtle' ],
		[ '.ohmylms-blank-answer-chip', 'color', '--oml-heading' ],
	],
};
async function verify( page, checks ) {
	await page.waitForTimeout( 700 );
	const values = await page.evaluate(
		( checks ) =>
			checks.map( ( [ selector, property, name ] ) => {
				const el = document.querySelector( selector ),
					probe = document.createElement( 'i' );
				probe.style.color = `var(${ name })`;
				el.parentElement.appendChild( probe );
				const expected = getComputedStyle( probe ).color,
					actual = getComputedStyle( el )[ property ];
				probe.remove();
				return { selector, property, expected, actual };
			} ),
		checks
	);
	for ( const value of values )
		expect( value.actual, `${ value.selector } ${ value.property }` ).toBe(
			value.expected
		);
}
for ( const [ name, checks ] of Object.entries( contracts ) )
	for ( const [ theme, device ] of [
		[ 'light', 'dark' ],
		[ 'dark', 'light' ],
		[ 'system', 'light' ],
		[ 'system', 'dark' ],
	] ) {
		test( `${ name }: ${ theme } on a ${ device } device`, async ( {
			page,
		}, testInfo ) => {
			await page.emulateMedia( { colorScheme: device } );
			await page.setContent( markup( theme ) );
			await verify( page, checks );
			const id = {
				'extended-questions': 'extended',
				interactive: 'interactive',
				paper: 'paper',
				'quiz-overview': 'overview',
				'question-stage': 'stage',
			}[ name ];
			await page.locator( `#${ id }` ).screenshot( {
				path: testInfo.outputPath(
					`${ name }-${ theme }-${ device }.png`
				),
			} );
		} );
	}
test( 'fixed illustration and quiz palettes keep their paired foregrounds in dark mode', async ( {
	page,
} ) => {
	await page.setContent( markup( 'dark' ) );
	await expect( page.locator( 'canvas' ) ).toHaveCSS(
		'background-color',
		'rgb(255, 255, 255)'
	);
	await expect( page.locator( '.ohmylms-vis-svg' ) ).toHaveCSS(
		'color',
		'rgb(17, 24, 39)'
	);
	await expect( page.locator( '.ohmylms-vis-text' ) ).toHaveCSS(
		'fill',
		'rgb(17, 24, 39)'
	);
	await expect(
		page.locator( '.is-whack-a-mole .ohmylms-pick-target' )
	).toHaveCSS( 'background-color', 'rgb(139, 94, 60)' );
	await expect(
		page.locator( '.is-whack-a-mole .ohmylms-pick-target' )
	).toHaveCSS( 'color', 'rgb(255, 255, 255)' );
	await expect( page.locator( '.ohmylms-bill' ) ).toHaveCSS(
		'color',
		'rgb(20, 83, 45)'
	);
	await expect(
		page.locator( '.ohmylms-form-description [role=textbox]' )
	).toHaveCSS( 'background-color', 'rgb(41, 21, 43)' );
	await expect(
		page.locator( '.ohmylms-form-description [role=textbox]' )
	).toHaveCSS( 'color', 'rgb(255, 255, 255)' );
	await expect(
		page.locator( '.ohmylms-shared-answer-row' ).first()
	).toHaveCSS( 'background-color', 'rgb(46, 114, 173)' );
} );
test( 'the paper stays white with dark ink and physical dimensions in every theme and print media', async ( {
	page,
} ) => {
	await page.setContent( markup( 'light' ) );
	const sheet = page.locator( '.ohmylms-print-sheet' );
	const physical = await sheet.evaluate( ( el ) => {
		const cs = getComputedStyle( el );
		return {
			width: cs.width,
			'font-size': cs.fontSize,
			padding: cs.padding,
		};
	} );
	for ( const theme of [ 'dark', 'system' ] ) {
		await page.emulateMedia( { colorScheme: 'dark' } );
		await page
			.locator( 'body' )
			.evaluate( ( el, theme ) => ( el.dataset.theme = theme ), theme );
		await expect( sheet ).toHaveCSS(
			'background-color',
			'rgb(255, 255, 255)'
		);
		await expect( sheet ).toHaveCSS( 'color', 'rgb(30, 30, 30)' );
		for ( const [ property, value ] of Object.entries( physical ) )
			await expect( sheet ).toHaveCSS( property, value );
	}
	await page.emulateMedia( { media: 'print', colorScheme: 'dark' } );
	await expect( sheet ).toHaveCSS( 'background-color', 'rgb(255, 255, 255)' );
	await expect( sheet ).toHaveCSS( 'color', 'rgb(30, 30, 30)' );
	await expect( page.locator( '.ohmylms-print-toolbar' ) ).toBeHidden();
	await expect( page.locator( '.ohmylms-print-note' ) ).toBeHidden();
} );
test( 'input focus uses the focus token and system changes work without reloading', async ( {
	page,
} ) => {
	await page.emulateMedia( { colorScheme: 'light' } );
	await page.setContent( markup( 'system' ) );
	await page.locator( '#extended input' ).focus();
	await verify( page, [
		[ '#extended input', 'outlineColor', '--oml-focus' ],
	] );
	await page.emulateMedia( { colorScheme: 'dark' } );
	await verify( page, Object.values( contracts ).flat() );
	await verify( page, [
		[ '#extended input', 'outlineColor', '--oml-focus' ],
	] );
} );
