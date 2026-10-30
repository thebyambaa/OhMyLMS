import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { useSearchParams } from 'react-router-dom';
import {
	getCourseReport,
	getDashboard,
	getEarnings,
	listCourseReport,
	listCourseStudents,
	listTransactions,
} from './api';
import {
	EMPTY_COURSE_QUERY,
	EMPTY_STUDENT_QUERY,
	EMPTY_TRANSACTION_QUERY,
	rangeFromSearch,
	rangeToSearch,
} from './model';
import { useRequest } from './useRequest';
import type { ApiError } from '../../lib/api';
import type {
	CourseOrderBy,
	CourseQuery,
	RangeSelection,
	SortOrder,
	StudentOrderBy,
	StudentProgressFilter,
	StudentQuery,
	TransactionOrderBy,
	TransactionQuery,
} from './types';

/**
 * The selected date range, kept in the URL search parameters (`?range=last_7_days`) so a reload,
 * the back button and a shared link keep it. Invalid parameters fall back to the default range.
 */
export function useRange() {
	const [ params, setParams ] = useSearchParams();
	const text = params.toString();
	const range = useMemo(
		() => rangeFromSearch( new URLSearchParams( text ) ),
		[ text ]
	);
	const setRange = useCallback(
		( next: RangeSelection ) => {
			const updated = new URLSearchParams( text );
			[ 'range', 'start', 'end' ].forEach( ( name ) =>
				updated.delete( name )
			);
			Object.entries( rangeToSearch( next ) ).forEach(
				( [ name, value ] ) => updated.set( name, value )
			);
			setParams( updated, { replace: true } );
		},
		[ text, setParams ]
	);
	return { range, setRange };
}

/**
 * Dashboard overview for a range.
 *
 * @param range Selected range.
 */
export function useDashboard( range: RangeSelection ) {
	return useRequest(
		( signal ) => getDashboard( range, signal ),
		JSON.stringify( range )
	);
}

/**
 * Earnings report for a range.
 *
 * @param range Selected range.
 */
export function useEarnings( range: RangeSelection ) {
	return useRequest(
		( signal ) => getEarnings( range, signal ),
		JSON.stringify( range )
	);
}

/**
 * Step back one page when the requested page no longer exists. Each error is handled once, so the
 * stale error of the previous page cannot trigger a second step.
 *
 * @param error   Error of the last request.
 * @param page    Requested page.
 * @param code    Error code that means "page does not exist".
 * @param setPage Changes the requested page.
 */
function useStepBack(
	error: ApiError | null,
	page: number,
	code: string,
	setPage: ( page: number ) => void
) {
	const handled = useRef< ApiError | null >( null );
	useEffect( () => {
		if (
			error &&
			handled.current !== error &&
			page > 1 &&
			code === error.code
		) {
			handled.current = error;
			setPage( Math.max( 1, page - 1 ) );
		}
	}, [ error, page, code, setPage ] );
}

/**
 * Paged, sortable orders of a range.
 *
 * @param range Selected range.
 */
export function useTransactions( range: RangeSelection ) {
	const [ query, setQuery ] = useState< TransactionQuery >(
		EMPTY_TRANSACTION_QUERY
	);
	const request = useRequest(
		( signal ) => listTransactions( range, query, signal ),
		JSON.stringify( [ range, query ] )
	);
	const setPage = useCallback(
		( page: number ) => setQuery( ( current ) => ( { ...current, page } ) ),
		[]
	);
	useStepBack(
		request.error,
		query.page,
		'ohmylms_analytics_invalid_page',
		setPage
	);
	return {
		...request,
		query,
		setPage,
		setSearch: ( search: string ) =>
			setQuery( ( current ) => ( { ...current, search, page: 1 } ) ),
		setPaymentMethod: ( method: string ) =>
			setQuery( ( current ) => ( {
				...current,
				payment_method: method,
				page: 1,
			} ) ),
		setSort: ( orderby: TransactionOrderBy, order: SortOrder ) =>
			setQuery( ( current ) => ( {
				...current,
				orderby,
				order,
				page: 1,
			} ) ),
	};
}

/**
 * Paged, sortable course report of a range.
 *
 * @param range Selected range.
 */
export function useCourseReport( range: RangeSelection ) {
	const [ query, setQuery ] = useState< CourseQuery >( EMPTY_COURSE_QUERY );
	const request = useRequest(
		( signal ) => listCourseReport( range, query, signal ),
		JSON.stringify( [ range, query ] )
	);
	const setPage = useCallback(
		( page: number ) => setQuery( ( current ) => ( { ...current, page } ) ),
		[]
	);
	useStepBack(
		request.error,
		query.page,
		'ohmylms_analytics_invalid_page',
		setPage
	);
	return {
		...request,
		query,
		setPage,
		setSearch: ( search: string ) =>
			setQuery( ( current ) => ( { ...current, search, page: 1 } ) ),
		setSort: ( orderby: CourseOrderBy, order: SortOrder ) =>
			setQuery( ( current ) => ( {
				...current,
				orderby,
				order,
				page: 1,
			} ) ),
	};
}

/**
 * Report of one course for a range.
 *
 * @param id    Course ID.
 * @param range Selected range.
 */
export function useSingleCourseReport( id: number, range: RangeSelection ) {
	return useRequest(
		( signal ) => getCourseReport( id, range, signal ),
		JSON.stringify( [ id, range ] )
	);
}

/**
 * Paged students of one course.
 *
 * @param id Course ID.
 */
export function useCourseStudents( id: number ) {
	const [ query, setQuery ] = useState< StudentQuery >( EMPTY_STUDENT_QUERY );
	const request = useRequest(
		( signal ) => listCourseStudents( id, query, signal ),
		JSON.stringify( [ id, query ] )
	);
	const setPage = useCallback(
		( page: number ) => setQuery( ( current ) => ( { ...current, page } ) ),
		[]
	);
	useStepBack(
		request.error,
		query.page,
		'ohmylms_analytics_invalid_page',
		setPage
	);
	return {
		...request,
		query,
		setPage,
		setSearch: ( search: string ) =>
			setQuery( ( current ) => ( { ...current, search, page: 1 } ) ),
		setProgress: ( progress: StudentProgressFilter ) =>
			setQuery( ( current ) => ( { ...current, progress, page: 1 } ) ),
		setSort: ( orderby: StudentOrderBy, order: SortOrder ) =>
			setQuery( ( current ) => ( {
				...current,
				orderby,
				order,
				page: 1,
			} ) ),
	};
}
