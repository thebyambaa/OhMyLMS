import { act, renderHook, waitFor } from '@testing-library/react';
import { getOptions, listWebhooks } from './api';
import { options, webhook } from './fixtures';
import { useWebhooks } from './useWebhooks';
import type { Collection } from '../../lib/api/collection';
import type { Webhook } from './types';

vi.mock( './api', () => ( { getOptions: vi.fn(), listWebhooks: vi.fn() } ) );

it( 'cancels obsolete searches and ignores responses from transports that finish after abort', async () => {
	let resolveOld!: ( value: Collection< Webhook > ) => void;
	vi.mocked( getOptions ).mockResolvedValue( options );
	vi.mocked( listWebhooks ).mockImplementationOnce(
		() =>
			new Promise( ( resolve ) => {
				resolveOld = resolve;
			} )
	);
	vi.mocked( listWebhooks ).mockResolvedValue( {
		items: [ { ...webhook, name: 'New result' } ],
		total: 1,
		totalPages: 1,
	} );
	const view = renderHook( useWebhooks );
	const oldSignal = vi.mocked( listWebhooks ).mock.calls[ 0 ]?.[ 2 ];
	act( () => view.result.current.search( 'new' ) );
	await waitFor( () =>
		expect( view.result.current.data.items[ 0 ]?.name ).toBe( 'New result' )
	);
	expect( oldSignal?.aborted ).toBe( true );
	await act( async () => {
		resolveOld( { items: [ webhook ], total: 1, totalPages: 1 } );
	} );
	expect( view.result.current.data.items[ 0 ]?.name ).toBe( 'New result' );
	const requestCount = vi.mocked( listWebhooks ).mock.calls.length;
	act( () => view.result.current.search( 'new' ) );
	await waitFor( () =>
		expect( listWebhooks ).toHaveBeenCalledTimes( requestCount + 1 )
	);
	const currentSignal = vi.mocked( listWebhooks ).mock.calls.at( -1 )?.[ 2 ];
	view.unmount();
	expect( currentSignal?.aborted ).toBe( true );
} );
