import { useEffect, useState } from '@wordpress/element';
import { ApiError, normalizeApiError } from '../../lib/api';
import { getCourse, getOptions } from './api';
import type { CourseDetail, CourseOptions } from './types';

/**
 * Load one course and the server options for the editor. Switching courses or unmounting cancels the
 * requests and ignores late results.
 *
 * @param id Course id, or `null` when the route id is invalid (nothing is requested).
 * @return The loaded data, loading and error state, a reload action and a setter for the saved copy.
 */
export function useCourseEditor( id: number | null ) {
	const [ course, setCourse ] = useState< CourseDetail | null >( null );
	const [ options, setOptions ] = useState< CourseOptions | null >( null );
	const [ loading, setLoading ] = useState( null !== id );
	const [ error, setError ] = useState< ApiError | null >( null );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		if ( null === id ) {
			return undefined;
		}
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		Promise.all( [
			getCourse( id, controller.signal ),
			getOptions( controller.signal ),
		] ).then(
			( [ loaded, choices ] ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setCourse( loaded );
				setOptions( choices );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setError( normalizeApiError( failure ) );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ id, version ] );
	return {
		course,
		options,
		loading,
		error,
		setCourse,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}
