import { Button, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { ListTable, ListTableSelect } from '../../../ui/ListTable';
import { useRequest } from '../../hooks/useRequest';
import { listSessions } from './api';
import { EMPTY_QUERY, formatStart, providerLabel, stateLabel } from './model';
import type { SessionQuery } from './types';

/** The `/sessions` route: live sessions the user may edit. */
export default function SessionsPage() {
	const [ query, setQuery ] = useState< SessionQuery >( EMPTY_QUERY );
	const [ text, setText ] = useState( '' );
	const load = useCallback(
		( signal: AbortSignal ) => listSessions( query, signal ),
		[ query ]
	);
	const request = useRequest( load );
	const items = request.data?.items ?? [];
	const total = request.data?.total ?? 0;

	return (
		<>
			<p>
				<Link
					to="/sessions/new"
					className="components-button is-primary"
				>
					{ __( 'Schedule a session', 'ohmylms' ) }
				</Link>
			</p>
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
					onFilter={ () =>
						setQuery( ( current ) => ( {
							...current,
							search: text,
							page: 1,
						} ) )
					}
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
								setQuery( ( current ) => ( {
									...current,
									provider:
										value as SessionQuery[ 'provider' ],
									page: 1,
								} ) )
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
						onChange: ( page ) =>
							setQuery( ( current ) => ( { ...current, page } ) ),
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
