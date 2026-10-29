import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';
import type { ApiError } from '../../lib/api';

interface RequestState< T > {
	data: T | null;
	loading: boolean;
	error: ApiError | null;
}

/**
 * Run a read request whenever `key` changes (or `reload` is called).
 *
 * The previous request is cancelled when a new one starts or the component unmounts, and a
 * response that arrives after cancellation is ignored even if the transport ignores the signal.
 *
 * @param load Starts the request. Always the latest closure is used.
 * @param key  Serialised inputs of `load`; a change starts a new request.
 */
export function useRequest< T >(
	load: ( signal: AbortSignal ) => Promise< T >,
	key: string
) {
	const loader = useRef( load );
	loader.current = load;
	const [ version, setVersion ] = useState( 0 );
	const [ state, setState ] = useState< RequestState< T > >( {
		data: null,
		loading: true,
		error: null,
	} );

	useEffect( () => {
		const controller = new AbortController();
		setState( ( current ) => ( {
			...current,
			loading: true,
			error: null,
		} ) );
		loader.current( controller.signal ).then(
			( data ) => {
				if ( ! controller.signal.aborted ) {
					setState( { data, loading: false, error: null } );
				}
			},
			( failure: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setState( ( current ) => ( {
						...current,
						loading: false,
						error: normalizeApiError( failure ),
					} ) );
				}
			}
		);
		return () => controller.abort();
	}, [ key, version ] );

	const reload = useCallback( () => setVersion( ( v ) => v + 1 ), [] );
	return { ...state, reload };
}
