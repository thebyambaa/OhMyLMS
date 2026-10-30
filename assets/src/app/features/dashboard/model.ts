import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type { QueryParams } from '../../lib/api';
import type {
	CatalogTotals,
	CountryRow,
	Counter,
	Currency,
	CourseContent,
	CourseInfo,
	CourseMoney,
	CourseQuery,
	CourseReport,
	CourseRow,
	CourseStudent,
	CourseStudentTotals,
	Dashboard,
	Earnings,
	Granularity,
	PaymentMethodRow,
	PeriodSummary,
	RangeInfo,
	RangePreset,
	RangeSelection,
	SeriesPoint,
	StudentQuery,
	Summary,
	Transaction,
	TransactionQuery,
} from './types';

export const PER_PAGE = 20;
/** Longest custom range the server accepts, in days. */
export const MAX_SPAN_DAYS = 731;

export const RANGE_PRESETS: readonly RangePreset[] = [
	'today',
	'last_7_days',
	'last_30_days',
	'last_90_days',
	'this_month',
	'last_month',
	'this_year',
	'last_12_months',
	'custom',
];

export const DEFAULT_RANGE: RangeSelection = {
	preset: 'last_30_days',
	start: '',
	end: '',
};

export const EMPTY_TRANSACTION_QUERY: TransactionQuery = {
	page: 1,
	search: '',
	payment_method: '',
	orderby: 'date',
	order: 'desc',
};
export const EMPTY_COURSE_QUERY: CourseQuery = {
	page: 1,
	search: '',
	orderby: 'net',
	order: 'desc',
};
export const EMPTY_STUDENT_QUERY: StudentQuery = {
	page: 1,
	search: '',
	progress: 'all',
	orderby: 'name',
	order: 'asc',
};

/**
 * Translated label of a range preset.
 *
 * @param preset Preset key.
 * @return Label for the filter.
 */
export function presetLabel( preset: RangePreset ): string {
	const labels: Record< RangePreset, string > = {
		today: __( 'Today', 'ohmylms' ),
		last_7_days: __( 'Last 7 days', 'ohmylms' ),
		last_30_days: __( 'Last 30 days', 'ohmylms' ),
		last_90_days: __( 'Last 90 days', 'ohmylms' ),
		this_month: __( 'This month', 'ohmylms' ),
		last_month: __( 'Last month', 'ohmylms' ),
		this_year: __( 'This year', 'ohmylms' ),
		last_12_months: __( 'Last 12 months', 'ohmylms' ),
		custom: __( 'Custom range', 'ohmylms' ),
	};
	return labels[ preset ];
}

/* ---------------------------------------------------------------- validation of responses */

export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid report data.', 'ohmylms' ),
	} );
}

export function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}
function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}
function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}
function count( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}
export function positiveId( value: unknown ): number {
	const id = count( value );
	if ( id < 1 ) {
		throw invalidResponse();
	}
	return id;
}
function amount( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isFinite( value ) ) {
		throw invalidResponse();
	}
	return value;
}
function list< T >( value: unknown, parse: ( item: unknown ) => T ): T[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value.map( parse );
}
function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( option ) => option === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}

function parseCurrency( value: unknown ): Currency {
	const item = record( value );
	const decimals = count( item.decimals );
	if ( decimals > 6 ) {
		throw invalidResponse();
	}
	return {
		code: text( item.code ),
		symbol: text( item.symbol ),
		decimals,
		position: text( item.position ),
	};
}

function parseRange( value: unknown ): RangeInfo {
	const item = record( value );
	return {
		preset: oneOf( item.preset, RANGE_PRESETS ),
		start: text( item.start ),
		end: text( item.end ),
		days: positiveId( item.days ),
		granularity: oneOf( item.granularity, [ 'day', 'month' ] as const ),
		previous_start: text( item.previous_start ),
		previous_end: text( item.previous_end ),
	};
}

function parseSummary( value: unknown ): Summary {
	const item = record( value );
	return {
		orders: count( item.orders ),
		gross: amount( item.gross ),
		refunds: amount( item.refunds ),
		net: amount( item.net ),
		sales: count( item.sales ),
		enrollments: count( item.enrollments ),
	};
}

function parsePeriods( value: unknown ): PeriodSummary {
	const item = record( value );
	return {
		current: parseSummary( item.current ),
		previous: parseSummary( item.previous ),
	};
}

function parsePoint( value: unknown ): SeriesPoint {
	const item = record( value );
	const date = text( item.date );
	if ( ! /^\d{4}-\d{2}(-\d{2})?$/.test( date ) ) {
		throw invalidResponse();
	}
	return {
		date,
		orders: count( item.orders ),
		gross: amount( item.gross ),
		refunds: amount( item.refunds ),
		net: amount( item.net ),
	};
}

/**
 * Validate one course row.
 *
 * @param value Decoded JSON.
 * @return The row.
 */
export function parseCourseRow( value: unknown ): CourseRow {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		status: text( item.status ),
		date: text( item.date ),
		sales: count( item.sales ),
		gross: amount( item.gross ),
		refunds: amount( item.refunds ),
		net: amount( item.net ),
		enrollments: count( item.enrollments ),
		completed: count( item.completed ),
		permalink: text( item.permalink ),
		thumbnail_url: nullableText( item.thumbnail_url ),
	};
}

function parseTotals( value: unknown ): CatalogTotals {
	const item = record( value );
	return {
		courses: count( item.courses ),
		published_courses: count( item.published_courses ),
		students: count( item.students ),
	};
}

/**
 * Validate the dashboard overview.
 *
 * @param value Decoded JSON.
 * @return The overview.
 */
export function parseDashboard( value: unknown ): Dashboard {
	const item = record( value );
	return {
		generated_at: text( item.generated_at ),
		range: parseRange( item.range ),
		currency: parseCurrency( item.currency ),
		summary: parsePeriods( item.summary ),
		totals: parseTotals( item.totals ),
		series: list( item.series, parsePoint ),
		recent_courses: list( item.recent_courses, parseCourseRow ),
		popular_courses: list( item.popular_courses, parseCourseRow ),
	};
}

function parseCountry( value: unknown ): CountryRow {
	const item = record( value );
	return {
		country: text( item.country ),
		orders: count( item.orders ),
		gross: amount( item.gross ),
	};
}

function parseMethod( value: unknown ): PaymentMethodRow {
	const item = record( value );
	return {
		id: text( item.id ),
		title: text( item.title ),
		orders: count( item.orders ),
		gross: amount( item.gross ),
	};
}

/**
 * Validate the earnings report.
 *
 * @param value Decoded JSON.
 * @return The report.
 */
export function parseEarnings( value: unknown ): Earnings {
	const item = record( value );
	return {
		generated_at: text( item.generated_at ),
		range: parseRange( item.range ),
		currency: parseCurrency( item.currency ),
		summary: parsePeriods( item.summary ),
		series: list( item.series, parsePoint ),
		by_country: list( item.by_country, parseCountry ),
		by_payment_method: list( item.by_payment_method, parseMethod ),
	};
}

/**
 * Validate one transaction row.
 *
 * @param value Decoded JSON.
 * @return The row.
 */
export function parseTransaction( value: unknown ): Transaction {
	const item = record( value );
	return {
		order_id: positiveId( item.order_id ),
		date: text( item.date ),
		status: oneOf( item.status, [ 'completed', 'refunded' ] as const ),
		payment_method: text( item.payment_method ),
		payment_title: text( item.payment_title ),
		total: amount( item.total ),
		refunded: amount( item.refunded ),
		net: amount( item.net ),
		items: list( item.items, ( entry ) => {
			const line = record( entry );
			return {
				course_id: count( line.course_id ),
				title: text( line.title ),
			};
		} ),
	};
}

function parseMoney( value: unknown ): CourseMoney {
	const item = record( value );
	return {
		sales: count( item.sales ),
		gross: amount( item.gross ),
		refunds: amount( item.refunds ),
		net: amount( item.net ),
	};
}

function parseCourseInfo( value: unknown ): CourseInfo {
	const item = record( value );
	const rating = record( item.rating );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		status: text( item.status ),
		permalink: text( item.permalink ),
		thumbnail_url: nullableText( item.thumbnail_url ),
		rating: {
			average: amount( rating.average ),
			count: count( rating.count ),
		},
	};
}

function parseContent( value: unknown ): CourseContent {
	const item = record( value );
	return {
		chapters: count( item.chapters ),
		lessons: count( item.lessons ),
		quizzes: count( item.quizzes ),
		assignments: count( item.assignments ),
	};
}

function parseStudentTotals( value: unknown ): CourseStudentTotals {
	const item = record( value );
	return {
		enrolled: count( item.enrolled ),
		in_range: count( item.in_range ),
		completed: count( item.completed ),
		in_progress: count( item.in_progress ),
	};
}

/**
 * Validate the report of one course.
 *
 * @param value Decoded JSON.
 * @return The report.
 */
export function parseCourseReport( value: unknown ): CourseReport {
	const item = record( value );
	const summary = record( item.summary );
	return {
		course: parseCourseInfo( item.course ),
		content: parseContent( item.content ),
		range: parseRange( item.range ),
		currency: parseCurrency( item.currency ),
		summary: {
			current: parseMoney( summary.current ),
			previous: parseMoney( summary.previous ),
		},
		students: parseStudentTotals( item.students ),
		series: list( item.series, parsePoint ),
	};
}

function counter( value: unknown ): Counter {
	const item = record( value );
	return { completed: count( item.completed ), total: count( item.total ) };
}

/**
 * Validate one student row of a course.
 *
 * @param value Decoded JSON.
 * @return The row.
 */
export function parseCourseStudent( value: unknown ): CourseStudent {
	const item = record( value );
	const percent = count( item.percent_complete );
	if ( percent > 100 ) {
		throw invalidResponse();
	}
	return {
		user_id: positiveId( item.user_id ),
		name: text( item.name ),
		email: text( item.email ),
		avatar_url: text( item.avatar_url ),
		status: oneOf( item.status, [ 'enrolled', 'banned' ] as const ),
		progress: oneOf( item.progress, [ 'running', 'completed' ] as const ),
		percent_complete: percent,
		enrolled_at: nullableText( item.enrolled_at ),
		completed_at: nullableText( item.completed_at ),
		lessons: counter( item.lessons ),
		quizzes: counter( item.quizzes ),
		assignments: counter( item.assignments ),
	};
}

/* ---------------------------------------------------------------- date ranges and queries */

/**
 * Whether a string is a real calendar day in `YYYY-MM-DD` form.
 *
 * @param value Candidate.
 * @return True for a valid day.
 */
export function isIsoDay( value: string ): boolean {
	if ( ! /^\d{4}-\d{2}-\d{2}$/.test( value ) ) {
		return false;
	}
	const [ year, month, day ] = value.split( '-' ).map( Number ) as [
		number,
		number,
		number,
	];
	const date = new Date( Date.UTC( year, month - 1, day ) );
	return (
		date.getUTCFullYear() === year &&
		date.getUTCMonth() === month - 1 &&
		date.getUTCDate() === day
	);
}

/**
 * Number of days from `start` to `end`, both included.
 *
 * @param start First day (`YYYY-MM-DD`).
 * @param end   Last day (`YYYY-MM-DD`).
 * @return Inclusive day count.
 */
export function daysBetween( start: string, end: string ): number {
	const parse = ( value: string ) => {
		const [ year, month, day ] = value.split( '-' ).map( Number ) as [
			number,
			number,
			number,
		];
		return Date.UTC( year, month - 1, day );
	};
	return Math.round( ( parse( end ) - parse( start ) ) / 86400000 ) + 1;
}

/**
 * Check a custom range before it is requested (the server enforces the same limits).
 *
 * @param start First day.
 * @param end   Last day.
 * @return A translated error, or null when the range is usable.
 */
export function validateCustomRange(
	start: string,
	end: string
): string | null {
	if ( ! start || ! end ) {
		return __( 'Choose both a start and an end date.', 'ohmylms' );
	}
	if ( ! isIsoDay( start ) || ! isIsoDay( end ) ) {
		return __( 'Enter valid dates.', 'ohmylms' );
	}
	if ( start > end ) {
		return __(
			'The start date must not be after the end date.',
			'ohmylms'
		);
	}
	if ( daysBetween( start, end ) > MAX_SPAN_DAYS ) {
		return sprintf(
			/* translators: %d: maximum number of days. */
			__( 'The range can be at most %d days.', 'ohmylms' ),
			MAX_SPAN_DAYS
		);
	}
	return null;
}

/**
 * Read the range from URL search parameters. Anything invalid falls back to the default range.
 *
 * @param params Search parameters (`range`, `start`, `end`).
 * @return The selection.
 */
export function rangeFromSearch( params: URLSearchParams ): RangeSelection {
	const preset = RANGE_PRESETS.find(
		( option ) => option === params.get( 'range' )
	);
	if ( ! preset ) {
		return DEFAULT_RANGE;
	}
	if ( 'custom' === preset ) {
		const start = params.get( 'start' ) ?? '';
		const end = params.get( 'end' ) ?? '';
		return null === validateCustomRange( start, end )
			? { preset, start, end }
			: DEFAULT_RANGE;
	}
	return { preset, start: '', end: '' };
}

/**
 * Search parameters that describe a range (the default range adds none).
 *
 * @param range Selection.
 * @return Parameters for the URL.
 */
export function rangeToSearch(
	range: RangeSelection
): Record< string, string > {
	if ( 'custom' === range.preset ) {
		return { range: 'custom', start: range.start, end: range.end };
	}
	return range.preset === DEFAULT_RANGE.preset ? {} : { range: range.preset };
}

/**
 * Query parameters of a range for the REST API.
 *
 * @param range Selection.
 * @return `range` and, for custom ranges, `start` and `end`.
 */
export function buildRangeQuery( range: RangeSelection ): QueryParams {
	return 'custom' === range.preset
		? { range: 'custom', start: range.start, end: range.end }
		: { range: range.preset };
}

export function buildTransactionQuery(
	range: RangeSelection,
	query: TransactionQuery
): QueryParams {
	const search = query.search.trim();
	return {
		...buildRangeQuery( range ),
		page: query.page,
		per_page: PER_PAGE,
		orderby: query.orderby,
		order: query.order,
		...( search ? { search } : {} ),
		...( query.payment_method
			? { payment_method: query.payment_method }
			: {} ),
	};
}

export function buildCourseQuery(
	range: RangeSelection,
	query: CourseQuery
): QueryParams {
	const search = query.search.trim();
	return {
		...buildRangeQuery( range ),
		page: query.page,
		per_page: PER_PAGE,
		orderby: query.orderby,
		order: query.order,
		...( search ? { search } : {} ),
	};
}

export function buildStudentQuery( query: StudentQuery ): QueryParams {
	const search = query.search.trim();
	return {
		page: query.page,
		per_page: PER_PAGE,
		orderby: query.orderby,
		order: query.order,
		...( search ? { search } : {} ),
		...( 'all' === query.progress ? {} : { progress: query.progress } ),
	};
}

/**
 * Parse a course ID from a route parameter.
 *
 * @param value Raw parameter.
 * @return A positive integer, or null.
 */
export function parseCourseId(
	value: string | null | undefined
): number | null {
	if ( ! value || ! /^[1-9]\d{0,14}$/.test( value ) ) {
		return null;
	}
	return Number( value );
}

/**
 * Route of a course report.
 *
 * @param id Course ID.
 * @return App route.
 */
export function courseReportPath( id: number ): string {
	return `/reports/courses/${ id }`;
}

/* ---------------------------------------------------------------- formatting and calculations */

/**
 * Percent change from the previous to the current value.
 *
 * @param current  Value of the period.
 * @param previous Value of the previous period.
 * @return Percent rounded to one decimal; 0 when both are zero; null when there is nothing to compare with (previous is zero).
 */
export function percentChange(
	current: number,
	previous: number
): number | null {
	if ( ! Number.isFinite( current ) || ! Number.isFinite( previous ) ) {
		return null;
	}
	if ( 0 === previous ) {
		return 0 === current ? 0 : null;
	}
	return (
		Math.round( ( ( current - previous ) / Math.abs( previous ) ) * 1000 ) /
		10
	);
}

/**
 * Short text of a change, such as `+12.5%`.
 *
 * @param change Result of {@link percentChange}.
 * @return Text for the card.
 */
export function formatChange( change: number | null ): string {
	if ( null === change ) {
		return __( 'New', 'ohmylms' );
	}
	if ( 0 === change ) {
		return '0%';
	}
	return `${ change > 0 ? '+' : '−' }${ Math.abs( change ) }%`;
}

/**
 * Sentence form of a change for assistive technology.
 *
 * @param change Result of {@link percentChange}.
 * @return Translated sentence.
 */
export function describeChange( change: number | null ): string {
	if ( null === change ) {
		return __( 'New, nothing in the previous period.', 'ohmylms' );
	}
	if ( 0 === change ) {
		return __( 'No change from the previous period.', 'ohmylms' );
	}
	const size = `${ Math.abs( change ) }%`;
	return change > 0
		? sprintf(
				/* translators: %s: percentage. */
				__( 'Up %s from the previous period.', 'ohmylms' ),
				size
			)
		: sprintf(
				/* translators: %s: percentage. */
				__( 'Down %s from the previous period.', 'ohmylms' ),
				size
			);
}

/**
 * Format an amount with the site currency. Intl decides the symbol position for the locale.
 *
 * @param value    Amount in major units.
 * @param currency Site currency.
 * @param locale   BCP 47 locale; the browser locale when omitted.
 * @return Formatted amount.
 */
export function formatMoney(
	value: number,
	currency: Currency,
	locale?: string
): string {
	try {
		return new Intl.NumberFormat( locale, {
			style: 'currency',
			currency: currency.code,
			minimumFractionDigits: currency.decimals,
			maximumFractionDigits: currency.decimals,
		} ).format( value );
	} catch {
		const number = value.toFixed( currency.decimals );
		return 'right' === currency.position
			? `${ number }${ currency.symbol }`
			: `${ currency.symbol }${ number }`;
	}
}

export function formatNumber( value: number ): string {
	return new Intl.NumberFormat().format( value );
}

/**
 * Format an ISO site-time date for display.
 *
 * @param value ISO string or `YYYY-MM-DD`, or null.
 * @return A localised date, or an empty string.
 */
export function formatDate( value: string | null ): string {
	if ( ! value ) {
		return '';
	}
	const date = new Date(
		value.length === 10 ? `${ value }T00:00:00` : value
	);
	return Number.isNaN( date.getTime() )
		? ''
		: new Intl.DateTimeFormat( undefined, { dateStyle: 'medium' } ).format(
				date
			);
}

/**
 * Label of a chart bucket.
 *
 * @param key         `YYYY-MM-DD` or `YYYY-MM`.
 * @param granularity Bucket size.
 * @return Localised label.
 */
export function formatBucket( key: string, granularity: Granularity ): string {
	if ( 'month' === granularity ) {
		const [ year, month ] = key.split( '-' ).map( Number ) as [
			number,
			number,
		];
		return new Intl.DateTimeFormat( undefined, {
			month: 'short',
			year: 'numeric',
		} ).format( new Date( year, month - 1, 1 ) );
	}
	return formatDate( key );
}

/**
 * Describe a range for headings and chart descriptions.
 *
 * @param range Range from the server.
 * @return For example `Mar 1, 2001 to Mar 31, 2001`.
 */
export function describeRange( range: RangeInfo ): string {
	return sprintf(
		/* translators: 1: first day, 2: last day. */
		__( '%1$s to %2$s', 'ohmylms' ),
		formatDate( range.start ),
		formatDate( range.end )
	);
}

export type CardKey = 'gross' | 'refunds' | 'net' | 'sales' | 'enrollments';

export interface SummaryCard {
	key: CardKey;
	label: string;
	value: string;
	change: string;
	changeDescription: string;
}

/**
 * Cards of the overview: earnings, refunds, net income, courses sold, students enrolled.
 *
 * @param summary  Current and previous totals.
 * @param currency Site currency.
 * @return The five cards.
 */
export function buildCards(
	summary: PeriodSummary,
	currency: Currency
): SummaryCard[] {
	const card = (
		key: CardKey,
		label: string,
		value: string,
		change: number | null
	): SummaryCard => ( {
		key,
		label,
		value,
		change: formatChange( change ),
		changeDescription: describeChange( change ),
	} );
	const { current, previous } = summary;
	return [
		card(
			'gross',
			__( 'Earnings', 'ohmylms' ),
			formatMoney( current.gross, currency ),
			percentChange( current.gross, previous.gross )
		),
		card(
			'refunds',
			__( 'Refunds', 'ohmylms' ),
			formatMoney( current.refunds, currency ),
			percentChange( current.refunds, previous.refunds )
		),
		card(
			'net',
			__( 'Net income', 'ohmylms' ),
			formatMoney( current.net, currency ),
			percentChange( current.net, previous.net )
		),
		card(
			'sales',
			__( 'Courses sold', 'ohmylms' ),
			formatNumber( current.sales ),
			percentChange( current.sales, previous.sales )
		),
		card(
			'enrollments',
			__( 'Students enrolled', 'ohmylms' ),
			formatNumber( current.enrollments ),
			percentChange( current.enrollments, previous.enrollments )
		),
	];
}

/**
 * Cards of one course report.
 *
 * @param current  Course totals of the range.
 * @param previous Course totals of the previous period.
 * @param currency Site currency.
 * @return Money cards and the sales card.
 */
export function buildCourseCards(
	current: CourseMoney,
	previous: CourseMoney,
	currency: Currency
): SummaryCard[] {
	return buildCards(
		{
			current: { ...current, orders: 0, enrollments: 0 },
			previous: { ...previous, orders: 0, enrollments: 0 },
		},
		currency
	).filter( ( item ) => 'enrollments' !== item.key );
}

/* ---------------------------------------------------------------- chart geometry */

export interface ChartBar {
	key: string;
	x: number;
	width: number;
	netY: number;
	netHeight: number;
	refundY: number;
	refundHeight: number;
}

export interface ChartTick {
	y: number;
	value: number;
}

export interface ChartGeometry {
	width: number;
	height: number;
	left: number;
	right: number;
	top: number;
	bottom: number;
	max: number;
	bars: ChartBar[];
	ticks: ChartTick[];
}

/**
 * Round a maximum up to 1, 2, 5 or 10 times a power of ten so the axis has clean ticks.
 *
 * @param value Largest value; zero or negative gives 1.
 * @return The axis maximum.
 */
export function niceMax( value: number ): number {
	if ( ! ( value > 0 ) ) {
		return 1;
	}
	const power = Math.pow( 10, Math.floor( Math.log10( value ) ) );
	const fraction = value / power;
	const step = [ 1, 2, 5, 10 ].find( ( option ) => fraction <= option ) ?? 10;
	return step * power;
}

/**
 * Compute stacked bars (net at the bottom, refunds on top, together the gross) for the earnings chart.
 *
 * @param series Chart points in date order.
 * @param width  Viewbox width.
 * @param height Viewbox height.
 * @return Pure geometry; no rendering.
 */
export function buildChart(
	series: readonly SeriesPoint[],
	width = 640,
	height = 240
): ChartGeometry {
	const left = 56;
	const right = width - 8;
	const top = 8;
	const bottom = height - 28;
	const plotWidth = right - left;
	const plotHeight = bottom - top;
	const max = niceMax(
		Math.max( 0, ...series.map( ( point ) => point.gross ) )
	);
	const scale = plotHeight / max;
	const slot = series.length ? plotWidth / series.length : plotWidth;
	const barWidth = Math.max( 1, slot * 0.7 );

	const bars = series.map( ( point, index ) => {
		const netHeight = Math.max( 0, point.net ) * scale;
		const refundHeight = Math.max( 0, point.refunds ) * scale;
		return {
			key: point.date,
			x: left + index * slot + ( slot - barWidth ) / 2,
			width: barWidth,
			netY: bottom - netHeight,
			netHeight,
			refundY: bottom - netHeight - refundHeight,
			refundHeight,
		};
	} );
	const ticks = [ 0, 1, 2, 3, 4 ].map( ( step ) => ( {
		y: bottom - ( plotHeight * step ) / 4,
		value: ( max * step ) / 4,
	} ) );

	return { width, height, left, right, top, bottom, max, bars, ticks };
}

/**
 * Compact amount for axis ticks.
 *
 * @param value Amount.
 * @return For example `1.2K`.
 */
export function formatCompact( value: number ): string {
	return new Intl.NumberFormat( undefined, {
		notation: 'compact',
		maximumFractionDigits: 1,
	} ).format( value );
}

/**
 * Text alternative of the chart: totals and the best bucket.
 *
 * @param series      Chart points.
 * @param currency    Site currency.
 * @param granularity Bucket size.
 * @return One or two sentences.
 */
export function describeSeries(
	series: readonly SeriesPoint[],
	currency: Currency,
	granularity: Granularity
): string {
	const gross = series.reduce( ( sum, point ) => sum + point.gross, 0 );
	if ( 0 === series.length || 0 === gross ) {
		return __( 'No earnings in this period.', 'ohmylms' );
	}
	const refunds = series.reduce( ( sum, point ) => sum + point.refunds, 0 );
	const best = series.reduce( ( top, point ) =>
		point.gross > top.gross ? point : top
	);
	return sprintf(
		/* translators: 1: gross total, 2: refunds total, 3: best bucket, 4: best bucket gross. */
		__(
			'Earnings %1$s, refunds %2$s. The highest period is %3$s with %4$s.',
			'ohmylms'
		),
		formatMoney( gross, currency ),
		formatMoney( refunds, currency ),
		formatBucket( best.date, granularity ),
		formatMoney( best.gross, currency )
	);
}

/* ---------------------------------------------------------------- errors */

/**
 * User-facing text for a failed request.
 *
 * @param error Typed API error.
 * @return Message for the notice.
 */
export function describeError( error: ApiError ): string {
	if ( 401 === error.status || 403 === error.status ) {
		return __( 'You do not have permission to view analytics.', 'ohmylms' );
	}
	if ( 'ohmylms_analytics_course_not_found' === error.code ) {
		return __( 'That course does not exist.', 'ohmylms' );
	}
	return (
		error.message ||
		__( 'The report could not be loaded. Try again.', 'ohmylms' )
	);
}

/**
 * Whether retrying could help (not for permission or missing-course errors).
 *
 * @param error Typed API error.
 * @return True when a retry button makes sense.
 */
export function isRetryable( error: ApiError ): boolean {
	return (
		401 !== error.status &&
		403 !== error.status &&
		'ohmylms_analytics_course_not_found' !== error.code
	);
}

/**
 * Label of the state of a course student.
 *
 * @param student Student row.
 * @return Translated label; access problems win over learning state.
 */
export function studentStatus( student: CourseStudent ): string {
	if ( 'banned' === student.status ) {
		return __( 'Blocked', 'ohmylms' );
	}
	return 'completed' === student.progress
		? __( 'Completed', 'ohmylms' )
		: __( 'In progress', 'ohmylms' );
}
