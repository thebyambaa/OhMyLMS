import { useCallback, useState } from '@wordpress/element';
import { useRequest } from '../../hooks/useRequest';
import type { Collection } from '../../lib/api/collection';

const EMPTY = { items: [], total: 0, totalPages: 0 };

/**
 * A paged, searchable server list. `load` must be stable (a module function or a memoised callback).
 *
 * @param load Reads one page for a search string.
 * @return The current page, totals and controls.
 */
export function usePagedList< T >(
	load: (
		page: number,
		search: string,
		signal: AbortSignal
	) => Promise< Collection< T > >
) {
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
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
		setPage,
		setSearch: ( value: string ) => {
			setSearch( value.trim() );
			setPage( 1 );
		},
		reload: request.retry,
	};
}
