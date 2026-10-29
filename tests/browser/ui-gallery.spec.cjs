/**
 * Design-language gallery: every shared component, in learner mode and in the admin Design system
 * preset, checked against stored screenshots and against the tokens.
 *
 * Needs PHP (set OHMYLMS_PHP when it is not on PATH) and a browser (OHMYLMS_CHROMIUM_PATH when
 * Playwright's own is not installed). Update baselines only for an intended visual change:
 *   npx playwright test tests/browser/ui-gallery.spec.cjs --update-snapshots
 */
const { test, expect } = require( '@playwright/test' );
const { spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const root = path.resolve( __dirname, '../..' );
const php = process.env.OHMYLMS_PHP || 'php';
const outFile = path.join( root, 'test-results', 'ui-gallery.html' );
const sections = [ 'buttons', 'badges', 'progress', 'cards', 'type', 'colors' ];
const contexts = [ 'learner', 'admin' ];

test.describe( 'design language gallery', () => {
	test.beforeAll( () => {
		const run = spawnSync(
			php,
			[ path.join( __dirname, 'fixtures/ui-gallery.php' ) ],
			{ encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 }
		);
		test.skip( run.error || 0 !== run.status, 'php is not available: ' + ( run.error || run.stderr ) );
		fs.mkdirSync( path.dirname( outFile ), { recursive: true } );
		fs.writeFileSync( outFile, run.stdout );
	} );

	test.beforeEach( async ( { page } ) => {
		await page.setViewportSize( { width: 800, height: 1000 } );
		await page.goto( 'file://' + outFile.replace( /\\/g, '/' ) );
	} );

	for ( const context of contexts ) {
		for ( const section of sections ) {
			test( `${ context } ${ section } looks as expected`, async ( { page } ) => {
				const target = page.locator(
					`[data-context="${ context }"] [data-gallery="${ section }"]`
				);
				await expect( target ).toHaveScreenshot( `${ context }-${ section }.png` );
			} );
		}
	}

	test( 'components read their colors from the tokens of their context', async ( { page } ) => {
		for ( const context of contexts ) {
			const result = await page.evaluate( ( name ) => {
				const main = document.querySelector( `[data-context="${ name }"]` );
				const token = ( n ) => {
					const probe = document.createElement( 'i' );
					probe.style.color = `var(${ n })`;
					main.appendChild( probe );
					const value = getComputedStyle( probe ).color;
					probe.remove();
					return value;
				};
				const style = ( selector, prop ) =>
					getComputedStyle( main.querySelector( selector ) )[ prop ];
				return {
					primaryButton: style( '.oml-btn-primary', 'backgroundColor' ),
					primary: token( '--oml-primary' ),
					dangerButton: style( '.oml-btn-danger', 'backgroundColor' ),
					danger: token( '--oml-danger' ),
					bar: style( '.oml-progress-bar', 'backgroundColor' ),
					cardBorder: style( '.oml-card', 'borderTopColor' ),
					border: token( '--oml-border' ),
				};
			}, context );
			expect( result.primaryButton, context + ' primary button' ).toBe( result.primary );
			expect( result.dangerButton, context + ' danger button' ).toBe( result.danger );
			expect( result.bar, context + ' progress bar' ).toBe( result.primary );
			expect( result.cardBorder, context + ' card border' ).toBe( result.border );
		}
	} );

	test( 'the two contexts differ only by their token values', async ( { page } ) => {
		const primaries = await page.evaluate( () =>
			[ 'learner', 'admin' ].map( ( name ) =>
				getComputedStyle(
					document.querySelector( `[data-context="${ name }"] .oml-btn-primary` )
				).backgroundColor
			)
		);
		expect( primaries[ 0 ] ).not.toBe( primaries[ 1 ] );
	} );
} );
