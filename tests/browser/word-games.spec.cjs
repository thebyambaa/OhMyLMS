const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );

test( 'Hangman and spelling preserve private answers and resume word progress', async ( {
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
	await page.goto( '/wp-admin/admin.php?page=ohmylms' );
	await page.waitForFunction( () => Boolean( window.wp?.apiFetch ) );
	const fixture = await page.evaluate( async () => {
		const api = ( path, data ) =>
			wp.apiFetch( { path, method: 'POST', data } );
		const questions = [];
		for ( const settings of [
			{ type: 'text-entry', accepted: [ 'үүл' ], case_sensitive: false },
			{
				type: 'build-expression',
				tile_mode: 'letters',
				correct: [ 'ү', 'ү', 'л' ],
				distractors: [],
				alternatives: [],
				form: 'any',
				equivalence: false,
			},
		] ) {
			const q = await api( '/ohmylms/v1/question', {
				name: 'Word games browser clue',
				quiz_id: 0,
				settings: { ...settings, score: { enabled: true, value: 1 } },
				questions: [],
			} );
			await api( `/ohmylms/v1/question-bank/${ q.id }/approve`, {} );
			questions.push( q.id );
		}
		const pages = [];
		for ( const activityMode of [ 'hangman', 'spell-word' ] ) {
			const post = await api( '/wp/v2/pages', {
				title: `Word games ${ activityMode } fixture`,
				status: 'publish',
				content: `<!-- wp:ohmylms/question-player ${ JSON.stringify( { source: 'selected', questionIds: questions, activityMode } ) } /-->`,
			} );
			pages.push( { id: post.id, mode: activityMode } );
		}
		return { questions, pages };
	} );
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	const stage = page.locator( '.ohmylms-question-player-stage' );
	const guess = async ( label, value ) => {
		await stage
			.getByRole( 'textbox', { name: label, exact: true } )
			.fill( value );
		await stage
			.getByRole( 'button', { name: 'Check', exact: true } )
			.click();
		await expect(
			page.locator( '.ohmylms-question-player' )
		).not.toHaveAttribute( 'aria-busy', 'true' );
	};
	try {
		for ( const mode of [ 'hangman', 'spell-word' ] ) {
			await page.goto(
				`/?page_id=${ fixture.pages.find( ( p ) => p.mode === mode ).id }`
			);
			const responsePromise = page.waitForResponse(
				( r ) =>
					r.url().includes( '/question-player/' ) &&
					r.request().method() === 'POST'
			);
			await page
				.getByRole( 'button', { name: 'Start questions', exact: true } )
				.click();
			const initial = await ( await responsePromise ).json();
			expect( initial.answer ).toBeUndefined();
			expect( JSON.stringify( initial ) ).not.toContain( 'үүл' );
			const label =
				mode === 'hangman' ? 'Guess a letter' : 'Spell the word';
			await guess( label, 'z' );
			await expect(
				stage.getByText( 'Mistakes: 1 / 6', { exact: true } )
			).toBeVisible();
			await page.reload();
			await expect(
				stage.getByText( 'Mistakes: 1 / 6', { exact: true } )
			).toBeVisible();
			if ( mode === 'hangman' ) {
				await guess( label, 'Ү' );
				await expect(
					stage
						.locator( '.ohmylms-word-slots span' )
						.filter( { hasText: 'ү' } )
				).toHaveCount( 2 );
				await guess( label, 'Ү' );
				await expect(
					stage.getByText( 'Mistakes: 1 / 6', { exact: true } )
				).toBeVisible();
				await guess( label, 'л' );
			} else {
				await guess( label, 'ҮҮЛ' );
			}
			await expect(
				stage.getByText( 'Correct!', { exact: true } )
			).toBeVisible();
			await stage
				.getByRole( 'button', { name: 'Next word', exact: true } )
				.click();
			await expect(
				stage.getByText( 'Round 2 of 2', { exact: true } )
			).toBeVisible();
			await expect(
				stage.getByText( 'Mistakes: 0 / 6', { exact: true } )
			).toBeVisible();
			if ( mode === 'hangman' ) {
				for ( const letter of [ 'a', 'b', 'c', 'd', 'e', 'f' ] ) {
					await guess( label, letter );
					await expect(
						stage.getByText(
							`Guessed letters: ${ [ 'a', 'b', 'c', 'd', 'e', 'f' ].slice( 0, [ 'a', 'b', 'c', 'd', 'e', 'f' ].indexOf( letter ) + 1 ).join( ', ' ) }`,
							{ exact: true }
						)
					).toBeVisible();
				}
			} else {
				await guess( label, 'ҮҮЛ' );
			}
			await expect(
				stage.getByRole( 'button', { name: 'Play again', exact: true } )
			).toBeVisible();
			await expect(
				stage.getByText( 'Answer: үүл', { exact: true } )
			).toBeVisible();
			await page
				.locator( '.ohmylms-question-player' )
				.screenshot( {
					path: `test-results/word-games-${ mode }.png`,
				} );
			await page.reload();
			await expect(
				stage.getByRole( 'button', { name: 'Play again', exact: true } )
			).toBeVisible();
			await stage
				.getByRole( 'button', { name: 'Play again', exact: true } )
				.click();
			await expect(
				stage.getByText( 'Round 1 of 2', { exact: true } )
			).toBeVisible();
		}
		await page.goto(
			`/wp-admin/post.php?post=${ fixture.pages[ 0 ].id }&action=edit`
		);
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect(
			canvas.getByLabel( 'Play mode', { exact: true } )
		).toHaveValue( 'hangman', { timeout: 30000 } );
		await expect(
			canvas
				.getByLabel( 'Play mode', { exact: true } )
				.locator( 'option[value="spell-word"]' )
		).toHaveCount( 1 );
		expect( errors ).toEqual( [] );
	} finally {
		await page.goto( '/wp-admin/admin.php?page=ohmylms' );
		await page.waitForFunction( () => Boolean( window.wp?.apiFetch ) );
		await page.evaluate( async ( { pages, questions } ) => {
			for ( const p of pages )
				await wp.apiFetch( {
					path: `/wp/v2/pages/${ p.id }?force=true`,
					method: 'DELETE',
				} );
			for ( const id of questions )
				await wp.apiFetch( {
					path: `/ohmylms/v1/question/${ id }`,
					method: 'DELETE',
				} );
		}, fixture );
	}
} );
