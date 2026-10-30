import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Table } from '../../../ui/Table';
import { Pagination } from '../../components/Pagination';
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
			<form
				role="search"
				aria-label={ __( 'Search sessions', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setQuery( ( current ) => ( {
						...current,
						search: text,
						page: 1,
					} ) );
				} }
			>
				<TextControl
					label={ __( 'Search sessions', 'ohmylms' ) }
					value={ text }
					onChange={ setText }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ request.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			<SelectControl
				label={ __( 'Provider', 'ohmylms' ) }
				value={ query.provider }
				options={ [
					{ value: 'all', label: __( 'All providers', 'ohmylms' ) },
					{ value: 'zoom', label: providerLabel( 'zoom' ) },
					{
						value: 'googlemeet',
						label: providerLabel( 'googlemeet' ),
					},
				] }
				onChange={ ( value ) =>
					setQuery( ( current ) => ( {
						...current,
						provider: value as SessionQuery[ 'provider' ],
						page: 1,
					} ) )
				}
			/>
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
			{ ! request.loading && ! request.error && ! items.length && (
				<p>{ __( 'No sessions found.', 'ohmylms' ) }</p>
			) }
			{ items.length > 0 && (
				<>
					<p role="status">
						{ sprintf(
							/* translators: %d: number of sessions. */
							_n(
								'%d session found.',
								'%d sessions found.',
								total,
								'ohmylms'
							),
							total
						) }
					</p>
					<Table caption={ __( 'Live sessions', 'ohmylms' ) } striped>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Session', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Starts', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Provider', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Course', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'State', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Meeting', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ items.map( ( session ) => (
								<tr key={ session.id }>
									<th scope="row">
										<Link
											to={ `/sessions/${ session.id }` }
										>
											{ session.title }
										</Link>
										{ 'draft' === session.status &&
											` · ${ __( 'Draft', 'ohmylms' ) }` }
									</th>
									<td>
										{ formatStart(
											session.start,
											session.timezone
										) }
									</td>
									<td>
										{ providerLabel( session.provider ) }
									</td>
									<td>{ session.course?.title ?? '—' }</td>
									<td>{ stateLabel( session.state ) }</td>
									<td>
										{ session.meeting.join_url ? (
											<a
												href={
													session.meeting.join_url
												}
												target="_blank"
												rel="noopener noreferrer"
											>
												{ __( 'Join link', 'ohmylms' ) }
											</a>
										) : (
											__( 'Not created', 'ohmylms' )
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</Table>
					<Pagination
						page={ query.page }
						pages={ request.data?.totalPages ?? 1 }
						busy={ request.loading }
						onChange={ ( page ) =>
							setQuery( ( current ) => ( { ...current, page } ) )
						}
					/>
				</>
			) }
		</>
	);
}
