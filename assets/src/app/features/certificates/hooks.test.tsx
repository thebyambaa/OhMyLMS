import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getCertificate, getOptions, listCertificates } from './api';
import { certificate, options } from './fixtures';
import { useCertificateEditor } from './useCertificateEditor';
import { useCertificates } from './useCertificates';
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
