const { test, expect } = require( '@playwright/test' );
const { spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const { modules } = require( './helpers/ui-controls.cjs' );
const root = path.resolve( __dirname, '../..' );
let gallery;
let themeTokens;
test.beforeAll( () => {
	const result = spawnSync(
		process.env.OHMYLMS_PHP || 'php',
		[ path.join( __dirname, 'fixtures/ui-gallery.php' ) ],
		{ encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 }
	);
	expect( result.status, result.stderr ).toBe( 0 );
	gallery = result.stdout;
	const themes = spawnSync(
		process.env.OHMYLMS_PHP || 'php',
		[ path.join( __dirname, 'fixtures/learner-tokens.php' ) ],
		{ encoding: 'utf8' }
	);
	expect( themes.status, themes.stderr ).toBe( 0 );
	themeTokens = themes.stdout;
} );

for ( const [ theme, device ] of [
	[ 'light', 'dark' ],
	[ 'dark', 'light' ],
	[ 'system', 'light' ],
	[ 'system', 'dark' ],
] ) {
	test( `shared panels, table, empty state and modal use ${ theme } tokens on a ${ device } device`, async ( {
		page,
	} ) => {
		await page.emulateMedia( { colorScheme: device } );
		await page.setViewportSize( { width: 360, height: 640 } );
		await page.setContent(
			gallery.replace( '</style>', themeTokens + '</style>' )
		);
		await page
			.locator( 'body' )
			.evaluate(
				( el, theme ) => el.setAttribute( 'data-theme', theme ),
				theme
			);
		await page.addScriptTag( {
			path: path.join( root, 'assets/js/ui.js' ),
		} );
		const area = page.locator( '[data-context="learner"]' );
		await area.evaluate( ( el ) => {
			el.style.width = 'auto';
		} );
		const surface = await area.evaluate( ( el ) => {
			const probe = document.createElement( 'i' );
			probe.style.backgroundColor = 'var(--oml-surface)';
			el.appendChild( probe );
			const color = getComputedStyle( probe ).backgroundColor;
			probe.remove();
			return color;
		} );
		for ( const selector of [ '.oml-table', '.oml-empty-state' ] ) {
			await expect( area.locator( selector ) ).toHaveCSS(
				'background-color',
				surface
			);
		}
		await expect( area.locator( '.oml-tab-panel[hidden]' ) ).toHaveCount(
			2
		);
		const scroll = area.getByRole( 'region', { name: 'Courses' } );
		await expect( scroll ).toHaveAttribute( 'tabindex', '0' );
		await area
			.getByRole( 'button', { name: 'Open dialog', exact: true } )
			.click();
		const dialog = page.getByRole( 'dialog', { name: 'Course settings' } );
		await expect( dialog ).toHaveCSS( 'background-color', surface );
		expect( ( await dialog.boundingBox() ).width ).toBeLessThanOrEqual(
			360
		);
		await page.keyboard.press( 'Escape' );
		await expect( dialog ).not.toBeVisible();
	} );
}

test( 'PHP tabs support keyboard activation, disabled tabs and unique panels; dialogs restore focus', async ( {
	page,
} ) => {
	await page.setContent( gallery );
	await page.addScriptTag( { path: path.join( root, 'assets/js/ui.js' ) } );
	const area = page.locator( '[data-context="learner"]' );
	const first = area.getByRole( 'tab', { name: 'Overview' } );
	await first.focus();
	await page.keyboard.press( 'ArrowRight' );
	await expect( area.getByRole( 'tab', { name: 'Lessons' } ) ).toBeFocused();
	await expect( area.getByRole( 'tabpanel' ) ).toHaveText(
		'Course lessons.'
	);
	await page.keyboard.press( 'Home' );
	await expect( first ).toBeFocused();
	await expect( area.getByRole( 'tab', { name: 'Locked' } ) ).toBeDisabled();
	const ids = await page
		.locator( '[role="tab"], [role="tabpanel"]' )
		.evaluateAll( ( nodes ) => nodes.map( ( el ) => el.id ) );
	expect( new Set( ids ).size ).toBe( ids.length );
	const open = area.getByRole( 'button', {
		name: 'Open dialog',
		exact: true,
	} );
	await open.click();
	const dialog = page.getByRole( 'dialog', { name: 'Course settings' } );
	await expect( dialog ).toBeVisible();
	await expect(
		dialog.getByRole( 'button', { name: 'Close', exact: true } )
	).toBeFocused();
	await page.keyboard.press( 'Shift+Tab' );
	await expect(
		dialog.getByRole( 'textbox', { name: 'Course name' } )
	).toBeFocused();
	await page.keyboard.press( 'Escape' );
	await expect( dialog ).not.toBeVisible();
	await expect( open ).toBeFocused();
	await open.click();
	await dialog.getByRole( 'button', { name: 'Close', exact: true } ).click();
	await expect( dialog ).not.toBeVisible();
	await expect( open ).toBeFocused();
} );

test( 'React shared panels, table, empty state and controlled dialog work together', async ( {
	page,
} ) => {
	await page.setContent( gallery );
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
				if ( name === '@wordpress/i18n' )
					return { __: ( text ) => text };
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
			const { Tabs } = require( './Tabs' );
			const { Modal } = require( './Modal' );
			const { Table } = require( './Table' );
			const { EmptyState } = require( './EmptyState' );
			const el = window.React.createElement;
			function Demo() {
				const [ open, setOpen ] = window.React.useState( false );
				return el(
					'div',
					null,
					el( Tabs, {
						label: 'Local panels',
						items: [
							{ id: 'one', label: 'One', content: 'First panel' },
							{
								id: 'locked',
								label: 'Locked',
								disabled: true,
								content: 'Locked',
							},
							{
								id: 'two',
								label: 'Two',
								content: 'Second panel',
							},
						],
					} ),
					el(
						Table,
						{ caption: 'Courses', striped: true },
						el(
							'thead',
							null,
							el(
								'tr',
								null,
								el( 'th', { scope: 'col' }, 'Title' )
							)
						),
						el(
							'tbody',
							null,
							el( 'tr', null, el( 'td', null, 'Mathematics' ) )
						)
					),
					el( EmptyState, {
						title: 'No results',
						description: 'Try a different search.',
					} ),
					el(
						'button',
						{ onClick: () => setOpen( true ) },
						'Open React dialog'
					),
					open &&
						el(
							Modal,
							{
								open,
								title: 'React dialog',
								onRequestClose: () => setOpen( false ),
							},
							el( 'button', null, 'Dialog action' )
						)
				);
			}
			const target = document.querySelector( '[data-context="learner"]' );
			target.innerHTML = '';
			window.ReactDOM.createRoot( target ).render( el( Demo ) );
		},
		modules( [
			'navigation.mjs',
			'button.mjs',
			'Button.jsx',
			'Tabs.jsx',
			'Modal.jsx',
			'Table.jsx',
			'EmptyState.jsx',
		] )
	);
	const area = page.locator( '[data-context="learner"]' );
	const one = area.getByRole( 'tab', { name: 'One' } );
	await one.focus();
	await page.keyboard.press( 'End' );
	await expect( area.getByRole( 'tab', { name: 'Two' } ) ).toBeFocused();
	await expect( area.getByRole( 'tabpanel' ) ).toHaveText( 'Second panel' );
	await page.keyboard.press( 'ArrowRight' );
	await expect( one ).toBeFocused();
	await area.evaluate( ( el ) => ( el.dir = 'rtl' ) );
	await page.keyboard.press( 'ArrowLeft' );
	await expect( area.getByRole( 'tab', { name: 'Two' } ) ).toBeFocused();
	await expect(
		area.getByRole( 'table', { name: 'Courses' } )
	).toBeVisible();
	await expect(
		area.getByText( 'No results', { exact: true } )
	).toBeVisible();
	const open = area.getByRole( 'button', {
		name: 'Open React dialog',
		exact: true,
	} );
	await open.click();
	const dialog = page.getByRole( 'dialog', { name: 'React dialog' } );
	await expect( dialog ).toBeVisible();
	await page.keyboard.press( 'Escape' );
	await expect( dialog ).not.toBeVisible();
	await expect( open ).toBeFocused();
	await open.click();
	await dialog.getByRole( 'button', { name: 'Close', exact: true } ).click();
	await expect( dialog ).not.toBeVisible();
} );
