import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../../lib/api';
import { getSettings, saveSettings } from './api';
import { currency, general, response } from './fixtures';
import { useSettingsGroup } from './hooks';

vi.mock( './api', () => ( {
	getSettings: vi.fn(),
	saveSettings: vi.fn(),
} ) );

beforeEach( () => vi.clearAllMocks() );

function setup( active = true ) {
	vi.mocked( getSettings ).mockResolvedValue(
		response( 'currency' ) as never
	);
	return renderHook(
		( { on }: { on: boolean } ) => useSettingsGroup( 'currency', on ),
		{ initialProps: { on: active } }
	);
}

describe( 'useSettingsGroup', () => {
	it( 'requests nothing until the tab is first active', async () => {
		const view = setup( false );
		expect( getSettings ).not.toHaveBeenCalled();
		expect( view.result.current.loading ).toBe( false );
		view.rerender( { on: true } );
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		expect( getSettings ).toHaveBeenCalledTimes( 1 );
		view.rerender( { on: false } );
		view.rerender( { on: true } );
		expect( getSettings ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'tracks dirty state and saves only the changed fields', async () => {
		const view = setup();
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		expect( view.result.current.dirty ).toBe( false );
		act( () => view.result.current.setField( 'price_num_decimals', 3 ) );
		expect( view.result.current.dirty ).toBe( true );
		expect( view.result.current.changed ).toEqual( [
			'price_num_decimals',
		] );
		vi.mocked( saveSettings ).mockResolvedValue(
			response( 'currency', { ...currency, price_num_decimals: 3 }, [
				'price_num_decimals',
			] ) as never
		);
		let ok = false;
		await act( async () => {
			ok = await view.result.current.save();
		} );
		expect( ok ).toBe( true );
		expect( saveSettings ).toHaveBeenCalledWith(
			'currency',
			{ price_num_decimals: 3 },
			expect.any( AbortSignal )
		);
		expect( view.result.current.dirty ).toBe( false );
		expect( view.result.current.saved?.price_num_decimals ).toBe( 3 );
		expect( view.result.current.lastSaved ).toEqual( [
			'price_num_decimals',
		] );
	} );

	it( 'does not call the server when nothing changed or the draft is invalid', async () => {
		const view = setup();
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		await act( async () => {
			expect( await view.result.current.save() ).toBe( true );
		} );
		act( () => view.result.current.setField( 'price_num_decimals', 99 ) );
		await act( async () => {
			expect( await view.result.current.save() ).toBe( false );
		} );
		expect( saveSettings ).not.toHaveBeenCalled();
		expect( view.result.current.errors ).toHaveProperty(
			'price_num_decimals'
		);
		expect( view.result.current.saveError?.message ).toBeTruthy();
		act( () => view.result.current.setField( 'price_num_decimals', 2 ) );
		expect( view.result.current.errors ).toEqual( {} );
	} );

	it( 'keeps the draft and shows server messages after a rejected save', async () => {
		const view = setup();
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		act( () => view.result.current.setField( 'currency', 'EUR' ) );
		vi.mocked( saveSettings ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_settings_invalid_value',
				message: 'Some settings are not valid.',
				details: {
					params: { currency: 'Choose a supported currency.' },
				},
			} )
		);
		await act( async () => {
			expect( await view.result.current.save() ).toBe( false );
		} );
		expect( view.result.current.errors ).toEqual( {
			currency: 'Choose a supported currency.',
		} );
		expect( view.result.current.draft?.currency ).toBe( 'EUR' );
		expect( view.result.current.dirty ).toBe( true );
		expect( view.result.current.saving ).toBe( false );
	} );

	it( 'reports a permission error', async () => {
		const view = setup();
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		act( () => view.result.current.setField( 'currency_pos', 'right' ) );
		vi.mocked( saveSettings ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_settings_cannot_edit',
				message: 'Sorry, you are not allowed to change settings.',
			} )
		);
		await act( async () => {
			await view.result.current.save();
		} );
		expect( view.result.current.saveError?.permission ).toBe( true );
	} );

	it( 'ignores a second save while one is pending and cancels on unmount', async () => {
		const view = setup();
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		act( () => view.result.current.setField( 'currency_pos', 'right' ) );
		vi.mocked( saveSettings ).mockImplementation(
			() => new Promise( () => undefined )
		);
		act( () => {
			void view.result.current.save();
		} );
		await waitFor( () =>
			expect( view.result.current.saving ).toBe( true )
		);
		await act( async () => {
			expect( await view.result.current.save() ).toBe( false );
		} );
		expect( saveSettings ).toHaveBeenCalledTimes( 1 );
		const signal = vi.mocked( saveSettings ).mock.calls[ 0 ]?.[ 2 ];
		view.unmount();
		expect( signal?.aborted ).toBe( true );
	} );

	it( 'shows a load failure and retries', async () => {
		vi.mocked( getSettings ).mockRejectedValueOnce(
			new ApiError( { status: 500, code: 'boom', message: 'Broken' } )
		);
		vi.mocked( getSettings ).mockResolvedValue(
			response( 'currency' ) as never
		);
		const view = renderHook( () => useSettingsGroup( 'currency', true ) );
		await waitFor( () =>
			expect( view.result.current.loadError?.message ).toBe( 'Broken' )
		);
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		expect( view.result.current.loadError ).toBeNull();
	} );

	it( 'reset drops edits and adopt keeps what the user typed', async () => {
		vi.mocked( getSettings ).mockResolvedValue(
			response( 'general' ) as never
		);
		const view = renderHook( () => useSettingsGroup( 'general', true ) );
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		act( () => view.result.current.setField( 'terms_page_id', 9 ) );
		act( () =>
			view.result.current.adopt( { ...general, checkout_page_id: 99 } )
		);
		expect( view.result.current.draft?.checkout_page_id ).toBe( 99 );
		expect( view.result.current.draft?.terms_page_id ).toBe( 9 );
		expect( view.result.current.saved?.checkout_page_id ).toBe( 99 );
		act( () => view.result.current.reset() );
		expect( view.result.current.draft?.terms_page_id ).toBe( 0 );
		expect( view.result.current.dirty ).toBe( false );
	} );
} );
