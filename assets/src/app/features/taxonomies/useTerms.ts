import { useEffect, useState } from '@wordpress/element';
import { getTermDetail, listTerms } from './api';
import { EMPTY_QUERY } from './model';
import { useRequest } from './useRequest';
import type { TaxonomyKind, TermQuery } from './types';

/**
 * State of one taxonomy list: search, paging and the request that follows them. A page that no
 * longer exists steps back one page.
 *
 * @param kind Taxonomy to list.
 */
export function useTerms( kind: TaxonomyKind ) {
	const [ query, setQuery ] = useState< TermQuery >( EMPTY_QUERY );
	const request = useRequest(
		( signal ) => listTerms( kind, query, signal ),
		`${ kind }:${ JSON.stringify( query ) }`
	);
	const { error } = request;
	const invalidPage = `ohmylms_${ 'categories' === kind ? 'category' : 'tag' }_invalid_page`;
	useEffect( () => {
		if ( query.page > 1 && invalidPage === error?.code ) {
			setQuery( ( current ) => ( {
				...current,
				page: Math.max( 1, current.page - 1 ),
			} ) );
		}
	}, [ error, query.page, invalidPage ] );

	return {
		...request,
		query,
		setPage: ( page: number ) =>
			setQuery( ( current ) => ( { ...current, page } ) ),
		setSearch: ( search: string ) =>
			setQuery( ( current ) => ( { ...current, search, page: 1 } ) ),
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
