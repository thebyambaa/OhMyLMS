import type { Course, CourseDetail, CourseOptions } from './types';

/** Test fixtures only; production components never import this module. */
export const options: CourseOptions = {
	statuses: [
		{ value: 'draft', label: 'Draft' },
		{ value: 'pending', label: 'Pending review' },
		{ value: 'publish', label: 'Published' },
		{ value: 'future', label: 'Scheduled' },
		{ value: 'private', label: 'Private' },
	],
	levels: [
		{ value: 'beginner', label: 'Beginner' },
		{ value: 'experience', label: 'Intermediate' },
		{ value: 'expert', label: 'Expert' },
		{ value: 'all', label: 'All levels' },
	],
	price_types: [
		{ value: 'free', label: 'Free' },
		{ value: 'paid', label: 'Paid' },
	],
	access_types: [
		{ value: 'public', label: 'Open to everyone who enrols' },
		{ value: 'password_protected', label: 'Protected by a password' },
	],
	currency: { code: 'USD', symbol: '$', position: 'left', decimals: 2 },
	limits: {
		max_title: 200,
		max_text: 5000,
		max_price: 99999999.99,
		max_capacity: 1000000,
		max_password: 100,
	},
	abilities: {
		publish: true,
		edit_others: true,
		upload_files: true,
		builder_url:
			'https://example.test/wp-admin/admin.php?page=ohmylms#/courses/',
	},
};

export const summary: Course = {
	id: 7,
	title: 'Algebra I',
	slug: 'algebra-i',
	status: 'publish',
	permalink: 'https://example.test/course/algebra-i/',
	author_id: 1,
	author_name: 'Ada Admin',
	level: 'beginner',
	price_type: 'paid',
	regular_price: '49.50',
	sale_price: '29.00',
	sale_start_date: '2026-11-01',
	sale_end_date: '2026-11-30',
	price: '29.00',
	currency: 'USD',
	access_type: 'public',
	has_password: false,
	available_date: null,
	duration: { hours: 3, minutes: 30 },
	has_capacity: true,
	capacity: 30,
	enable_reviews: true,
	thumbnail_id: 21,
	thumbnail_url: 'https://example.test/thumb.png',
	enrolled_count: 4,
	scheduled_at: null,
	created_at: '2026-10-30T09:15:00',
	updated_at: '2026-10-30T09:15:00',
};

export const detail: CourseDetail = {
	...summary,
	description: '<p>Learn algebra.</p>',
	excerpt: 'A short course.',
	learning_outcomes: 'Solve equations',
	target_audience: 'Beginners',
	requirements: 'None',
	password: '',
};

export const draftFree: Course = {
	...summary,
	id: 8,
	title: 'Free draft',
	status: 'draft',
	price_type: 'free',
	regular_price: '0.00',
	sale_price: null,
	sale_start_date: null,
	sale_end_date: null,
	price: '0.00',
	duration: null,
	has_capacity: false,
	capacity: null,
	thumbnail_id: null,
	thumbnail_url: null,
	enrolled_count: 0,
};
