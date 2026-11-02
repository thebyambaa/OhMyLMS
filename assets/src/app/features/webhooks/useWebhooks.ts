import { useEffect, useState } from '@wordpress/element';
import { useListState } from '../../components/listQuery';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getOptions, listWebhooks } from './api';
import type { Webhook, WebhookOptions } from './types';

const NO_FILTERS = { search: '' };

/**
 * Page-owned state, with the search and page in the address; obsolete requests cannot overwrite newer
 * search, pagination or mutations.
 */
export function useWebhooks() {
	const list = useListState( NO_FILTERS, { search: 's' } );
	const { page, setPage } = list;
	const { search } = list.filters;
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< Webhook > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ options, setOptions ] = useState< WebhookOptions | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		Promise.all( [
			listWebhooks( page, search, controller.signal ),
			getOptions( controller.signal ),
		] ).then(
			( [ next, choices ] ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setData( next );
				setOptions( choices );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				const nextError = normalizeApiError( failure );
				if (
					page > 1 &&
					'ohmylms_webhook_invalid_page' === nextError.code
				) {
					setPage( page - 1, { replace: true } );
					return;
				}
				setError( nextError.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ page, search, version ] );
	return {
		data,
		options,
		loading,
		error,
		page,
		searchTerm: search,
		setPage: ( next: number ) => setPage( next ),
		pageHref: list.pageHref,
		search: ( value: string ) => {
			list.setFilters( { search: value } );
			setVersion( ( current ) => current + 1 );
		},
		reload: () => setVersion( ( current ) => current + 1 ),
		afterDelete: () => {
			if ( data.items.length === 1 && page > 1 ) {
				setPage( page - 1 );
			} else {
				setVersion( ( current ) => current + 1 );
			}
		},
	};
}
