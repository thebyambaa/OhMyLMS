const { test, expect } = require( '@playwright/test' );
const { spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { mountControls } = require( './helpers/ui-controls.cjs' );
const root = path.resolve( __dirname, '../..' );
const css = fs.readFileSync(
	path.join( root, 'assets/css/oml-ui.css' ),
	'utf8'
);
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
async function render( page, theme = 'light' ) {
	await page.setContent(
		`<style>${ tokens }${ css }body{margin:24px;background:var(--oml-background)}#react-root{max-width:500px}form{display:grid;gap:16px}</style><body data-theme="${ theme }"><main id="react-root"></main></body>`
	);
	await mountControls( page );
}
async function token( locator, property, name ) {
	return locator.evaluate(
		( el, [ property, name ] ) => {
			const probe = document.createElement( 'i' );
			probe.style[ property ] = `var(${ name })`;
			el.parentElement.appendChild( probe );
			const value = getComputedStyle( probe )[ property ];
			probe.remove();
			return value;
		},
		[ property, name ]
	);
}
test( 'React fields retain native validation, descriptions, ref access and form data', async ( {
	page,
} ) => {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await render( page );
	const email = page.getByRole( 'textbox', { name: 'Email', exact: true } );
	await page
		.locator( 'label' )
		.filter( { hasText: /^Email/ } )
		.click();
	await expect( email ).toBeFocused();
	await email.fill( 'bad' );
	await expect( email ).toHaveAttribute( 'aria-invalid', 'true' );
	const associations = await email.evaluate( ( el ) =>
		( el.getAttribute( 'aria-describedby' ) || '' )
			.split( ' ' )
			.map( ( id ) => document.getElementById( id )?.textContent )
	);
	expect( associations ).toEqual( [
		'Additional guidance.',
		'School email address.',
		'Enter a valid email.',
	] );
	await expect( page.getByRole( 'alert' ) ).toHaveText(
		'Enter a valid email.'
	);
	await page.getByRole( 'button', { name: 'Submit', exact: true } ).click();
	expect(
		await page.evaluate( () => window.controlSubmission )
	).toBeUndefined();
	await email.fill( 'sora@example.com' );
	await expect( email ).not.toHaveAttribute( 'aria-invalid' );
	await expect( page.getByRole( 'alert' ) ).toHaveCount( 0 );
	await page
		.getByRole( 'combobox', { name: 'Course', exact: true } )
		.selectOption( 'math' );
	await page
		.getByLabel( 'Subjects', { exact: true } )
		.selectOption( [ 'math', 'science' ] );
	await expect( page.getByLabel( 'Reference' ) ).not.toBeEditable();
	await expect( page.getByLabel( 'Unavailable' ) ).toBeDisabled();
	await page.getByRole( 'button', { name: 'Submit', exact: true } ).click();
	expect( await page.evaluate( () => window.controlSubmission ) ).toEqual( [
		[ 'email', 'sora@example.com' ],
		[ 'course', 'math' ],
		[ 'subjects[]', 'math' ],
		[ 'subjects[]', 'science' ],
		[ 'reference', '1024' ],
		[ 'due', '2026-11-01' ],
	] );
	await page.evaluate( () => window.controlRef.current.focus() );
	await expect( email ).toBeFocused();
	const ids = await page
		.locator( '.oml-control' )
		.evaluateAll( ( fields ) => fields.map( ( el ) => el.id ) );
	expect( new Set( ids ).size ).toBe( ids.length );
	expect( errors ).toEqual( [] );
} );
for ( const [ theme, device ] of [
	[ 'light', 'dark' ],
	[ 'dark', 'light' ],
	[ 'system', 'light' ],
	[ 'system', 'dark' ],
] ) {
	test( `native controls follow ${ theme } on a ${ device } device`, async ( {
		page,
	} ) => {
		await page.emulateMedia( { colorScheme: device } );
		await render( page, theme );
		const input = page.getByRole( 'textbox', {
				name: 'Email',
				exact: true,
			} ),
			select = page.getByRole( 'combobox', {
				name: 'Course',
				exact: true,
			} );
		for ( const field of [ input, select ] ) {
			await expect( field ).toHaveCSS(
				'background-color',
				await token( field, 'backgroundColor', '--oml-surface' )
			);
			await expect( field ).toHaveCSS(
				'color',
				await token( field, 'color', '--oml-text' )
			);
			await expect( field ).toHaveCSS(
				'color-scheme',
				theme === 'dark' || ( theme === 'system' && device === 'dark' )
					? 'dark'
					: 'light'
			);
		}
		await input.fill( 'bad' );
		await input.focus();
		await expect( input ).toHaveCSS(
			'border-top-color',
			await token( input, 'color', '--oml-danger' )
		);
		await expect( input ).toHaveCSS(
			'outline-color',
			await token( input, 'color', '--oml-focus' )
		);
		await expect( page.getByLabel( 'Unavailable' ) ).toHaveCSS(
			'background-color',
			await token( input, 'backgroundColor', '--oml-subtle' )
		);
	} );
}
test( 'controls keep token styling with WordPress admin form CSS', async ( {
	page,
} ) => {
	await render( page );
	const forms = fs.readFileSync(
		path.resolve( root, '../../../wp-admin/css/forms.css' ),
		'utf8'
	);
	await page.addStyleTag( { content: forms } );
	await page
		.locator( 'body' )
		.evaluate( ( el ) => el.classList.add( 'wp-admin', 'wp-core-ui' ) );
	const email = page.getByRole( 'textbox', { name: 'Email', exact: true } );
	await email.fill( 'bad' );
	await email.focus();
	await expect( email ).toHaveCSS(
		'background-color',
		await token( email, 'backgroundColor', '--oml-surface' )
	);
	await expect( email ).toHaveCSS(
		'border-top-color',
		await token( email, 'color', '--oml-danger' )
	);
	await expect( email ).toHaveCSS( 'box-shadow', 'none' );
} );
test( 'system switching and reduced motion apply without remounting fields', async ( {
	page,
} ) => {
	await page.emulateMedia( { colorScheme: 'light' } );
	await render( page, 'system' );
	const email = page.getByRole( 'textbox', { name: 'Email', exact: true } );
	const light = await email.evaluate(
		( el ) => getComputedStyle( el ).backgroundColor
	);
	await email.fill( 'sora@example.com' );
	await page.emulateMedia( { colorScheme: 'dark', reducedMotion: 'reduce' } );
	await expect( email ).toHaveCSS(
		'background-color',
		await token( email, 'backgroundColor', '--oml-surface' )
	);
	expect(
		await email.evaluate( ( el ) => getComputedStyle( el ).backgroundColor )
	).not.toBe( light );
	await expect( email ).toHaveValue( 'sora@example.com' );
	await expect( email ).toHaveCSS( 'transition-duration', '0s' );
} );
