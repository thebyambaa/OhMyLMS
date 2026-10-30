import { get, post } from '../../lib/api';
import {
	completeSetup,
	dismissSetup,
	getSetup,
	listCurrencies,
	resetSetup,
	saveStep,
	skipStep,
} from './api';
import { currencies, noResult, rawState } from './fixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
} ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'typed setup API', () => {
	it( 'reads and validates the state', async () => {
		vi.mocked( get ).mockResolvedValue( rawState() );
		const signal = new AbortController().signal;
		const state = await getSetup( signal );
		expect( state.currentStep ).toBe( 'welcome' );
		expect( get ).toHaveBeenCalledWith( '/ohmylms/v1/setup', { signal } );
		vi.mocked( get ).mockResolvedValueOnce( { nope: true } );
		await expect( getSetup() ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'POSTs a step with its input', async () => {
		vi.mocked( post ).mockResolvedValue( {
			state: rawState(),
			result: { changed: [ 'currency' ], pages: [] },
		} );
		const input = {
			currency: 'EUR',
			currency_pos: 'right' as const,
			price_num_decimals: 0,
		};
		const result = await saveStep( 'preferences', input );
		expect( result.result.changed ).toEqual( [ 'currency' ] );
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/setup/steps/preferences',
			input,
			expect.any( Object )
		);
	} );

	it( 'skips, completes, dismisses and resets', async () => {
		vi.mocked( post ).mockResolvedValue( {
			state: rawState(),
			result: noResult,
		} );
		await skipStep( 'pages' );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/setup/steps/pages/skip',
			undefined,
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValue( { state: rawState() } );
		await completeSetup();
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/setup/complete',
			undefined,
			expect.any( Object )
		);
		await dismissSetup( 'niche' );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/setup/dismiss',
			{ step: 'niche' },
			expect.any( Object )
		);
		await dismissSetup( null );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/setup/dismiss',
			{},
			expect.any( Object )
		);
		await resetSetup();
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/setup/reset',
			undefined,
			expect.any( Object )
		);
	} );

	it( 'rejects a malformed action response', async () => {
		vi.mocked( post ).mockResolvedValue( { state: 'x' } );
		await expect( completeSetup() ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		await expect( saveStep( 'welcome', {} ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'lists currencies from the settings API', async () => {
		vi.mocked( get ).mockResolvedValue( currencies );
		expect( await listCurrencies() ).toEqual( currencies );
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/currencies',
			expect.any( Object )
		);
	} );
} );
