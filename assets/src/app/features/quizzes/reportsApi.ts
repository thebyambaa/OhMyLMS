import { get } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	REPORT_PER_PAGE,
	filterParams,
	parseReportRow,
	parseReportSummary,
} from './reportsModel';
import type { QuizReportQuery } from './types';

const BASE = '/ohmylms/v1/reports/quizzes';

/**
 * One page of the quiz reports overview: quizzes the user may grade with their attempt figures.
 *
 * @param query  Filters, page and sort.
 * @param signal Cancels the request.
 * @return Validated rows and pagination totals.
 */
export function listQuizReports(
	query: QuizReportQuery,
	signal?: AbortSignal
) {
	return getCollection( BASE, parseReportRow, {
		signal,
		query: {
			...filterParams( query ),
			page: query.page,
			per_page: REPORT_PER_PAGE,
			orderby: query.orderby,
			order: query.order,
		},
	} );
}

/**
 * Totals over every quiz that matches the filters.
 *
 * @param query  Filters; page and sort are ignored.
 * @param signal Cancels the request.
 * @return Validated totals.
 */
export async function getQuizReportSummary(
	query: QuizReportQuery,
	signal?: AbortSignal
) {
	return parseReportSummary(
		await get< unknown >( `${ BASE }/summary`, {
			signal,
			query: filterParams( query ),
		} )
	);
}
