import { get, patch, post } from '../../lib/api';
import {
	clearCache,
	getSettings,
	listCountries,
	listCurrencies,
	listStates,
	restoreDefaultPages,
	saveSettings,
	searchPages,
} from './api';
import {
	countries,
	currencies,
	general,
	pages,
	response,
	usStates,
} from './fixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	patch: vi.fn(),
	post: vi.fn(),
} ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'typed settings API', () => {
	it( 'reads a group from its own route and validates the answer', async () => {
		vi.mocked( get ).mockResolvedValue( response( 'general' ) );
		const signal = new AbortController().signal;
		const result = await getSettings( 'general', signal );
		expect( result.values ).toEqual( general );
		expect( get ).toHaveBeenCalledWith( '/ohmylms/v1/settings/general', {
			signal,
		} );
		vi.mocked( get ).mockResolvedValueOnce( response( 'currency' ) );
		await expect( getSettings( 'general' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		vi.mocked( get ).mockResolvedValueOnce( { group: 'general' } );
		await expect( getSettings( 'general' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'PATCHes only the changes it is given', async () => {
		vi.mocked( patch ).mockResolvedValue(
			response( 'tax', undefined, [ 'tax_enabled' ] )
		);
		const result = await saveSettings( 'tax', { tax_enabled: true } );
		expect( result.changed ).toEqual( [ 'tax_enabled' ] );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/tax',
			{ tax_enabled: true },
			expect.any( Object )
		);
	} );

	it( 'searches pages by text with a bounded size', async () => {
		vi.mocked( get ).mockResolvedValue( pages );
		expect( await searchPages( { search: '  shop ' } ) ).toEqual( pages );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/settings/pages',
			expect.objectContaining( {
				query: { search: 'shop', per_page: 20 },
			} )
		);
		await searchPages( { perPage: 500 } );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/settings/pages',
			expect.objectContaining( {
				query: { search: undefined, per_page: 20 },
			} )
		);
	} );

	it( 'resolves saved choices by id and ignores empty ids', async () => {
		vi.mocked( get ).mockResolvedValue( [ pages[ 0 ] ] );
		await searchPages( { include: [ 11, 0 ] } );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/settings/pages',
			expect.objectContaining( { query: { include: [ 11 ] } } )
		);
	} );

	it( 'rejects malformed reference data', async () => {
		vi.mocked( get ).mockResolvedValue( [ { id: 'x' } ] );
		await expect( searchPages() ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		vi.mocked( get ).mockResolvedValue( {} );
		await expect( listCurrencies() ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'loads currencies, countries and states', async () => {
		vi.mocked( get ).mockResolvedValueOnce( currencies );
		expect( await listCurrencies() ).toEqual( currencies );
		vi.mocked( get ).mockResolvedValueOnce( countries );
		expect( await listCountries() ).toEqual( countries );
		vi.mocked( get ).mockResolvedValueOnce( usStates );
		expect( await listStates( 'US' ) ).toEqual( usStates );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/settings/countries/US/states',
			expect.any( Object )
		);
	} );

	it( 'restores pages and clears the cache with POST', async () => {
		vi.mocked( post ).mockResolvedValueOnce( {
			restored: [ { key: 'checkout', page_id: 5, title: 'Checkout' } ],
			values: general,
		} );
		const restored = await restoreDefaultPages();
		expect( restored.restored[ 0 ]?.page_id ).toBe( 5 );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/settings/general/restore-pages',
			undefined,
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValueOnce( { deleted: 4 } );
		expect( await clearCache() ).toBe( 4 );
		vi.mocked( post ).mockResolvedValueOnce( { deleted: 'many' } );
		await expect( clearCache() ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
} );
