/**
 * Smoke test of the typed admin app (admin.php?page=ohmylms-app): every main screen opens without
 * "Page not found", an error notice, a script error or a failed plugin request, and an old
 * page=ohmylms#/order-edit/<id> address lands on the order screen.
 *
 * Needs an administrator in OHMYLMS_TEST_CREDENTIALS and the site in OHMYLMS_TEST_URL. Read-only:
 * it creates and changes nothing on the site.
 */
const { test, expect } = require( '@playwright/test' );
const {
	readAdminCredentials,
	logInOnce,
} = require( './helpers/admin-session.cjs' );

const APP = '/wp-admin/admin.php?page=ohmylms-app';

const SCREENS = [
	'/dashboard',
	'/content-hub',
	'/courses',
	'/quizzes',
	'/reports/quizzes',
	'/exams',
	'/content-hub/question-bank',
	'/orders',
	'/memberships',
	'/students',
	'/account-hub',
	'/gamification',
	'/sessions',
	'/emails',
	'/integrations',
	'/settings',
	'/webhooks',
];

const PLUGIN_REQUEST =
	/\/plugins\/ohmylms\/|\/wp-json\/ohmylms|[?&]rest_route=(?:\/|%2F)ohmylms/i;

const credentials = readAdminCredentials();
let cookies = [];

test.skip( ! credentials, 'OHMYLMS_TEST_CREDENTIALS is not set' );

test.beforeAll( async ( { browser }, testInfo ) => {
	cookies = await logInOnce(
		browser,
		testInfo.project.use.baseURL,
		credentials
	);
} );

test.beforeEach( async ( { context } ) => {
	await context.addCookies( cookies );
} );

/**
 * Collect script errors and failed plugin requests for the rest of the test.
 *
 * @param {import('@playwright/test').Page} page Page to watch.
 */
function watchProblems( page ) {
	const problems = [];
	page.on( 'pageerror', ( error ) =>
		problems.push( `script error: ${ error.message }` )
	);
	page.on( 'requestfailed', ( request ) => {
		const reason = request.failure()?.errorText || '';
		// The app cancels its own requests on navigation; that is not a failure.
		if (
			PLUGIN_REQUEST.test( request.url() ) &&
			! reason.includes( 'ERR_ABORTED' )
		) {
			problems.push( `request failed: ${ request.url() } ${ reason }` );
		}
	} );
	page.on( 'response', ( response ) => {
		if (
			PLUGIN_REQUEST.test( response.url() ) &&
			response.status() >= 400
		) {
			problems.push( `HTTP ${ response.status() }: ${ response.url() }` );
		}
	} );
	return problems;
}

/**
 * Wait until the routed screen inside the app's main landmark finished loading.
 *
 * @param {import('@playwright/test').Page} page App page.
 */
async function settle( page ) {
	const main = page.getByRole( 'main' );
	await expect( main ).toBeVisible();
	await page.waitForLoadState( 'networkidle' );
	await expect( main.getByText( 'Loading…' ) ).toHaveCount( 0 );
	return main;
}

test.describe( 'admin app screens', () => {
	for ( const route of SCREENS ) {
		test( `#${ route } opens cleanly`, async ( { page } ) => {
			const problems = watchProblems( page );
			await page.goto( `${ APP }#${ route }` );
			const main = await settle( page );

			await expect(
				main.getByRole( 'heading', { name: 'Page not found' } )
			).toHaveCount( 0 );
			await expect(
				main.getByRole( 'alert' ).filter( {
					hasText: 'Something went wrong',
				} )
			).toHaveCount( 0 );
			// WordPress' own error notice classes (components Notice and admin notices).
			await expect(
				main.locator( '.components-notice.is-error, .notice-error' )
			).toHaveCount( 0 );
			await expect( main ).toContainText( /\S/ );
			expect( new URL( page.url() ).hash ).toBe( `#${ route }` );
			expect( problems ).toEqual( [] );
		} );
	}
} );

test( 'legacy order-edit address lands on the order screen without the marker', async ( {
	page,
} ) => {
	const problems = watchProblems( page );
	// Use a real order when the site has one; the mapping itself does not depend on the id.
	await page.goto( `${ APP }#/orders` );
	await settle( page );
	const orderId = await page.evaluate( async () => {
		const orders = await window.wp.apiFetch( {
			path: '/ohmylms-commerce/v1/orders?per_page=1',
		} );
		return Array.isArray( orders ) && orders[ 0 ]?.id ? orders[ 0 ].id : 12;
	} );

	await page.goto(
		`/wp-admin/admin.php?page=ohmylms#/order-edit/${ orderId }`
	);
	await expect
		.poll( () => new URL( page.url() ).hash )
		.toBe( `#/orders/${ orderId }` );
	await expect
		.poll( () => new URL( page.url() ).searchParams.get( 'page' ) )
		.toBe( 'ohmylms-app' );
	expect( page.url() ).not.toContain( 'ohmylms_from_legacy' );
	await settle( page );
	expect( page.url() ).not.toContain( 'ohmylms_from_legacy' );
	await expect(
		page
			.getByRole( 'main' )
			.getByRole( 'heading', { name: 'Page not found' } )
	).toHaveCount( 0 );
	expect( problems ).toEqual( [] );
} );
