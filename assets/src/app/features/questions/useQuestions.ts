import { useEffect, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listQuestions } from './api';
import type { QuestionListQuery, QuestionSummary, StatusFilter } from './types';

/**
 * List state for the question bank. Obsolete requests are cancelled and their results ignored, so a
 * slow earlier search can never overwrite a newer one. A page past the end steps back one page.
 *
 * @return Items, loading and error state, the current query and the actions that change it.
 */
export function useQuestions() {
	const [ query, setQuery ] = useState< QuestionListQuery >( {
		page: 1,
		search: '',
		type: '',
		difficulty: '',
		status: 'active',
	} );
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< QuestionSummary > >( {
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
		listQuestions( query, controller.signal ).then(
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
					query.page > 1 &&
					'ohmylms_question_invalid_page' === problem.code
				) {
					setQuery( ( current ) => ( {
						...current,
						page: current.page - 1,
					} ) );
					return;
				}
				setError( problem.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ query, version ] );
	const reload = () => setVersion( ( current ) => current + 1 );
	const filter = ( change: Partial< QuestionListQuery > ) =>
		setQuery( ( current ) => ( { ...current, ...change, page: 1 } ) );
	return {
		data,
		loading,
		error,
		query,
		reload,
		setPage: ( page: number ) =>
			setQuery( ( current ) => ( { ...current, page } ) ),
		search: ( search: string ) => {
			filter( { search } );
			reload();
		},
		setType: ( type: string ) => filter( { type } ),
		setDifficulty: ( difficulty: string ) => filter( { difficulty } ),
		setStatus: ( status: StatusFilter ) => filter( { status } ),
	};
}
