import { useCallback, useState } from '@wordpress/element';
import { useListState } from '../../components/listQuery';
import { useRequest } from '../../hooks/useRequest';
import type { Collection } from '../../lib/api/collection';

const EMPTY = { items: [], total: 0, totalPages: 0 };
const NO_FILTERS = { search: '' };

/**
 * A paged, searchable server list. `load` must be stable (a module function or a memoised callback).
 *
 * @param load      Reads one page for a search string.
 * @param inAddress Keep the search and page in the address (`&s=…&paged=…`): for the screen's own
 *                  list. Lists inside an editor keep them in memory, as a screen has one address.
 * @return The current page, totals and controls.
 */
export function usePagedList< T >(
	load: (
		page: number,
		search: string,
		signal: AbortSignal
	) => Promise< Collection< T > >,
	inAddress = false
) {
	const address = useListState( NO_FILTERS, { search: 's' } );
	const [ memory, setMemory ] = useState( { page: 1, search: '' } );
	const page = inAddress ? address.page : memory.page;
	const search = inAddress ? address.filters.search : memory.search;
	const request = useRequest(
		useCallback(
			( signal: AbortSignal ) => load( page, search, signal ),
			[ load, page, search ]
		)
	);
	return {
		page,
		search,
		loading: request.loading,
		error: request.error,
		data: ( request.data ?? EMPTY ) as Collection< T >,
		setPage: ( next: number ) =>
			inAddress
				? address.setPage( next )
				: setMemory( ( current ) => ( { ...current, page: next } ) ),
		/** Address of a page; only for lists kept in the address. */
		pageHref: inAddress ? address.pageHref : undefined,
		setSearch: ( value: string ) =>
			inAddress
				? address.setFilters( { search: value.trim() } )
				: setMemory( { page: 1, search: value.trim() } ),
		reload: request.retry,
	};
}
