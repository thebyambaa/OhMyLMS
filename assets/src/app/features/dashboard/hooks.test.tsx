import { act, renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getDashboard, listTransactions } from './api';
import { dashboard, transaction } from './fixtures';
import { useDashboard, useRange, useTransactions } from './hooks';
import { DEFAULT_RANGE } from './model';
import type { Dashboard, Transaction } from './types';

vi.mock( './api', () => ( {
	getDashboard: vi.fn(),
	getEarnings: vi.fn(),
	listTransactions: vi.fn(),
	listCourseReport: vi.fn(),
	getCourseReport: vi.fn(),
	listCourseStudents: vi.fn(),
} ) );

const page = (
	items: Transaction[],
	totalPages = 1
): Collection< Transaction > => ( {
	items,
	total: items.length,
	totalPages,
} );

beforeEach( () => vi.clearAllMocks() );

describe( 'useDashboard', () => {
	it( 'cancels the old request and ignores a late response when the range changes', async () => {
		let resolveOld!: ( value: Dashboard ) => void;
		vi.mocked( getDashboard ).mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveOld = resolve;
				} )
		);
		vi.mocked( getDashboard ).mockResolvedValue( {
			...dashboard,
			totals: { ...dashboard.totals, courses: 99 },
		} );
		const view = renderHook( ( { range } ) => useDashboard( range ), {
			initialProps: { range: DEFAULT_RANGE },
		} );
		const oldSignal = vi.mocked( getDashboard ).mock.calls[ 0 ]?.[ 1 ];
		view.rerender( {
			range: { preset: 'last_7_days', start: '', end: '' },
		} );
		await waitFor( () =>
			expect( view.result.current.data?.totals.courses ).toBe( 99 )
		);
		expect( oldSignal?.aborted ).toBe( true );

		await act( async () => {
			resolveOld( dashboard );
		} );
		expect( view.result.current.data?.totals.courses ).toBe( 99 );

		const latest = vi.mocked( getDashboard ).mock.calls.at( -1 )?.[ 1 ];
		view.unmount();
		expect( latest?.aborted ).toBe( true );
	} );

	it( 'keeps the last data next to an error and recovers on reload', async () => {
		vi.mocked( getDashboard ).mockResolvedValueOnce( dashboard );
		const view = renderHook( () => useDashboard( DEFAULT_RANGE ) );
		await waitFor( () =>
			expect( view.result.current.data ).not.toBeNull()
		);

		vi.mocked( getDashboard ).mockRejectedValueOnce(
			new ApiError( { status: 500, code: 'boom', message: 'Broken' } )
		);
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.error?.status ).toBe( 500 )
		);
		expect( view.result.current.data ).toEqual( dashboard );

		vi.mocked( getDashboard ).mockResolvedValue( dashboard );
		act( () => view.result.current.reload() );
		await waitFor( () => expect( view.result.current.error ).toBeNull() );
	} );
} );

describe( 'useTransactions', () => {
	it( 'resets to page one on search, filter and sort and steps back from a missing page', async () => {
		vi.mocked( listTransactions ).mockImplementation(
			async ( _range, query ) => {
				if ( query.page > 2 ) {
					throw new ApiError( {
						status: 400,
						code: 'ohmylms_analytics_invalid_page',
						message: 'Too far',
					} );
				}
				return page( [ transaction ], 5 );
			}
		);
		const view = renderHook( () => useTransactions( DEFAULT_RANGE ) );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);

		act( () => view.result.current.setPage( 2 ) );
		expect( view.result.current.query.page ).toBe( 2 );
		act( () => view.result.current.setSearch( '77' ) );
		expect( view.result.current.query ).toMatchObject( {
			page: 1,
			search: '77',
		} );
		act( () => view.result.current.setPage( 2 ) );
		act( () => view.result.current.setPaymentMethod( 'paypal' ) );
		expect( view.result.current.query.page ).toBe( 1 );
		act( () => view.result.current.setSort( 'total', 'asc' ) );
		expect( view.result.current.query ).toMatchObject( {
			orderby: 'total',
			order: 'asc',
			page: 1,
		} );

		act( () => view.result.current.setPage( 3 ) );
		await waitFor( () =>
			expect( view.result.current.query.page ).toBe( 2 )
		);
		await waitFor( () => expect( view.result.current.error ).toBeNull() );
	} );
} );

describe( 'useRange', () => {
	const wrapper = ( initial: string ) =>
		function Wrapper( { children }: { children: ReactNode } ) {
			return (
				<MemoryRouter initialEntries={ [ initial ] }>
					{ children }
				</MemoryRouter>
			);
		};

	it( 'reads the range from the URL and updates it', () => {
		const view = renderHook( () => useRange(), {
			wrapper: wrapper( '/dashboard?range=last_90_days&keep=1' ),
		} );
		expect( view.result.current.range.preset ).toBe( 'last_90_days' );
		act( () =>
			view.result.current.setRange( {
				preset: 'custom',
				start: '2001-03-01',
				end: '2001-03-31',
			} )
		);
		expect( view.result.current.range ).toEqual( {
			preset: 'custom',
			start: '2001-03-01',
			end: '2001-03-31',
		} );
		act( () => view.result.current.setRange( DEFAULT_RANGE ) );
		expect( view.result.current.range ).toEqual( DEFAULT_RANGE );
	} );

	it( 'falls back to the default for an invalid URL', () => {
		const view = renderHook( () => useRange(), {
			wrapper: wrapper(
				'/dashboard?range=custom&start=nope&end=2001-03-01'
			),
		} );
		expect( view.result.current.range ).toEqual( DEFAULT_RANGE );
	} );
} );
