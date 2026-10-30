import { useEffect, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';

/**
 * Load a read-only reference list (currencies, countries, states) once per `key`.
 *
 * @param key    Identity of the list; a new key loads again. `null` loads nothing.
 * @param loader Fetches the list; receives the abort signal.
 * @return `items` (empty until loaded), `loading` and an `error` message.
 */
export function useReference< T >(
	key: string | null,
	loader: ( signal: AbortSignal ) => Promise< T[] >
) {
	const [ items, setItems ] = useState< T[] >( [] );
	const [ loading, setLoading ] = useState( null !== key );
	const [ error, setError ] = useState< string | null >( null );
	useEffect( () => {
		if ( null === key ) {
			setItems( [] );
			setLoading( false );
			return undefined;
		}
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		loader( controller.signal ).then(
			( next ) => {
				if ( ! controller.signal.aborted ) {
					setItems( next );
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
		// The loader is identified by `key`; callers pass a fresh closure each render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key ] );
	return { items, loading, error };
}
