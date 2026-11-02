import { get, patch } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	getEngagement,
	listMilestoneBadges,
	saveEngagement,
} from './engagementApi';
import { streakBadge, streakSettings, xpSettings } from './engagementFixtures';
import { toEngagementDraft } from './engagementModel';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	patch: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const BASE = '/ohmylms/v1/engagement/settings';

beforeEach( () => vi.clearAllMocks() );

describe( 'typed engagement API', () => {
	it( 'reads and validates a group', async () => {
		vi.mocked( get ).mockResolvedValue( streakSettings );
		const controller = new AbortController();
		await expect(
			getEngagement( 'streak', controller.signal )
		).resolves.toEqual( streakSettings );
		expect( get ).toHaveBeenCalledWith( `${ BASE }/streak`, {
			signal: controller.signal,
		} );
	} );

	it( 'rejects an invalid group body', async () => {
		vi.mocked( get ).mockResolvedValue( { enable: 'yes' } );
		await expect( getEngagement( 'xp' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'saves with PATCH and returns the stored group', async () => {
		vi.mocked( patch ).mockResolvedValue( {
			success: true,
			message: 'Settings saved successfully.',
			settings: { ...xpSettings, goals: [ 15, 30 ], default_goal: 15 },
		} );
		const draft = {
			...toEngagementDraft( 'xp', xpSettings ),
			goals: '30, 15',
			default_goal: 15,
		};
		const saved = await saveEngagement( 'xp', draft );
		expect( patch ).toHaveBeenCalledWith(
			`${ BASE }/xp`,
			expect.objectContaining( { goals: [ 30, 15 ], default_goal: 15 } ),
			{ signal: undefined }
		);
		expect( saved.goals ).toEqual( [ 15, 30 ] );
	} );

	it( 'lists every badge a milestone can use', async () => {
		vi.mocked( getCollection ).mockResolvedValue( {
			items: [ streakBadge ],
			total: 1,
			totalPages: 1,
		} );
		await expect( listMilestoneBadges() ).resolves.toEqual( [
			streakBadge,
		] );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/gamification/badges',
			expect.any( Function ),
			{ signal: undefined, query: { per_page: 100, source: 'all' } }
		);
	} );
} );
