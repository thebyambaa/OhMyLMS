import { __, sprintf } from '@wordpress/i18n';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';
import type { QueryParams } from '../../lib/api';
import type {
	QuizReportQuery,
	QuizReportRow,
	QuizReportSummary,
	ReportActivity,
	ReportOrderBy,
} from './types';

/** Rows per page of the report. */
export const REPORT_PER_PAGE = 20;

const ACTIVITIES: readonly ReportActivity[] = [
	'all',
	'attempted',
	'to_grade',
];
const STATUSES: readonly QuizReportQuery[ 'status' ][] = [
	'all',
	'publish',
	'draft',
];
const ORDERS: readonly ReportOrderBy[] = [
	'last_submitted',
	'attempts',
	'to_grade',
	'title',
	'created_at',
];

/** The report's start state: every quiz, latest submission first. */
export const DEFAULT_REPORT_QUERY: QuizReportQuery = {
	page: 1,
	search: '',
	course: 0,
	status: 'all',
	activity: 'all',
	orderby: 'last_submitted',
	order: 'desc',
};

function count( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}

function nullable< T >(
	value: unknown,
	parse: ( raw: unknown ) => T
): T | null {
	return null === value ? null : parse( value );
}

function finite( value: unknown ): number {
	return 'number' === typeof value && Number.isFinite( value ) && value >= 0
		? value
		: invalid();
}

function flag( value: unknown ): boolean {
	return 'boolean' === typeof value ? value : invalid();
}

function date( value: unknown ): string {
	const iso = text( value );
	return Number.isNaN( Date.parse( iso ) ) ? invalid() : iso;
}

function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( item ) => item === value );
	return undefined === found ? invalid() : found;
}

/**
 * Validate a report row.
 *
 * @param value Parsed JSON.
 * @return The row.
 */
export function parseReportRow( value: unknown ): QuizReportRow {
	const row = record( value );
	const attempts = count( row.attempts );
	const inProgress = count( row.in_progress );
	const toGrade = count( row.to_grade );
	const completed = count( row.completed );
	const passed = nullable( row.passed, count );
	const passRate = nullable( row.pass_rate, finite );
	if (
		inProgress + toGrade + completed > attempts ||
		( null !== passed && passed > completed ) ||
		( null !== passRate && passRate > 100 )
	) {
		return invalid();
	}
	return {
		id: positiveId( row.id ),
		title: text( row.title ),
		status: oneOf( row.status, [ 'publish', 'draft' ] as const ),
		course: nullable( row.course, ( raw ) => {
			const item = record( raw );
			return { id: positiveId( item.id ), title: text( item.title ) };
		} ),
		attempts,
		learners: count( row.learners ),
		in_progress: inProgress,
		to_grade: toGrade,
		completed,
		average_score: nullable( row.average_score, finite ),
		passing_score: finite( row.passing_score ),
		passed,
		pass_rate: passRate,
		last_submitted_at: nullable( row.last_submitted_at, date ),
		can_edit: flag( row.can_edit ),
		can_grade: flag( row.can_grade ),
	};
}

/**
 * Validate the report totals.
 *
 * @param value Parsed JSON.
 * @return The totals.
 */
export function parseReportSummary( value: unknown ): QuizReportSummary {
	const row = record( value );
	return {
		quizzes: count( row.quizzes ),
		attempts: count( row.attempts ),
		learners: count( row.learners ),
		to_grade: count( row.to_grade ),
		completed: count( row.completed ),
	};
}

/**
 * Read the report state from the address; unknown or malformed values fall back to the defaults.
 *
 * @param params Search parameters of the route.
 * @return Query.
 */
export function queryFromParams( params: URLSearchParams ): QuizReportQuery {
	const pick = < T extends string >(
		name: string,
		allowed: readonly T[],
		fallback: T
	): T => allowed.find( ( item ) => item === params.get( name ) ) ?? fallback;
	const number = ( name: string ) => {
		const raw = params.get( name ) ?? '';
		return /^[1-9]\d{0,8}$/.test( raw ) ? Number( raw ) : 0;
	};
	return {
		page: number( 'page' ) || 1,
		search: ( params.get( 'search' ) ?? '' ).slice( 0, 200 ),
		course: number( 'course' ),
		status: pick( 'status', STATUSES, DEFAULT_REPORT_QUERY.status ),
		activity: pick( 'activity', ACTIVITIES, DEFAULT_REPORT_QUERY.activity ),
		orderby: pick( 'orderby', ORDERS, DEFAULT_REPORT_QUERY.orderby ),
		order: pick( 'order', [ 'asc', 'desc' ] as const, 'desc' ),
	};
}

/**
 * The address parameters of a query; defaults are left out.
 *
 * @param query Query.
 * @return Search parameters.
 */
export function paramsFromQuery( query: QuizReportQuery ): URLSearchParams {
	const params = new URLSearchParams();
	for ( const key of Object.keys( query ) as ( keyof QuizReportQuery )[] ) {
		if ( query[ key ] !== DEFAULT_REPORT_QUERY[ key ] ) {
			params.set( key, String( query[ key ] ) );
		}
	}
	return params;
}

/**
 * Filter arguments shared by the list and the summary.
 *
 * @param query Query.
 * @return REST query parameters.
 */
export function filterParams( query: QuizReportQuery ): QueryParams {
	const search = query.search.trim();
	return {
		...( search ? { search } : {} ),
		...( query.course > 0 ? { course: query.course } : {} ),
		status: query.status,
		activity: query.activity,
	};
}

/**
 * Sort choices of the report, as `orderby:order` values.
 *
 * @return Options for a select control.
 */
export function sortOptions(): { value: string; label: string }[] {
	return [
		{
			value: 'last_submitted:desc',
			label: __( 'Latest submission', 'ohmylms' ),
		},
		{ value: 'attempts:desc', label: __( 'Most attempts', 'ohmylms' ) },
		{ value: 'to_grade:desc', label: __( 'Most to grade', 'ohmylms' ) },
		{ value: 'title:asc', label: __( 'Title A–Z', 'ohmylms' ) },
		{ value: 'created_at:desc', label: __( 'Newest quiz', 'ohmylms' ) },
	];
}

/**
 * Pass rate of a row as text, such as "50% (1 of 2)".
 *
 * @param row Report row.
 * @return Text, or a dash without a pass mark or completed attempts.
 */
export function passRateText( row: QuizReportRow ): string {
	if ( null === row.pass_rate || null === row.passed ) {
		return '—';
	}
	return sprintf(
		/* translators: 1: pass rate in percent; 2: passed attempts; 3: completed attempts. */
		__( '%1$s%% (%2$d of %3$d)', 'ohmylms' ),
		String( row.pass_rate ),
		row.passed,
		row.completed
	);
}
