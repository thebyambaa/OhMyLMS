import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listStudents } from './api';
import { student } from './fixtures';
import { useOperation } from './useOperation';
import { useStudents } from './useStudents';
import type { Student } from './types';

vi.mock( './api', () => ( {
	listStudents: vi.fn(),
	getStudent: vi.fn(),
	listCourses: vi.fn(),
	listEnrollments: vi.fn(),
} ) );

const page = ( items: Student[], totalPages = 1 ): Collection< Student > => ( {
	items,
	total: items.length,
	totalPages,
} );

beforeEach( () => vi.clearAllMocks() );

describe( 'useStudents', () => {
	it( 'cancels obsolete searches and ignores late responses', async () => {
		let resolveOld!: ( value: Collection< Student > ) => void;
		vi.mocked( listStudents ).mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveOld = resolve;
				} )
		);
		vi.mocked( listStudents ).mockResolvedValue(
			page( [ { ...student, name: 'New result' } ] )
		);
		const view = renderHook( useStudents );
		const oldSignal = vi.mocked( listStudents ).mock.calls[ 0 ]?.[ 1 ];

		act( () => view.result.current.setSearch( 'new' ) );
		await waitFor( () =>
			expect( view.result.current.data?.items[ 0 ]?.name ).toBe(
				'New result'
			)
		);
		expect( oldSignal?.aborted ).toBe( true );

		await act( async () => {
			resolveOld( page( [ student ] ) );
		} );
		expect( view.result.current.data?.items[ 0 ]?.name ).toBe(
			'New result'
		);

		const current = vi.mocked( listStudents ).mock.calls.at( -1 )?.[ 1 ];
		view.unmount();
		expect( current?.aborted ).toBe( true );
	} );

	it( 'resets to page one when the search, course or sort changes', async () => {
		vi.mocked( listStudents ).mockResolvedValue( page( [ student ], 5 ) );
		const view = renderHook( useStudents );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 3 ) );
		expect( view.result.current.query.page ).toBe( 3 );
		act( () => view.result.current.setCourse( 40 ) );
		expect( view.result.current.query ).toMatchObject( {
			page: 1,
			course: 40,
		} );
		act( () => view.result.current.setPage( 2 ) );
		act( () => view.result.current.setSort( 'registered:desc' ) );
		expect( view.result.current.query ).toMatchObject( {
			page: 1,
			orderby: 'registered',
			order: 'desc',
		} );
		act( () => view.result.current.setSort( 'bogus' ) );
		expect( view.result.current.query.orderby ).toBe( 'registered' );
	} );

	it( 'steps back when the requested page no longer exists', async () => {
		vi.mocked( listStudents ).mockImplementation( async ( query ) => {
			if ( query.page > 1 ) {
				throw new ApiError( {
					code: 'ohmylms_student_invalid_page',
					status: 400,
					message: 'Too far',
				} );
			}
			return page( [ student ] );
		} );
		const view = renderHook( useStudents );
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

	it( 'exposes typed errors and can retry', async () => {
		vi.mocked( listStudents ).mockRejectedValueOnce(
			new ApiError( {
				code: 'rest_forbidden',
				status: 403,
				message: 'No',
			} )
		);
		vi.mocked( listStudents ).mockResolvedValue( page( [ student ] ) );
		const view = renderHook( useStudents );
		await waitFor( () =>
			expect( view.result.current.error?.status ).toBe( 403 )
		);
		act( () => view.result.current.reload() );
		await waitFor( () => expect( view.result.current.error ).toBeNull() );
		expect( view.result.current.data?.items ).toHaveLength( 1 );
	} );
} );

describe( 'useOperation', () => {
	it( 'rejects a second write while one runs and reports failures', async () => {
		let finish!: () => void;
		const view = renderHook( useOperation );
		let first!: Promise< boolean >;
		act( () => {
			first = view.result.current.run(
				() =>
					new Promise< void >( ( resolve ) => {
						finish = resolve;
					} )
			);
		} );
		expect( view.result.current.busy ).toBe( true );
		const second = vi.fn();
		let secondResult = true;
		await act( async () => {
			secondResult = await view.result.current.run( second );
		} );
		expect( secondResult ).toBe( false );
		expect( second ).not.toHaveBeenCalled();
		await act( async () => {
			finish();
			await first;
		} );
		expect( view.result.current.busy ).toBe( false );

		await act( async () => {
			await view.result.current.run( () =>
				Promise.reject(
					new ApiError( {
						code: 'ohmylms_student_already_enrolled',
						status: 409,
						message: 'Already enrolled',
					} )
				)
			);
		} );
		expect( view.result.current.error ).toBe( 'Already enrolled' );
	} );

	it( 'cancels the running write on unmount', () => {
		let signal: AbortSignal | undefined;
		const view = renderHook( useOperation );
		act( () => {
			void view.result.current.run( ( received ) => {
				signal = received;
				return new Promise< void >( () => undefined );
			} );
		} );
		view.unmount();
		expect( signal?.aborted ).toBe( true );
	} );
} );
