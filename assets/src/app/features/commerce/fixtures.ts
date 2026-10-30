import type {
	Coupon,
	CourseOption,
	OrderDetail,
	OrderNote,
	OrderSummary,
	StatusCounts,
} from './types';

/** Test fixtures only; production components never import this module. */
export const order: OrderSummary = {
	id: 311,
	number: '311',
	status: 'completed',
	currency: 'USD',
	total: '49.50',
	date_created: '2026-10-29T08:30:00',
	payment_method: 'offline',
	payment_method_title: 'Offline',
	student_id: 12,
	student_name: 'Ada Lovelace',
};
export const otherOrder: OrderSummary = {
	...order,
	id: 312,
	number: '312',
	status: 'pending',
	total: '10.00',
	student_id: 13,
	student_name: 'Grace Hopper',
};
export const detail: OrderDetail = {
	...order,
	parent_id: 0,
	subtotal: '59.50',
	discount_total: '10.00',
	tax_amount: '0.00',
	date_paid: '2026-10-29T08:31:00',
	date_completed: '2026-10-29T08:31:00',
	transaction_id: 'txn_1',
	billing: {
		first_name: 'Ada',
		last_name: 'Lovelace',
		email: 'ada@example.com',
		phone: '555-0100',
		address: '1 Analytical St',
		city: 'London',
		state: '',
		postcode: 'N1',
		country: 'GB',
		vat_number: '',
	},
	line_items: [
		{
			id: 1,
			course_id: 40,
			name: 'Intro to Algebra',
			quantity: 1,
			subtotal: '59.50',
			total: '49.50',
		},
	],
	coupon_lines: [ { code: 'SPRING10', discount: '10.00' } ],
	refunds: [
		{
			id: 900,
			amount: '5.00',
			reason: 'Goodwill',
			date_created: '2026-10-30T09:00:00',
		},
	],
	total_refunded: '5.00',
	remaining_refundable: '44.50',
	related_orders: [
		{
			id: 320,
			type: 'order',
			relationship: 'Renewal Order',
			status: 'completed',
			total: '49.50',
			date_created: '2026-11-29T08:30:00',
		},
	],
	allowed_statuses: [ 'cancelled' ],
};
export const pendingDetail: OrderDetail = {
	...detail,
	id: 312,
	number: '312',
	status: 'pending',
	allowed_statuses: [ 'processing', 'on-hold', 'completed', 'cancelled' ],
	refunds: [],
	total_refunded: '0.00',
	remaining_refundable: '10.00',
	related_orders: [],
};
export const notes: OrderNote[] = [
	{
		id: 5,
		content: 'Called the student',
		added_by: 'Admin',
		date_created: '2026-10-30T10:00:00',
	},
];
export const counts: StatusCounts = {
	pending: 1,
	processing: 0,
	'on-hold': 0,
	completed: 4,
	cancelled: 2,
	failed: 0,
	refunded: 0,
};
export const coupon: Coupon = {
	id: 57,
	code: 'SPRING15',
	title: 'Spring sale',
	description: 'Internal',
	status: 'publish',
	discount_type: 'percent',
	amount: '15.00',
	date_start: null,
	date_expires: '2026-12-31T23:59:59',
	usage_count: 3,
	usage_limit: 100,
	usage_limit_per_user: null,
	individual_use: false,
	course_id_type: 'all',
	course_ids: [],
	date_created: '2026-10-01T09:00:00',
	currency: 'USD',
};
export const flatCoupon: Coupon = {
	...coupon,
	id: 58,
	code: 'TENOFF',
	discount_type: 'flat-rate',
	amount: '10.00',
	status: 'draft',
	date_expires: null,
	usage_limit: null,
	usage_count: 0,
};
export const courses: CourseOption[] = [
	{ id: 40, title: 'Intro to Algebra', status: 'publish' },
	{ id: 41, title: 'Geometry', status: 'publish' },
];
