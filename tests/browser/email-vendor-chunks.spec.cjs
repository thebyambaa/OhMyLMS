const { test, expect } = require( '@playwright/test' );
const { harness } = require( './helpers/recovered-runtime.cjs' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const chunkDirectory = path.resolve(
	__dirname,
	'../../assets/dist/admin/chunks'
);
const packageChunks = fs
	.readdirSync( chunkDirectory )
	.filter( ( file ) => {
		if ( ! file.endsWith( '.js.map' ) ) return false;
		const map = JSON.parse(
			fs.readFileSync( path.join( chunkDirectory, file ), 'utf8' )
		);
		return map.sources.some(
			( source ) =>
				/node_modules\/(?:@arco-design\/web-react|mjml-browser)\//.test(
					source
				) || source.endsWith( '/features/vendor-packages/email.js' )
		);
	} )
	.map( ( file ) => file.slice( 0, -4 ) );

test( 'email libraries stay out of startup and load through the production chunk loader', async ( {
	page,
} ) => {
	const { requests, errors } = await harness( page );
	expect( packageChunks.length ).toBeGreaterThanOrEqual( 2 );
	expect(
		requests.some( ( url ) =>
			packageChunks.some( ( name ) => url.includes( name ) )
		)
	).toBe( false );
	expect(
		await page.evaluate( () => typeof window.ohmylmsEmailPackages )
	).toBe( 'undefined' );
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
	for ( const name of packageChunks )
		expect(
			requests.filter( ( url ) => url.includes( name ) )
		).toHaveLength( 1 );
	expect(
		requests.filter( ( url ) =>
			/\/chunks\/355\.js\?ver=[a-f0-9]{12}$/.test( url )
		)
	).toHaveLength( 1 );
	expect( errors ).toEqual( [] );
} );

test( 'npm email package downloads are shared and retry after failure', async ( {
	page,
} ) => {
	await harness( page );
	let failures = 1;
	let downloads = 0;
	await page.route( '**/plugin/assets/dist/admin/chunks/*.js', ( route ) => {
		const name = new URL( route.request().url() ).pathname
			.split( '/' )
			.pop();
		if ( ! packageChunks.includes( name ) ) return route.fallback();
		downloads++;
		if ( failures-- > 0 ) return route.abort();
		return route.fallback();
	} );
	expect(
		await page.evaluate( () =>
			window.testRequire.e( 355 ).then(
				() => '',
				( error ) => error.name
			)
		)
	).toBe( 'ChunkLoadError' );
	await page.evaluate( () =>
		Promise.all( [
			window.testRequire.e( 355 ),
			window.testRequire.e( 355 ),
		] )
	);
	expect(
		await page.evaluate( () => ( {
			arco:
				window.testRequire( 3270 ).Button ===
				window.ohmylmsEmailPackages.arco.Button,
			mjml:
				window.testRequire( 44098 ) ===
				window.ohmylmsEmailPackages.mjml,
		} ) )
	).toEqual( { arco: true, mjml: true } );
	expect( downloads ).toBe( packageChunks.length + 1 );
} );

test( 'npm Arco preserves exports and interactive email designer controls', async ( {
	page,
} ) => {
	test.setTimeout( 45000 );
	async function sample( baseline ) {
		const { errors } = await harness(
			page,
			false,
			baseline,
			false,
			false,
			baseline
		);
		await page.addStyleTag( {
			content: fs.readFileSync(
				path.resolve(
					__dirname,
					'../../assets/src/admin-styles/main-087-email-editor.css'
				),
				'utf8'
			),
		} );
		const keys = await page.evaluate( async () => {
			await window.testRequire.e( 355 );
			const arco = window.testRequire( 3270 );
			function Demo() {
				const [ visible, setVisible ] = React.useState( false );
				return React.createElement(
					arco.ConfigProvider,
					{ prefixCls: 'arco' },
					React.createElement( arco.Input, {
						'aria-label': 'Subject',
						onChange: ( value ) => {
							window.subjectValue = value;
						},
					} ),
					React.createElement( arco.Select, {
						placeholder: 'Choose layout',
						options: [
							{ value: 'one', label: 'One column' },
							{ value: 'two', label: 'Two columns' },
						],
						onChange: ( value ) => {
							window.layoutValue = value;
						},
					} ),
					React.createElement( arco.Switch, {
						'aria-label': 'Enabled',
						onChange: ( value ) => {
							window.enabledValue = value;
						},
					} ),
					React.createElement(
						arco.Button,
						{ onClick: () => setVisible( true ) },
						'Open settings'
					),
					React.createElement(
						arco.Modal,
						{
							title: 'Email settings',
							okText: 'Apply',
							cancelText: 'Cancel',
							visible,
							onOk: () => setVisible( false ),
							onCancel: () => setVisible( false ),
							focusLock: true,
						},
						'Modal content'
					)
				);
			}
			ReactDOM.createRoot( document.getElementById( 'app' ) ).render(
				React.createElement( Demo )
			);
			return Object.keys( arco ).sort();
		} );
		await page
			.getByRole( 'textbox', { name: 'Subject' } )
			.fill( 'Монгол course update' );
		await page.getByRole( 'combobox' ).click( { timeout: 10000 } );
		await page.getByText( 'Two columns', { exact: true } ).click();
		await page.getByRole( 'switch', { name: 'Enabled' } ).click();
		await page.getByRole( 'button', { name: 'Open settings' } ).click();
		await expect( page.getByRole( 'dialog' ) ).toContainText(
			'Modal content'
		);
		await page
			.getByRole( 'button', { name: 'Apply', exact: true } )
			.click();
		await expect( page.getByRole( 'dialog' ) ).toHaveCount( 0 );
		expect( errors ).toEqual( [] );
		return {
			keys,
			values: await page.evaluate( () => ( {
				subject: window.subjectValue,
				layout: window.layoutValue,
				enabled: window.enabledValue,
			} ) ),
		};
	}
	const migrated = await sample( false );
	expect( migrated.values ).toEqual( {
		subject: 'Монгол course update',
		layout: 'two',
		enabled: true,
	} );
	expect( migrated ).toEqual( await sample( true ) );
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
	await harness( page, false, true, false, false, true );
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
