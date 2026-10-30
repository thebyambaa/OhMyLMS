import { useEffect, useRef, useState } from '@wordpress/element';
import { normalizeApiError } from '../lib/api';

/** One owned mutation at a time; late completion never updates an unmounted feature. */
export function useOperation() {
	const active = useRef< AbortController | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	useEffect( () => () => active.current?.abort(), [] );
	async function run< T >(
		work: ( signal: AbortSignal ) => Promise< T >,
		success: ( result: T ) => void
	): Promise< boolean > {
		if ( active.current ) {
			return false;
		}
		const controller = new AbortController();
		active.current = controller;
		setBusy( true );
		setError( '' );
		try {
			const result = await work( controller.signal );
			if ( controller.signal.aborted ) {
				return false;
			}
			success( result );
			return true;
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
	}
	return { busy, error, run, clearError: () => setError( '' ) };
}
