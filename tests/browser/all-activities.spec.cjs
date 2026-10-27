const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const families = require( '../fixtures/activity-families.json' );
test( 'all remaining activity modes play and the maths generator saves in Gutenberg', async ( {
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

	test.setTimeout( 300000 );
	page.setDefaultTimeout( 15000 );
	const modes = {
		'spin-wheel': 'matching',
		'open-box': 'matching',
		'speaking-cards': 'matching',
		'flip-tiles': 'matching',
		'find-match': 'matching',
		'pair-or-no-pair': 'matching',
		'balloon-pop': 'matching',
		gameshow: 'choice',
		'timed-true-false': 'boolean',
		'win-or-lose': 'choice',
		'maze-chase': 'choice',
		'flying-fruit': 'choice',
		'whack-a-mole': 'choice',
		airplane: 'choice',
		'speed-sorting': 'sort',
		'word-magnets': 'magnets',
	};
	const fixture = await page.evaluate(
		async ( { families, modes } ) => {
			const api = ( path, data ) =>
				wp.apiFetch( { path, method: 'POST', data } );
			const ids = {};
			for ( const [ key, def ] of Object.entries( families ) ) {
				const q = await api( '/ohmylms/v1/question', {
					...def,
					name: `Activities ${ key }`,
					quiz_id: 0,
					settings: {
						...def.settings,
						score: { enabled: true, value: 1 },
					},
				} );
				await api( `/ohmylms/v1/question-bank/${ q.id }/approve`, {} );
				ids[ key ] = q.id;
			}
			const math = await api( '/ohmylms/v1/question', {
				name: 'Maths generator fixture',
				quiz_id: 0,
				settings: {
					type: 'numerical',
					answer: 1,
					score: { enabled: true, value: 1 },
				},
				questions: [],
			} );
			ids.math = math.id;
			const content = Object.entries( modes )
				.map(
					( [ mode, key ] ) =>
						`<!-- wp:ohmylms/question-player ${ JSON.stringify( { questionIds: [ ids[ key ] ], activityMode: mode, roundSeconds: 60, graded: false, affectsSkills: false } ) } /-->`
				)
				.join( '\n' );
			const post = await api( '/wp/v2/pages', {
				title: 'All activities browser fixture',
				status: 'publish',
				content,
			} );
			const mathPost = await api( '/wp/v2/pages', {
				title: 'Generated maths browser fixture',
				status: 'publish',
				content: `<!-- wp:ohmylms/question-player ${ JSON.stringify( { questionIds: [ ids.math ], graded: true, affectsSkills: false } ) } /-->`,
			} );
			return { ids, post: post.id, mathPost: mathPost.id };
		},
		{ families, modes }
	);
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	try {
		await page.goto( `/?page_id=${ fixture.post }` );
		for ( const [ index, [ mode, family ] ] of Object.entries(
			modes
		).entries() ) {
			if ( index === 8 )
				await page.setViewportSize( { width: 390, height: 844 } );
			const root = page
				.locator( '.ohmylms-question-player' )
				.nth( index );
			await root
				.getByRole( 'button', { name: 'Start questions', exact: true } )
				.click();
			if (
				[
					'spin-wheel',
					'open-box',
					'speaking-cards',
					'flip-tiles',
				].includes( mode )
			) {
				if ( mode === 'spin-wheel' || mode === 'speaking-cards' )
					await root
						.getByRole( 'button', {
							name:
								mode === 'spin-wheel'
									? 'Spin'
									: 'Draw random card',
							exact: true,
						} )
						.click();
				else
					await root
						.locator( '.ohmylms-game-deck button' )
						.first()
						.click();
				await expect(
					root.locator( '.ohmylms-game-reader' )
				).toBeVisible();
				await root
					.getByRole( 'button', {
						name: 'Finish activity',
						exact: true,
					} )
					.click();
				await expect(
					root.getByRole( 'button', {
						name: 'Play again',
						exact: true,
					} )
				).toBeVisible();
			} else if ( mode === 'word-magnets' ) {
				await root
					.getByRole( 'button', { name: 'We', exact: true } )
					.click();
				const magnet = root.locator( '.ohmylms-magnet' );
				await expect( magnet ).toBeVisible();
				await magnet.focus();
				await page.keyboard.press( 'ArrowRight' );
				await page.keyboard.up( 'ArrowRight' );
				await expect( root ).not.toHaveAttribute( 'aria-busy', 'true' );
				await page.reload();
				await expect( magnet ).toBeVisible();
				await root
					.getByRole( 'button', {
						name: 'Finish composition',
						exact: true,
					} )
					.click();
				await expect(
					root.getByRole( 'button', {
						name: 'Play again',
						exact: true,
					} )
				).toBeVisible();
			} else {
				let round = 0;
				await expect(
					root.getByRole( 'button', {
						name: 'Begin round',
						exact: true,
					} )
				).toBeVisible();
				while (
					await root
						.getByRole( 'button', {
							name: 'Begin round',
							exact: true,
						} )
						.count()
				) {
					if ( mode === 'win-or-lose' ) {
						await root
							.getByLabel( 'Stake', { exact: true } )
							.selectOption( '50' );
						await expect( root ).not.toHaveAttribute(
							'aria-busy',
							'true'
						);
					}
					await root
						.getByRole( 'button', {
							name: 'Begin round',
							exact: true,
						} )
						.click();
					if ( mode === 'gameshow' ) {
						await root
							.getByRole( 'button', {
								name: '50/50 lifeline',
								exact: true,
							} )
							.click();
						await expect(
							root.locator( '.ohmylms-game-answers button' )
						).toHaveCount( 2 );
					}
					if ( round === 0 ) {
						await root
							.getByRole( 'button', {
								name: 'Pause round',
								exact: true,
							} )
							.click();
						await root
							.getByRole( 'button', {
								name: 'Resume round',
								exact: true,
							} )
							.click();
					}
					let wanted = mode === 'timed-true-false' ? 'True' : 'Yes';
					const prompt = await root
						.locator( 'h4' )
						.first()
						.textContent();
					if ( family === 'matching' ) {
						const expected = { One: '1', Two: '2', Three: '3' }[
							prompt
						];
						wanted =
							mode === 'pair-or-no-pair'
								? (
										await root
											.locator( 'p' )
											.allTextContents()
									).includes( expected )
									? 'Pair'
									: 'No pair'
								: expected;
					}
					if ( mode === 'speed-sorting' )
						wanted = prompt === '1' ? 'Odd' : 'Even';
					if ( [ 'maze-chase', 'airplane' ].includes( mode ) ) {
						await expect(
							root.locator( '.ohmylms-arcade' )
						).toBeVisible();
						await root.locator( '.ohmylms-arcade' ).focus();
						await page.keyboard.press( 'ArrowRight' );
						await root
							.locator( '.ohmylms-arcade' )
							.getByRole( 'button', {
								name: wanted,
								exact: true,
							} )
							.click( { force: true } );
					} else if (
						[
							'balloon-pop',
							'flying-fruit',
							'whack-a-mole',
						].includes( mode )
					) {
						await root
							.locator( '.ohmylms-arcade' )
							.getByRole( 'button', {
								name: wanted,
								exact: true,
							} )
							.click( { force: true } );
					} else
						await root
							.locator( '.ohmylms-game-answers' )
							.getByRole( 'button', {
								name: wanted,
								exact: true,
							} )
							.click();
					await expect( root.getByRole( 'status' ) ).toContainText(
						'Correct'
					);
					round++;
					if (
						await root
							.getByRole( 'button', {
								name: 'Next round',
								exact: true,
							} )
							.count()
					) {
						await root
							.getByRole( 'button', {
								name: 'Next round',
								exact: true,
							} )
							.click();
						await expect(
							root.getByRole( 'button', {
								name: 'Begin round',
								exact: true,
							} )
						).toBeVisible();
					} else break;
				}
				await expect(
					root.getByRole( 'button', {
						name: 'Play again',
						exact: true,
					} )
				).toBeVisible();
			}
			console.info( 'Validated activity:', mode );
			if (
				[ 'spin-wheel', 'maze-chase', 'word-magnets' ].includes( mode )
			)
				await root.screenshot( {
					path: `test-results/all-activities-${ mode }.png`,
				} );
		}
		await page.setViewportSize( { width: 1440, height: 1000 } );
		await page.goto(
			`/wp-admin/post.php?post=${ fixture.ids.math }&action=edit`
		);
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await canvas
			.getByRole( 'textbox', { name: 'Question statement', exact: true } )
			.click();
		await canvas.getByText( 'Maths generator', { exact: true } ).click();
		await canvas
			.getByLabel( 'Maths topic', { exact: true } )
			.selectOption( 'division' );
		await canvas
			.getByLabel( 'Minimum generated number', { exact: true } )
			.fill( '2' );
		await canvas
			.getByLabel( 'Maximum generated number', { exact: true } )
			.fill( '9' );
		await canvas
			.getByRole( 'button', {
				name: 'Apply generated question',
				exact: true,
			} )
			.click();
		await page.keyboard.press( 'Control+s' );
		await expect
			.poll( () =>
				page.evaluate(
					async ( id ) =>
						(
							await wp.apiFetch( {
								path: `/ohmylms/v1/question/${ id }`,
							} )
						).settings.maths_generator?.kind,
					fixture.ids.math
				)
			)
			.toBe( 'division' );
		await page.reload();
		await canvas
			.getByRole( 'textbox', { name: 'Question statement', exact: true } )
			.click();
		await canvas.getByText( 'Maths generator', { exact: true } ).click();
		await expect(
			canvas.getByLabel( 'Maths topic', { exact: true } )
		).toHaveValue( 'division' );
		await page.evaluate(
			async ( id ) =>
				wp.apiFetch( {
					path: `/ohmylms/v1/question-bank/${ id }/approve`,
					method: 'POST',
					data: {},
				} ),
			fixture.ids.math
		);
		await page.goto( `/?page_id=${ fixture.mathPost }` );
		await page
			.getByRole( 'button', { name: 'Start questions', exact: true } )
			.click();
		const prompt = await page
			.locator( '.ohmylms-question-player-description' )
			.textContent();
		const values = prompt.match( /(\d+)\s*÷\s*(\d+)/ );
		expect( values ).toBeTruthy();
		await page
			.locator(
				'.ohmylms-question-player input[type="text"], .ohmylms-question-player input[type="number"]'
			)
			.first()
			.fill( String( Number( values[ 1 ] ) / Number( values[ 2 ] ) ) );
		await page
			.getByRole( 'button', { name: 'Submit responses', exact: true } )
			.click();
		await expect(
			page.getByText( 'Score: 1 / 1', { exact: true } )
		).toBeVisible();
		expect( errors ).toEqual( [] );
	} finally {
		await page.goto( '/wp-admin/admin.php?page=ohmylms' );
		await page.waitForFunction( () => Boolean( window.wp?.apiFetch ) );
		await page.evaluate( async ( fixture ) => {
			for ( const post of [ fixture.post, fixture.mathPost ] )
				await wp.apiFetch( {
					path: `/wp/v2/pages/${ post }?force=true`,
					method: 'DELETE',
				} );
			for ( const id of Object.values( fixture.ids ) )
				await wp.apiFetch( {
					path: `/ohmylms/v1/question/${ id }`,
					method: 'DELETE',
				} );
		}, fixture );
	}
} );
