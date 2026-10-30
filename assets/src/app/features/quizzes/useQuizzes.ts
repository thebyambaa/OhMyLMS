import { useEffect, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listQuizzes } from './api';
import type { Quiz } from './types';

/**
 * List state for the quiz list. Obsolete requests are cancelled and their results ignored, so a slow
 * earlier search can never overwrite a newer one.
 *
 * @return Items, loading and error state, and the actions that change the query.
 */
export function useQuizzes() {
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( 'all' );
	const [ course, setCourse ] = useState( 0 );
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< Quiz > >( {
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
		listQuizzes( page, search, status, course, controller.signal ).then(
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
					'ohmylms_quiz_invalid_page' === problem.code
				) {
					setPage( page - 1 );
					return;
				}
				setError( problem.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ page, search, status, course, version ] );
	const reload = () => setVersion( ( current ) => current + 1 );
	return {
		data,
		loading,
		error,
		page,
		status,
		course,
		setPage,
		setStatus: ( value: string ) => {
			setPage( 1 );
			setStatus( value );
		},
		setCourse: ( value: number ) => {
			setPage( 1 );
			setCourse( value );
		},
		search: ( value: string ) => {
			setPage( 1 );
			setSearch( value );
			reload();
		},
		reload,
		afterRemove: () => {
			if ( data.items.length === 1 && page > 1 ) {
				setPage( page - 1 );
			} else {
				reload();
			}
		},
	};
}
