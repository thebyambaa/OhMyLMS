import { useEffect, useState } from '@wordpress/element';
import { getStudent, listCourses, listEnrollments, listStudents } from './api';
import { EMPTY_ENROLLMENT_QUERY, EMPTY_QUERY, getSortChoices } from './model';
import { useRequest } from './useRequest';
import type { EnrollmentQuery, ProgressFilter, StudentQuery } from './types';

/**
 * State of the student list: query, paging, filters and the request that follows them.
 * A page that no longer exists (after the last student of a page was removed elsewhere)
 * steps back one page.
 */
export function useStudents() {
	const [ query, setQuery ] = useState< StudentQuery >( EMPTY_QUERY );
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
		setCourse: ( course: number | null ) =>
			setQuery( ( current ) => ( { ...current, course, page: 1 } ) ),
		setSort: ( value: string ) => {
			const choice = getSortChoices().find(
				( option ) => option.value === value
			);
			if ( choice ) {
				setQuery( ( current ) => ( {
					...current,
					orderby: choice.orderby,
					order: choice.order,
					page: 1,
				} ) );
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
