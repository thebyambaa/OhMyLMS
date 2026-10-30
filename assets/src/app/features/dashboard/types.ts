export type SortOrder = 'asc' | 'desc';

export type RangePreset =
	| 'today'
	| 'last_7_days'
	| 'last_30_days'
	| 'last_90_days'
	| 'this_month'
	| 'last_month'
	| 'this_year'
	| 'last_12_months'
	| 'custom';

export type Granularity = 'day' | 'month';

/** The date range a screen shows. `start` and `end` only matter for `custom`. */
export interface RangeSelection {
	preset: RangePreset;
	/** `YYYY-MM-DD`, site time. */
	start: string;
	/** `YYYY-MM-DD`, site time. */
	end: string;
}

/** Site currency (`currency` member of every report). */
export interface Currency {
	code: string;
	symbol: string;
	decimals: number;
	position: string;
}

/** The range as resolved by the server, with the previous period. */
export interface RangeInfo {
	preset: RangePreset;
	start: string;
	end: string;
	days: number;
	granularity: Granularity;
	previous_start: string;
	previous_end: string;
}

/** Totals of one period. */
export interface Summary {
	orders: number;
	gross: number;
	refunds: number;
	net: number;
	sales: number;
	enrollments: number;
}

export interface PeriodSummary {
	current: Summary;
	previous: Summary;
}

/** One bucket of the earnings chart. `date` is `YYYY-MM-DD` or `YYYY-MM`. */
export interface SeriesPoint {
	date: string;
	orders: number;
	gross: number;
	refunds: number;
	net: number;
}

/** A course with its figures for the range. */
export interface CourseRow {
	id: number;
	title: string;
	status: string;
	date: string;
	sales: number;
	gross: number;
	refunds: number;
	net: number;
	enrollments: number;
	completed: number;
	permalink: string;
	thumbnail_url: string | null;
}

export interface CatalogTotals {
	courses: number;
	published_courses: number;
	students: number;
}

export interface Dashboard {
	generated_at: string;
	range: RangeInfo;
	currency: Currency;
	summary: PeriodSummary;
	totals: CatalogTotals;
	series: SeriesPoint[];
	recent_courses: CourseRow[];
	popular_courses: CourseRow[];
}

export interface CountryRow {
	country: string;
	orders: number;
	gross: number;
}

export interface PaymentMethodRow {
	id: string;
	title: string;
	orders: number;
	gross: number;
}

export interface Earnings {
	generated_at: string;
	range: RangeInfo;
	currency: Currency;
	summary: PeriodSummary;
	series: SeriesPoint[];
	by_country: CountryRow[];
	by_payment_method: PaymentMethodRow[];
}

export interface TransactionItem {
	course_id: number;
	title: string;
}

export interface Transaction {
	order_id: number;
	date: string;
	status: 'completed' | 'refunded';
	payment_method: string;
	payment_title: string;
	total: number;
	refunded: number;
	net: number;
	items: TransactionItem[];
}

export type TransactionOrderBy = 'date' | 'id' | 'total' | 'refunded' | 'net';

export interface TransactionQuery {
	page: number;
	search: string;
	payment_method: string;
	orderby: TransactionOrderBy;
	order: SortOrder;
}

export type CourseOrderBy =
	'title' | 'date' | 'sales' | 'gross' | 'refunds' | 'net' | 'enrollments';

export interface CourseQuery {
	page: number;
	search: string;
	orderby: CourseOrderBy;
	order: SortOrder;
}

export interface CourseInfo {
	id: number;
	title: string;
	status: string;
	permalink: string;
	thumbnail_url: string | null;
	rating: { average: number; count: number };
}

export interface CourseContent {
	chapters: number;
	lessons: number;
	quizzes: number;
	assignments: number;
}

export interface CourseMoney {
	sales: number;
	gross: number;
	refunds: number;
	net: number;
}

export interface CourseStudentTotals {
	enrolled: number;
	in_range: number;
	completed: number;
	in_progress: number;
}

export interface CourseReport {
	course: CourseInfo;
	content: CourseContent;
	range: RangeInfo;
	currency: Currency;
	summary: { current: CourseMoney; previous: CourseMoney };
	students: CourseStudentTotals;
	series: SeriesPoint[];
}

export interface Counter {
	completed: number;
	total: number;
}

/** A student of one course. Personal data: only shown to administrators. */
export interface CourseStudent {
	user_id: number;
	name: string;
	email: string;
	avatar_url: string;
	status: 'enrolled' | 'banned';
	progress: 'running' | 'completed';
	percent_complete: number;
	enrolled_at: string | null;
	completed_at: string | null;
	lessons: Counter;
	quizzes: Counter;
	assignments: Counter;
}

export type StudentProgressFilter = 'all' | 'running' | 'completed';
export type StudentOrderBy = 'name' | 'enrolled_at' | 'percent';

export interface StudentQuery {
	page: number;
	search: string;
	progress: StudentProgressFilter;
	orderby: StudentOrderBy;
	order: SortOrder;
}
