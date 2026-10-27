const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const root = path.resolve( __dirname, '../..' );
const spec = {
	version: 1,
	bounds: [ -1, 5, 6, -1 ],
	objects: [
		{ id: 'A', type: 'point', x: 0, y: 0, label: 'A' },
		{ id: 'B', type: 'point', x: 4, y: 0, label: 'B' },
		{ id: 'C', type: 'point', x: 2, y: 3, label: 'C' },
		{ id: 'ABC', type: 'polygon', points: [ 'A', 'B', 'C' ] },
		{ id: 'angle', type: 'angle', points: [ 'B', 'A', 'C' ], label: 'x°' },
		{ id: 'circle', type: 'circle', center: 'C', radius: 0.5 },
		{ id: 'curve', type: 'function', expression: { op: '*', args: [ 0.1, { op: '^', args: [ 'x', 2 ] } ] } },
	],
};

async function setup( page, value = spec, fail = false ) {
	await page.setContent( '<main><figure class="ohmylms-import-figure"><img alt="Triangle ABC with labelled angle" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6S8AAAAASUVORK5CYII="><figcaption>Not to scale</figcaption></figure></main>' );
	await page.locator( 'figure' ).evaluate( ( node, figureSpec ) => { node.dataset.figureSpec = JSON.stringify( figureSpec ); }, value );
	await page.addStyleTag( { content: fs.readFileSync( path.join( root, 'assets/css/import-figures.css' ), 'utf8' ) } );
	await page.addScriptTag( { path: path.join( root, 'assets/dist/jsxgraph/jsxgraphcore.js' ) } );
	if ( fail ) {
		await page.evaluate( () => { window.JXG.JSXGraph.initBoard = () => { throw new Error( 'Injected renderer failure' ); }; } );
	}
	await page.addScriptTag( { path: path.join( root, 'assets/js/import-figures.js' ) } );
}

test( 'fixed JSXGraph geometry renders with scale note and printable selected SVG', async ( { page } ) => {
	await setup( page );
	await expect( page.locator( '.ohmylms-figure-board svg' ) ).toBeVisible();
	await expect( page.locator( 'figcaption' ) ).toHaveText( 'Not to scale' );
	await expect( page.locator( 'img' ) ).toBeHidden();
	await expect( page.locator( '.ohmylms-figure-static svg' ) ).toHaveCount( 1 );
	await expect( page.locator( '.ohmylms-figure-static svg' ) ).toHaveAttribute( 'viewBox', /^0 0 / );
	await page.emulateMedia( { media: 'print' } );
	await expect( page.locator( '.ohmylms-figure-static' ) ).toBeVisible();
	await expect( page.locator( '.ohmylms-figure-board' ) ).toBeHidden();
} );

test( 'navigation restores the original and remounts after browser history restoration', async ( { page } ) => {
	await setup( page );
	await expect( page.locator( '.ohmylms-figure-board svg' ) ).toBeVisible();
	await page.evaluate( () => window.dispatchEvent( new Event( 'pagehide' ) ) );
	await expect( page.locator( 'img' ) ).toBeVisible();
	await page.evaluate( () => window.dispatchEvent( new Event( 'pageshow' ) ) );
	await expect( page.locator( '.ohmylms-figure-board svg' ) ).toBeVisible();
	await expect( page.locator( '.ohmylms-figure-static' ) ).toHaveCount( 1 );
} );

test( 'renderer failure keeps the original accessible image', async ( { page } ) => {
	await setup( page, spec, true );
	await expect( page.locator( 'figure' ) ).toHaveAttribute( 'data-figure-failed', 'true' );
	await expect( page.locator( 'img' ) ).toBeVisible();
	await expect( page.locator( '.ohmylms-figure-board' ) ).toHaveCount( 0 );
} );

test( 'unknown spec version falls back and mobile figure stays within the viewport', async ( { page } ) => {
	await setup( page, { ...spec, version: 999 } );
	await expect( page.locator( 'img' ) ).toBeVisible();
	await page.setViewportSize( { width: 375, height: 700 } );
	await setup( page );
	await expect( page.locator( '.ohmylms-figure-board svg' ) ).toBeVisible();
	expect( await page.evaluate( () => document.documentElement.scrollWidth ) ).toBeLessThanOrEqual( 375 );
} );
