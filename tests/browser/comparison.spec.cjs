const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const config = () =>
	JSON.parse(
		fs.readFileSync( process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8' )
	);
const api = ( page, method, path, data ) =>
	page.evaluate(
		async ( input ) =>
			window.wp.apiFetch( {
				method: input.method,
				path: '/ohmylms/v1/' + input.path,
				data: input.data,
			} ),
		{ method, path, data }
	);

test( 'roadmaps open the syllabus home, preserve steps when sources change, and launch the node editor', async ( {
	page,
} ) => {
	const { username, password } = config();
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( username );
	await page.locator( '#user_pass' ).fill( password );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL(
		( url ) => ! url.pathname.endsWith( 'wp-login.php' )
	);
	await page.goto( '/wp-admin/admin.php?page=ohmylms' );
	await page.waitForFunction( () => window.wp?.apiFetch );
	const items = [];
	const terms = [];
	const tag = Date.now().toString( 36 );
	try {
		for ( const name of [ 'Source A', 'Source B' ] ) {
			const created = await api( page, 'POST', 'curriculum/items', {
				name: `${ name } ${ tag }`,
				is_syllabus: true,
				version: '2028–2030',
			} );
			items.push( created.item.id );
			const group = await api(
				page,
				'POST',
				`curriculum/items/${ created.item.id }/syllabus/groups`,
				{ name: 'Algebra' }
			);
			const objective = await api(
				page,
				'POST',
				`curriculum/items/${ created.item.id }/syllabus/groups/${ group.group_id }/skills`,
				{ name: `${ name } equation ${ tag }`, code: 'L1' }
			);
			terms.push( objective.term_id );
		}
		const shared = await api(
			page,
			'POST',
			'syllabus-comparison/shared-skills/save',
			{
				name: `Shared equation ${ tag }`,
				subject: 'Mathematics',
				topic: 'Algebra',
				description: 'Solve a linear equation.',
			}
		);
		terms.push( shared.id );
		await api( page, 'PUT', 'syllabus-comparison/mapping', {
			specific_id: terms[ 0 ],
			shared_id: shared.id,
			relation: 'related',
			note: '',
			expected_revision: '',
		} );
		const created = await api(
			page,
			'POST',
			'syllabus-comparison/roadmaps',
			{ name: `Roadmap ${ tag }`, syllabus_ids: [ items[ 0 ] ] }
		);
		const roadmapId = created.syllabus.id;
		items.push( roadmapId );
		const original = await api(
			page,
			'GET',
			`syllabus-comparison/roadmaps/${ roadmapId }`
		);
		await page.goto(
			'/wp-admin/admin.php?page=ohmylms#/content-hub/curriculum'
		);
		await page
			.getByRole( 'link', { name: `Roadmap ${ tag }`, exact: true } )
			.click();
		await expect( page ).toHaveURL(
			new RegExp( `/syllabus/${ roadmapId }$` )
		);
		const sources = page.getByRole( 'region', {
			name: 'Roadmap sources',
			exact: true,
		} );
		await expect( sources ).toBeVisible();
		const launch = page.getByRole( 'button', {
			name: 'Open visual editor',
			exact: true,
		} );
		await expect( launch ).toBeEnabled();
		await expect( page.locator( '.ohmylms-comparison-grid' ) ).toHaveCount(
			0
		);
		await sources
			.getByRole( 'checkbox', {
				name: `Source B ${ tag } · 2028–2030`,
				exact: true,
			} )
			.check();
		await expect( launch ).toBeDisabled();
		await sources
			.getByRole( 'button', {
				name: 'Save roadmap sources',
				exact: true,
			} )
			.click();
		await expect( launch ).toBeEnabled();
		const saved = await api(
			page,
			'GET',
			`syllabus-comparison/roadmaps/${ roadmapId }`
		);
		expect( saved.syllabus_ids ).toEqual( [ items[ 0 ], items[ 1 ] ] );
		expect( saved.skills ).toEqual( original.skills );
		expect( saved.folders ).toEqual( original.folders );
		await launch.click();
		const studio = page.getByRole( 'dialog', {
			name: 'Shared skills visual editor',
			exact: true,
		} );
		await expect( studio ).toBeVisible();
		await studio
			.getByRole( 'button', {
				name: `Shared equation ${ tag } Algebra`,
				exact: true,
			} )
			.click();
		await expect(
			studio.getByRole( 'main' ).getByRole( 'button', {
				name: `Source A equation ${ tag }`,
				exact: true,
			} )
		).toBeVisible();
		await studio
			.getByRole( 'button', { name: 'Close editor', exact: true } )
			.click();
		// Existing bookmarks now open the same home instead of comparison columns.
		await page.goto(
			`/wp-admin/admin.php?page=ohmylms#/content-hub/curriculum/compare?roadmap=${ roadmapId }`
		);
		await expect(
			page.getByRole( 'region', { name: 'Roadmap sources', exact: true } )
		).toBeVisible();
		await expect( page.locator( '.ohmylms-comparison-grid' ) ).toHaveCount(
			0
		);
	} finally {
		for ( const id of items.reverse() ) {
			const current = await api(
				page,
				'GET',
				`curriculum/items/${ id }`
			).catch( () => null );
			await api( page, 'DELETE', `curriculum/items/${ id }`, {
				children: 'delete',
				confirm: true,
			} ).catch( () => null );
			if ( current?.item?.course_id ) {
				await api(
					page,
					'DELETE',
					`courses/${ current.item.course_id }`
				).catch( () => null );
			}
		}
		for ( const id of terms ) {
			await api( page, 'DELETE', `skills/${ id }` ).catch( () => null );
		}
	}
} );
