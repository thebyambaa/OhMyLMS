import { Table } from '../../../ui/Table';
import { Button, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
import { useRequest } from '../../hooks/useRequest';
import { listVersions } from './api';
import { formatDateTime } from './model';
import type { QuestionVersion } from './types';

function flags( version: QuestionVersion ): string {
	const labels: string[] = [];
	if ( version.is_current ) {
		labels.push( __( 'Current', 'ohmylms' ) );
	}
	if ( version.is_approved ) {
		labels.push( __( 'Approved', 'ohmylms' ) );
	}
	return labels.join( ', ' ) || '—';
}

/**
 * Saved versions of a question, newest first (read only). Remount it (change its key) to reload.
 *
 * @param props    Component props.
 * @param props.id Question id.
 */
export function Versions( { id }: { id: number } ) {
	const [ page, setPage ] = useState( 1 );
	const load = useCallback(
		( signal: AbortSignal ) => listVersions( id, page, signal ),
		[ id, page ]
	);
	const versions = useRequest( load );
	return (
		<section aria-labelledby="oml-question-versions">
			<h3 id="oml-question-versions">{ __( 'Versions', 'ohmylms' ) }</h3>
			{ versions.loading && (
				<p role="status">{ __( 'Loading versions…', 'ohmylms' ) }</p>
			) }
			{ versions.error && (
				<Notice status="error" isDismissible={ false }>
					{ versions.error }{ ' ' }
					<Button variant="link" onClick={ versions.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ versions.data &&
				( versions.data.items.length ? (
					<>
						<Table caption={ __( 'Versions', 'ohmylms' ) } compact>
							<thead>
								<tr>
									{ [
										__( 'Version', 'ohmylms' ),
										__( 'Created', 'ohmylms' ),
										__( 'By', 'ohmylms' ),
										__( 'State', 'ohmylms' ),
									].map( ( title ) => (
										<th key={ title } scope="col">
											{ title }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ versions.data.items.map( ( version ) => (
									<tr key={ version.id }>
										<th scope="row">
											v{ version.version_no }
										</th>
										<td>
											{ formatDateTime(
												version.created_at
											) }
										</td>
										<td>
											{ version.created_by.name || '—' }
										</td>
										<td>{ flags( version ) }</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						{ versions.data.totalPages > 1 && (
							<Pagination
								page={ page }
								pages={ versions.data.totalPages }
								busy={ versions.loading }
								onChange={ setPage }
							/>
						) }
					</>
				) : (
					<p>
						{ __(
							'No versions yet. A version is kept each time the question is saved.',
							'ohmylms'
						) }
					</p>
				) ) }
		</section>
	);
}
