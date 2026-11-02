const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const fixtures = require( '../fixtures/word-puzzles.json' );

test( 'puzzle builders save in Gutenberg and players support keyboard, selection, refresh and grading', async ( {
	page,
	baseURL,
} ) => {
	test.skip(
		baseURL !== 'http://127.0.0.1:8100',
		'Requires disposable migration clone'
	);
	test.setTimeout( 150000 );
	page.setDefaultTimeout( 15000 );
	const credentials = JSON.parse(
		fs.readFileSync( process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8' )
	);
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( credentials.username );
	await page.locator( '#user_pass' ).fill( credentials.password );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL(
		( url ) => ! url.pathname.endsWith( 'wp-login.php' )
	);
	await page.goto( '/wp-admin/admin.php?page=ohmylms-app' );
	await page.waitForFunction( () => Boolean( window.wp?.apiFetch ) );
	const fixture = await page.evaluate( async ( definitions ) => {
		const api = ( path, data ) =>
			wp.apiFetch( { path, method: 'POST', data } );
		const leftovers = await wp.apiFetch( {
			path: '/wp/v2/pages?search=Word%20puzzles%20browser%20fixture&context=edit',
		} );
		for ( const old of leftovers ) {
			if ( old.title.raw !== 'Word puzzles browser fixture' ) continue;
			const match = old.content.raw.match(
				/<!-- wp:ohmylms\/question-player (\{.*?\}) \/-->/
			);
			if ( ! match ) continue;
			for ( const id of JSON.parse( match[ 1 ] ).questionIds || [] )
				await wp.apiFetch( {
					path: `/ohmylms/v1/questions/${ id }`,
					method: 'DELETE',
				} );
			await wp.apiFetch( {
				path: `/wp/v2/pages/${ old.id }?force=true`,
				method: 'DELETE',
			} );
		}
		const questions = [];
		for ( const [ type, settings ] of Object.entries( definitions ) ) {
			const q = await api( '/ohmylms/v1/questions', {
				name: `Solve the ${ type } puzzle`,
				settings: {
					...settings,
					type,
					required: true,
					score: { enabled: true, value: 1 },
				},
				options: [],
			} );
			await api( `/ohmylms/v1/questions/${ q.id }/approve`, {} );
			questions.push( q.id );
		}
		const post = await api( '/wp/v2/pages', {
			title: 'Word puzzles browser fixture',
			status: 'publish',
			content: `<!-- wp:ohmylms/question-player ${ JSON.stringify( { questionIds: questions, source: 'selected', graded: true, affectsSkills: false } ) } /-->`,
		} );
		return { questions, post: post.id };
	}, fixtures );
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	const cellsFor = ( entry ) => {
		const dr = entry.direction === 'down' ? 1 : 0;
		const dc = entry.direction === 'across' ? 1 : 0;
		return Array.from(
			entry.answer,
			( _, i ) => `r${ entry.row + dr * i }c${ entry.col + dc * i }`
		);
	};
	try {
		await page.goto(
			`/wp-admin/post.php?post=${ fixture.questions[ 0 ] }&action=edit`
		);
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await canvas
			.getByRole( 'textbox', { name: 'Question statement', exact: true } )
			.click();
		await expect(
			canvas.getByLabel( 'Answer 1', { exact: true } )
		).toHaveValue( 'CAT', { timeout: 30000 } );
		await canvas
			.getByLabel( 'Clue 1', { exact: true } )
			.fill( 'A small pet that meows' );
		await page.keyboard.press( 'Control+s' );
		await expect
			.poll( async () =>
				page.evaluate(
					async ( id ) =>
						(
							await wp.apiFetch( {
								path: `/ohmylms/v1/questions/${ id }`,
							} )
						).settings.entries[ 0 ].clue,
					fixture.questions[ 0 ]
				)
			)
			.toBe( 'A small pet that meows' );
		await page.reload();
		await canvas
			.getByRole( 'textbox', { name: 'Question statement', exact: true } )
			.click();
		await expect(
			canvas.getByLabel( 'Clue 1', { exact: true } )
		).toHaveValue( 'A small pet that meows' );
		await canvas
			.getByRole( 'button', { name: 'Preview puzzle', exact: true } )
			.click();
		await expect(
			canvas.getByRole( 'group', { name: 'Crossword grid', exact: true } )
		).toBeVisible();
		await page.screenshot( {
			path: 'test-results/puzzle-crossword-editor.png',
		} );
		await page.goto(
			`/wp-admin/post.php?post=${ fixture.questions[ 1 ] }&action=edit`
		);
		await canvas
			.getByRole( 'textbox', { name: 'Question statement', exact: true } )
			.click();
		await expect(
			canvas.getByLabel( 'Word list', { exact: true } )
		).toHaveValue( 'words', { timeout: 30000 } );
		await canvas
			.getByRole( 'button', { name: 'Preview puzzle', exact: true } )
			.click();
		await expect(
			canvas.getByRole( 'group', {
				name: 'Wordsearch grid',
				exact: true,
			} )
		).toBeVisible();
		await page.goto( '/wp-admin/admin.php?page=ohmylms-app' );
		await page.waitForFunction( () => Boolean( window.wp?.apiFetch ) );
		await page.evaluate(
			( id ) =>
				wp.apiFetch( {
					path: `/ohmylms/v1/questions/${ id }/approve`,
					method: 'POST',
					data: {},
				} ),
			fixture.questions[ 0 ]
		);
		await page.goto( `/?page_id=${ fixture.post }` );
		const loaded = page.waitForResponse(
			( response ) =>
				response.url().includes( '/question-player/' ) &&
				response.request().method() === 'POST'
		);
		await page
			.getByRole( 'button', { name: 'Start questions', exact: true } )
			.click();
		const data = await ( await loaded ).json();
		expect( data.questions[ 0 ].html ).not.toContain( 'CAT' );
		const crossword = page.locator(
			'[data-ohmylms-interactive="crossword"]'
		);
		await expect(
			crossword.locator( '.ohmylms-puzzle-grid input' )
		).toHaveCount( 5 );
		const enterWord = async ( entry ) => {
			await crossword.locator( `[data-clue="${ entry.id }"]` ).click();
			const input = crossword.locator(
				`input[data-answer-key="${ cellsFor( entry )[ 0 ] }"]`
			);
			await input.focus();
			await input.pressSequentially( entry.answer );
			for ( const [ i, key ] of cellsFor( entry ).entries() )
				await expect(
					crossword.locator( `input[data-answer-key="${ key }"]` )
				).toHaveValue( entry.answer[ i ] );
		};
		await enterWord( fixtures.crossword.entries[ 0 ] );
		await page.reload();
		for ( const [ i, key ] of cellsFor(
			fixtures.crossword.entries[ 0 ]
		).entries() )
			await expect(
				crossword.locator( `input[data-answer-key="${ key }"]` )
			).toHaveValue( fixtures.crossword.entries[ 0 ].answer[ i ] );
		await enterWord( fixtures.crossword.entries[ 1 ] );
		await page
			.locator( '.ohmylms-question-player' )
			.screenshot( { path: 'test-results/puzzle-crossword-player.png' } );
		await page.getByRole( 'button', { name: 'Next', exact: true } ).click();
		const search = page.locator(
			'[data-ohmylms-interactive="wordsearch"]'
		);
		await expect(
			search.getByRole( 'group', {
				name: 'Wordsearch grid',
				exact: true,
			} )
		).toBeVisible();
		const choose = async ( entry ) => {
			const cells = cellsFor( entry );
			await search
				.locator( `[data-puzzle-cell="${ cells[ 0 ] }"]` )
				.press( 'Enter' );
			await search
				.locator( `[data-puzzle-cell="${ cells.at( -1 ) }"]` )
				.press( 'Enter' );
		};
		await choose( fixtures.wordsearch.entries[ 0 ] );
		await expect(
			search.locator( 'input[data-answer-key="p0"]' )
		).not.toHaveValue( '' );
		await page.reload();
		await page.getByRole( 'button', { name: 'Next', exact: true } ).click();
		await expect(
			search.locator( 'input[data-answer-key="p0"]' )
		).not.toHaveValue( '' );
		await expect( search.locator( '.is-selected' ) ).toHaveCount(
			fixtures.wordsearch.entries[ 0 ].answer.length
		);
		const secondPath = cellsFor( fixtures.wordsearch.entries[ 1 ] );
		const firstBox = await search
			.locator( `[data-puzzle-cell="${ secondPath[ 0 ] }"]` )
			.boundingBox();
		const lastBox = await search
			.locator( `[data-puzzle-cell="${ secondPath.at( -1 ) }"]` )
			.boundingBox();
		await page.mouse.move(
			firstBox.x + firstBox.width / 2,
			firstBox.y + firstBox.height / 2
		);
		await page.mouse.down();
		await page.mouse.move(
			lastBox.x + lastBox.width / 2,
			lastBox.y + lastBox.height / 2,
			{ steps: 8 }
		);
		await page.mouse.up();
		await expect(
			search.locator( 'input[data-answer-key="p1"]' )
		).not.toHaveValue( '' );
		await page.locator( '.ohmylms-question-player' ).screenshot( {
			path: 'test-results/puzzle-wordsearch-player.png',
		} );
		await page.setViewportSize( { width: 390, height: 844 } );
		await expect(
			search.getByRole( 'group', {
				name: 'Wordsearch grid',
				exact: true,
			} )
		).toBeVisible();
		await page
			.locator( '.ohmylms-question-player' )
			.screenshot( {
				path: 'test-results/puzzle-wordsearch-mobile.png',
			} );
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
		await page.evaluate( async ( { post, questions } ) => {
			await wp.apiFetch( {
				path: `/wp/v2/pages/${ post }?force=true`,
				method: 'DELETE',
			} );
			for ( const id of questions )
				await wp.apiFetch( {
					path: `/ohmylms/v1/questions/${ id }`,
					method: 'DELETE',
				} );
		}, fixture );
	}
} );
