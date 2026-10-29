import { del, get, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	deleteWebhook,
	getOptions,
	listWebhooks,
	rotateSecret,
	saveWebhook,
	sendTest,
} from './api';
import { options, webhook } from './fixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

describe( 'typed webhooks API', () => {
	it( 'loads server options and requests bounded paginated collections', async () => {
		vi.mocked( get ).mockResolvedValue( options );
		expect( await getOptions() ).toEqual( options );
		await listWebhooks( 2, 'enrollment' );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/webhooks',
			expect.any( Function ),
			expect.objectContaining( {
				query: expect.objectContaining( {
					page: 2,
					per_page: 20,
					search: 'enrollment',
				} ),
			} )
		);
	} );
	it( 'keeps creation secrets separate and only sends writable update fields', async () => {
		vi.mocked( post ).mockResolvedValue( {
			...webhook,
			secret: 'whsec_once',
		} );
		const created = await saveWebhook( webhook, null );
		expect( created.secret ).toBe( 'whsec_once' );
		expect( created.webhook ).not.toHaveProperty( 'secret' );
		vi.mocked( put ).mockResolvedValue( webhook );
		await saveWebhook( webhook, 7 );
		expect( put ).toHaveBeenCalledWith(
			'/ohmylms/v1/webhooks/7',
			expect.not.objectContaining( { id: 7 } ),
			expect.any( Object )
		);
		expect(
			vi.mocked( put ).mock.calls.at( -1 )?.[ 1 ]
		).not.toHaveProperty( 'secret_hint' );
	} );
	it( 'uses explicit mutation endpoints and validates their results', async () => {
		vi.mocked( post ).mockResolvedValueOnce( {
			...webhook,
			secret: 'whsec_new',
		} );
		expect( ( await rotateSecret( 7 ) ).secret ).toBe( 'whsec_new' );
		vi.mocked( post ).mockResolvedValueOnce( { success: false } );
		expect( await sendTest( 7 ) ).toBe( false );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/webhooks/test',
			{ id: 7 },
			expect.any( Object )
		);
		vi.mocked( del ).mockResolvedValue( { deleted: true } );
		await deleteWebhook( 7 );
		vi.mocked( del ).mockResolvedValueOnce( {} );
		await expect( deleteWebhook( 7 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		await expect( deleteWebhook( -1 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
} );
