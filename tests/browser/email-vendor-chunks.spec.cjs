const { test, expect } = require( '@playwright/test' );
const { harness } = require( './helpers/recovered-runtime.cjs' );

test( 'email libraries stay out of startup and load through the production chunk loader', async ( {
	page,
} ) => {
	const { requests, errors } = await harness( page );
	expect( requests.some( ( url ) => url.includes( '/chunks/355' ) ) ).toBe(
		false
	);
	expect(
		await page.evaluate( () =>
			[ 3270, 50587, 44098, 47909, 58088, 43203, 17190 ].every(
				( id ) => ! window.testRequire.m[ id ]
			)
		)
	).toBe( true );
	expect(
		await page.evaluate( () => {
			const model = window.testRequire( 49050 );
			return typeof model.JsonToMjml;
		} )
	).toBe( 'function' );
	await page.evaluate( async () => {
		await window.testRequire.e( 355 );
		const design = window.testRequire( 3270 );
		const capture = window.testRequire( 50587 );
		window.libraryResults = {
			version: design.version,
			capture: typeof capture.default,
		};
		ReactDOM.createRoot( document.getElementById( 'app' ) ).render(
			React.createElement(
				design.Button,
				{
					onClick: () => {
						document.title = 'clicked';
					},
				},
				'Email designer control'
			)
		);
	} );
	await page
		.getByRole( 'button', { name: 'Email designer control' } )
		.click();
	await expect( page ).toHaveTitle( 'clicked' );
	expect( await page.evaluate( () => window.libraryResults ) ).toEqual( {
		version: '2.66.5',
		capture: 'function',
	} );
	await page.evaluate( () => window.testRequire.e( 355 ) );
	expect(
		requests.filter( ( url ) =>
			/\/chunks\/355\.js\?ver=[a-f0-9]{12}$/.test( url )
		)
	).toHaveLength( 1 );
	expect( errors ).toEqual( [] );
} );

test( 'saved email JSON produces the same HTML after deferred loading', async ( {
	page,
} ) => {
	await harness( page );
	const renderSavedTemplate = () =>
		page.evaluate( async () => {
			const model = window.testRequire( 49050 );
			const template = window.testRequire( 87381 ).defaultEmailTemplate;
			const original = JSON.stringify( template );
			await window.testRequire.e( 355 );
			const renderer = window.testRequire( 44098 );
			const render = renderer.default || renderer;
			const content = template.json_content.content;
			const mjml = model.JsonToMjml( {
				data: content,
				context: content,
				mode: 'production',
			} );
			const html = render( mjml, {
				beautify: true,
				validationLevel: 'soft',
			} ).html;
			return {
				html,
				unchanged: original === JSON.stringify( template ),
				converted: typeof model.MjmlToJson( mjml ),
			};
		} );
	const result = await renderSavedTemplate();
	expect( result.html ).toContain( '<!doctype html>' );
	expect( result.html ).toContain( '<table' );
	expect( result.unchanged ).toBe( true );
	expect( result.converted ).toBe( 'object' );
	await harness( page, false, true );
	const baseline = await renderSavedTemplate();
	expect( result.html ).toBe( baseline.html );
} );

test( 'the email chunk can be retried after a network failure', async ( {
	page,
} ) => {
	await harness( page, true );
	const failure = await page.evaluate( () =>
		window.testRequire.e( 355 ).then(
			() => '',
			( error ) => error.name
		)
	);
	expect( failure ).toBe( 'ChunkLoadError' );
	expect(
		await page.evaluate( async () => {
			await window.testRequire.e( 355 );
			return typeof window.testRequire( 50587 ).default;
		} )
	).toBe( 'function' );
} );
