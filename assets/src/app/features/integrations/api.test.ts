import { get, patch } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	getIntegrationSettings,
	listIntegrations,
	saveIntegrationSettings,
	setIntegrationEnabled,
} from './api';
import { zoom, zoomSettings } from './fixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	patch: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'typed integrations API', () => {
	it( 'lists the whole catalogue through the validating collection reader', async () => {
		vi.mocked( getCollection ).mockResolvedValue( {
			items: [ zoom ],
			total: 1,
			totalPages: 1,
		} );
		const signal = new AbortController().signal;
		expect( await listIntegrations( signal ) ).toEqual( [ zoom ] );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/integrations',
			expect.any( Function ),
			{ signal }
		);
	} );
	it( 'patches only the enabled flag and verifies the echoed state', async () => {
		vi.mocked( patch ).mockResolvedValue( { ...zoom, enabled: true } );
		const result = await setIntegrationEnabled( 'zoom', true );
		expect( result.enabled ).toBe( true );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/integrations/zoom',
			{ enabled: true },
			expect.any( Object )
		);
		vi.mocked( patch ).mockResolvedValueOnce( { ...zoom, enabled: false } );
		await expect(
			setIntegrationEnabled( 'zoom', true )
		).rejects.toMatchObject( { code: 'invalid_response' } );
		vi.mocked( patch ).mockResolvedValueOnce( {
			...zoom,
			id: 'other',
			enabled: true,
		} );
		await expect(
			setIntegrationEnabled( 'zoom', true )
		).rejects.toMatchObject( { code: 'invalid_response' } );
	} );
	it( 'rejects ids that could alter the URL before any request', async () => {
		await expect(
			setIntegrationEnabled( '../x', true )
		).rejects.toMatchObject( { code: 'invalid_request' } );
		await expect( getIntegrationSettings( 'a/b' ) ).rejects.toMatchObject( {
			code: 'invalid_request',
		} );
		expect( patch ).not.toHaveBeenCalled();
		expect( get ).not.toHaveBeenCalled();
	} );
	it( 'reads and saves settings with validated responses', async () => {
		vi.mocked( get ).mockResolvedValue( zoomSettings );
		expect( await getIntegrationSettings( 'zoom' ) ).toEqual(
			zoomSettings
		);
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/integrations/zoom/settings',
			expect.any( Object )
		);
		vi.mocked( patch ).mockResolvedValue( zoomSettings );
		await saveIntegrationSettings( 'zoom', {
			client_id: 'c',
			webhook_secret_token: null,
		} );
		expect( patch ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/integrations/zoom/settings',
			{ client_id: 'c', webhook_secret_token: null },
			expect.any( Object )
		);
		vi.mocked( get ).mockResolvedValueOnce( {
			...zoomSettings,
			integration: 'google_signin',
		} );
		await expect( getIntegrationSettings( 'zoom' ) ).rejects.toMatchObject(
			{ code: 'invalid_response' }
		);
		vi.mocked( get ).mockResolvedValueOnce( { fields: 'nope' } );
		await expect( getIntegrationSettings( 'zoom' ) ).rejects.toMatchObject(
			{ code: 'invalid_response' }
		);
	} );
} );
