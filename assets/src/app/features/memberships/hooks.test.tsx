import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getMembership, getOptions, listMemberships } from './api';
import { options, plan, summary } from './fixtures';
import { useMembershipEditor } from './useMembershipEditor';
import { useMemberships } from './useMemberships';
import type { Membership } from './types';

vi.mock( './api', () => ( {
	listMemberships: vi.fn(),
	getMembership: vi.fn(),
	getOptions: vi.fn(),
} ) );

beforeEach( () => vi.resetAllMocks() );

describe( 'useMemberships', () => {
	it( 'cancels obsolete searches and ignores late responses', async () => {
		let resolveOld!: ( value: Collection< Membership > ) => void;
		vi.mocked( listMemberships ).mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveOld = resolve;
				} )
		);
		vi.mocked( listMemberships ).mockResolvedValue( {
			items: [ { ...summary, name: 'New result' } ],
			total: 1,
			totalPages: 1,
		} );
		const view = renderHook( useMemberships );
		const oldSignal = vi.mocked( listMemberships ).mock.calls[ 0 ]?.[ 3 ];
		act( () => view.result.current.search( 'new' ) );
		await waitFor( () =>
			expect( view.result.current.data.items[ 0 ]?.name ).toBe(
				'New result'
			)
		);
		expect( oldSignal?.aborted ).toBe( true );
		await act( async () => {
			resolveOld( { items: [ summary ], total: 1, totalPages: 1 } );
		} );
		expect( view.result.current.data.items[ 0 ]?.name ).toBe(
			'New result'
		);
		const lastSignal = vi
			.mocked( listMemberships )
			.mock.calls.at( -1 )?.[ 3 ];
		view.unmount();
		expect( lastSignal?.aborted ).toBe( true );
	} );
	it( 'resets to page one on a new status and steps back from a missing last page', async () => {
		vi.mocked( listMemberships ).mockResolvedValue( {
			items: [ summary ],
			total: 21,
			totalPages: 2,
		} );
		const view = renderHook( useMemberships );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 2 ) );
		await waitFor( () =>
			expect( listMemberships ).toHaveBeenLastCalledWith(
				2,
				'',
				'all',
				expect.any( AbortSignal )
			)
		);
		act( () => view.result.current.setStatus( 'draft' ) );
		await waitFor( () =>
			expect( listMemberships ).toHaveBeenLastCalledWith(
				1,
				'',
				'draft',
				expect.any( AbortSignal )
			)
		);
		vi.mocked( listMemberships ).mockRejectedValueOnce(
			new ApiError( {
				status: 400,
				code: 'ohmylms_membership_invalid_page',
				message: 'Too far',
			} )
		);
		act( () => view.result.current.setPage( 3 ) );
		await waitFor( () =>
			expect( listMemberships ).toHaveBeenLastCalledWith(
				2,
				'',
				'draft',
				expect.any( AbortSignal )
			)
		);
		expect( view.result.current.error ).toBeNull();
	} );
	it( 'goes back a page after deleting the only row of the last page', async () => {
		vi.mocked( listMemberships ).mockResolvedValue( {
			items: [ summary ],
			total: 21,
			totalPages: 2,
		} );
		const view = renderHook( useMemberships );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 2 ) );
		await waitFor( () => expect( view.result.current.page ).toBe( 2 ) );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.afterDelete() );
		await waitFor( () => expect( view.result.current.page ).toBe( 1 ) );
	} );
	it( 'exposes the failure message', async () => {
		vi.mocked( listMemberships ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_membership_cannot_view',
				message: 'No access',
			} )
		);
		const view = renderHook( useMemberships );
		await waitFor( () =>
			expect( view.result.current.error ).toBe( 'No access' )
		);
		expect( view.result.current.loading ).toBe( false );
	} );
} );

describe( 'useMembershipEditor', () => {
	it( 'requests nothing for an invalid id', () => {
		const view = renderHook( () => useMembershipEditor( null ) );
		expect( view.result.current.loading ).toBe( false );
		expect( getMembership ).not.toHaveBeenCalled();
	} );
	it( 'loads the plan and options together and keeps the error object', async () => {
		vi.mocked( getMembership ).mockResolvedValueOnce( plan );
		vi.mocked( getOptions ).mockResolvedValueOnce( options );
		const view = renderHook( () => useMembershipEditor( 7 ) );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		expect( view.result.current.plan ).toEqual( plan );
		expect( view.result.current.options ).toEqual( options );
		vi.mocked( getMembership ).mockRejectedValueOnce(
			new ApiError( {
				status: 404,
				code: 'ohmylms_membership_not_found',
				message: 'Gone',
			} )
		);
		vi.mocked( getOptions ).mockResolvedValueOnce( options );
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.error?.status ).toBe( 404 )
		);
	} );
	it( 'aborts the requests on unmount', () => {
		vi.mocked( getMembership ).mockReturnValue(
			new Promise( () => undefined )
		);
		vi.mocked( getOptions ).mockReturnValue(
			new Promise( () => undefined )
		);
		const view = renderHook( () => useMembershipEditor( 7 ) );
		const signal = vi.mocked( getMembership ).mock.calls[ 0 ]?.[ 1 ];
		view.unmount();
		expect( signal?.aborted ).toBe( true );
	} );
} );
