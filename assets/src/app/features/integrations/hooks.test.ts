import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../../lib/api';
import { getIntegrationSettings, listIntegrations } from './api';
import { gamification, integrations, zoom, zoomSettings } from './fixtures';
import { useIntegrationSettings, useIntegrations } from './hooks';
import type { Integration } from './types';

vi.mock( './api', () => ( {
	listIntegrations: vi.fn(),
	getIntegrationSettings: vi.fn(),
} ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'useIntegrations', () => {
	it( 'loads, replaces one item, and reloads with a fresh request', async () => {
		vi.mocked( listIntegrations ).mockResolvedValue( integrations );
		const view = renderHook( useIntegrations );
		expect( view.result.current.loading ).toBe( true );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.replace( { ...zoom, enabled: true } ) );
		expect( view.result.current.items[ 0 ]?.enabled ).toBe( true );
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( listIntegrations ).toHaveBeenCalledTimes( 2 )
		);
	} );
	it( 'ignores a response that arrives after a newer request or unmount', async () => {
		let resolveOld!: ( value: Integration[] ) => void;
		vi.mocked( listIntegrations ).mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveOld = resolve;
				} )
		);
		vi.mocked( listIntegrations ).mockResolvedValue( [ gamification ] );
		const view = renderHook( useIntegrations );
		const oldSignal = vi.mocked( listIntegrations ).mock.calls[ 0 ]?.[ 0 ];
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.items ).toEqual( [ gamification ] )
		);
		expect( oldSignal?.aborted ).toBe( true );
		await act( async () => resolveOld( [ zoom ] ) );
		expect( view.result.current.items ).toEqual( [ gamification ] );
		const lastSignal = vi
			.mocked( listIntegrations )
			.mock.calls.at( -1 )?.[ 0 ];
		view.unmount();
		expect( lastSignal?.aborted ).toBe( true );
	} );
	it( 'marks permission failures so the screen can hide retry', async () => {
		vi.mocked( listIntegrations ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'rest_forbidden',
				message: 'No access',
			} )
		);
		const view = renderHook( useIntegrations );
		await waitFor( () =>
			expect( view.result.current.error ).toEqual( {
				message: 'No access',
				permission: true,
			} )
		);
	} );
} );

describe( 'useIntegrationSettings', () => {
	it( 'loads settings for an id and reloads when the id changes', async () => {
		vi.mocked( getIntegrationSettings ).mockResolvedValue( zoomSettings );
		const view = renderHook( ( { id } ) => useIntegrationSettings( id ), {
			initialProps: { id: 'zoom' },
		} );
		await waitFor( () =>
			expect( view.result.current.settings ).toEqual( zoomSettings )
		);
		view.rerender( { id: 'google_signin' } );
		await waitFor( () =>
			expect( getIntegrationSettings ).toHaveBeenLastCalledWith(
				'google_signin',
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'reports a failure', async () => {
		vi.mocked( getIntegrationSettings ).mockRejectedValue(
			new Error( 'boom' )
		);
		const view = renderHook( () => useIntegrationSettings( 'zoom' ) );
		await waitFor( () =>
			expect( view.result.current.error?.message ).toBe( 'boom' )
		);
		expect( view.result.current.error?.permission ).toBe( false );
	} );
} );
