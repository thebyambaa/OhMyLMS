import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { normalizeApiError, type ApiError } from '../lib/api';
import type { ErrorShape } from './useRequest';

export interface OperationOptions< Shape extends ErrorShape = ErrorShape > {
	/** Report failures as their message (default, `''` when none) or as the `ApiError` (`null` when none). */
	errorShape?: Shape;
}

export interface Operation< E > {
	busy: boolean;
	error: E;
	/**
	 * Run `work` unless another operation is running.
	 *
	 * @return True when `work` finished and its owner is still mounted.
	 */
	run: < T >(
		work: ( signal: AbortSignal ) => Promise< T >,
		success?: ( result: T ) => void
	) => Promise< boolean >;
	clearError: () => void;
}

/**
 * One owned mutation at a time; late completion never updates an unmounted feature.
 *
 * @param options            Operation options.
 * @param options.errorShape `'message'` (default) or `'object'` for the `ApiError`.
 */
export function useOperation(
	options: OperationOptions< 'object' > & { errorShape: 'object' }
): Operation< ApiError | null >;
export function useOperation(
	options?: OperationOptions< 'message' >
): Operation< string >;
export function useOperation( {
	errorShape = 'message',
}: OperationOptions = {} ): Operation< ApiError | null > | Operation< string > {
	const active = useRef< AbortController | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< ApiError | null >( null );
	useEffect( () => () => active.current?.abort(), [] );
	const run = useCallback(
		async < T >(
			work: ( signal: AbortSignal ) => Promise< T >,
			success?: ( result: T ) => void
		): Promise< boolean > => {
			if ( active.current ) {
				return false;
			}
			const controller = new AbortController();
			active.current = controller;
			setBusy( true );
			setError( null );
			try {
				const result = await work( controller.signal );
				if ( controller.signal.aborted ) {
					return false;
				}
				success?.( result );
				return true;
			} catch ( failure ) {
				if ( ! controller.signal.aborted ) {
					setError( normalizeApiError( failure ) );
				}
				return false;
			} finally {
				if ( ! controller.signal.aborted ) {
					active.current = null;
					setBusy( false );
				}
			}
		},
		[]
	);
	const clearError = useCallback( () => setError( null ), [] );
	if ( 'object' === errorShape ) {
		return { busy, error, run, clearError };
	}
	return { busy, error: error?.message ?? '', run, clearError };
}
