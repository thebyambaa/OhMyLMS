import { del, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	createBadge,
	createLevel,
	deleteBadge,
	deleteLevel,
	getOptions,
	getSection,
	listBadges,
	listLevels,
	listMedia,
	saveSection,
	updateBadge,
	updateLevel,
} from './api';
import { bronze, leaderboard, options, points, starter, at } from './fixtures';
import { badgeToDraft, levelToDraft, toDraft } from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	patch: vi.fn(),
	post: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

beforeEach( () => vi.clearAllMocks() );

const wire = ( value: unknown ) => JSON.parse( JSON.stringify( value ) );

describe( 'typed gamification API', () => {
	it( 'reads a section from its own route and validates it', async () => {
		vi.mocked( get ).mockResolvedValue( wire( leaderboard ) );
		const signal = new AbortController().signal;
		expect( await getSection( 'leaderboard', signal ) ).toEqual(
			leaderboard
		);
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/gamification/settings/leaderboard',
			{ signal }
		);
		vi.mocked( get ).mockResolvedValue( { enabled: 'yes' } );
		await expect( getSection( 'levels' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'saves a section with PATCH and only the editable fields', async () => {
		vi.mocked( patch ).mockResolvedValue( wire( points ) );
		const draft = toDraft< 'points' >( points );
		at( draft.rules, 0 ).points = 9;
		await saveSection( 'points', draft );
		const [ path, body ] = at( vi.mocked( patch ).mock.calls );
		expect( path ).toBe( '/ohmylms/v1/gamification/settings/points' );
		expect(
			( body as { rules: Array< Record< string, unknown > > } ).rules[ 0 ]
		).toEqual( {
			event: 'lesson_complete',
			enabled: true,
			points: 9,
			email: { enabled: false, subject: '', body: '' },
		} );
	} );

	it( 'loads options', async () => {
		vi.mocked( get ).mockResolvedValue( wire( options ) );
		expect( await getOptions() ).toEqual( options );
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/gamification/options',
			{
				signal: undefined,
			}
		);
	} );

	it( 'lists levels and custom badges with a bounded page', async () => {
		vi.mocked( getCollection ).mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 1,
		} );
		await listLevels();
		await listBadges();
		expect( getCollection ).toHaveBeenNthCalledWith(
			1,
			'/ohmylms/v1/gamification/levels',
			expect.any( Function ),
			{ signal: undefined, query: { per_page: 100 } }
		);
		expect( getCollection ).toHaveBeenNthCalledWith(
			2,
			'/ohmylms/v1/gamification/badges',
			expect.any( Function ),
			{ signal: undefined, query: { per_page: 100, source: 'custom' } }
		);
	} );

	it( 'creates, updates and deletes levels', async () => {
		vi.mocked( post ).mockResolvedValue( wire( bronze ) );
		vi.mocked( patch ).mockResolvedValue( wire( bronze ) );
		vi.mocked( del ).mockResolvedValue( { deleted: true, previous: {} } );
		await createLevel( levelToDraft( bronze ) );
		expect( at( vi.mocked( post ).mock.calls )[ 0 ] ).toBe(
			'/ohmylms/v1/gamification/levels'
		);
		await updateLevel( 'bronze', levelToDraft( bronze ) );
		expect( at( vi.mocked( patch ).mock.calls )[ 0 ] ).toBe(
			'/ohmylms/v1/gamification/levels/bronze'
		);
		await deleteLevel( 'bronze' );
		expect( at( vi.mocked( del ).mock.calls )[ 0 ] ).toBe(
			'/ohmylms/v1/gamification/levels/bronze'
		);
		vi.mocked( del ).mockResolvedValue( {} );
		await expect( deleteLevel( 'bronze' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'creates, updates and deletes badges; the image is an id, never a URL', async () => {
		vi.mocked( post ).mockResolvedValue( wire( starter ) );
		vi.mocked( patch ).mockResolvedValue( wire( starter ) );
		vi.mocked( del ).mockResolvedValue( { deleted: true } );
		await createBadge( badgeToDraft( starter ) );
		const body = at( vi.mocked( post ).mock.calls )[ 1 ] as Record<
			string,
			unknown
		>;
		expect( body.image_id ).toBe( 41 );
		expect( body ).not.toHaveProperty( 'image' );
		expect( body ).not.toHaveProperty( 'image_url' );
		await updateBadge( 'a/b', badgeToDraft( starter ) );
		expect( at( vi.mocked( patch ).mock.calls )[ 0 ] ).toBe(
			'/ohmylms/v1/gamification/badges/a%2Fb'
		);
		await deleteBadge( 'starter' );
		expect( at( vi.mocked( del ).mock.calls )[ 0 ] ).toBe(
			'/ohmylms/v1/gamification/badges/starter'
		);
	} );

	it( 'searches only image media through the core route', async () => {
		vi.mocked( getCollection ).mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		await listMedia( 'trophy', 2 );
		const [ path, , extras ] = at( vi.mocked( getCollection ).mock.calls );
		expect( path ).toBe( '/wp/v2/media' );
		expect( extras?.query ).toMatchObject( {
			search: 'trophy',
			page: 2,
			per_page: 12,
			media_type: 'image',
			mime_type: 'image/jpeg,image/png,image/gif,image/webp',
		} );
	} );
} );
