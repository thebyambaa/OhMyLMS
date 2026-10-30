import { useEffect, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listCourses } from './api';
import type { Course, CourseFilters } from './types';

const NO_FILTERS: CourseFilters = {
	search: '',
	status: 'all',
	price_type: 'all',
	created_after: '',
	created_before: '',
};

/**
 * List state for the course list. Obsolete requests are cancelled and their results ignored, so a
 * slow earlier search can never overwrite a newer one.
 *
 * @return Items, loading and error state, the filters and the actions that change them.
 */
export function useCourses() {
	const [ page, setPage ] = useState( 1 );
	const [ filters, setFilters ] = useState< CourseFilters >( NO_FILTERS );
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< Course > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
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
					setPage( page - 1 );
					return;
				}
				setError( problem.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ page, filters, version ] );
	const reload = () => setVersion( ( current ) => current + 1 );
	return {
		data,
		loading,
		error,
		page,
		filters,
		setPage,
		/**
		 * Replace the filters and go back to the first page.
		 *
		 * @param next New filters.
		 */
		applyFilters: ( next: CourseFilters ) => {
			setPage( 1 );
			setFilters( next );
		},
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
