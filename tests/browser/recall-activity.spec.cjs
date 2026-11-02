const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
test( 'memory recall saves study deadline, choices and practice-only feedback', async ( {
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
	const fixture = await page.evaluate( async () => {
		const api = ( path, data ) =>
			wp.apiFetch( { path, method: 'POST', data } );
		const q = await api( '/ohmylms/v1/questions', {
			name: 'Recall browser fixture',
			settings: { type: 'matching', score: { enabled: true, value: 1 } },
			options: [ 'Үүл', 'Rain', 'Sun', 'Snow' ].map( ( answer ) => ( {
				answer,
				matching_data: { label: `Private ${ answer }` },
			} ) ),
		} );
		await api( `/ohmylms/v1/questions/${ q.id }/approve`, {} );
		const post = await api( '/wp/v2/pages', {
			title: 'Recall browser fixture',
			status: 'publish',
			content: `<!-- wp:ohmylms/question-player ${ JSON.stringify( { source: 'selected', questionIds: [ q.id ], activityMode: 'memory-recall', rememberCount: 2, studySeconds: 5, graded: true, affectsSkills: true } ) } /-->`,
		} );
		return { question: q.id, post: post.id };
	} );
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	const stage = page.locator( '.ohmylms-question-player-stage' );
	const idle = () =>
		expect(
			page.locator( '.ohmylms-question-player' )
		).not.toHaveAttribute( 'aria-busy', 'true' );
	try {
		await page.goto( `/?page_id=${ fixture.post }` );
		await page
			.getByRole( 'button', { name: 'Start questions', exact: true } )
			.click();
		await expect( stage.locator( '.ohmylms-study-cards' ) ).toHaveCount(
			0
		);
		await stage
			.getByRole( 'button', { name: 'Begin study', exact: true } )
			.click();
		await expect( stage.locator( '.ohmylms-recall-study li' ) ).toHaveCount(
			2
		);
		const targets = await stage
			.locator( '.ohmylms-recall-study li' )
			.allTextContents();
		await page.reload();
		await expect(
			stage.getByRole( 'button', { name: 'Check recall', exact: true } )
		).toBeVisible( { timeout: 12000 } );
		await expect( stage.locator( '.ohmylms-recall-study' ) ).toHaveCount(
			0
		);
		await expect(
			stage.getByText( 'Shown during study', { exact: true } )
		).toHaveCount( 0 );
		for ( const text of targets ) {
			await stage
				.getByRole( 'button', { name: text, exact: true } )
				.click();
			await idle();
		}
		await page.reload();
		await expect(
			stage.locator( 'button[aria-pressed="true"]' )
		).toHaveCount( 2 );
		await expect(
			stage.locator( '.ohmylms-study-cards button:disabled' )
		).toHaveCount( 2 );
		await stage
			.getByRole( 'button', { name: 'Check recall', exact: true } )
			.click();
		await expect(
			stage.getByText( 'Remembered: 2 / 2. Incorrect selections: 0.', {
				exact: true,
			} )
		).toBeVisible();
		await expect(
			stage.getByText(
				'Memory activity complete. No academic grade was recorded.',
				{ exact: true }
			)
		).toBeVisible();
		await stage
			.getByRole( 'button', { name: 'Play again', exact: true } )
			.click();
		await stage
			.getByRole( 'button', { name: 'Begin study', exact: true } )
			.click();
		await stage
			.getByRole( 'button', { name: 'Ready to recall', exact: true } )
			.click();
		await expect(
			stage.getByRole( 'button', { name: 'Check recall', exact: true } )
		).toBeVisible();
		await stage.locator( '.ohmylms-study-cards button' ).first().click();
		await idle();
		await page.setViewportSize( { width: 390, height: 844 } );
		await page
			.locator( '.ohmylms-question-player' )
			.screenshot( { path: 'test-results/recall-player-mobile.png' } );
		expect( errors ).toEqual( [] );
		await page.goto(
			`/wp-admin/post.php?post=${ fixture.post }&action=edit`
		);
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect(
			canvas.getByLabel( 'Play mode', { exact: true } )
		).toHaveValue( 'memory-recall', { timeout: 30000 } );
		await expect(
			canvas.getByRole( 'slider', {
				name: 'Number to remember',
				exact: true,
			} )
		).toHaveValue( '2' );
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
