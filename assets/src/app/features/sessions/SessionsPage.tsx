import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { ListTable, ListTableSelect } from '../../../ui/ListTable';
import { useAddressQuery } from '../../components/listQuery';
import { useRequest } from '../../hooks/useRequest';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { listSessions } from './api';
import { EMPTY_QUERY, formatStart, providerLabel, stateLabel } from './model';
import type { SessionQuery } from './types';

/**
 * The `/sessions` route: live sessions the user may edit, with search, provider and page in the
 * address.
 */
export default function SessionsPage() {
	const address = useAddressQuery( EMPTY_QUERY, { search: 's' } );
	const { query } = address;
	const [ text, setText ] = useState( query.search );
	useEffect( () => setText( query.search ), [ query.search ] );
	const load = useCallback(
		( signal: AbortSignal ) => listSessions( query, signal ),
		[ query ]
	);
	const request = useRequest( load );
	const items = request.data?.items ?? [];
	const total = request.data?.total ?? 0;

	return (
		<>
			<PageTitleActions>
				<PageTitleAction to="/sessions/new">
					{ __( 'Schedule a session', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }{ ' ' }
					<Button variant="link" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.loading && (
				<p role="status">{ __( 'Loading sessions…', 'ohmylms' ) }</p>
			) }
			{ ! request.error && ( ! request.loading || items.length > 0 ) && (
				<ListTable
					caption={ __( 'Live sessions', 'ohmylms' ) }
					heading={ __( 'Live sessions list', 'ohmylms' ) }
					formLabel={ __( 'Search sessions', 'ohmylms' ) }
					busy={ request.loading }
					rows={ items }
					rowKey={ ( session ) => session.id }
					emptyMessage={ __( 'No sessions found.', 'ohmylms' ) }
					search={ {
						label: __( 'Search sessions', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: text,
						onChange: setText,
					} }
					onFilter={ () => address.setFilters( { search: text } ) }
					filterLabel={ null }
					filters={
						<ListTableSelect
							label={ __( 'Provider', 'ohmylms' ) }
							value={ query.provider }
							options={ [
								{
									value: 'all',
									label: __( 'All providers', 'ohmylms' ),
								},
								{
									value: 'zoom',
									label: providerLabel( 'zoom' ),
								},
								{
									value: 'googlemeet',
									label: providerLabel( 'googlemeet' ),
								},
							] }
							onChange={ ( value ) =>
								address.setFilters( {
									provider:
										value as SessionQuery[ 'provider' ],
								} )
							}
						/>
					}
					pagination={ {
						page: query.page,
						totalPages: request.data?.totalPages ?? 1,
						totalItems: total,
						itemsLabel: sprintf(
							/* translators: %d: number of sessions. */
							_n(
								'%d session found.',
								'%d sessions found.',
								total,
								'ohmylms'
							),
							total
						),
						onChange: ( page ) => address.setPage( page ),
						href: address.pageHref,
					} }
					columns={ [
						{
							id: 'session',
							label: __( 'Session', 'ohmylms' ),
							primary: true,
							render: ( session ) => (
								<>
									<strong>
										<Link
											className="row-title"
											to={ `/sessions/${ session.id }` }
										>
											{ session.title }
										</Link>
										{ 'draft' === session.status &&
											` · ${ __( 'Draft', 'ohmylms' ) }` }
									</strong>
								</>
							),
						},
						{
							id: 'starts',
							label: __( 'Starts', 'ohmylms' ),
							render: ( session ) =>
								formatStart( session.start, session.timezone ),
						},
						{
							id: 'provider',
							label: __( 'Provider', 'ohmylms' ),
							render: ( session ) =>
								providerLabel( session.provider ),
						},
						{
							id: 'course',
							label: __( 'Course', 'ohmylms' ),
							render: ( session ) => session.course?.title ?? '—',
						},
						{
							id: 'state',
							label: __( 'State', 'ohmylms' ),
							render: ( session ) => stateLabel( session.state ),
						},
						{
							id: 'meeting',
							label: __( 'Meeting', 'ohmylms' ),
							render: ( session ) =>
								session.meeting.join_url ? (
									<a
										href={ session.meeting.join_url }
										target="_blank"
										rel="noopener noreferrer"
									>
										{ __( 'Join link', 'ohmylms' ) }
									</a>
								) : (
									__( 'Not created', 'ohmylms' )
								),
						},
					] }
					rowActions={ ( session ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							render: (
								<Link to={ `/sessions/${ session.id }` }>
									{ __( 'Edit', 'ohmylms' ) }
								</Link>
							),
						},
					] }
				/>
			) }
		</>
	);
}
