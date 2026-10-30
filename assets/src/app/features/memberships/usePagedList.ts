import { useEffect, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';

/**
 * Load one page of a read-only collection (members, course search). Changing the inputs cancels the
 * previous request and ignores its late result.
 *
 * @param load Starts the request; must be stable for the same inputs.
 * @param deps Values the request depends on (page, search term, id).
 * @return The current page, loading and error state.
 */
export function usePagedList< T >(
	load: ( signal: AbortSignal ) => Promise< Collection< T > >,
	deps: readonly unknown[]
) {
	const [ data, setData ] = useState< Collection< T > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		load( controller.signal ).then(
			( next ) => {
				if ( ! controller.signal.aborted ) {
					setData( next );
					setLoading( false );
				}
			},
			( failure: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setError( normalizeApiError( failure ).message );
					setLoading( false );
				}
			}
		);
		return () => controller.abort();
		// The caller lists exactly what the request depends on.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ ...deps, version ] );
	return {
		data,
		loading,
		error,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}
