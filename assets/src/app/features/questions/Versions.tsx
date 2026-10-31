import { ListTable } from '../../../ui/ListTable';
import { Button, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
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
			{ versions.data && (
				<ListTable< QuestionVersion >
					caption={ __( 'Versions', 'ohmylms' ) }
					busy={ versions.loading }
					footer={ false }
					rows={ versions.data.items }
					rowKey={ ( version ) => version.id }
					emptyMessage={ __(
						'No versions yet. A version is kept each time the question is saved.',
						'ohmylms'
					) }
					columns={ [
						{
							id: 'version',
							label: __( 'Version', 'ohmylms' ),
							primary: true,
							render: ( version ) => `v${ version.version_no }`,
						},
						{
							id: 'created',
							label: __( 'Created', 'ohmylms' ),
							render: ( version ) =>
								formatDateTime( version.created_at ),
						},
						{
							id: 'author',
							label: __( 'By', 'ohmylms' ),
							render: ( version ) =>
								version.created_by.name || '—',
						},
						{
							id: 'state',
							label: __( 'State', 'ohmylms' ),
							render: flags,
						},
					] }
					pagination={
						versions.data.totalPages > 1
							? {
									page,
									totalPages: versions.data.totalPages,
									onChange: setPage,
								}
							: undefined
					}
				/>
			) }
		</section>
	);
}
