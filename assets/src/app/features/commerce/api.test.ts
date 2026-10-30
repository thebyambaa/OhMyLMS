import { del, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	addNote,
	changeOrderStatus,
	createCoupon,
	deleteCoupon,
	getOrder,
	getStatusCounts,
	listCoupons,
	listCourses,
	listNotes,
	listOrders,
	trashOrder,
	updateCoupon,
} from './api';
import { coupon, counts, detail, notes } from './fixtures';
import { EMPTY_COUPON_QUERY, EMPTY_ORDER_QUERY } from './model';
import type { CouponPayload } from './types';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	patch: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const BASE = '/ohmylms-commerce/v1';
const payload: CouponPayload = {
	code: 'SPRING15',
	title: '',
	description: '',
	status: 'publish',
	discount_type: 'percent',
	amount: '15',
	date_start: null,
	date_expires: null,
	usage_limit: null,
	usage_limit_per_user: null,
	individual_use: false,
	course_id_type: 'all',
	course_ids: [],
};

beforeEach( () => vi.clearAllMocks() );

describe( 'typed commerce API', () => {
	it( 'requests bounded, filtered order pages', async () => {
		await listOrders( {
			...EMPTY_ORDER_QUERY,
			page: 2,
			search: 'ada',
			status: 'pending',
			after: '2026-01-01',
		} );
		expect( getCollection ).toHaveBeenCalledWith(
			`${ BASE }/orders`,
			expect.any( Function ),
			{
				signal: undefined,
				query: {
					page: 2,
					per_page: 20,
					orderby: 'date',
					order: 'desc',
					search: 'ada',
					status: 'pending',
					after: '2026-01-01',
				},
			}
		);
	} );

	it( 'reads and validates one order', async () => {
		vi.mocked( get ).mockResolvedValue( detail );
		const controller = new AbortController();
		await expect( getOrder( 311, controller.signal ) ).resolves.toEqual(
			detail
		);
		expect( get ).toHaveBeenCalledWith( `${ BASE }/orders/311`, {
			signal: controller.signal,
		} );
		vi.mocked( get ).mockResolvedValue( { id: 311 } );
		await expect( getOrder( 311 ) ).rejects.toThrow(
			'invalid commerce data'
		);
	} );

	it( 'reads status counts and notes', async () => {
		vi.mocked( get ).mockResolvedValueOnce( counts );
		await expect( getStatusCounts() ).resolves.toEqual( counts );
		expect( get ).toHaveBeenLastCalledWith(
			`${ BASE }/orders/status-counts`,
			{ signal: undefined }
		);
		vi.mocked( get ).mockResolvedValueOnce( notes );
		await expect( listNotes( 311 ) ).resolves.toEqual( notes );
		expect( get ).toHaveBeenLastCalledWith( `${ BASE }/orders/311/notes`, {
			signal: undefined,
		} );
	} );

	it( 'changes status with a PATCH that carries only the status', async () => {
		vi.mocked( patch ).mockResolvedValue( {
			...detail,
			status: 'cancelled',
		} );
		const result = await changeOrderStatus( 311, 'cancelled' );
		expect( result.status ).toBe( 'cancelled' );
		expect( patch ).toHaveBeenCalledWith(
			`${ BASE }/orders/311`,
			{ status: 'cancelled' },
			{ signal: undefined }
		);
	} );

	it( 'trashes an order and returns its id', async () => {
		vi.mocked( del ).mockResolvedValue( {
			deleted: true,
			previous: detail,
		} );
		await expect( trashOrder( 311 ) ).resolves.toBe( 311 );
		expect( del ).toHaveBeenCalledWith( `${ BASE }/orders/311`, {
			signal: undefined,
		} );
	} );

	it( 'posts plain notes', async () => {
		vi.mocked( post ).mockResolvedValue( notes[ 0 ] );
		await addNote( 311, 'Called the student' );
		expect( post ).toHaveBeenCalledWith(
			`${ BASE }/orders/311/notes`,
			{ note: 'Called the student' },
			{ signal: undefined }
		);
	} );

	it( 'creates, updates and deletes coupons', async () => {
		vi.mocked( post ).mockResolvedValue( coupon );
		await createCoupon( payload );
		expect( post ).toHaveBeenCalledWith( `${ BASE }/coupons`, payload, {
			signal: undefined,
		} );
		vi.mocked( patch ).mockResolvedValue( coupon );
		await updateCoupon( 57, payload );
		expect( patch ).toHaveBeenCalledWith( `${ BASE }/coupons/57`, payload, {
			signal: undefined,
		} );
		vi.mocked( del ).mockResolvedValue( {
			deleted: true,
			previous: coupon,
		} );
		await expect( deleteCoupon( 57 ) ).resolves.toBe( 57 );
		expect( del ).toHaveBeenCalledWith( `${ BASE }/coupons/57`, {
			signal: undefined,
		} );
	} );

	it( 'lists coupons with the query', async () => {
		await listCoupons( { ...EMPTY_COUPON_QUERY, status: 'draft' } );
		expect( getCollection ).toHaveBeenCalledWith(
			`${ BASE }/coupons`,
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 1,
					per_page: 20,
					orderby: 'date',
					order: 'desc',
					status: 'draft',
				},
			} )
		);
	} );

	it( 'requests at most 100 course choices', async () => {
		await listCourses( ' alg ' );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/students/courses',
			expect.any( Function ),
			expect.objectContaining( {
				query: { page: 1, per_page: 100, search: 'alg' },
			} )
		);
	} );

	it( 'refuses invalid ids before any request', async () => {
		await expect( getOrder( 0 ) ).rejects.toThrow();
		await expect( getOrder( -4 ) ).rejects.toThrow();
		await expect( deleteCoupon( 1.5 ) ).rejects.toThrow();
		expect( get ).not.toHaveBeenCalled();
		expect( del ).not.toHaveBeenCalled();
	} );
} );
