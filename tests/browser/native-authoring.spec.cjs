const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );

async function openPanel( page, name ) {
	const button = page.getByRole( 'button', { name, exact: true } );
	await expect( button ).toBeVisible();
	if ( ( await button.getAttribute( 'aria-expanded' ) ) !== 'true' ) {
		await button.click();
	}
}

test.beforeEach( async ( { page } ) => {
	const credentials = JSON.parse(
		fs.readFileSync( process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8' )
	);
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( credentials.username );
	await page.locator( '#user_pass' ).fill( credentials.password );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /wp-admin/ );
} );

test( 'standalone questions use the native canvas and survive document saves', async ( {
	page,
} ) => {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( '/wp-admin/post-new.php?post_type=ohmylms-question' );
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const prompt = canvas.getByRole( 'textbox', {
		name: 'Question statement',
		exact: true,
	} );
	await expect( prompt ).toBeVisible();
	const id = await page.evaluate( () =>
		wp.data.select( 'core/editor' ).getCurrentPostId()
	);
	try {
		await prompt.fill( 'Which number is even?' );
		await canvas
			.getByRole( 'textbox', { name: 'Answer 1', exact: true } )
			.fill( '4' );
		await canvas
			.getByRole( 'textbox', { name: 'Answer 2', exact: true } )
			.fill( '5' );
		await canvas.locator( 'input[type="radio"]' ).first().check();
		await expect
			.poll( () =>
				page.evaluate( async ( questionId ) => {
					const question = await wp.apiFetch( {
						path: `/ohmylms/v1/question-bank/${ questionId }`,
					} );
					return question.options.map( ( option ) => option.answer );
				}, id )
			)
			.toEqual( [ '4', '5' ] );
		await expect
			.poll( () =>
				page.evaluate( () =>
					wp.data.select( 'core/editor' ).isPostSavingLocked()
				)
			)
			.toBe( false );
		await page.evaluate( () =>
			wp.data.dispatch( 'core/editor' ).savePost()
		);
		await page.reload();
		await expect( prompt ).toContainText( 'Which number is even?' );
		await prompt.click();
		await expect(
			canvas.getByRole( 'textbox', { name: 'Answer 1', exact: true } )
		).toHaveText( '4' );
		await canvas
			.getByRole( 'textbox', { name: 'Answer 2', exact: true } )
			.fill( '7' );
		await expect
			.poll( () =>
				page.evaluate(
					async ( questionId ) =>
						(
							await wp.apiFetch( {
								path: `/ohmylms/v1/question-bank/${ questionId }`,
							} )
						).options[ 1 ].answer,
					id
				)
			)
			.toBe( '7' );
		const undoInPage = page.getByRole( 'button', {
			name: 'Undo question edit',
			exact: true,
		} );
		const undo = ( await undoInPage.count() )
			? undoInPage
			: canvas.getByRole( 'button', {
					name: 'Undo question edit',
					exact: true,
				} );
		await undo.click();
		await expect
			.poll( () =>
				page.evaluate(
					async ( questionId ) =>
						(
							await wp.apiFetch( {
								path: `/ohmylms/v1/question-bank/${ questionId }`,
							} )
						).options[ 1 ].answer,
					id
				)
			)
			.toBe( '5' );
		const types = await canvas
			.getByLabel( 'Question type', { exact: true } )
			.locator( 'option' )
			.evaluateAll( ( options ) =>
				options.map( ( option ) => option.value )
			);
		for ( const type of [
			'true-false',
			'statement',
			'categorize',
			'passage',
			'audio-response',
			'interactive-video',
			'example-number',
		] ) {
			expect( types ).toContain( type );
		}
		expect( errors ).toEqual( [] );
	} finally {
		await page.evaluate(
			( questionId ) =>
				wp.apiFetch( {
					path: `/wp/v2/ohmylms-questions/${ questionId }?force=true`,
					method: 'DELETE',
				} ),
			id
		);
	}
} );

test( 'quizzes open in Gutenberg and save quiz settings with the document', async ( {
	page,
} ) => {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( '/wp-admin/admin.php?page=ohmylms-app#/quizzes' );
	await page.waitForFunction( () => window.wp?.apiFetch );
	const quiz = await page.evaluate( () =>
		wp.apiFetch( {
			path: '/ohmylms/v1/quiz',
			method: 'POST',
			data: {
				name: 'Gutenberg migration browser fixture',
				status: 'draft',
			},
		} )
	);
	try {
		await page.goto( `/wp-admin/post.php?post=${ quiz.id }&action=edit` );
		await page.waitForFunction( () =>
			window.wp?.plugins?.getPlugin( 'ohmylms-native-quiz-settings' )
		);
		await page.evaluate( () =>
			wp.data
				.dispatch( 'core/edit-post' )
				.openGeneralSidebar( 'edit-post/document' )
		);
		await openPanel( page, 'Quiz settings' );
		await page
			.getByLabel( 'Attempts allowed', { exact: true } )
			.fill( '4' );
		await page.evaluate( () =>
			wp.data.dispatch( 'core/editor' ).savePost()
		);
		await expect
			.poll( () =>
				page.evaluate(
					async ( id ) =>
						(
							await wp.apiFetch( {
								path: `/ohmylms/v1/quiz/${ id }`,
							} )
						).settings.allow_attempts,
					quiz.id
				)
			)
			.toBe( 4 );
		await page.reload();
		await page.evaluate( () =>
			wp.data
				.dispatch( 'core/edit-post' )
				.openGeneralSidebar( 'edit-post/document' )
		);
		const panel = page.getByRole( 'button', {
			name: 'Quiz settings',
			exact: true,
		} );
		await expect( panel ).toBeVisible();
		if ( ( await panel.getAttribute( 'aria-expanded' ) ) !== 'true' ) {
			await panel.click();
		}
		await expect(
			page.getByLabel( 'Attempts allowed', { exact: true } )
		).toHaveValue( '4' );
		expect( errors ).toEqual( [] );
	} finally {
		await page.evaluate(
			( id ) =>
				wp.apiFetch( {
					path: `/ohmylms/v1/quiz/${ id }`,
					method: 'DELETE',
				} ),
			quiz.id
		);
	}
} );

test( 'bank insertion stays in the native draft and unsaved assessment settings block publication', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=ohmylms-app#/quizzes' );
	await page.waitForFunction( () => window.wp?.apiFetch );
	const fixture = await page.evaluate( async () => {
		const quiz = await wp.apiFetch( {
			path: '/ohmylms/v1/quiz',
			method: 'POST',
			data: { name: 'Native bank insertion fixture', status: 'draft' },
		} );
		const question = await wp.apiFetch( {
			path: '/ohmylms/v1/question',
			method: 'POST',
			data: {
				name: `Unique native bank picker fixture ${ quiz.id }`,
				settings: {
					type: 'single-choice',
					score: { enabled: true, value: 1 },
				},
				questions: [
					{ answer: '4', is_correct: true },
					{ answer: '5', is_correct: false },
				],
			},
		} );
		await wp.apiFetch( {
			path: `/ohmylms/v1/question-bank/${ question.id }/approve`,
			method: 'POST',
			data: {},
		} );
		return { quiz, question };
	} );
	try {
		await page.goto( fixture.quiz.native_editor_url );
		await page.waitForFunction( () =>
			window.wp?.plugins?.getPlugin( 'ohmylms-native-quiz-settings' )
		);
		await page.evaluate( () =>
			wp.data
				.dispatch( 'core/edit-post' )
				.openGeneralSidebar( 'edit-post/document' )
		);
		const authoringPanel = page.getByRole( 'button', {
			name: 'Quiz authoring',
			exact: true,
		} );
		if (
			( await authoringPanel.getAttribute( 'aria-expanded' ) ) !== 'true'
		) {
			await authoringPanel.click();
		}
		await page
			.getByRole( 'button', {
				name: 'Add from question bank',
				exact: true,
			} )
			.click();
		const picker = page.getByRole( 'dialog' );
		await picker
			.getByLabel( 'Search', { exact: true } )
			.fill( `Unique native bank picker fixture ${ fixture.quiz.id }` );
		await picker
			.getByRole( 'checkbox', {
				name: /Unique native bank picker fixture/,
			} )
			.check();
		await picker
			.getByRole( 'button', { name: 'Add 1 question(s)', exact: true } )
			.click();
		await expect( picker ).toHaveCount( 0 );
		const beforeSave = await page.evaluate(
			async ( id ) =>
				( await wp.apiFetch( { path: `/ohmylms/v1/quiz/${ id }` } ) )
					.content.length,
			fixture.quiz.id
		);
		expect( beforeSave ).toBe( 0 );
		await expect
			.poll( () =>
				page.evaluate( () =>
					wp.data.select( 'core/editor' ).isPostSavingLocked()
				)
			)
			.toBe( false );
		await page.evaluate( () =>
			wp.data.dispatch( 'core/editor' ).savePost()
		);
		await expect
			.poll( () =>
				page.evaluate(
					async ( id ) =>
						(
							await wp.apiFetch( {
								path: `/ohmylms/v1/quiz/${ id }`,
							} )
						).content.length,
					fixture.quiz.id
				)
			)
			.toBe( 1 );
		await page.evaluate( () =>
			wp.data
				.dispatch( 'core/edit-post' )
				.openGeneralSidebar( 'edit-post/document' )
		);
		await openPanel( page, 'Assessment and pools' );
		await openPanel( page, 'Assessment settings (exam, sections, timing)' );
		await page.getByLabel( 'Kind', { exact: true } ).selectOption( 'exam' );
		await expect
			.poll( () =>
				page.evaluate( () =>
					wp.data.select( 'core/editor' ).isPostSavingLocked()
				)
			)
			.toBe( true );
		await page
			.getByRole( 'button', {
				name: 'Save assessment settings',
				exact: true,
			} )
			.click();
		await expect
			.poll( () =>
				page.evaluate( () =>
					wp.data.select( 'core/editor' ).isPostSavingLocked()
				)
			)
			.toBe( false );
		expect(
			await page.evaluate( () =>
				wp.data.select( 'core/editor' ).isEditedPostDirty()
			)
		).toBe( true );
	} finally {
		await page.evaluate( async ( data ) => {
			await wp.apiFetch( {
				path: `/ohmylms/v1/quiz/${ data.quiz.id }`,
				method: 'DELETE',
			} );
			await wp.apiFetch( {
				path: `/wp/v2/ohmylms-questions/${ data.question.id }?force=true`,
				method: 'DELETE',
			} );
		}, fixture );
	}
} );
