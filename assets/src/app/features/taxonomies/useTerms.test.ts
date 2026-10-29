import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getTermDetail, listTerms } from './api';
import { child, detail, root } from './fixtures';
import { useTermDetail, useTerms } from './useTerms';
import type { Term } from './types';

vi.mock( './api', () => ( { listTerms: vi.fn(), getTermDetail: vi.fn() } ) );

const page = ( items: Term[], totalPages = 1 ): Collection< Term > => ( {
	items,
	total: items.length,
	totalPages,
} );

beforeEach( () => vi.clearAllMocks() );

describe( 'useTerms', () => {
	it( 'cancels obsolete searches and ignores late responses', async () => {
		let resolveOld!: ( value: Collection< Term > ) => void;
		vi.mocked( listTerms ).mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveOld = resolve;
				} )
		);
		vi.mocked( listTerms ).mockResolvedValue( page( [ child ] ) );
		const view = renderHook( () => useTerms( 'categories' ) );
		const oldSignal = vi.mocked( listTerms ).mock.calls[ 0 ]?.[ 2 ];

		act( () => view.result.current.setSearch( 'alg' ) );
		await waitFor( () =>
			expect( view.result.current.data?.items[ 0 ]?.name ).toBe(
				'Algebra'
			)
		);
		expect( oldSignal?.aborted ).toBe( true );
		await act( async () => {
			resolveOld( page( [ root ] ) );
		} );
		expect( view.result.current.data?.items[ 0 ]?.name ).toBe( 'Algebra' );

		const current = vi.mocked( listTerms ).mock.calls.at( -1 )?.[ 2 ];
		view.unmount();
		expect( current?.aborted ).toBe( true );
	} );

	it( 'steps back from a page that no longer exists, using the taxonomy specific code', async () => {
		vi.mocked( listTerms ).mockImplementation( async ( _kind, query ) => {
			if ( query.page > 1 ) {
				throw new ApiError( {
					code: 'ohmylms_tag_invalid_page',
					status: 400,
					message: 'Too far',
				} );
			}
			return page( [ root ] );
		} );
		const view = renderHook( () => useTerms( 'tags' ) );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 2 ) );
		await waitFor( () =>
			expect( view.result.current.query.page ).toBe( 1 )
		);
		await waitFor( () =>
			expect( view.result.current.data?.items ).toHaveLength( 1 )
		);
		expect( view.result.current.error ).toBeNull();
	} );

	it( 'resets to page one on a new search and reports typed errors', async () => {
		vi.mocked( listTerms ).mockRejectedValueOnce(
			new ApiError( {
				code: 'rest_forbidden',
				status: 403,
				message: 'No',
			} )
		);
		vi.mocked( listTerms ).mockResolvedValue( page( [ root ], 4 ) );
		const view = renderHook( () => useTerms( 'categories' ) );
		await waitFor( () =>
			expect( view.result.current.error?.status ).toBe( 403 )
		);
		act( () => view.result.current.reload() );
		await waitFor( () => expect( view.result.current.error ).toBeNull() );
		act( () => view.result.current.setPage( 3 ) );
		act( () => view.result.current.setSearch( 'x' ) );
		expect( view.result.current.query ).toEqual( { page: 1, search: 'x' } );
	} );
} );

describe( 'useTermDetail', () => {
	it( 'loads one term and exposes a permission error', async () => {
		vi.mocked( getTermDetail ).mockResolvedValueOnce( detail );
		const view = renderHook( () => useTermDetail( 'categories', 4 ) );
		await waitFor( () =>
			expect( view.result.current.data ).toEqual( detail )
		);
		expect( getTermDetail ).toHaveBeenCalledWith(
			'categories',
			4,
			expect.any( AbortSignal )
		);

		vi.mocked( getTermDetail ).mockRejectedValue(
			new ApiError( {
				code: 'rest_forbidden_context',
				status: 403,
				message: 'Forbidden',
			} )
		);
		const denied = renderHook( () => useTermDetail( 'tags', 8 ) );
		await waitFor( () =>
			expect( denied.result.current.error?.status ).toBe( 403 )
		);
	} );
} );
