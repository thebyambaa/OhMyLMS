import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';

/**
 * Serialise write requests: only one runs at a time, so a double click or a second button cannot
 * submit twice. The running request is cancelled on unmount and its late result is ignored.
 */
export function useOperation() {
	const active = useRef< AbortController | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => () => active.current?.abort(), [] );

	const run = useCallback(
		async (
			work: ( signal: AbortSignal ) => Promise< void >
		): Promise< boolean > => {
			if ( active.current ) {
				return false;
			}
			const controller = new AbortController();
			active.current = controller;
			setBusy( true );
			setError( null );
			try {
				await work( controller.signal );
				return ! controller.signal.aborted;
			} catch ( failure ) {
				if ( ! controller.signal.aborted ) {
					setError( normalizeApiError( failure ).message );
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
	return { busy, error, run, clearError };
}
