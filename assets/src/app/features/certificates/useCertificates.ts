import { useEffect, useState } from '@wordpress/element';
import { useListState } from '../../components/listQuery';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listCertificates } from './api';
import type { Certificate } from './types';

const NO_FILTERS = { search: '', status: 'all' };

/**
 * List state for the template list, kept in the address (`&status=…&s=…&paged=…`). Obsolete requests
 * are cancelled and their results ignored, so a slow earlier search can never overwrite a newer one.
 *
 * @return Items, loading and error state, and the actions that change the query.
 */
export function useCertificates() {
	const list = useListState( NO_FILTERS, { search: 's' } );
	const { page, setPage } = list;
	const { search, status } = list.filters;
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< Certificate > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		listCertificates( page, search, status, controller.signal ).then(
			( next ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setData( next );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				const problem = normalizeApiError( failure );
				if (
					page > 1 &&
					'ohmylms_certificate_invalid_page' === problem.code
				) {
					setPage( page - 1, { replace: true } );
					return;
				}
				setError( problem.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ page, search, status, version ] );
	const reload = () => setVersion( ( current ) => current + 1 );
	return {
		data,
		loading,
		error,
		page,
		status,
		searchTerm: search,
		setPage: ( next: number ) => setPage( next ),
		pageHref: list.pageHref,
		statusHref: ( value: string ) =>
			list.filtersHref( { ...list.filters, status: value } ),
		setStatus: ( value: string ) =>
			list.setFilters( { ...list.filters, status: value } ),
		search: ( value: string ) => {
			if ( value === search && 1 === page ) {
				reload();
			}
			list.setFilters( { ...list.filters, search: value } );
		},
		reload,
		afterDelete: () => {
			if ( data.items.length === 1 && page > 1 ) {
				setPage( page - 1 );
			} else {
				reload();
			}
		},
	};
}
