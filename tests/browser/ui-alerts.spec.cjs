const { test, expect } = require( '@playwright/test' );
const { spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const { modules } = require( './helpers/ui-controls.cjs' );
const root = path.resolve( __dirname, '../..' );
let gallery;
let tokens;

test.beforeAll( () => {
	const php = process.env.OHMYLMS_PHP || 'php';
	const render = ( file ) => {
		const run = spawnSync(
			php,
			[ path.join( __dirname, 'fixtures', file ) ],
			{ encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 }
		);
		expect( run.error ).toBeUndefined();
		expect( run.status, run.stderr ).toBe( 0 );
		return run.stdout;
	};
	gallery = render( 'ui-gallery.php' );
	tokens = render( 'learner-tokens.php' );
} );

test( 'PHP alerts escape content and attributes and retain accessible roles', async ( {
	page,
} ) => {
	await page.setContent( gallery );
	const area = page.locator(
		'[data-context="learner"] [data-gallery="alerts"]'
	);
	const escaped = area.locator( '[data-test="escaped-alert"]' );
	await expect( escaped ).toHaveClass( 'oml-alert oml-alert-info' );
	await expect( escaped ).toHaveAttribute( 'role', 'status' );
	await expect( escaped.locator( '.oml-alert-title' ) ).toHaveText(
		'<em>Plain text</em>'
	);
	await expect( escaped.locator( 'script, em' ) ).toHaveCount( 0 );
	await expect( escaped ).not.toHaveAttribute( 'onclick' );
	await expect( escaped ).not.toHaveAttribute( 'style' );
	await expect( area.getByRole( 'status' ) ).toHaveCount( 5 );
	await expect( area.getByRole( 'alert' ) ).toHaveClass(
		'oml-alert oml-alert-danger'
	);
} );

for ( const [ theme, device ] of [
	[ 'light', 'dark' ],
	[ 'dark', 'light' ],
	[ 'system', 'light' ],
	[ 'system', 'dark' ],
] ) {
	test( `React alerts use ${ theme } tokens on a ${ device } device and retain link focus`, async ( {
		page,
	} ) => {
		await page.emulateMedia( { colorScheme: device } );
		await page.setContent(
			gallery.replace( '</style>', tokens + '</style>' )
		);
		await page
			.locator( 'body' )
			.evaluate(
				( el, value ) => el.setAttribute( 'data-theme', value ),
				theme
			);
		await page.addScriptTag( {
			path: path.resolve(
				root,
				'../../../wp-includes/js/dist/vendor/react.js'
			),
		} );
		await page.addScriptTag( {
			path: path.resolve(
				root,
				'../../../wp-includes/js/dist/vendor/react-dom.js'
			),
		} );
		await page.evaluate(
			( sources ) => {
				const cache = {};
				function require( name ) {
					if ( name === '@wordpress/element' ) return window.React;
					const key = name
						.replace( /^\.\//, '' )
						.replace( /\.(mjs|jsx)$/, '' );
					if ( ! cache[ key ] ) {
						cache[ key ] = {};
						new Function( 'require', 'exports', sources[ key ] )(
							require,
							cache[ key ]
						);
					}
					return cache[ key ];
				}
				const target = document.querySelector(
					'[data-context="learner"]'
				);
				target.innerHTML = '<div id="react-alerts"></div>';
				const { Alert } = require( './Alert' );
				const el = window.React.createElement;
				window.ReactDOM.createRoot(
					document.getElementById( 'react-alerts' )
				).render(
					el(
						window.React.Fragment,
						null,
						...[ 'info', 'success', 'warning', 'danger' ].map(
							( tone ) =>
								el(
									Alert,
									{ key: tone, tone, title: tone },
									el(
										'a',
										{ href: '#details' },
										'View details'
									)
								)
						),
						el(
							Alert,
							{
								tone: 'danger',
								role: 'none',
								'data-test': 'static',
							},
							'Static warning'
						)
					)
				);
			},
			modules( [ 'components.mjs', 'Alert.jsx' ] )
		);
		const area = page.locator( '#react-alerts' );
		await expect( area.getByRole( 'alert' ) ).toHaveCount( 1 );
		await expect( area.getByRole( 'status' ) ).toHaveCount( 3 );
		await expect( area.locator( '[data-test="static"]' ) ).toHaveAttribute(
			'role',
			'none'
		);
		for ( const tone of [ 'info', 'success', 'warning', 'danger' ] ) {
			const alert = area.locator( '.oml-alert-' + tone ).first();
			const colors = await alert.evaluate( ( el, name ) => {
				const probe = document.createElement( 'i' );
				probe.style.backgroundColor = `var(--oml-${ name }-soft)`;
				probe.style.color = 'var(--oml-text)';
				el.appendChild( probe );
				const result = {
					background: getComputedStyle( probe ).backgroundColor,
					color: getComputedStyle( probe ).color,
				};
				probe.remove();
				return result;
			}, tone );
			await expect( alert ).toHaveCSS(
				'background-color',
				colors.background
			);
			await expect( alert ).toHaveCSS( 'color', colors.color );
		}
		await page.setViewportSize( { width: 360, height: 800 } );
		await page.locator( '[data-context="learner"]' ).evaluate( ( el ) => {
			el.style.width = 'auto';
		} );
		await area.getByRole( 'link' ).first().focus();
		await page.keyboard.press( 'Tab' );
		const focused = area.getByRole( 'link' ).nth( 1 );
		await expect( focused ).toBeFocused();
		await expect( focused ).toHaveCSS( 'outline-style', 'solid' );
		expect(
			await area.evaluate( ( el ) => el.scrollWidth <= el.clientWidth )
		).toBe( true );
	} );
}
