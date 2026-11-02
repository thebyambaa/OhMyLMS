import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { normalizeApiError, type ApiError } from '../lib/api';

/** How a failure is reported: its message (`''` when none) or the `ApiError` (`null` when none). */
export type ErrorShape = 'message' | 'object';

export interface RequestOptions< Shape extends ErrorShape = ErrorShape > {
	/**
	 * Keep the last loaded data while the next request runs and after it fails, instead of
	 * clearing it, so a list does not flash empty between pages or searches.
	 */
	keepData?: boolean;
	/**
	 * Serialised inputs of `load`. When given, only a change of `key` (or `reload`) starts a new
	 * request and the latest `load` closure is used, so `load` does not need to be stable.
	 */
	key?: string;
	/** Report failures as their message (default) or as the `ApiError`. */
	errorShape?: Shape;
}

export interface RequestResult< T, E > {
	data: T | null;
	error: E;
	loading: boolean;
	/** Run the request again with the same inputs. */
	reload: () => void;
	/** Same as `reload`. */
	retry: () => void;
	/** Show data from a mutation result without a new request; clears the error. */
	replace: ( data: T ) => void;
}

/**
 * Ignore late responses and cancel transports when their owner changes or unmounts.
 *
 * @param load               Starts the request; without `key`, a new (unstable) function starts a new request.
 * @param options            Request options.
 * @param options.keepData   Keep the last data while reloading and after a failure.
 * @param options.key        Start a new request only when this changes.
 * @param options.errorShape `'message'` (default) or `'object'` for the `ApiError`.
 */
export function useRequest< T >(
	load: ( signal: AbortSignal ) => Promise< T >,
	options: RequestOptions< 'object' > & { errorShape: 'object' }
): RequestResult< T, ApiError | null >;
export function useRequest< T >(
	load: ( signal: AbortSignal ) => Promise< T >,
	options?: RequestOptions< 'message' >
): RequestResult< T, string >;
export function useRequest< T >(
	load: ( signal: AbortSignal ) => Promise< T >,
	{ keepData = false, key, errorShape = 'message' }: RequestOptions = {}
): RequestResult< T, ApiError | null > | RequestResult< T, string > {
	const loader = useRef( load );
	loader.current = load;
	const [ state, setState ] = useState< {
		data: T | null;
		error: ApiError | null;
		loading: boolean;
	} >( { data: null, error: null, loading: true } );
	const [ version, setVersion ] = useState( 0 );
	const trigger = undefined === key ? load : key;
	useEffect( () => {
		const controller = new AbortController();
		setState( ( current ) => ( {
			data: keepData ? current.data : null,
			error: null,
			loading: true,
		} ) );
		loader.current( controller.signal ).then(
			( data ) => {
				if ( ! controller.signal.aborted ) {
					setState( { data, error: null, loading: false } );
				}
			},
			( error ) => {
				if ( ! controller.signal.aborted ) {
					setState( ( current ) => ( {
						data: keepData ? current.data : null,
						error: normalizeApiError( error ),
						loading: false,
					} ) );
				}
			}
		);
		return () => controller.abort();
	}, [ trigger, version, keepData ] );
	const reload = useCallback(
		() => setVersion( ( current ) => current + 1 ),
		[]
	);
	const replace = useCallback(
		( data: T ) =>
			setState( ( current ) => ( { ...current, data, error: null } ) ),
		[]
	);
	const actions = { reload, retry: reload, replace };
	if ( 'object' === errorShape ) {
		return { ...state, ...actions };
	}
	return { ...state, error: state.error?.message ?? '', ...actions };
}
