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
import { getCertificate, getOptions, listCertificates } from './api';
import { certificate, options } from './fixtures';
import { useCertificateEditor } from './useCertificateEditor';
import { useCertificates } from './useCertificates';
import { useUnsavedChangesGuard } from './useUnsavedChangesGuard';
import type { Certificate } from './types';

vi.mock( './api', () => ( {
	listCertificates: vi.fn(),
	getCertificate: vi.fn(),
	getOptions: vi.fn(),
} ) );

beforeEach( () => vi.resetAllMocks() );

describe( 'useCertificates', () => {
	it( 'cancels obsolete searches and ignores late responses', async () => {
		let resolveOld!: ( value: Collection< Certificate > ) => void;
		vi.mocked( listCertificates ).mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveOld = resolve;
				} )
		);
		vi.mocked( listCertificates ).mockResolvedValue( {
			items: [ { ...certificate, name: 'New result' } ],
			total: 1,
			totalPages: 1,
		} );
		const view = renderHook( useCertificates );
		const oldSignal = vi.mocked( listCertificates ).mock.calls[ 0 ]?.[ 3 ];
		act( () => view.result.current.search( 'new' ) );
		await waitFor( () =>
			expect( view.result.current.data.items[ 0 ]?.name ).toBe(
				'New result'
			)
		);
		expect( oldSignal?.aborted ).toBe( true );
		await act( async () => {
			resolveOld( { items: [ certificate ], total: 1, totalPages: 1 } );
		} );
		expect( view.result.current.data.items[ 0 ]?.name ).toBe(
			'New result'
		);
		const lastSignal = vi
			.mocked( listCertificates )
			.mock.calls.at( -1 )?.[ 3 ];
		view.unmount();
		expect( lastSignal?.aborted ).toBe( true );
	} );
	it( 'resets to page one on a new search or status and steps back from an empty last page', async () => {
		vi.mocked( listCertificates ).mockResolvedValue( {
			items: [ certificate ],
			total: 21,
			totalPages: 2,
		} );
		const view = renderHook( useCertificates );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 2 ) );
		await waitFor( () =>
			expect( listCertificates ).toHaveBeenLastCalledWith(
				2,
				'',
				'all',
				expect.any( AbortSignal )
			)
		);
		act( () => view.result.current.setStatus( 'draft' ) );
		await waitFor( () =>
			expect( listCertificates ).toHaveBeenLastCalledWith(
				1,
				'',
				'draft',
				expect.any( AbortSignal )
			)
		);
		vi.mocked( listCertificates ).mockRejectedValueOnce(
			new ApiError( {
				status: 400,
				code: 'ohmylms_certificate_invalid_page',
				message: 'Too far',
			} )
		);
		act( () => view.result.current.setPage( 3 ) );
		await waitFor( () =>
			expect( listCertificates ).toHaveBeenLastCalledWith(
				2,
				'',
				'draft',
				expect.any( AbortSignal )
			)
		);
		expect( view.result.current.error ).toBeNull();
	} );
	it( 'exposes the failure message', async () => {
		vi.mocked( listCertificates ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'rest_forbidden',
				message: 'No access',
			} )
		);
		const view = renderHook( useCertificates );
		await waitFor( () =>
			expect( view.result.current.error ).toBe( 'No access' )
		);
		expect( view.result.current.loading ).toBe( false );
	} );
} );

describe( 'useCertificateEditor', () => {
	it( 'requests nothing for an invalid id', () => {
		const view = renderHook( () => useCertificateEditor( null ) );
		expect( view.result.current.loading ).toBe( false );
		expect( getCertificate ).not.toHaveBeenCalled();
	} );
	it( 'loads the template and options together and keeps the error object', async () => {
		vi.mocked( getCertificate ).mockResolvedValueOnce( certificate );
		vi.mocked( getOptions ).mockResolvedValueOnce( options );
		const view = renderHook( () => useCertificateEditor( 7 ) );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		expect( view.result.current.certificate ).toEqual( certificate );
		expect( view.result.current.options ).toEqual( options );
		vi.mocked( getCertificate ).mockRejectedValueOnce(
			new ApiError( {
				status: 404,
				code: 'ohmylms_certificate_not_found',
				message: 'Gone',
			} )
		);
		vi.mocked( getOptions ).mockResolvedValueOnce( options );
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.error?.status ).toBe( 404 )
		);
	} );
	it( 'aborts the requests on unmount', async () => {
		vi.mocked( getCertificate ).mockReturnValue(
			new Promise( () => undefined )
		);
		vi.mocked( getOptions ).mockReturnValue(
			new Promise( () => undefined )
		);
		const view = renderHook( () => useCertificateEditor( 7 ) );
		const signal = vi.mocked( getCertificate ).mock.calls[ 0 ]?.[ 1 ];
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
				<a href="/new-tab" target="_blank">
					New tab
				</a>
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
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent(
			'/hash-route'
		);
		await user.click( screen.getByRole( 'button', { name: 'Leave' } ) );
		expect( await screen.findByText( 'Hash arrived' ) ).toBeInTheDocument();
	} );
	it( 'leaves external, new-tab and modified clicks alone', async () => {
		const user = userEvent.setup();
		render( wrap( true ) );
		const external = screen.getByRole( 'link', { name: 'External' } );
		external.addEventListener( 'click', ( event ) =>
			event.preventDefault()
		);
		await user.click( external );
		const tab = screen.getByRole( 'link', { name: 'New tab' } );
		tab.addEventListener( 'click', ( event ) => event.preventDefault() );
		await user.click( tab );
		await user.keyboard( '{Control>}' );
		await user.click( screen.getByRole( 'link', { name: 'Other page' } ) );
		await user.keyboard( '{/Control}' );
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
