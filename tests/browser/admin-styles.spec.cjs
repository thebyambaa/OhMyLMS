const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const root = path.resolve( __dirname, '../..' );
const modules = JSON.parse(
	fs.readFileSync(
		path.join( root, 'assets/dist/css/admin/modules.json' ),
		'utf8'
	)
);

test( 'failed CSS requests reject and can be retried in the same slot', async ( {
	page,
} ) => {
	let attempts = 0;
	await page.route( 'https://retry-styles.test/feature.css', ( route ) => {
		attempts++;
		return attempts === 1
			? route.abort()
			: route.fulfill( {
					contentType: 'text/css',
					body: '.course{color:rgb(1,2,3)}',
				} );
	} );
	await page.setContent(
		'<link id="feature" rel="stylesheet" data-oml-group="courses" data-oml-href="https://retry-styles.test/feature.css"><main>Dashboard</main>'
	);
	await page.addScriptTag( {
		path: path.join( root, 'assets/js/admin-styles.js' ),
	} );
	expect(
		await page.evaluate( () =>
			window.ohmylmsStyles.ensure( 'courseComponents' ).then(
				() => false,
				() => true
			)
		)
	).toBe( true );
	await page.evaluate( () =>
		window.ohmylmsStyles.ensure( 'courseComponents' )
	);
	expect( attempts ).toBe( 2 );
	expect( await page.locator( '#feature' ).count() ).toBe( 1 );
} );

for ( const rtl of [ false, true ] ) {
	test( `admin CSS loads features on demand with stable cascade (${ rtl ? 'RTL' : 'LTR' })`, async ( {
		page,
	} ) => {
		const requested = [];
		await page.route( 'https://styles.test/**', ( route ) => {
			const name = new URL( route.request().url() ).pathname.slice( 1 );
			requested.push( name );
			if ( ! name ) {
				const links = modules
					.filter( ( entry ) => rtl || ! entry.rtl )
					.map( ( entry, index ) =>
						entry.inline !== null
							? `<style id="module-${ index }">${ entry.inline }</style>`
							: `<link id="module-${ index }" rel="stylesheet" ${ entry.group === 'shared' ? 'href' : `data-oml-group="${ entry.group }" data-oml-href` }="/${ entry.path }">`
					)
					.join( '' );
				return route.fulfill( {
					contentType: 'text/html',
					body: `<!doctype html><html ${ rtl ? 'dir="rtl"' : '' }><head>${ links }<style id="override">.ohmylms-course-builder-wrapper{color:rgb(1,2,3)}</style><script src="/loader.js"></script></head><body class="toplevel_page_ohmylms ${ rtl ? 'rtl' : '' }"><main id="app">Dashboard</main></body></html>`,
				} );
			}
			return route.fulfill( {
				path: path.join(
					root,
					name === 'loader.js' ? 'assets/js/admin-styles.js' : name
				),
				contentType: name.endsWith( '.js' )
					? 'application/javascript'
					: 'text/css',
			} );
		} );
		await page.goto( 'https://styles.test/' );
		const groups = () =>
			modules
				.filter( ( entry ) => requested.includes( entry.path ) )
				.map( ( entry ) => entry.group );
		expect( new Set( groups() ) ).toEqual( new Set( [ 'shared' ] ) );
		const order = await page
			.locator( 'head link' )
			.evaluateAll( ( links ) => links.map( ( link ) => link.id ) );
		await page.evaluate( () =>
			window.ohmylmsStyles.ensure( 'courseComponents' )
		);
		expect( groups() ).toContain( 'courses' );
		expect( groups() ).toContain( 'block-editor' );
		expect( groups() ).not.toContain( 'email-editor' );
		await page.evaluate( () => {
			document.querySelector( '#app' ).innerHTML =
				'<div class="ohmylms-course-builder-wrapper">Course</div>';
		} );
		await expect(
			page.locator( '.ohmylms-course-builder-wrapper' )
		).toHaveCSS( 'color', 'rgb(1, 2, 3)' );
		await page.evaluate( () =>
			window.ohmylmsStyles.ensure( 'emailComponents' )
		);
		expect( groups() ).not.toContain( 'email-editor' );
		await page.evaluate( () =>
			window.ohmylmsStyles.ensure( 'EmailTemplate' )
		);
		expect( groups() ).toContain( 'email-editor' );
		expect( groups() ).toContain( 'automations' );
		expect(
			await page
				.locator( 'head link' )
				.evaluateAll( ( links ) => links.map( ( link ) => link.id ) )
		).toEqual( order );
		const count = requested.length;
		await page.evaluate( () =>
			window.ohmylmsStyles.ensure( 'EmailTemplate' )
		);
		expect( requested.length ).toBe( count );
	} );
}
