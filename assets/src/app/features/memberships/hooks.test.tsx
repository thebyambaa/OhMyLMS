import {
	act,
	render,
	renderHook,
	screen,
	waitFor,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Link, MemoryRouter, Route, Routes } from 'react-router-dom';
import type { ReactNode } from 'react';
import { ApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getMembership, getOptions, listMemberships } from './api';
import { options, plan, summary } from './fixtures';
import { useMembershipEditor } from './useMembershipEditor';
import { useMemberships } from './useMemberships';
import { usePagedList } from './usePagedList';
import { useUnsavedChangesGuard } from './useUnsavedChangesGuard';
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

describe( 'usePagedList', () => {
	it( 'reloads on demand, reports failures and aborts on unmount', async () => {
		const load = vi
			.fn< ( signal: AbortSignal ) => Promise< Collection< number > > >()
			.mockResolvedValueOnce( { items: [ 1 ], total: 1, totalPages: 1 } )
			.mockRejectedValueOnce(
				new ApiError( { status: 500, code: 'x', message: 'Broken' } )
			)
			.mockReturnValue( new Promise( () => undefined ) );
		const view = renderHook( () => usePagedList( load, [ 'a' ] ) );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		expect( view.result.current.data.items ).toEqual( [ 1 ] );
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.error ).toBe( 'Broken' )
		);
		act( () => view.result.current.reload() );
		const signal = load.mock.calls.at( -1 )?.[ 0 ];
		view.unmount();
		expect( signal?.aborted ).toBe( true );
	} );
} );

describe( 'useUnsavedChangesGuard', () => {
	function Harness( { dirty }: { dirty: boolean } ) {
		const guard = useUnsavedChangesGuard( dirty );
		return (
			<div>
				<Link to="/other">Other page</Link>
				<a href="#/hash-route">Hash link</a>
				<a href="https://example.com/">External</a>
				<span data-testid="pending">
					{ guard.pendingPath ?? 'none' }
				</span>
				<button onClick={ guard.stay }>Stay</button>
				<button onClick={ guard.leave }>Leave</button>
			</div>
		);
	}
	const wrap = ( dirty: boolean ): ReactNode => (
		<MemoryRouter initialEntries={ [ '/here' ] }>
			<Routes>
				<Route path="/here" element={ <Harness dirty={ dirty } /> } />
				<Route path="/other" element={ <p>Arrived</p> } />
				<Route path="/hash-route" element={ <p>Hash arrived</p> } />
			</Routes>
		</MemoryRouter>
	);

	it( 'does nothing while the form is clean', async () => {
		const user = userEvent.setup();
		render( wrap( false ) );
		await user.click( screen.getByRole( 'link', { name: 'Other page' } ) );
		expect( await screen.findByText( 'Arrived' ) ).toBeInTheDocument();
	} );
	it( 'holds an in-app link while dirty, then continues or stays', async () => {
		const user = userEvent.setup();
		render( wrap( true ) );
		await user.click( screen.getByRole( 'link', { name: 'Other page' } ) );
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent( '/other' );
		await user.click( screen.getByRole( 'button', { name: 'Stay' } ) );
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent( 'none' );
		await user.click( screen.getByRole( 'link', { name: 'Hash link' } ) );
		await user.click( screen.getByRole( 'button', { name: 'Leave' } ) );
		expect( await screen.findByText( 'Hash arrived' ) ).toBeInTheDocument();
	} );
	it( 'leaves external links alone', async () => {
		const user = userEvent.setup();
		render( wrap( true ) );
		const external = screen.getByRole( 'link', { name: 'External' } );
		external.addEventListener( 'click', ( event ) =>
			event.preventDefault()
		);
		await user.click( external );
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent( 'none' );
	} );
	it( 'asks the browser to confirm closing the page only while dirty', () => {
		const view = render( wrap( true ) );
		const dirtyEvent = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( dirtyEvent );
		expect( dirtyEvent.defaultPrevented ).toBe( true );
		view.rerender( wrap( false ) );
		const cleanEvent = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( cleanEvent );
		expect( cleanEvent.defaultPrevented ).toBe( false );
	} );
} );
