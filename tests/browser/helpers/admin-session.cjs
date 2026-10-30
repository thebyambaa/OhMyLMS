const fs = require( 'node:fs' );

/**
 * Administrator credentials from the JSON file named by OHMYLMS_TEST_CREDENTIALS
 * (`{ "username": "…", "password": "…" }`), or null when none are configured.
 */
function readAdminCredentials() {
	const file = process.env.OHMYLMS_TEST_CREDENTIALS;
	if ( ! file || ! fs.existsSync( file ) ) {
		return null;
	}
	return JSON.parse( fs.readFileSync( file, 'utf8' ) );
}

/**
 * Log in through wp-login.php and return the session cookies, so a suite logs in once and reuses them.
 *
 * @param {import('@playwright/test').Browser} browser     Browser fixture.
 * @param {string}                             baseURL     Site URL.
 * @param {{username: string, password: string}} credentials Administrator account.
 */
async function logInOnce( browser, baseURL, credentials ) {
	const context = await browser.newContext( { baseURL } );
	const page = await context.newPage();
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( credentials.username );
	await page.locator( '#user_pass' ).fill( credentials.password );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL(
		( url ) => ! url.pathname.endsWith( 'wp-login.php' )
	);
	const { cookies } = await context.storageState();
	await context.close();
	return cookies;
}

module.exports = { readAdminCredentials, logInOnce };
