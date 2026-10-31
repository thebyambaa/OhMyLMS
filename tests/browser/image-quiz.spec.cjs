const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
test( 'image quiz persists the buzzer, scores once and supports mobile and author controls', async ( {
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

	const imageBytes = Array.from(
		fs.readFileSync( '../../../wp-includes/images/w-logo-blue.png' )
	);
	const fixture = await page.evaluate( async ( bytes ) => {
		const api = ( path, data ) =>
			wp.apiFetch( { path, method: 'POST', data } );
		const media = await wp.apiFetch( {
			path: '/wp/v2/media',
			method: 'POST',
			headers: {
				'Content-Disposition':
					'attachment; filename="image-quiz-fixture.png"',
				'Content-Type': 'image/png',
			},
			body: new Uint8Array( bytes ),
		} );
		const questions = [];
		for ( let index = 0; index < 2; index++ ) {
			const q = await api( '/ohmylms/v1/questions', {
				name: `What logo is this? ${ index + 1 }`,
				thumbnail_id: media.id,
				settings: {
					type: 'single-choice',
					score: { enabled: true, value: 1 },
				},
				options: [
					{ answer: 'WordPress', is_correct: true },
					{ answer: 'Other', is_correct: false },
				],
			} );
			questions.push( q.id );
			await api( `/ohmylms/v1/questions/${ q.id }/approve`, {} );
		}
		const post = await api( '/wp/v2/pages', {
			title: 'Image quiz fixture',
			status: 'publish',
			content: `<!-- wp:ohmylms/question-player ${ JSON.stringify( { questionIds: questions, activityMode: 'image-quiz', revealSeconds: 10, graded: true, affectsSkills: true } ) } /-->`,
		} );
		return { post: post.id, questions, media: media.id };
	}, imageBytes );
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	page.setDefaultTimeout( 15000 );
	const stage = page.locator( '.ohmylms-question-player-stage' );
	try {
		await page.goto( `/?page_id=${ fixture.post }` );
		await page
			.getByRole( 'button', { name: 'Start questions', exact: true } )
			.click();
		await expect(
			stage.getByRole( 'button', { name: 'Reveal image', exact: true } )
		).toBeEnabled();
		await expect( stage.locator( '.ohmylms-image-choices' ) ).toHaveCount(
			0
		);
		await stage
			.getByRole( 'button', { name: 'Reveal image', exact: true } )
			.click();
		await expect
			.poll( () =>
				stage
					.locator( '.ohmylms-image-mask span[style*="hidden"]' )
					.count()
			)
			.toBeGreaterThan( 0 );
		await stage
			.getByRole( 'button', { name: 'Buzz! I know it', exact: true } )
			.click();
		await expect(
			stage.locator( '.ohmylms-image-choices button' )
		).toHaveCount( 2 );
		const paused = await stage
			.locator( '.ohmylms-image-mask span[style*="hidden"]' )
			.count();
		await page.reload();
		await expect(
			stage.getByText( 'Reveal paused. Choose your answer.', {
				exact: true,
			} )
		).toBeVisible();
		await expect(
			stage.locator( '.ohmylms-image-mask span[style*="hidden"]' )
		).toHaveCount( paused );
		await stage
			.locator( '.ohmylms-image-choices button' )
			.filter( { hasText: 'WordPress' } )
			.click();
		await expect( stage.getByText( /Correct · \d+ points/ ) ).toBeVisible();
		await page.reload();
		await expect(
			stage.getByRole( 'button', { name: 'Next image', exact: true } )
		).toBeVisible();
		await page
			.locator( '.ohmylms-question-player' )
			.screenshot( { path: 'test-results/image-quiz-desktop.png' } );
		await stage
			.getByRole( 'button', { name: 'Next image', exact: true } )
			.click();
		await expect( stage.getByText( /Image 2 \/ 2/ ) ).toBeVisible();
		await stage
			.getByRole( 'button', { name: 'Reveal image', exact: true } )
			.click();
		await expect(
			stage.getByText(
				'Image fully revealed. Buzz to choose your answer.',
				{ exact: true }
			)
		).toBeVisible( { timeout: 15000 } );
		await stage
			.getByRole( 'button', { name: 'Buzz! I know it', exact: true } )
			.click();
		await page.setViewportSize( { width: 390, height: 844 } );
		await expect(
			stage.getByText( 'Reveal paused. Choose your answer.', {
				exact: true,
			} )
		).toBeVisible();
		await page
			.locator( '.ohmylms-question-player' )
			.screenshot( { path: 'test-results/image-quiz-mobile.png' } );
		await stage
			.locator( '.ohmylms-image-choices button' )
			.filter( { hasText: 'Other' } )
			.click();
		await expect(
			stage.getByText( 'Incorrect · 0 points', { exact: true } )
		).toBeVisible();
		await expect(
			stage.getByText(
				'Image quiz complete. No academic grade was recorded.',
				{ exact: true }
			)
		).toBeVisible();
		await stage
			.getByRole( 'button', { name: 'Play again', exact: true } )
			.click();
		await stage
			.getByRole( 'button', { name: 'Skip image', exact: true } )
			.click();
		await expect(
			stage.getByText( 'Image skipped. 0 points.', { exact: true } )
		).toBeVisible();
		const clue = await stage
			.locator( '.ohmylms-image-reveal > img' )
			.getAttribute( 'src' );
		await page.route( clue, ( route ) => route.abort() );
		await stage
			.getByRole( 'button', { name: 'Next image', exact: true } )
			.click();
		await expect(
			stage.getByText(
				'The image could not load. Reload the page to retry, or skip this image.',
				{ exact: true }
			)
		).toBeVisible();
		await expect(
			stage.getByRole( 'button', { name: 'Reveal image', exact: true } )
		).toBeDisabled();
		await stage
			.getByRole( 'button', { name: 'Skip image', exact: true } )
			.click();
		await expect(
			stage.getByRole( 'button', { name: 'Play again', exact: true } )
		).toBeEnabled();
		await page.unroute( clue );
		expect( errors ).toEqual( [] );
		await page.setViewportSize( { width: 1440, height: 1000 } );
		await page.goto(
			`/wp-admin/post.php?post=${ fixture.post }&action=edit`
		);
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect(
			canvas.getByLabel( 'Play mode', { exact: true } )
		).toHaveValue( 'image-quiz', { timeout: 30000 } );
		await expect(
			canvas.getByRole( 'slider', {
				name: 'Image reveal time (seconds)',
				exact: true,
			} )
		).toHaveValue( '10' );
	} finally {
		await page.goto( '/wp-admin/admin.php?page=ohmylms-app' );
		await page.waitForFunction( () => Boolean( window.wp?.apiFetch ) );
		await page.evaluate( async ( fixture ) => {
			await wp.apiFetch( {
				path: `/wp/v2/pages/${ fixture.post }?force=true`,
				method: 'DELETE',
			} );
			for ( const id of fixture.questions ) {
				await wp.apiFetch( {
					path: `/ohmylms/v1/questions/${ id }`,
					method: 'DELETE',
				} );
			}
			await wp.apiFetch( {
				path: `/wp/v2/media/${ fixture.media }?force=true`,
				method: 'DELETE',
			} );
		}, fixture );
	}
} );
