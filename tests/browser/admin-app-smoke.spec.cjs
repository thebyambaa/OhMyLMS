/**
 * Smoke test of the typed admin app: every main screen opens without "Page not found",
 * an error notice, a script error or a failed plugin request, and an old
 * page=ohmylms#/order-edit/<id> address lands on the order screen.
 *
 * Entry still uses the retired app hash (`page=ohmylms-app#/…`); assertions use the
 * query-string routing contract from tests/fixtures/admin-navigation.json.
 *
 * Needs an administrator in OHMYLMS_TEST_CREDENTIALS and the site in OHMYLMS_TEST_URL.
 * Read-only: it creates and changes nothing on the site.
 */
const { test, expect } = require( '@playwright/test' );
const {
	readAdminCredentials,
	logInOnce,
} = require( './helpers/admin-session.cjs' );
const navigation = require( '../fixtures/admin-navigation.json' );

const APP = '/wp-admin/admin.php?page=ohmylms-app';

const GROUP_PAGES = Object.fromEntries(
	navigation.groups.map( ( group ) => [ group.id, group.page ] )
);

/**
 * Canonical admin.php query string for an app route path.
 *
 * @param {string} route Path such as `/courses` or `/content-hub`.
 * @return {string} Query without `?`.
 */
function addressForRoute( route ) {
	const exact = navigation.cases.find( ( row ) => row[ 0 ] === route );
	if ( exact ) {
		return exact[ 1 ];
	}
	const feature = navigation.routes.find( ( row ) => row.path === route );
	if ( ! feature ) {
		throw new Error( `No navigation case or route for ${ route }` );
	}
	const pairs = [ [ 'page', GROUP_PAGES[ feature.group ] ] ];
	if ( feature.tab ) {
		pairs.push( [ 'tab', feature.tab ] );
	}
	if ( feature.section ) {
		pairs.push( [ 'section', feature.section ] );
	}
	return pairs.map( ( [ name, value ] ) => `${ name }=${ value }` ).join( '&' );
}

/**
 * Optional visibility flag filter for a page/tab address.
 *
 * @param {string} address Query string from addressForRoute().
 * @return {string} Filter name, or '' when the tab is always shown.
 */
function flagForAddress( address ) {
	const params = new URLSearchParams( address );
	const page = params.get( 'page' );
	const tab = params.get( 'tab' ) || '';
	const group = navigation.groups.find( ( row ) => row.page === page );
	const tabDef = group?.tabs.find( ( row ) => row.id === tab );
	return tabDef?.flag || '';
}

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
].map( ( route ) => {
	const address = addressForRoute( route );
	return {
		route,
		address,
		flag: flagForAddress( address ),
		params: Object.fromEntries( new URLSearchParams( address ) ),
	};
} );

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

/**
 * Default section the PHP chrome uses when the address omits `section`.
 *
 * @param {string} pageSlug Page slug.
 * @param {string} tabId    Tab id.
 * @return {string|null} Fixture default, or null when the address has no default.
 */
function defaultSectionFor( pageSlug, tabId ) {
	const group = navigation.groups.find( ( row ) => row.page === pageSlug );
	const tab = group?.tabs.find( ( row ) => row.id === tabId );
	if ( ! tab ) {
		return null;
	}
	return tab.defaultSection || null;
}

/**
 * Assert the browser landed on the query-string address for a screen.
 * Mount data-* attributes are the authoritative place (flags can rewrite the tab).
 * Bare addresses may open the tab's default section (settings → general).
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {{page?: string, tab?: string, section?: string}} expected Expected place.
 */
async function expectPlace( page, expected ) {
	const root = page.locator( '#ohmylms-app-root' );
	await expect( root ).toBeVisible();
	await expect( root ).toHaveAttribute( 'data-page', expected.page || '' );
	await expect( root ).toHaveAttribute( 'data-tab', expected.tab || '' );
	const url = new URL( page.url() );
	expect( url.hash ).toBe( '' );
	expect( url.searchParams.get( 'page' ) ).not.toBe( 'ohmylms-app' );
	expect( url.searchParams.get( 'page' ) ).toBe( expected.page );
	if ( expected.tab ) {
		expect( url.searchParams.get( 'tab' ) ).toBe( expected.tab );
	}
	if ( expected.section ) {
		await expect( root ).toHaveAttribute(
			'data-section',
			expected.section
		);
		expect( url.searchParams.get( 'section' ) ).toBe( expected.section );
	} else {
		const fallback = defaultSectionFor(
			expected.page || '',
			expected.tab || ''
		);
		if ( fallback ) {
			await expect( root ).toHaveAttribute( 'data-section', fallback );
		}
	}
}

test.describe( 'admin app screens', () => {
	for ( const screen of SCREENS ) {
		test( `${ screen.route } opens cleanly`, async ( { page }, testInfo ) => {
			const problems = watchProblems( page );
			await page.goto( `${ APP }#${ screen.route }` );
			const main = await settle( page );

			const root = page.locator( '#ohmylms-app-root' );
			await expect( root ).toBeVisible();
			const landedTab = ( await root.getAttribute( 'data-tab' ) ) || '';
			const expectedTab = screen.params.tab || '';
			if ( screen.flag && landedTab !== expectedTab ) {
				testInfo.skip(
					true,
					`${ screen.flag } is off; ${ screen.route } fell back to data-tab=${ landedTab || '(empty)' }`
				);
			}

			await expectPlace( page, {
				page: screen.params.page,
				tab: screen.params.tab || '',
				section: screen.params.section || '',
			} );

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
			expect( problems ).toEqual( [] );
		} );
	}
} );

test( 'legacy order-edit address lands on the order screen without the marker', async ( {
	page,
}, testInfo ) => {
	const problems = watchProblems( page );
	// Prefer a real order so the detail screen does not 404; routing itself is id-agnostic.
	await page.goto( '/wp-admin/admin.php?page=ohmylms-commerce&tab=orders' );
	await settle( page );
	const orderId = await page.evaluate( async () => {
		const orders = await window.wp.apiFetch( {
			path: '/ohmylms-commerce/v1/orders?per_page=1',
		} );
		return Array.isArray( orders ) && orders[ 0 ]?.id
			? orders[ 0 ].id
			: null;
	} );
	if ( ! orderId ) {
		testInfo.skip(
			true,
			'No commerce orders on the disposable site to open via the legacy order-edit address'
		);
	}

	await page.goto(
		`/wp-admin/admin.php?page=ohmylms#/order-edit/${ orderId }`
	);
	await expect
		.poll( () => new URL( page.url() ).searchParams.get( 'page' ) )
		.toBe( 'ohmylms-commerce' );
	await expect
		.poll( () => new URL( page.url() ).searchParams.get( 'tab' ) )
		.toBe( 'orders' );
	await expect
		.poll( () => new URL( page.url() ).searchParams.get( 'id' ) )
		.toBe( String( orderId ) );
	expect( new URL( page.url() ).hash ).toBe( '' );
	expect( page.url() ).not.toContain( 'ohmylms_from_legacy' );
	await settle( page );
	expect( page.url() ).not.toContain( 'ohmylms_from_legacy' );
	await expectPlace( page, {
		page: 'ohmylms-commerce',
		tab: 'orders',
	} );
	await expect(
		page
			.getByRole( 'main' )
			.getByRole( 'heading', { name: 'Page not found' } )
	).toHaveCount( 0 );
	expect( problems ).toEqual( [] );
} );
