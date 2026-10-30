import type {
	CourseReport,
	CourseRow,
	CourseStudent,
	Currency,
	Dashboard,
	Earnings,
	RangeInfo,
	SeriesPoint,
	Summary,
	Transaction,
} from './types';

/** Test fixtures only; production components never import this module. */
export const currency: Currency = {
	code: 'USD',
	symbol: '$',
	decimals: 2,
	position: 'left',
};

export const rangeInfo: RangeInfo = {
	preset: 'last_30_days',
	start: '2026-10-01',
	end: '2026-10-30',
	days: 30,
	granularity: 'day',
	previous_start: '2026-09-01',
	previous_end: '2026-09-30',
};

export const current: Summary = {
	orders: 3,
	gross: 330,
	refunds: 110,
	net: 220,
	sales: 4,
	enrollments: 2,
};
export const previous: Summary = {
	orders: 1,
	gross: 300,
	refunds: 0,
	net: 300,
	sales: 0,
	enrollments: 2,
};

export const series: SeriesPoint[] = [
	{ date: '2026-10-05', orders: 2, gross: 250, refunds: 30, net: 220 },
	{ date: '2026-10-06', orders: 0, gross: 0, refunds: 0, net: 0 },
	{ date: '2026-10-07', orders: 1, gross: 80, refunds: 80, net: 0 },
];

export const algebra: CourseRow = {
	id: 41,
	title: 'Intro to Algebra',
	status: 'publish',
	date: '2026-09-01T10:00:00',
	sales: 2,
	gross: 150,
	refunds: 10,
	net: 140,
	enrollments: 2,
	completed: 1,
	permalink: 'https://example.test/course/algebra/',
	thumbnail_url: null,
};
export const geometry: CourseRow = {
	...algebra,
	id: 42,
	title: 'Geometry',
	status: 'draft',
	sales: 0,
	gross: 0,
	refunds: 0,
	net: 0,
	enrollments: 0,
	completed: 0,
	permalink: '',
};

export const dashboard: Dashboard = {
	generated_at: '2026-10-30T09:15:00',
	range: rangeInfo,
	currency,
	summary: { current, previous },
	totals: { courses: 3, published_courses: 2, students: 2 },
	series,
	recent_courses: [ algebra, geometry ],
	popular_courses: [ algebra ],
};

export const emptyDashboard: Dashboard = {
	...dashboard,
	summary: {
		current: {
			orders: 0,
			gross: 0,
			refunds: 0,
			net: 0,
			sales: 0,
			enrollments: 0,
		},
		previous: {
			orders: 0,
			gross: 0,
			refunds: 0,
			net: 0,
			sales: 0,
			enrollments: 0,
		},
	},
	totals: { courses: 0, published_courses: 0, students: 0 },
	series: [ { date: '2026-10-05', orders: 0, gross: 0, refunds: 0, net: 0 } ],
	recent_courses: [],
	popular_courses: [],
};

export const earnings: Earnings = {
	generated_at: dashboard.generated_at,
	range: rangeInfo,
	currency,
	summary: dashboard.summary,
	series,
	by_country: [
		{ country: 'US', orders: 2, gross: 180 },
		{ country: 'GB', orders: 1, gross: 150 },
	],
	by_payment_method: [
		{ id: 'stripe', title: 'Stripe', orders: 2, gross: 180 },
		{ id: 'paypal', title: 'PayPal', orders: 1, gross: 150 },
	],
};

export const transaction: Transaction = {
	order_id: 77,
	date: '2026-10-05T18:00:00',
	status: 'completed',
	payment_method: 'paypal',
	payment_title: 'PayPal',
	total: 150,
	refunded: 30,
	net: 120,
	items: [
		{ course_id: 41, title: 'Intro to Algebra' },
		{ course_id: 42, title: 'Geometry' },
	],
};
export const refundedTransaction: Transaction = {
	...transaction,
	order_id: 78,
	status: 'refunded',
	payment_method: 'stripe',
	payment_title: 'Stripe',
	total: 80,
	refunded: 80,
	net: 0,
	items: [ { course_id: 42, title: 'Geometry' } ],
};

export const courseReport: CourseReport = {
	course: {
		id: 41,
		title: 'Intro to Algebra',
		status: 'publish',
		permalink: 'https://example.test/course/algebra/',
		thumbnail_url: null,
		rating: { average: 4.5, count: 10 },
	},
	content: { chapters: 1, lessons: 2, quizzes: 1, assignments: 0 },
	range: rangeInfo,
	currency,
	summary: {
		current: { sales: 2, gross: 150, refunds: 10, net: 140 },
		previous: { sales: 1, gross: 25, refunds: 0, net: 25 },
	},
	students: { enrolled: 2, in_range: 2, completed: 1, in_progress: 1 },
	series,
};

export const student: CourseStudent = {
	user_id: 12,
	name: 'Ada Lovelace',
	email: 'ada@example.com',
	avatar_url: 'https://example.com/ada.png',
	status: 'enrolled',
	progress: 'running',
	percent_complete: 66,
	enrolled_at: '2026-10-02T09:00:00',
	completed_at: null,
	lessons: { completed: 1, total: 2 },
	quizzes: { completed: 1, total: 1 },
	assignments: { completed: 0, total: 0 },
};
export const completedStudent: CourseStudent = {
	...student,
	user_id: 13,
	name: 'Grace Hopper',
	email: 'grace@example.com',
	progress: 'completed',
	percent_complete: 100,
	completed_at: '2026-10-20T09:00:00',
	lessons: { completed: 2, total: 2 },
};
