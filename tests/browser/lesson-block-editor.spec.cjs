const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );

test( 'native Gutenberg edits lessons and saves LMS settings together', async ( {
	page,
	context,
} ) => {
	if ( process.env.OHMYLMS_TEST_STORAGE_STATE ) {
		const state = JSON.parse(
			fs.readFileSync( process.env.OHMYLMS_TEST_STORAGE_STATE, 'utf8' )
		);
		await context.addCookies( state.cookies );
	} else {
		const { username, password } = JSON.parse(
			fs.readFileSync( process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8' )
		);
		await page.goto( '/wp-login.php' );
		await page.locator( '#user_login' ).fill( username );
		await page.locator( '#user_pass' ).fill( password );
		await page.locator( '#wp-submit' ).click();
		await page.waitForURL(
			( url ) => ! url.pathname.endsWith( 'wp-login.php' )
		);
	}
	await page.goto( '/wp-admin/admin.php?page=ohmylms-app#/content-hub/lessons' );
	await page.waitForFunction( () => window.wp?.apiFetch );
	const lesson = await page.evaluate( () =>
		wp.apiFetch( {
			path: '/ohmylms/v1/lessons',
			method: 'POST',
			data: {
				name: 'Native Gutenberg test',
				type: 'text',
				status: 'draft',
				description:
					'<!-- wp:paragraph --><p>Original paragraph.</p><!-- /wp:paragraph -->',
			},
		} )
	);
	try {
		await page.goto(
			`/wp-admin/post.php?post=${ lesson.id }&action=edit&ohmylms_return=${ encodeURIComponent( '/content-hub/curriculum/syllabus/24?node=s%3A1' ) }`
		);
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		const paragraph = canvas.locator( '[data-type="core/paragraph"]' );
		await expect( paragraph ).toHaveText( 'Original paragraph.' );
		// WordPress restores the user's sidebar preference; editing does not require a specific tab.
		await paragraph.fill( 'Saved native block paragraph.' );
		await page.getByRole( 'tab', { name: 'Lesson', exact: true } ).click();
		const settingsPanel = page.getByRole( 'button', {
			name: 'Lesson settings',
			exact: true,
		} );
		if (
			( await settingsPanel.getAttribute( 'aria-expanded' ) ) !== 'true'
		) {
			await settingsPanel.click();
		}
		await page
			.getByRole( 'checkbox', {
				name: 'Allow lesson preview',
				exact: true,
			} )
			.check();
		await page.keyboard.press( 'Control+s' );
		await expect
			.poll( async () => {
				const data = await page.evaluate(
					( id ) =>
						wp.apiFetch( { path: `/ohmylms/v1/lessons/${ id }` } ),
					lesson.id
				);
				return {
					...data,
					preview_enable:
						data.preview_enable === true ||
						data.preview_enable === '1' ||
						data.preview_enable === 1,
				};
			} )
			.toMatchObject( {
				description: expect.stringContaining(
					'Saved native block paragraph.'
				),
				preview_enable: true,
			} );
		await page.reload();
		await expect( paragraph ).toHaveText( 'Saved native block paragraph.' );
		await page.getByRole( 'tab', { name: 'Lesson', exact: true } ).click();
		if (
			( await settingsPanel.getAttribute( 'aria-expanded' ) ) !== 'true'
		) {
			await settingsPanel.click();
		}
		await expect(
			page.getByRole( 'checkbox', {
				name: 'Allow lesson preview',
				exact: true,
			} )
		).toBeChecked();
		await expect(
			page
				.getByRole( 'link', {
					name: 'Back to Content Hub',
					exact: true,
				} )
				.first()
		).toHaveAttribute(
			'href',
			/#\/content-hub\/curriculum\/syllabus\/24\?node=s%3A1$/
		);
		await page
			.getByRole( 'button', { name: 'Block Inserter', exact: true } )
			.click();
		await expect(
			page.getByRole( 'tab', { name: 'Blocks', exact: true } )
		).toBeVisible();
		await expect( paragraph ).toBeVisible();
		await page
			.getByRole( 'button', { name: 'Block Inserter', exact: true } )
			.click();
		await page
			.getByRole( 'button', { name: 'Document Overview', exact: true } )
			.click();
		await expect(
			page.getByRole( 'tab', { name: 'List View', exact: true } )
		).toBeVisible();
		await page.screenshot( {
			path:
				process.env.OHMYLMS_NATIVE_SCREENSHOT ||
				'test-results/native-lesson-editor.png',
			fullPage: false,
		} );
	} finally {
		await page.evaluate(
			( id ) =>
				wp.apiFetch( {
					path: '/ohmylms/v1/content-hub/lessons/trash',
					method: 'POST',
					data: { ids: [ id ] },
				} ),
			lesson.id
		);
	}
} );
