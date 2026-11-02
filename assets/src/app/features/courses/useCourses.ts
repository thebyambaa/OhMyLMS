import { useEffect, useMemo, useState } from '@wordpress/element';
import { useListQuery } from '../../components/listQuery';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listCourses } from './api';
import type { Course, CourseFilters } from './types';

/** List parameters as the address names them (`s` is the search, as on WordPress lists). */
const DEFAULTS = {
	s: '',
	status: 'all',
	price_type: 'all',
	created_after: '',
	created_before: '',
};

/**
 * List state for the course list, kept in the address (`&status=draft&s=…&paged=2`). Obsolete requests
 * are cancelled and their results ignored, so a slow earlier search can never overwrite a newer one.
 *
 * @return Items, loading and error state, the filters and the actions that change them.
 */
export function useCourses() {
	const query = useListQuery( DEFAULTS );
	const { page } = query;
	const filters = useMemo< CourseFilters >(
		() => ( {
			search: query.values.s,
			status: query.values.status,
			price_type: query.values.price_type,
			created_after: query.values.created_after,
			created_before: query.values.created_before,
		} ),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ query.key ]
	);
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< Course > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	const setPage = ( next: number ) => query.set( { page: next } );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		listCourses( page, filters, controller.signal ).then(
			( next ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setData( next );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				const problem = normalizeApiError( failure );
				if (
					page > 1 &&
					'ohmylms_course_invalid_page' === problem.code
				) {
					query.set( { page: page - 1 }, { replace: true } );
					return;
				}
				setError( problem.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ page, filters, version ] );
	const reload = () => setVersion( ( current ) => current + 1 );
	const toQuery = ( next: CourseFilters ) => ( {
		s: next.search,
		status: next.status,
		price_type: next.price_type,
		created_after: next.created_after,
		created_before: next.created_before,
	} );
	return {
		data,
		loading,
		error,
		page,
		filters,
		setPage,
		/**
		 * Address of a page of the list.
		 * @param next
		 */
		pageHref: ( next: number ) => query.href( { page: next } ),
		/**
		 * Address of the list with other filters (first page).
		 *
		 * @param next Filters.
		 */
		filtersHref: ( next: CourseFilters ) => query.href( toQuery( next ) ),
		/**
		 * Replace the filters and go back to the first page.
		 *
		 * @param next New filters.
		 */
		applyFilters: ( next: CourseFilters ) => query.set( toQuery( next ) ),
		reload,
		/** Reload after a removal, stepping back when the page became empty. */
		afterRemoval: () => {
			if ( data.items.length === 1 && page > 1 ) {
				setPage( page - 1 );
			} else {
				reload();
			}
		},
	};
}
