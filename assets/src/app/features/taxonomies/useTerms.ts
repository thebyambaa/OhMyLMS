import { useEffect, useMemo } from '@wordpress/element';
import { useListState } from '../../components/listQuery';
import { getTermDetail, listTerms } from './api';
import { EMPTY_QUERY } from './model';
import { useRequest } from './useRequest';
import type { TaxonomyKind, TermQuery } from './types';

const NO_FILTERS = { search: EMPTY_QUERY.search };

/**
 * State of one taxonomy list: search and paging, kept in the address (`&s=…&paged=…`), and the request
 * that follows them. A page that no longer exists steps back one page.
 *
 * @param kind Taxonomy to list.
 */
export function useTerms( kind: TaxonomyKind ) {
	const list = useListState( NO_FILTERS, { search: 's' } );
	const query = useMemo< TermQuery >(
		() => ( { page: list.page, search: list.filters.search } ),
		[ list.page, list.filters ]
	);
	const request = useRequest(
		( signal ) => listTerms( kind, query, signal ),
		`${ kind }:${ JSON.stringify( query ) }`
	);
	const { error } = request;
	const invalidPage = `ohmylms_${ 'categories' === kind ? 'category' : 'tag' }_invalid_page`;
	useEffect( () => {
		if ( query.page > 1 && invalidPage === error?.code ) {
			list.setPage( Math.max( 1, query.page - 1 ), { replace: true } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ error, query.page, invalidPage ] );

	return {
		...request,
		query,
		setPage: ( page: number ) => list.setPage( page ),
		pageHref: list.pageHref,
		setSearch: ( search: string ) => list.setFilters( { search } ),
	};
}

/**
 * Load one term with its courses.
 *
 * @param kind Taxonomy.
 * @param id   Term ID.
 */
export function useTermDetail( kind: TaxonomyKind, id: number ) {
	return useRequest(
		( signal ) => getTermDetail( kind, id, signal ),
		`${ kind }:${ id }`
	);
}
