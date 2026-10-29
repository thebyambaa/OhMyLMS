import { Table } from '../../../ui/Table';
import { Modal, Notice, Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listDeliveries } from './api';
import { Pagination } from '../../components/Pagination';
import type { Delivery, Webhook } from './types';

export function Deliveries( {
	item,
	onClose,
}: {
	item: Webhook;
	onClose: () => void;
} ) {
	const [ page, setPage ] = useState( 1 );
	const [ retry, setRetry ] = useState( 0 );
	const [ data, setData ] = useState< Collection< Delivery > >( {
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
		listDeliveries( item.id, page, controller.signal ).then(
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
				setError( normalizeApiError( failure ).message );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ item.id, page, retry ] );
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: webhook name. */
				__( 'Deliveries for %s', 'ohmylms' ),
				item.name
			) }
			onRequestClose={ onClose }
		>
			{ loading && (
				<p role="status">{ __( 'Loading deliveries…', 'ohmylms' ) }</p>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }{ ' ' }
					<Button
						variant="link"
						onClick={ () => setRetry( retry + 1 ) }
					>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ! loading &&
				! error &&
				( data.items.length ? (
					<>
						<Table
							caption={ __(
								'Recent delivery attempts',
								'ohmylms'
							) }
							striped
						>
							<thead>
								<tr>
									{ [
										__( 'Date', 'ohmylms' ),
										__( 'Result', 'ohmylms' ),
										__( 'HTTP status', 'ohmylms' ),
										__( 'Details', 'ohmylms' ),
									].map( ( title ) => (
										<th scope="col" key={ title }>
											{ title }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ data.items.map( ( row ) => (
									<tr key={ row.id }>
										<td>{ row.created_at }</td>
										<td>
											{ 'success' === row.status
												? __( 'Succeeded', 'ohmylms' )
												: __( 'Failed', 'ohmylms' ) }
										</td>
										<td>{ row.response_code || '—' }</td>
										<td>
											{ row.error_message ||
												row.delivery_id }
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ page }
							pages={ data.totalPages }
							busy={ loading }
							onChange={ setPage }
						/>
					</>
				) : (
					<p>{ __( 'No deliveries yet.', 'ohmylms' ) }</p>
				) ) }
			<Button variant="secondary" onClick={ onClose }>
				{ __( 'Close', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
