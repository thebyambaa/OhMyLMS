import { useEffect, useMemo, useState } from '@wordpress/element';
import { useListState } from '../../components/listQuery';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listQuestions } from './api';
import type { QuestionListQuery, QuestionSummary, StatusFilter } from './types';

const NO_FILTERS = { search: '', type: '', difficulty: '', status: 'active' };

type Filters = typeof NO_FILTERS;

/**
 * List state for the question bank, kept in the address (`&s=…&type=…&difficulty=…&status=…&paged=…`).
 * Obsolete requests are cancelled and their results ignored, so a slow earlier search can never
 * overwrite a newer one. A page past the end steps back one page.
 *
 * @return Items, loading and error state, the current query and the actions that change it.
 */
export function useQuestions() {
	const list = useListState< Filters >( NO_FILTERS, { search: 's' } );
	const query = useMemo< QuestionListQuery >(
		() => ( {
			page: list.page,
			search: list.filters.search,
			type: list.filters.type,
			difficulty: list.filters.difficulty,
			status: list.filters.status as StatusFilter,
		} ),
		[ list.page, list.filters ]
	);
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
					list.setPage( query.page - 1, { replace: true } );
					return;
				}
				setError( problem.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ query, version ] );
	const reload = () => setVersion( ( current ) => current + 1 );
	const filter = ( change: Partial< Filters > ) =>
		list.setFilters( { ...list.filters, ...change } );
	return {
		data,
		loading,
		error,
		query,
		reload,
		setPage: ( page: number ) => list.setPage( page ),
		pageHref: list.pageHref,
		statusHref: ( status: string ) =>
			list.filtersHref( { ...list.filters, status } ),
		search: ( search: string ) => {
			if ( search === list.filters.search && 1 === list.page ) {
				reload();
			}
			filter( { search } );
		},
		setType: ( type: string ) => filter( { type } ),
		setDifficulty: ( difficulty: string ) => filter( { difficulty } ),
		setStatus: ( status: StatusFilter ) => filter( { status } ),
	};
}
