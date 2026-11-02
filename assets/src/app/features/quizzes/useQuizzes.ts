import { useEffect, useState } from '@wordpress/element';
import { useListQuery } from '../../components/listQuery';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listQuizzes } from './api';
import type { Quiz } from './types';

const DEFAULTS = { s: '', status: 'all', course: '0' };

/**
 * List state for the quiz list, kept in the address (`&status=…&course=…&s=…&paged=…`). Obsolete
 * requests are cancelled and their results ignored, so a slow earlier search can never overwrite a
 * newer one.
 *
 * @return Items, loading and error state, and the actions that change the query.
 */
export function useQuizzes() {
	const query = useListQuery( DEFAULTS );
	const { page } = query;
	const search = query.values.s;
	const status = query.values.status;
	const course = Math.max( 0, Number( query.values.course ) || 0 );
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< Quiz > >( {
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
					query.set( { page: page - 1 }, { replace: true } );
					return;
				}
				setError( problem.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ page, search, status, course, version ] );
	const reload = () => setVersion( ( current ) => current + 1 );
	return {
		data,
		loading,
		error,
		page,
		searchTerm: search,
		status,
		course,
		setPage,
		pageHref: ( next: number ) => query.href( { page: next } ),
		statusHref: ( value: string ) => query.href( { status: value } ),
		setStatus: ( value: string ) => query.set( { status: value } ),
		setCourse: ( value: number ) =>
			query.set( { course: String( value ) } ),
		search: ( value: string ) => {
			if ( value === search && 1 === page ) {
				reload();
			}
			query.set( { s: value, page: 1 } );
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
