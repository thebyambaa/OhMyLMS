import type {
	FormState,
	Member,
	Membership,
	MembershipDetail,
	MembershipOptions,
} from './types';

/** Test fixtures only; production components never import this module. */
export const options: MembershipOptions = {
	statuses: [
		{ value: 'draft', label: 'Draft' },
		{ value: 'publish', label: 'Published' },
	],
	billing_periods: [
		{ value: 'one_time', label: 'One time' },
		{ value: 'year', label: 'Every year' },
		{ value: 'month', label: 'Every month' },
		{ value: 'week', label: 'Every week' },
		{ value: 'day', label: 'Every day' },
	],
	currency: { code: 'USD', symbol: '$', position: 'left', decimals: 2 },
	limits: {
		max_courses: 500,
		max_curriculum_items: 200,
		max_price: 99999999.99,
		max_name: 200,
	},
};

export const summary: Membership = {
	id: 7,
	name: 'All access',
	slug: 'all-access',
	status: 'publish',
	permalink: 'https://example.test/membership/all-access/',
	regular_price: '29.50',
	sale_price: '19.00',
	sale_start_date: '2026-11-01',
	sale_end_date: '2026-11-30',
	price: '19.00',
	sign_up_fee: null,
	billing_period: 'month',
	billing_interval: 1,
	currency: 'USD',
	all_learning_content: false,
	course_count: 2,
	member_count: 3,
	created_at: '2026-10-30T09:15:00',
	updated_at: '2026-10-30T09:15:00',
};

export const plan: MembershipDetail = {
	...summary,
	description: 'Everything.',
	course_ids: [ 11, 12 ],
	courses: [
		{ id: 11, title: 'Algebra', status: 'publish' },
		{ id: 12, title: 'Geometry', status: 'draft' },
	],
	excluded_course_ids: [],
	excluded_courses: [],
	curriculum_item_ids: [],
};

export const member: Member = {
	id: 4,
	user_id: 9,
	display_name: 'Alex Morgan',
	email: 'alex@example.org',
	status: 'enrolled',
	order_id: 0,
	start_date: '2026-10-30T09:20:00Z',
	end_date: null,
};

/** A valid form with no sale. */
export const form: FormState = {
	name: 'All access',
	status: 'publish',
	description: '',
	regular_price: '29.50',
	sale_price: '',
	sale_start_date: '',
	sale_end_date: '',
	sign_up_fee: '',
	billing_period: 'month',
	billing_interval: '1',
	all_learning_content: false,
	courses: [],
	excluded_courses: [],
	curriculum_item_ids: [],
};
