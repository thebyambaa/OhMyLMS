import { useEffect, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';

export interface Loaded< T > {
	data: T | null;
	error: string;
	loading: boolean;
	reload: () => void;
}

/**
 * Load data for a key, keeping the previous data on screen while the next request runs (so a grid does
 * not flash on every filter change). Late responses are dropped; requests are cancelled on change.
 *
 * @param key  Changes when the request changes; `null` loads nothing.
 * @param load Performs the request.
 * @return Data, error, loading flag and reload.
 */
export function useLoad< T >(
	key: string | null,
	load: ( signal: AbortSignal ) => Promise< T >
): Loaded< T > {
	const [ state, setState ] = useState< {
		data: T | null;
		error: string;
		loading: boolean;
	} >( { data: null, error: '', loading: null !== key } );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		if ( null === key ) {
			setState( { data: null, error: '', loading: false } );
			return;
		}
		const controller = new AbortController();
		setState( ( current ) => ( { ...current, error: '', loading: true } ) );
		load( controller.signal ).then(
			( data ) => {
				if ( ! controller.signal.aborted ) {
					setState( { data, error: '', loading: false } );
				}
			},
			( error ) => {
				if ( ! controller.signal.aborted ) {
					setState( ( current ) => ( {
						data: current.data,
						error: normalizeApiError( error ).message,
						loading: false,
					} ) );
				}
			}
		);
		return () => controller.abort();
		// `load` is recreated every render; `key` says when the request changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key, version ] );
	return {
		...state,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}
