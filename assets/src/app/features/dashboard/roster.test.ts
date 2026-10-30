import { post } from '../../lib/api';
import {
	bulkUnenroll,
	describeBulkResult,
	describeReminderResult,
	enrollByEmail,
	parseBulkResult,
	parseReminderResult,
	sendReminders,
	validateEmail,
} from './roster';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	post: vi.fn(),
} ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'roster API', () => {
	it( 'sends only the e-mail, ids and a fixed template key', async () => {
		vi.mocked( post ).mockResolvedValueOnce( {
			user_id: 12,
			name: 'Ada',
			enrollment: {},
		} );
		await expect(
			enrollByEmail( 40, ' ada@example.com ' )
		).resolves.toEqual( { user_id: 12, name: 'Ada' } );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/analytics/courses/40/enrollments',
			{ email: 'ada@example.com' },
			{ signal: undefined }
		);

		vi.mocked( post ).mockResolvedValueOnce( {
			sent: [ 12 ],
			skipped: [],
			remaining: 49,
		} );
		const ids = Array.from( { length: 30 }, ( _, index ) => index + 1 );
		await sendReminders( 40, 'start', ids );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/analytics/courses/40/reminders',
			{ template: 'start', user_ids: ids.slice( 0, 20 ) },
			{ signal: undefined }
		);

		vi.mocked( post ).mockResolvedValueOnce( {
			action: 'unenroll',
			results: [ { user_id: 12, ok: true, code: null } ],
		} );
		await bulkUnenroll( 40, [ 12 ] );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/analytics/courses/40/enrollments/bulk',
			{ action: 'unenroll', user_ids: [ 12 ] },
			{ signal: undefined }
		);
	} );

	it( 'validates responses', () => {
		expect( () =>
			parseReminderResult( {
				sent: [],
				skipped: [ { user_id: 1, reason: 'other' } ],
				remaining: 1,
			} )
		).toThrow();
		expect( () =>
			parseBulkResult( { results: [ { user_id: 1, ok: 'yes' } ] } )
		).toThrow();
	} );
} );

describe( 'roster text', () => {
	it( 'checks e-mail addresses', () => {
		expect( validateEmail( '' ) ).toMatch( /Enter the e-mail/ );
		expect( validateEmail( 'nope' ) ).toMatch( /valid/ );
		expect( validateEmail( 'ada@example.com' ) ).toBeNull();
	} );

	it( 'summarises results', () => {
		expect(
			describeReminderResult( {
				sent: [ 1 ],
				skipped: [
					{ user_id: 2, reason: 'completed' },
					{ user_id: 3, reason: 'completed' },
					{ user_id: 4, reason: 'recently_reminded' },
				],
				remaining: 10,
			} )
		).toBe(
			'1 reminder sent. Skipped: already completed (2), reminded in the last day (1).'
		);
		expect(
			describeBulkResult( {
				results: [
					{ user_id: 1, ok: true, code: null },
					{ user_id: 2, ok: false, code: 'x' },
				],
			} )
		).toBe( '1 student unenrolled. 1 could not be unenrolled.' );
	} );
} );
