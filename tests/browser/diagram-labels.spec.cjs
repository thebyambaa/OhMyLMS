const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
test( 'diagram builder saves pins and labels support drag, keyboard, restore, menus and grading', async ( {
	page,
	baseURL,
} ) => {
	test.skip(
		baseURL !== 'http://127.0.0.1:8100',
		'Requires disposable migration clone'
	);
	const credentials = JSON.parse(
		fs.readFileSync( process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8' )
	);
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( credentials.username );
	await page.locator( '#user_pass' ).fill( credentials.password );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( ( u ) => ! u.pathname.endsWith( 'wp-login.php' ) );
	await page.goto( '/wp-admin/admin.php?page=ohmylms-app' );
	await page.waitForFunction( () => Boolean( window.wp?.apiFetch ) );

	test.setTimeout( 150000 );
	page.setDefaultTimeout( 15000 );
	const fixture = await page.evaluate( async () => {
		const api = ( path, data ) =>
			wp.apiFetch( { path, method: 'POST', data } );
		const q = await api( '/ohmylms/v1/questions', {
			name: 'Label the colours',
			settings: {
				type: 'labeling',
				required: true,
				image_url: `${ location.origin }/wp-content/plugins/ohmylms/tests/fixtures/diagram.svg`,
				targets: [
					{ id: 'left', x: 25, y: 50, answer: 'Улаан' },
					{ id: 'right', x: 75, y: 50, answer: 'Blue' },
				],
				distractors: [ 'Green' ],
				score: { enabled: true, value: 2 },
			},
			options: [],
		} );
		const post = await api( '/wp/v2/pages', {
			title: 'Diagram labels fixture',
			status: 'publish',
			content: `<!-- wp:ohmylms/question-player ${ JSON.stringify( { questionIds: [ q.id ], graded: true, affectsSkills: false } ) } /-->`,
		} );
		return { post: post.id, question: q.id };
	} );
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	try {
		await page.goto(
			`/wp-admin/post.php?post=${ fixture.question }&action=edit`
		);
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await canvas
			.getByRole( 'textbox', { name: 'Question statement', exact: true } )
			.click();
		await expect(
			canvas.getByLabel( 'Correct label', { exact: true } ).first()
		).toHaveValue( 'Улаан' );
		await canvas
			.getByRole( 'button', {
				name: 'Place labels full screen',
				exact: true,
			} )
			.click();
		// The editor modal is portalled to the main document.
		const popup = page.getByRole( 'dialog', {
			name: 'Place labels',
			exact: true,
		} );
		await popup
			.getByRole( 'button', { name: 'Label 1', exact: true } )
			.focus();
		await page.keyboard.press( 'ArrowRight' );
		await popup
			.getByRole( 'button', { name: 'Done', exact: true } )
			.click();
		await canvas
			.getByLabel( 'Distractor labels', { exact: true } )
			.fill( 'Yellow' );
		await canvas
			.getByLabel( 'Distractor labels', { exact: true } )
			.press( 'Enter' );
		await page.keyboard.press( 'Control+s' );
		await expect
			.poll( () =>
				page.evaluate(
					async ( id ) =>
						(
							await wp.apiFetch( {
								path: `/ohmylms/v1/questions/${ id }`,
							} )
						).settings.targets[ 0 ].x,
					fixture.question
				)
			)
			.toBe( 26 );
		await page.reload();
		await canvas
			.getByRole( 'textbox', { name: 'Question statement', exact: true } )
			.click();
		await expect(
			canvas.getByLabel( 'X (%)', { exact: true } ).first()
		).toHaveValue( '26' );
		await page.evaluate(
			async ( id ) =>
				wp.apiFetch( {
					path: `/ohmylms/v1/questions/${ id }/approve`,
					method: 'POST',
					data: {},
				} ),
			fixture.question
		);
		await page.goto( `/?page_id=${ fixture.post }` );
		await page
			.getByRole( 'button', { name: 'Start questions', exact: true } )
			.click();
		const widget = page.locator( '[data-ohmylms-interactive="labeling"]' );
		await expect( widget ).toHaveAttribute( 'data-labels-ready', '1' );
		await expect(
			widget.locator( '.ohmylms-label-bank button' )
		).toHaveCount( 4 );
		await expect(
			widget.locator( '.ohmylms-label-pin' ).first()
		).toHaveAttribute( 'data-label', '' );
		const bank = widget.getByRole( 'group', {
			name: 'Available diagram labels',
			exact: true,
		} );
		await bank
			.getByRole( 'button', { name: 'Улаан', exact: true } )
			.dragTo( widget.locator( '[data-label-target="left"]' ) );
		await expect(
			widget.locator( '[data-label-target="left"]' )
		).toHaveAttribute( 'data-label', 'Улаан' );
		await bank.getByRole( 'button', { name: 'Blue', exact: true } ).focus();
		await page.keyboard.press( 'Enter' );
		await widget.locator( '[data-label-target="right"]' ).focus();
		await page.keyboard.press( 'Enter' );
		await expect( widget.getByRole( 'status' ) ).toHaveText(
			'2 / 2 pins labelled.'
		);
		await page.reload();
		await expect(
			widget.locator( '[data-label-target="left"]' )
		).toHaveAttribute( 'data-label', 'Улаан' );
		await expect(
			widget.locator( '[data-label-target="right"]' )
		).toHaveAttribute( 'data-label', 'Blue' );
		await widget.locator( '[data-label-target="right"]' ).focus();
		await page.keyboard.press( 'Delete' );
		await widget.getByText( 'Use label menus', { exact: true } ).click();
		await widget
			.getByLabel( 'Pin 2', { exact: true } )
			.selectOption( 'Blue' );
		await widget
			.getByRole( 'button', {
				name: 'View image full screen',
				exact: true,
			} )
			.click();
		const full = page.getByRole( 'dialog', {
			name: 'Question diagram',
			exact: true,
		} );
		await expect(
			full.getByRole( 'button', { name: 'Clear labels', exact: true } )
		).toBeVisible();
		await full.locator( '[data-label-target="left"]' ).focus();
		await page.keyboard.press( 'Delete' );
		await full
			.getByRole( 'button', { name: 'Улаан', exact: true } )
			.click();
		await full.locator( '[data-label-target="left"]' ).click();
		await page.keyboard.press( 'Escape' );
		await expect(
			widget.getByRole( 'button', {
				name: 'View image full screen',
				exact: true,
			} )
		).toBeFocused();
		await page
			.locator( '.ohmylms-question-player' )
			.screenshot( { path: 'test-results/diagram-labels-desktop.png' } );
		await page.setViewportSize( { width: 390, height: 844 } );
		await widget
			.getByRole( 'button', { name: 'Clear labels', exact: true } )
			.click();
		await bank
			.getByRole( 'button', { name: 'Улаан', exact: true } )
			.click();
		await widget.locator( '[data-label-target="left"]' ).click();
		await bank.getByRole( 'button', { name: 'Blue', exact: true } ).click();
		await widget.locator( '[data-label-target="right"]' ).click();
		await page
			.locator( '.ohmylms-question-player' )
			.screenshot( { path: 'test-results/diagram-labels-mobile.png' } );
		await page
			.getByRole( 'button', { name: 'Submit responses', exact: true } )
			.click();
		await expect(
			page.getByText( 'Score: 2 / 2', { exact: true } )
		).toBeVisible();
		await page
			.getByRole( 'button', { name: 'Play again', exact: true } )
			.click();
		await expect(
			widget.locator( '[data-label-target="left"]' )
		).toHaveAttribute( 'data-label', '' );
		// A formal quiz restore uses these same fields and event.
		await widget.evaluate( ( root ) => {
			root.querySelector( 'input[data-answer-key="left"]' ).value =
				'Улаан';
			root.closest( 'form' ).dispatchEvent(
				new CustomEvent( 'ohmylms:answer-restored', {
					detail: { questionId: root.dataset.questionId },
				} )
			);
		} );
		await expect(
			widget.locator( '[data-label-target="left"]' )
		).toHaveAttribute( 'data-label', 'Улаан' );
		await page.route( '**/diagram-labels.mjs*', ( route ) =>
			route.abort()
		);
		await page.reload();
		await expect(
			widget.locator( '.ohmylms-diagram-stage select' )
		).toHaveCount( 2 );
		await widget
			.getByLabel( 'Diagram label left', { exact: true } )
			.selectOption( 'Улаан' );
		await widget
			.getByLabel( 'Diagram label right', { exact: true } )
			.selectOption( 'Blue' );
		await page
			.getByRole( 'button', { name: 'Submit responses', exact: true } )
			.click();
		await expect(
			page.getByText( 'Score: 2 / 2', { exact: true } )
		).toBeVisible();
		expect( errors ).toEqual( [] );
	} finally {
		await page.goto( '/wp-admin/admin.php?page=ohmylms-app' );
		await page.waitForFunction( () => Boolean( window.wp?.apiFetch ) );
		await page.evaluate( async ( { post, question } ) => {
			await wp.apiFetch( {
				path: `/wp/v2/pages/${ post }?force=true`,
				method: 'DELETE',
			} );
			await wp.apiFetch( {
				path: `/ohmylms/v1/questions/${ question }`,
				method: 'DELETE',
			} );
		}, fixture );
	}
} );
