import { useEffect, useState } from '@wordpress/element';
import { normalizeApiError } from '../lib/api';

export interface RequestOptions {
	/**
	 * Keep the last loaded data while the next request runs and after it fails, instead of
	 * clearing it, so a list does not flash empty between pages or searches.
	 */
	keepData?: boolean;
}

/**
 * Ignore late responses and cancel transports when their owner changes or unmounts.
 *
 * @param load             Starts the request; a new (unstable) function starts a new request.
 * @param options          Request options.
 * @param options.keepData Keep the last data while reloading and after a failure.
 */
export function useRequest< T >(
	load: ( signal: AbortSignal ) => Promise< T >,
	{ keepData = false }: RequestOptions = {}
) {
	const [ state, setState ] = useState< {
		data: T | null;
		error: string;
		loading: boolean;
	} >( { data: null, error: '', loading: true } );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		const controller = new AbortController();
		setState( ( current ) => ( {
			data: keepData ? current.data : null,
			error: '',
			loading: true,
		} ) );
		load( controller.signal ).then(
			( data ) => {
				if ( ! controller.signal.aborted ) {
					setState( { data, error: '', loading: false } );
				}
			},
			( error ) => {
				if ( ! controller.signal.aborted ) {
					setState( ( current ) => ( {
						data: keepData ? current.data : null,
						error: normalizeApiError( error ).message,
						loading: false,
					} ) );
				}
			}
		);
		return () => controller.abort();
	}, [ load, version, keepData ] );
	return { ...state, retry: () => setVersion( ( current ) => current + 1 ) };
}
