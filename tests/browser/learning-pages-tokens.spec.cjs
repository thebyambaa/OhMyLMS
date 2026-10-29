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
	expect( run.status, run.stderr ).toBe( 0 );
	tokens = run.stdout;
} );
for ( const [ theme, device ] of [
	[ 'light', 'dark' ],
	[ 'dark', 'light' ],
	[ 'system', 'light' ],
	[ 'system', 'dark' ],
] ) {
	test( `course and lesson chrome uses shared ${ theme } tokens on a ${ device } device`, async ( {
		page,
	} ) => {
		await page.emulateMedia( { colorScheme: device } );
		const css = [
			'oml-ui.css',
			'frontend/core.css',
			'frontend/course.css',
			'frontend/lesson.css',
		]
			.map( ( name ) =>
				fs.readFileSync( path.join( root, 'assets/css', name ), 'utf8' )
			)
			.join( '\n' );
		await page.setContent(
			`<style>${ tokens }${ css }</style><body class="ohmylms-page" data-theme="${ theme }"><div class="ohmylms-course-header"><h1 class="ohmylms-course-title">Course</h1></div><section class="ohmylms-lesson-details"><div class="ohmylms-lesson-content"><h1>Lesson</h1><ul class="ohmylms-resources-list"><li>Resources</li></ul></div></section></body>`
		);
		const values = await page.evaluate( () => {
			const el = document.querySelector( '.ohmylms-lesson-details' );
			const probe = document.createElement( 'i' );
			probe.style.cssText =
				'color:var(--oml-heading);background:var(--oml-background);border:1px solid var(--oml-border)';
			el.appendChild( probe );
			const style = getComputedStyle( probe );
			return {
				heading: style.color,
				background: style.backgroundColor,
				border: style.borderTopColor,
			};
		} );
		await expect( page.locator( '.ohmylms-course-title' ) ).toHaveCSS(
			'color',
			values.heading
		);
		await expect( page.locator( '.ohmylms-lesson-content h1' ) ).toHaveCSS(
			'color',
			values.heading
		);
		await expect( page.locator( '.ohmylms-lesson-details' ) ).toHaveCSS(
			'background-color',
			values.background
		);
		await expect( page.locator( '.ohmylms-resources-list' ) ).toHaveCSS(
			'border-top-color',
			values.border
		);
	} );
}
