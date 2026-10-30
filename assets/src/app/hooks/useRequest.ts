import { useEffect, useState } from '@wordpress/element';
import { normalizeApiError } from '../lib/api';

/**
 * Ignore late responses and cancel transports when their owner changes or unmounts.
 * @param load
 */
export function useRequest< T >(
	load: ( signal: AbortSignal ) => Promise< T >
) {
	const [ state, setState ] = useState< {
		data: T | null;
		error: string;
		loading: boolean;
	} >( { data: null, error: '', loading: true } );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		const controller = new AbortController();
		setState( { data: null, error: '', loading: true } );
		load( controller.signal ).then(
			( data ) => {
				if ( ! controller.signal.aborted ) {
					setState( { data, error: '', loading: false } );
				}
			},
			( error ) => {
				if ( ! controller.signal.aborted ) {
					setState( {
						data: null,
						error: normalizeApiError( error ).message,
						loading: false,
					} );
				}
			}
		);
		return () => controller.abort();
	}, [ load, version ] );
	return { ...state, retry: () => setVersion( ( current ) => current + 1 ) };
}
