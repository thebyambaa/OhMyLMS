import { useCallback, useState } from '@wordpress/element';
import { useListState } from '../components/listQuery';
import type { Collection } from '../lib/api/collection';
import { useRequest } from './useRequest';

const EMPTY = { items: [], total: 0, totalPages: 0 };
const NO_FILTERS = { search: '' };

/** Reads one page of a collection for a search string. */
export type PageLoader< T > = (
	page: number,
	search: string,
	signal: AbortSignal
) => Promise< Collection< T > >;

export interface PagedListOptions {
	/**
	 * Keep the search and page in the address (`&s=…&paged=…`): for the screen's own list. Lists
	 * inside an editor keep them in memory, as a screen has one address.
	 */
	inAddress?: boolean;
	/** Keep showing the last page while the next one loads or after it fails. */
	keepPrevious?: boolean;
}

/**
 * A paged, searchable server list. A new `load` function (other inputs such as an id or a filter)
 * starts a new request; the previous one is cancelled and its late result ignored. `load` must
 * therefore be stable: a module function or a memoised callback.
 *
 * @param load                 Reads one page for a search string.
 * @param options              List options.
 * @param options.inAddress    Keep the search and page in the address.
 * @param options.keepPrevious Keep the last page while the next loads or after it fails.
 * @return The current page, totals and controls.
 */
export function usePagedList< T >(
	load: PageLoader< T >,
	{ inAddress = false, keepPrevious = false }: PagedListOptions = {}
) {
	const address = useListState( NO_FILTERS, { search: 's' } );
	const [ memory, setMemory ] = useState( { page: 1, search: '' } );
	const page = inAddress ? address.page : memory.page;
	const search = inAddress ? address.filters.search : memory.search;
	const request = useRequest(
		useCallback(
			( signal: AbortSignal ) => load( page, search, signal ),
			[ load, page, search ]
		),
		{ keepData: keepPrevious }
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
