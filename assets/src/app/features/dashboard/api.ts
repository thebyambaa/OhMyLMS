import { get } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	buildCourseQuery,
	buildRangeQuery,
	buildStudentQuery,
	buildTransactionQuery,
	parseCourseReport,
	parseCourseRow,
	parseCourseStudent,
	parseDashboard,
	parseEarnings,
	parseTransaction,
	positiveId,
} from './model';
import type {
	CourseQuery,
	RangeSelection,
	StudentQuery,
	TransactionQuery,
} from './types';

const NAMESPACE = '/ohmylms/v1';
const courseRoute = ( id: number ) =>
	`${ NAMESPACE }/analytics/courses/${ positiveId( id ) }`;

/**
 * Dashboard overview for a range.
 *
 * @param range  Selected range.
 * @param signal Cancels the request.
 */
export async function getDashboard(
	range: RangeSelection,
	signal?: AbortSignal
) {
	return parseDashboard(
		await get< unknown >( `${ NAMESPACE }/dashboard`, {
			signal,
			query: buildRangeQuery( range ),
		} )
	);
}

/**
 * Earnings summary, series and breakdowns for a range.
 *
 * @param range  Selected range.
 * @param signal Cancels the request.
 */
export async function getEarnings(
	range: RangeSelection,
	signal?: AbortSignal
) {
	return parseEarnings(
		await get< unknown >( `${ NAMESPACE }/analytics/earnings`, {
			signal,
			query: buildRangeQuery( range ),
		} )
	);
}

export function listTransactions(
	range: RangeSelection,
	query: TransactionQuery,
	signal?: AbortSignal
) {
	return getCollection(
		`${ NAMESPACE }/analytics/transactions`,
		parseTransaction,
		{ signal, query: buildTransactionQuery( range, query ) }
	);
}

export function listCourseReport(
	range: RangeSelection,
	query: CourseQuery,
	signal?: AbortSignal
) {
	return getCollection( `${ NAMESPACE }/analytics/courses`, parseCourseRow, {
		signal,
		query: buildCourseQuery( range, query ),
	} );
}

export async function getCourseReport(
	id: number,
	range: RangeSelection,
	signal?: AbortSignal
) {
	return parseCourseReport(
		await get< unknown >( courseRoute( id ), {
			signal,
			query: buildRangeQuery( range ),
		} )
	);
}

export function listCourseStudents(
	id: number,
	query: StudentQuery,
	signal?: AbortSignal
) {
	return getCollection(
		`${ courseRoute( id ) }/students`,
		parseCourseStudent,
		{ signal, query: buildStudentQuery( query ) }
	);
}
