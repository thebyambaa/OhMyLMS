import { useCallback, useEffect, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';
import { getIntegrationSettings, listIntegrations } from './api';
import { replaceIntegration, toLoadError } from './model';
import type { Integration, IntegrationSettings, LoadError } from './types';

/**
 * Loads the integration list. A newer load or unmount cancels the previous request, and a response that
 * arrives after cancellation is ignored.
 *
 * @return The list, loading and error state, `reload`, and `replace` for applying a mutation result.
 */
export function useIntegrations() {
	const [ items, setItems ] = useState< Integration[] >( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< LoadError | null >( null );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		listIntegrations( controller.signal ).then(
			( next ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setItems( next );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setError( toLoadError( normalizeApiError( failure ) ) );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ version ] );
	return {
		items,
		loading,
		error,
		reload: useCallback( () => setVersion( ( n ) => n + 1 ), [] ),
		replace: useCallback(
			( next: Integration ) =>
				setItems( ( current ) => replaceIntegration( current, next ) ),
			[]
		),
	};
}

/**
 * Loads one integration's credential settings.
 *
 * @param id Integration id.
 * @return Settings (never containing secrets), loading and error state, and `reload`.
 */
export function useIntegrationSettings( id: string ) {
	const [ settings, setSettings ] = useState< IntegrationSettings | null >(
		null
	);
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< LoadError | null >( null );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		getIntegrationSettings( id, controller.signal ).then(
			( next ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setSettings( next );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setError( toLoadError( normalizeApiError( failure ) ) );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ id, version ] );
	return {
		settings,
		setSettings,
		loading,
		error,
		reload: useCallback( () => setVersion( ( n ) => n + 1 ), [] ),
	};
}
