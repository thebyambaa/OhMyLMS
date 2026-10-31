import { act, renderHook, waitFor } from '@testing-library/react';
import { MemoryRouter, useLocation } from 'react-router-dom';
import type { ReactNode } from 'react';
import { ApiError } from '../lib/api';
import type { Collection } from '../lib/api/collection';
import { usePagedList } from './usePagedList';
import type { PageLoader } from './usePagedList';

const page = ( items: number[], totalPages = 1 ) => ( {
	items,
	total: items.length,
	totalPages,
} );

const loader = () =>
	vi.fn< PageLoader< number > >().mockResolvedValue( page( [ 1 ], 3 ) );

describe( 'usePagedList', () => {
	it( 'reloads on demand, reports failures and aborts on unmount', async () => {
		const load = vi
			.fn< PageLoader< number > >()
			.mockResolvedValueOnce( page( [ 1 ] ) )
			.mockRejectedValueOnce(
				new ApiError( { status: 500, code: 'x', message: 'Broken' } )
			)
			.mockReturnValue( new Promise( () => undefined ) );
		const view = renderHook( () => usePagedList( load ) );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		expect( view.result.current.data.items ).toEqual( [ 1 ] );
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.error ).toBe( 'Broken' )
		);
		expect( view.result.current.data.items ).toEqual( [] );
		act( () => view.result.current.reload() );
		const signal = load.mock.calls.at( -1 )?.[ 2 ];
		view.unmount();
		expect( signal?.aborted ).toBe( true );
	} );

	it( 'keeps page and search in memory; a new search goes back to page one', async () => {
		const load = loader();
		const view = renderHook( () => usePagedList( load ) );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		expect( load ).toHaveBeenLastCalledWith(
			1,
			'',
			expect.any( AbortSignal )
		);
		expect( view.result.current.pageHref ).toBeUndefined();
		act( () => view.result.current.setPage( 2 ) );
		await waitFor( () =>
			expect( load ).toHaveBeenLastCalledWith(
				2,
				'',
				expect.any( AbortSignal )
			)
		);
		act( () => view.result.current.setSearch( '  maths ' ) );
		await waitFor( () =>
			expect( load ).toHaveBeenLastCalledWith(
				1,
				'maths',
				expect.any( AbortSignal )
			)
		);
		expect( view.result.current.search ).toBe( 'maths' );
	} );

	it( 'cancels the previous request when the loader changes and ignores its late result', async () => {
		let resolveOld!: ( value: Collection< number > ) => void;
		const old = vi.fn< PageLoader< number > >(
			() =>
				new Promise( ( resolve ) => {
					resolveOld = resolve;
				} )
		);
		const next = vi
			.fn< PageLoader< number > >()
			.mockResolvedValue( page( [ 2 ] ) );
		const view = renderHook(
			( { load }: { load: PageLoader< number > } ) =>
				usePagedList( load ),
			{ initialProps: { load: old } }
		);
		const oldSignal = old.mock.calls[ 0 ]?.[ 2 ];
		view.rerender( { load: next } );
		expect( oldSignal?.aborted ).toBe( true );
		await waitFor( () =>
			expect( view.result.current.data.items ).toEqual( [ 2 ] )
		);
		await act( async () => resolveOld( page( [ 1 ] ) ) );
		expect( view.result.current.data.items ).toEqual( [ 2 ] );
	} );

	it( 'keeps the last page while the next loads and after it fails with keepPrevious', async () => {
		let resolveNext!: ( value: Collection< number > ) => void;
		const load = vi
			.fn< PageLoader< number > >()
			.mockResolvedValueOnce( page( [ 1 ], 2 ) )
			.mockImplementationOnce(
				() =>
					new Promise( ( resolve ) => {
						resolveNext = resolve;
					} )
			)
			.mockRejectedValueOnce(
				new ApiError( { status: 500, code: 'x', message: 'Broken' } )
			);
		const view = renderHook( () =>
			usePagedList( load, { keepPrevious: true } )
		);
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 2 ) );
		expect( view.result.current.loading ).toBe( true );
		expect( view.result.current.data.items ).toEqual( [ 1 ] );
		await act( async () => resolveNext( page( [ 2 ], 2 ) ) );
		expect( view.result.current.data.items ).toEqual( [ 2 ] );
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.error ).toBe( 'Broken' )
		);
		expect( view.result.current.data.items ).toEqual( [ 2 ] );
	} );

	it( 'keeps page and search in the address with inAddress', async () => {
		const load = loader();
		const wrapper = ( { children }: { children: ReactNode } ) => (
			<MemoryRouter initialEntries={ [ '/schools?s=north&page=2' ] }>
				{ children }
			</MemoryRouter>
		);
		const view = renderHook(
			() => ( {
				list: usePagedList( load, { inAddress: true } ),
				location: useLocation(),
			} ),
			{ wrapper }
		);
		await waitFor( () =>
			expect( view.result.current.list.loading ).toBe( false )
		);
		expect( load ).toHaveBeenLastCalledWith(
			2,
			'north',
			expect.any( AbortSignal )
		);
		expect( view.result.current.list.pageHref?.( 3 ) ).toContain(
			'page=3'
		);
		act( () => view.result.current.list.setSearch( 'south ' ) );
		await waitFor( () =>
			expect( view.result.current.location.search ).toBe( '?s=south' )
		);
		expect( load ).toHaveBeenLastCalledWith(
			1,
			'south',
			expect.any( AbortSignal )
		);
	} );
} );
