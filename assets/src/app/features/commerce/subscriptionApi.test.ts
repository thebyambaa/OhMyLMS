import { get, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	getSubscription,
	listSubscriptions,
	runSubscriptionAction,
} from './subscriptionApi';
import { subscriptionDetail } from './subscriptionFixtures';
import { EMPTY_SUBSCRIPTION_QUERY } from './subscriptionModel';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	patch: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const BASE = '/ohmylms/v1/subscriptions';

beforeEach( () => vi.clearAllMocks() );

describe( 'typed subscriptions API', () => {
	it( 'requests bounded, filtered pages', async () => {
		const controller = new AbortController();
		await listSubscriptions(
			{ ...EMPTY_SUBSCRIPTION_QUERY, status: 'active', search: 'ada' },
			controller.signal
		);
		expect( getCollection ).toHaveBeenCalledWith(
			BASE,
			expect.any( Function ),
			{
				signal: controller.signal,
				query: {
					page: 1,
					per_page: 20,
					orderby: 'date',
					order: 'desc',
					search: 'ada',
					status: 'active',
				},
			}
		);
	} );

	it( 'reads and validates one subscription', async () => {
		vi.mocked( get ).mockResolvedValue( subscriptionDetail );
		await expect( getSubscription( 501 ) ).resolves.toEqual(
			subscriptionDetail
		);
		expect( get ).toHaveBeenCalledWith( `${ BASE }/501`, {
			signal: undefined,
		} );
	} );

	it( 'rejects an invalid body', async () => {
		vi.mocked( get ).mockResolvedValue( { id: 501 } );
		await expect( getSubscription( 501 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'never builds a route from an invalid id', async () => {
		await expect( getSubscription( 0 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		await expect(
			runSubscriptionAction( -1, 'cancel' )
		).rejects.toBeTruthy();
		expect( get ).not.toHaveBeenCalled();
		expect( post ).not.toHaveBeenCalled();
	} );

	it.each( [ 'pause', 'resume', 'cancel' ] as const )(
		'posts %s with confirm=true',
		async ( action ) => {
			vi.mocked( post ).mockResolvedValue( subscriptionDetail );
			await runSubscriptionAction( 501, action );
			expect( post ).toHaveBeenCalledWith(
				`${ BASE }/501/${ action }`,
				{ confirm: true },
				{ signal: undefined }
			);
		}
	);
} );
