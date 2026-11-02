import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../lib/api';
import { useRequest } from './useRequest';

const forbidden = () =>
	new ApiError( { status: 403, code: 'rest_forbidden', message: 'No' } );

function deferred< T >() {
	let resolve!: ( value: T ) => void;
	let reject!: ( reason: unknown ) => void;
	const promise = new Promise< T >( ( done, fail ) => {
		resolve = done;
		reject = fail;
	} );
	return { promise, resolve, reject };
}

describe( 'useRequest without a key', () => {
	it( 'reports the failure message and clears data while reloading', async () => {
		const load = vi
			.fn< ( signal: AbortSignal ) => Promise< string > >()
			.mockResolvedValueOnce( 'first' )
			.mockRejectedValueOnce( forbidden() );
		const view = renderHook( () => useRequest( load ) );
		expect( view.result.current.error ).toBe( '' );
		await waitFor( () =>
			expect( view.result.current.data ).toBe( 'first' )
		);
		act( () => view.result.current.retry() );
		expect( view.result.current.data ).toBeNull();
		await waitFor( () => expect( view.result.current.error ).toBe( 'No' ) );
		expect( view.result.current.data ).toBeNull();
	} );

	it( 'starts a new request when the load function changes', async () => {
		const view = renderHook( ( { load } ) => useRequest( load ), {
			initialProps: { load: async () => 'a' },
		} );
		await waitFor( () => expect( view.result.current.data ).toBe( 'a' ) );
		view.rerender( { load: async () => 'b' } );
		await waitFor( () => expect( view.result.current.data ).toBe( 'b' ) );
	} );
} );

describe( 'useRequest with a key', () => {
	it( 'uses the latest closure, reloads only when the key changes, and ignores late responses', async () => {
		const old = deferred< string >();
		const load = vi
			.fn< ( id: string, signal: AbortSignal ) => Promise< string > >()
			.mockImplementationOnce( () => old.promise )
			.mockImplementation( async ( id ) => `item ${ id }` );
		const view = renderHook(
			( { id } ) =>
				useRequest( ( signal ) => load( id, signal ), {
					key: id,
					keepData: true,
					errorShape: 'object',
				} ),
			{ initialProps: { id: '1' } }
		);
		const firstSignal = load.mock.calls[ 0 ]?.[ 1 ];
		view.rerender( { id: '1' } );
		expect( load ).toHaveBeenCalledTimes( 1 );
		view.rerender( { id: '2' } );
		await waitFor( () =>
			expect( view.result.current.data ).toBe( 'item 2' )
		);
		expect( firstSignal?.aborted ).toBe( true );
		await act( async () => old.resolve( 'stale' ) );
		expect( view.result.current.data ).toBe( 'item 2' );
		const lastSignal = load.mock.calls.at( -1 )?.[ 1 ];
		view.unmount();
		expect( lastSignal?.aborted ).toBe( true );
	} );

	it( 'keeps data while reloading and after a failure, next to the ApiError', async () => {
		const next = deferred< string >();
		const load = vi
			.fn< ( signal: AbortSignal ) => Promise< string > >()
			.mockResolvedValueOnce( 'good' )
			.mockImplementationOnce( () => next.promise );
		const view = renderHook( () =>
			useRequest( load, {
				key: 'one',
				keepData: true,
				errorShape: 'object',
			} )
		);
		expect( view.result.current.error ).toBeNull();
		await waitFor( () =>
			expect( view.result.current.data ).toBe( 'good' )
		);
		act( () => view.result.current.reload() );
		expect( view.result.current.loading ).toBe( true );
		expect( view.result.current.data ).toBe( 'good' );
		await act( async () => next.reject( forbidden() ) );
		expect( view.result.current.error ).toBeInstanceOf( ApiError );
		expect( view.result.current.error?.status ).toBe( 403 );
		expect( view.result.current.data ).toBe( 'good' );
	} );

	it( 'replaces data from a mutation result and clears the error', async () => {
		const view = renderHook( () =>
			useRequest< string >( () => Promise.reject( forbidden() ), {
				key: 'x',
				errorShape: 'object',
			} )
		);
		await waitFor( () =>
			expect( view.result.current.error?.code ).toBe( 'rest_forbidden' )
		);
		act( () => view.result.current.replace( 'saved' ) );
		expect( view.result.current.data ).toBe( 'saved' );
		expect( view.result.current.error ).toBeNull();
	} );

	it( 'keeps reload, retry and replace stable', async () => {
		const view = renderHook( () =>
			useRequest( async () => 1, { key: 'k' } )
		);
		const { reload, replace } = view.result.current;
		await waitFor( () => expect( view.result.current.data ).toBe( 1 ) );
		expect( view.result.current.reload ).toBe( reload );
		expect( view.result.current.retry ).toBe( reload );
		expect( view.result.current.replace ).toBe( replace );
	} );
} );
