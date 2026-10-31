import { useEffect, useMemo, useState } from '@wordpress/element';
import { useListState } from '../../components/listQuery';
import { getStudent, listCourses, listEnrollments, listStudents } from './api';
import { EMPTY_ENROLLMENT_QUERY, EMPTY_QUERY, getSortChoices } from './model';
import { useRequest } from './useRequest';
import type { EnrollmentQuery, ProgressFilter, StudentQuery } from './types';

const LIST_DEFAULTS = {
	search: EMPTY_QUERY.search,
	course: '',
	orderby: EMPTY_QUERY.orderby as string,
	order: EMPTY_QUERY.order as string,
};

/**
 * State of the student list: query, paging and filters, kept in the address (`&s=…&course=…&orderby=…
 * &order=…&paged=…`), and the request that follows them. A page that no longer exists (after the last
 * student of a page was removed elsewhere) steps back one page.
 */
export function useStudents() {
	const list = useListState( LIST_DEFAULTS, { search: 's' } );
	const query = useMemo< StudentQuery >( () => {
		const sort =
			getSortChoices().find(
				( option ) =>
					option.orderby === list.filters.orderby &&
					option.order === list.filters.order
			) ?? EMPTY_QUERY;
		return {
			page: list.page,
			search: list.filters.search,
			course: /^[1-9]\d*$/.test( list.filters.course )
				? Number( list.filters.course )
				: null,
			orderby: sort.orderby,
			order: sort.order,
		};
	}, [ list.page, list.filters ] );
	const request = useRequest(
		( signal ) => listStudents( query, signal ),
		JSON.stringify( query )
	);
	const { error } = request;
	useEffect( () => {
		if (
			query.page > 1 &&
			'ohmylms_student_invalid_page' === error?.code
		) {
			list.setPage( Math.max( 1, query.page - 1 ), { replace: true } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ error, query.page ] );
	const filter = ( change: Partial< typeof LIST_DEFAULTS > ) =>
		list.setFilters( { ...list.filters, ...change } );

	return {
		...request,
		query,
		setPage: ( page: number ) => list.setPage( page ),
		pageHref: list.pageHref,
		setSearch: ( search: string ) => filter( { search } ),
		setCourse: ( course: number | null ) =>
			filter( { course: null === course ? '' : String( course ) } ),
		setSort: ( value: string ) => {
			const choice = getSortChoices().find(
				( option ) => option.value === value
			);
			if ( choice ) {
				filter( { orderby: choice.orderby, order: choice.order } );
			}
		},
	};
}

/**
 * Load one student.
 *
 * @param id Student ID.
 */
export function useStudent( id: number ) {
	return useRequest( ( signal ) => getStudent( id, signal ), String( id ) );
}

/**
 * Enrolments of one student with search, state filter and paging.
 *
 * @param studentId Student ID.
 */
export function useEnrollments( studentId: number ) {
	const [ query, setQuery ] = useState< EnrollmentQuery >(
		EMPTY_ENROLLMENT_QUERY
	);
	const request = useRequest(
		( signal ) => listEnrollments( studentId, query, signal ),
		`${ studentId }:${ JSON.stringify( query ) }`
	);
	const { error } = request;
	useEffect( () => {
		if (
			query.page > 1 &&
			'ohmylms_enrollment_invalid_page' === error?.code
		) {
			setQuery( ( current ) => ( {
				...current,
				page: Math.max( 1, current.page - 1 ),
			} ) );
		}
	}, [ error, query.page ] );

	return {
		...request,
		query,
		setPage: ( page: number ) =>
			setQuery( ( current ) => ( { ...current, page } ) ),
		setSearch: ( search: string ) =>
			setQuery( ( current ) => ( { ...current, search, page: 1 } ) ),
		setProgress: ( progress: ProgressFilter ) =>
			setQuery( ( current ) => ( { ...current, progress, page: 1 } ) ),
	};
}

/**
 * Course choices for the filter and the enrol form (at most 100, server side search).
 *
 * @param search Title search; empty for the first courses by title.
 */
export function useCourseOptions( search: string ) {
	return useRequest( ( signal ) => listCourses( search, signal ), search );
}
