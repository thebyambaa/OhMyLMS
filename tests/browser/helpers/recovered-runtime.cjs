const { expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { parse, parseExpression } = require( '@babel/parser' );
const traverse = require( '@babel/traverse' ).default;
const generate = require( '@babel/generator' ).default;

const root = path.resolve( __dirname, '../../..' );
const wordpress = path.resolve( root, '../../../wp-includes/js/dist/vendor' );

async function harness(
	page,
	failFirst = false,
	baselineVendor = false,
	baselineEditor = false
) {
	// Run the shipped Webpack loader, replacing only application startup in memory.
	// No login or database is needed to exercise real lazy chunks and real UI libraries.
	const ast = parse(
		fs.readFileSync(
			path.join( root, 'assets/dist/admin/ohmylms.js' ),
			'utf8'
		)
	);
	let entries = 0;
	traverse( ast, {
		ObjectProperty( property ) {
			if (
				property.node.key.value === 1841 &&
				property.node.value.type === 'ArrowFunctionExpression'
			) {
				property.node.value = parseExpression(
					'(module,exports,require)=>{window.testRequire=require}'
				);
				entries++;
				property.skip();
			}
		},
	} );
	expect( entries ).toBe( 1 );
	const application = generate( ast, { compact: true } ).code;
	const requests = [];
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.addInitScript( () => {
		window.scriptErrors = [];
		window.addEventListener( 'error', ( event ) =>
			window.scriptErrors.push( {
				message: event.message,
				file: event.filename,
				line: event.lineno,
			} )
		);
	} );
	let failures = failFirst ? 1 : 0;
	await page.route( 'https://ohmylms.test/**', async ( route ) => {
		const url = new URL( route.request().url() );
		if ( url.pathname === '/' )
			return route.fulfill( {
				contentType: 'text/html',
				body: `<!doctype html><html><head><meta charset="utf-8"></head><body><div id="app"></div>
        <script src="/react.js"></script><script src="/react-dom.js"></script>
        <script>window.wp={element:{...React,...ReactDOM}};</script>
        <script src="/plugin/assets/dist/admin/editor-lowlight.js"></script>
        <script src="/plugin/assets/dist/admin/editor-tiptap.js"></script>
        <script src="/plugin/assets/dist/vendors/vendors.js"></script>
        <script src="/plugin/assets/dist/vendors/editor.js"></script>
        <script src="/plugin/assets/dist/vendors/emotion.js"></script>
        <script src="/plugin/assets/dist/admin/ohmylms.js"></script>
        </body></html>`,
			} );
		if ( [ '/react.js', '/react-dom.js' ].includes( url.pathname ) )
			return route.fulfill( {
				contentType: 'application/javascript',
				path: path.join( wordpress, url.pathname.slice( 1 ) ),
			} );
		requests.push( url.pathname + url.search );
		if ( url.pathname.endsWith( '/admin/ohmylms.js' ) )
			return route.fulfill( {
				contentType: 'application/javascript; charset=utf-8',
				body: application,
			} );
		if (
			( baselineVendor &&
				url.pathname.endsWith( '/vendors/vendors.js' ) ) ||
			( baselineEditor && url.pathname.endsWith( '/vendors/editor.js' ) )
		) {
			const baselineFile = path.join( root,
				`build/parity/assets/dist/vendors/${ baselineEditor && url.pathname.endsWith( '/editor.js' ) ? 'editor' : 'vendors' }.js` );
			let body = fs.readFileSync( baselineFile, 'utf8' );
			if ( baselineEditor && baselineFile.endsWith( 'vendors.js' ) ) {
				// Preserve the pre-migration production deduplication adapter.
				const { adaptVendors } = await import( '../../../tools/vendor-adapters.mjs' );
				const baseline = parse( body );
				adaptVendors( baseline );
				body = generate( baseline, { compact: true } ).code;
			}
			return route.fulfill( { body, contentType: 'application/javascript; charset=utf-8' } );
		}
		if ( url.pathname.endsWith( '/chunks/355.js' ) && failures-- > 0 )
			return route.abort();
		const relative = url.pathname.replace( /^\/plugin\//, '' );
		if (
			! relative.startsWith( 'assets/dist/' ) ||
			relative.includes( '..' )
		)
			return route.abort();
		return route.fulfill( {
			path: path.join( root, relative ),
			contentType: relative.endsWith( '.css' )
				? 'text/css'
				: 'application/javascript; charset=utf-8',
		} );
	} );
	await page.goto( 'https://ohmylms.test/' );
	expect( await page.evaluate( () => window.scriptErrors ) ).toEqual( [] );
	await page.waitForFunction( () => window.testRequire, undefined, {
		timeout: 10000,
	} );
	return { requests, errors };
}

module.exports = { harness };
