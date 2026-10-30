const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const { useFormAuthoring } = require( './helpers/form-authoring.cjs' );

test.beforeEach( async ( { page } ) => {
	await useFormAuthoring( page );
	const config = JSON.parse(
		fs.readFileSync( process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8' )
	);
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( config.username );
	await page.locator( '#user_pass' ).fill( config.password );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /wp-admin/ );
	await page.goto( '/wp-admin/admin.php?page=ohmylms-app' );
} );

test( 'former quiz routes always open Gutenberg and preserve existing question types despite rollback settings', async ( {
	page,
} ) => {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	const fixture = await page.evaluate( async () => {
		const quiz = await wp.apiFetch( {
			path: '/ohmylms/v1/quiz',
			method: 'POST',
			data: { name: 'Source quiz fixture', status: 'draft' },
		} );
		const types = [
			'multiple-choice',
			'single-choice',
			'true-false',
			'short-text',
			'long-text',
			'statement',
			'fill-in-the-blank',
			'reorder',
			'matching',
			'example-number',
		];
		const ids = [];
		for ( const type of types ) {
			const options = [ 'short-text', 'long-text' ].includes( type )
				? []
				: [
						{
							answer: type === 'true-false' ? 'True' : 'Answer A',
							is_correct: true,
							order_number: 1,
							matching_data: { label: 'Match A' },
						},
						{
							answer:
								type === 'true-false' ? 'False' : 'Answer B',
							is_correct: false,
							order_number: 2,
							matching_data: { label: 'Match B' },
						},
					];
			const question = await wp.apiFetch( {
				path: '/ohmylms/v1/question',
				method: 'POST',
				data: {
					quiz_id: quiz.id,
					name: `Fixture ${ type }`,
					order_number: ids.length + 1,
					settings: {
						type,
						expected: 1,
						required: false,
						score: { enabled: true, value: 1 },
					},
					questions: options,
				},
			} );
			ids.push( question.id );
		}
		return { quiz, types, ids };
	} );
	try {
		await page.goto(
			`/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/quiz-edit/${ fixture.quiz.id }`
		);
		await page.waitForURL(
			( url ) =>
				url.pathname.endsWith( '/post.php' ) &&
				url.searchParams.get( 'post' ) === String( fixture.quiz.id )
		);
		await page.waitForFunction( () =>
			window.wp?.plugins?.getPlugin( 'ohmylms-native-quiz-settings' )
		);
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect(
			canvas.getByRole( 'textbox', {
				name: 'Question statement',
				exact: true,
			} )
		).toHaveCount( 10, { timeout: 20000 } );
		await expect(
			page.getByRole( 'region', { name: 'Quiz form editor' } )
		).toHaveCount( 0 );
		await page.evaluate( async () => {
			wp.data
				.dispatch( 'core/editor' )
				.editPost( { title: 'Saved Gutenberg quiz' } );
			await wp.data.dispatch( 'core/editor' ).savePost();
		} );
		await expect
			.poll( () =>
				page.evaluate(
					async ( id ) =>
						(
							await wp.apiFetch( {
								path: `/ohmylms/v1/quiz/${ id }`,
							} )
						).name,
					fixture.quiz.id
				)
			)
			.toBe( 'Saved Gutenberg quiz' );
		await page.reload();
		await expect(
			canvas.getByRole( 'textbox', {
				name: 'Question statement',
				exact: true,
			} )
		).toHaveCount( 10, { timeout: 20000 } );
		const saved = await page.evaluate(
			( id ) => wp.apiFetch( { path: `/ohmylms/v1/quiz/${ id }` } ),
			fixture.quiz.id
		);
		expect(
			saved.content.map( ( question ) => Number( question.id ) )
		).toEqual( fixture.ids );
		expect(
			saved.content.map( ( question ) => question.settings.type )
		).toEqual( fixture.types );
		expect( errors ).toEqual( [] );
	} finally {
		await page.evaluate( async ( data ) => {
			await wp.apiFetch( {
				path: `/ohmylms/v1/quiz/${ data.quiz.id }`,
				method: 'DELETE',
			} );
			for ( const id of data.ids ) {
				await wp.apiFetch( {
					path: `/wp/v2/ohmylms-questions/${ id }?force=true`,
					method: 'DELETE',
				} );
			}
		}, fixture );
	}
} );
