import { get } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	getCourseReport,
	getDashboard,
	getEarnings,
	listCourseReport,
	listCourseStudents,
	listTransactions,
} from './api';
import { courseReport, dashboard, earnings } from './fixtures';
import {
	DEFAULT_RANGE,
	EMPTY_COURSE_QUERY,
	EMPTY_STUDENT_QUERY,
	EMPTY_TRANSACTION_QUERY,
} from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const custom = {
	preset: 'custom',
	start: '2001-03-01',
	end: '2001-03-31',
} as const;

beforeEach( () => vi.clearAllMocks() );

describe( 'typed analytics API', () => {
	it( 'requests the dashboard with the preset', async () => {
		vi.mocked( get ).mockResolvedValue( { ...dashboard, _links: {} } );
		expect( await getDashboard( DEFAULT_RANGE ) ).toEqual( dashboard );
		expect( get ).toHaveBeenCalledWith( '/ohmylms/v1/dashboard', {
			signal: undefined,
			query: { range: 'last_30_days' },
		} );
	} );

	it( 'sends start and end only for custom ranges', async () => {
		vi.mocked( get ).mockResolvedValue( earnings );
		await getEarnings( custom );
		expect( get ).toHaveBeenCalledWith( '/ohmylms/v1/analytics/earnings', {
			signal: undefined,
			query: { range: 'custom', start: '2001-03-01', end: '2001-03-31' },
		} );
	} );

	it( 'passes the abort signal on', async () => {
		vi.mocked( get ).mockResolvedValue( dashboard );
		const controller = new AbortController();
		await getDashboard( DEFAULT_RANGE, controller.signal );
		expect( vi.mocked( get ).mock.calls[ 0 ]?.[ 1 ]?.signal ).toBe(
			controller.signal
		);
	} );

	it( 'rejects invalid responses with a typed error', async () => {
		vi.mocked( get ).mockResolvedValue( { ...dashboard, series: null } );
		await expect( getDashboard( DEFAULT_RANGE ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		vi.mocked( get ).mockResolvedValue( {} );
		await expect( getEarnings( DEFAULT_RANGE ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'pages transactions with range, sort and filters', async () => {
		await listTransactions(
			custom,
			{
				...EMPTY_TRANSACTION_QUERY,
				page: 2,
				search: '77',
				payment_method: 'paypal',
				orderby: 'total',
				order: 'asc',
			},
			undefined
		);
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/analytics/transactions',
			expect.any( Function ),
			{
				signal: undefined,
				query: {
					range: 'custom',
					start: '2001-03-01',
					end: '2001-03-31',
					page: 2,
					per_page: 20,
					orderby: 'total',
					order: 'asc',
					search: '77',
					payment_method: 'paypal',
				},
			}
		);
	} );

	it( 'pages the course report', async () => {
		await listCourseReport( DEFAULT_RANGE, EMPTY_COURSE_QUERY );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/analytics/courses',
			expect.any( Function ),
			{
				signal: undefined,
				query: {
					range: 'last_30_days',
					page: 1,
					per_page: 20,
					orderby: 'net',
					order: 'desc',
				},
			}
		);
	} );

	it( 'reads one course report and its students from the course route', async () => {
		vi.mocked( get ).mockResolvedValue( courseReport );
		expect( await getCourseReport( 41, DEFAULT_RANGE ) ).toEqual(
			courseReport
		);
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/analytics/courses/41',
			{
				signal: undefined,
				query: { range: 'last_30_days' },
			}
		);
		await listCourseStudents( 41, {
			...EMPTY_STUDENT_QUERY,
			progress: 'completed',
		} );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/analytics/courses/41/students',
			expect.any( Function ),
			{
				signal: undefined,
				query: {
					page: 1,
					per_page: 20,
					orderby: 'name',
					order: 'asc',
					progress: 'completed',
				},
			}
		);
	} );

	it( 'refuses invalid course ids before making a request', async () => {
		await expect(
			getCourseReport( 0, DEFAULT_RANGE )
		).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		expect( () => listCourseStudents( -1, EMPTY_STUDENT_QUERY ) ).toThrow();
		expect( get ).not.toHaveBeenCalled();
		expect( getCollection ).not.toHaveBeenCalled();
	} );
} );
