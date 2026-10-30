import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listCoupons, listOrders } from './api';
import { coupon, order } from './fixtures';
import { useCoupons, useOrders } from './hooks';
import { useOperation } from './useOperation';
import type { Coupon, OrderSummary } from './types';

vi.mock( './api', () => ( {
	listOrders: vi.fn(),
	listCoupons: vi.fn(),
	getOrder: vi.fn(),
	getStatusCounts: vi.fn(),
	listNotes: vi.fn(),
	listCourses: vi.fn(),
} ) );

const page = < T, >( items: T[], totalPages = 1 ): Collection< T > => ( {
	items,
	total: items.length,
	totalPages,
} );

beforeEach( () => vi.clearAllMocks() );

describe( 'useOrders', () => {
	it( 'cancels obsolete searches and ignores late responses', async () => {
		let resolveOld!: ( value: Collection< OrderSummary > ) => void;
		vi.mocked( listOrders ).mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveOld = resolve;
				} )
		);
		vi.mocked( listOrders ).mockResolvedValue(
			page( [ { ...order, student_name: 'New result' } ] )
		);
		const view = renderHook( useOrders );
		const oldSignal = vi.mocked( listOrders ).mock.calls[ 0 ]?.[ 1 ];

		act( () => view.result.current.setFilters( { search: 'new' } ) );
		await waitFor( () =>
			expect( view.result.current.data?.items[ 0 ]?.student_name ).toBe(
				'New result'
			)
		);
		expect( oldSignal?.aborted ).toBe( true );

		await act( async () => {
			resolveOld( page( [ order ] ) );
		} );
		expect( view.result.current.data?.items[ 0 ]?.student_name ).toBe(
			'New result'
		);

		const current = vi.mocked( listOrders ).mock.calls.at( -1 )?.[ 1 ];
		view.unmount();
		expect( current?.aborted ).toBe( true );
	} );

	it( 'returns to page one when a filter changes', async () => {
		vi.mocked( listOrders ).mockResolvedValue( page( [ order ], 5 ) );
		const view = renderHook( useOrders );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 3 ) );
		expect( view.result.current.query.page ).toBe( 3 );
		act( () => view.result.current.setFilters( { status: 'pending' } ) );
		expect( view.result.current.query ).toMatchObject( {
			page: 1,
			status: 'pending',
		} );
	} );

	it( 'steps back when the page no longer exists', async () => {
		vi.mocked( listOrders )
			.mockResolvedValueOnce( page( [ order ], 3 ) )
			.mockRejectedValueOnce(
				new ApiError( {
					status: 400,
					code: 'ohmylms_order_invalid_page',
					message: 'Too far.',
				} )
			)
			.mockResolvedValue( page( [ order ], 2 ) );
		const view = renderHook( useOrders );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 3 ) );
		await waitFor( () =>
			expect( view.result.current.query.page ).toBe( 2 )
		);
		await waitFor( () => expect( view.result.current.error ).toBeNull() );
	} );

	it( 'keeps the previous data visible while the next page loads', async () => {
		let release!: () => void;
		vi.mocked( listOrders ).mockResolvedValueOnce( page( [ order ], 2 ) );
		vi.mocked( listOrders ).mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					release = () => resolve( page( [ order ], 2 ) );
				} )
		);
		const view = renderHook( useOrders );
		await waitFor( () =>
			expect( view.result.current.data ).not.toBeNull()
		);
		act( () => view.result.current.setPage( 2 ) );
		expect( view.result.current.loading ).toBe( true );
		expect( view.result.current.data?.items ).toHaveLength( 1 );
		await act( async () => release() );
	} );
} );

describe( 'useCoupons', () => {
	it( 'resets paging on filter change and reloads on demand', async () => {
		vi.mocked( listCoupons ).mockResolvedValue(
			page< Coupon >( [ coupon ], 4 )
		);
		const view = renderHook( useCoupons );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 2 ) );
		act( () => view.result.current.setFilters( { status: 'draft' } ) );
		expect( view.result.current.query ).toMatchObject( {
			page: 1,
			status: 'draft',
		} );
		const calls = vi.mocked( listCoupons ).mock.calls.length;
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect(
				vi.mocked( listCoupons ).mock.calls.length
			).toBeGreaterThan( calls )
		);
	} );
} );

describe( 'useOperation', () => {
	it( 'runs one write at a time', async () => {
		const view = renderHook( useOperation );
		let release!: () => void;
		const work = vi.fn(
			() =>
				new Promise< void >( ( resolve ) => {
					release = resolve;
				} )
		);
		let first!: Promise< boolean >;
		act( () => {
			first = view.result.current.run( work );
		} );
		let second = true;
		await act( async () => {
			second = await view.result.current.run( work );
		} );
		expect( second ).toBe( false );
		expect( work ).toHaveBeenCalledTimes( 1 );
		expect( view.result.current.busy ).toBe( true );
		await act( async () => {
			release();
			await first;
		} );
		expect( view.result.current.busy ).toBe( false );
	} );

	it( 'reports failures and cancels on unmount', async () => {
		const view = renderHook( useOperation );
		await act( async () => {
			const done = await view.result.current.run( () =>
				Promise.reject(
					new ApiError( {
						status: 409,
						code: 'x',
						message: 'Conflict happened.',
					} )
				)
			);
			expect( done ).toBe( false );
		} );
		expect( view.result.current.error ).toBe( 'Conflict happened.' );

		let signal!: AbortSignal;
		act( () => {
			void view.result.current.run(
				( received ) =>
					new Promise< void >( () => {
						signal = received;
					} )
			);
		} );
		view.unmount();
		expect( signal.aborted ).toBe( true );
	} );
} );
