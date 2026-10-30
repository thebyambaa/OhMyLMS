import { useEffect, useState } from '@wordpress/element';
import { ApiError, normalizeApiError } from '../../lib/api';
import { getMembership, getOptions } from './api';
import type { MembershipDetail, MembershipOptions } from './types';

/**
 * Load one plan and the server options for the editor. Switching plans or unmounting cancels the
 * requests and ignores late results.
 *
 * @param id Plan id, or `null` when the route id is invalid (nothing is requested).
 * @return The loaded data, loading and error state, a reload action and a setter for the saved copy.
 */
export function useMembershipEditor( id: number | null ) {
	const [ plan, setPlan ] = useState< MembershipDetail | null >( null );
	const [ options, setOptions ] = useState< MembershipOptions | null >(
		null
	);
	const [ loading, setLoading ] = useState( null !== id );
	const [ error, setError ] = useState< ApiError | null >( null );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		if ( null === id ) {
			return undefined;
		}
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		Promise.all( [
			getMembership( id, controller.signal ),
			getOptions( controller.signal ),
		] ).then(
			( [ loaded, choices ] ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setPlan( loaded );
				setOptions( choices );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setError( normalizeApiError( failure ) );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ id, version ] );
	return {
		plan,
		options,
		loading,
		error,
		setPlan,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}
