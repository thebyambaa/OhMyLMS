import { Button, Notice } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Table } from '../../../ui/Table';
import { useBootstrap } from '../../bootstrap/BootstrapContext';
import { hasCapability } from '../../bootstrap/config';
import { useRequest } from '../../hooks/useRequest';
import {
	AREAS,
	areaLabel,
	countPhrases,
	formatTime,
	recentPath,
	statusLabel,
} from './model';
import { getOverview } from './overviewApi';

/**
 * Content Hub overview (`#/content-hub`): every content area the user may open with its counts, and the
 * most recently changed courses, lessons, quizzes, exams, questions and assignments.
 */
export default function OverviewPage() {
	const { user } = useBootstrap();
	const request = useRequest( getOverview );
	const sent = new Map(
		( request.data?.areas ?? [] ).map( ( area ) => [ area.id, area ] )
	);
	const rows = AREAS.filter(
		( area ) =>
			sent.has( area.id ) &&
			hasCapability( user.capabilities, area.capability )
	).map( ( area ) => ( { ...area, summary: sent.get( area.id )! } ) );
	const areaPath = new Map( rows.map( ( row ) => [ row.id, row.path ] ) );
	const recent = ( request.data?.recent ?? [] ).filter( ( item ) =>
		areaPath.has( item.area )
	);

	return (
		<>
			<h2>{ __( 'Content Hub', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Courses, lessons, assessments, skills and the curriculum in one place. Open an area to manage it.',
					'ohmylms'
				) }
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
				<p role="status">
					{ __( 'Loading the Content Hub…', 'ohmylms' ) }
				</p>
			) }
			{ request.data && (
				<>
					<Table caption={ __( 'Content areas', 'ohmylms' ) } striped>
						<thead>
							<tr>
								<th scope="col">{ __( 'Area', 'ohmylms' ) }</th>
								<th scope="col">
									{ __( 'Items', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Details', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Reports', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ rows.map( ( row ) => (
								<tr key={ row.id }>
									<th scope="row">
										<Link to={ row.path }>
											{ areaLabel( row.id ) }
										</Link>
									</th>
									<td>{ row.summary.total }</td>
									<td>
										{ countPhrases( row.summary ).join(
											', '
										) || '—' }
									</td>
									<td>
										{ 'quizzes' === row.id ? (
											<Link to="/reports/quizzes">
												{ __(
													'Quiz reports',
													'ohmylms'
												) }
											</Link>
										) : (
											'—'
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</Table>
					<h3>{ __( 'Recently changed', 'ohmylms' ) }</h3>
					{ recent.length ? (
						<>
							<p role="status">
								{ sprintf(
									/* translators: %d: number of items. */
									_n(
										'%d item shown.',
										'%d items shown.',
										recent.length,
										'ohmylms'
									),
									recent.length
								) }
							</p>
							<Table
								caption={ __(
									'Recently changed content',
									'ohmylms'
								) }
								striped
							>
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Title', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Area', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Status', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Updated', 'ohmylms' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ recent.map( ( item ) => (
										<tr
											key={ `${ item.area }-${ item.id }` }
										>
											<th scope="row">
												<Link to={ recentPath( item ) }>
													{ item.title ||
														__(
															'(no title)',
															'ohmylms'
														) }
												</Link>
											</th>
											<td>{ areaLabel( item.area ) }</td>
											<td>
												{ statusLabel( item.status ) }
											</td>
											<td>
												{ formatTime(
													item.updated_at
												) }
											</td>
										</tr>
									) ) }
								</tbody>
							</Table>
						</>
					) : (
						<p>
							{ __( 'Nothing has been changed yet.', 'ohmylms' ) }
						</p>
					) }
				</>
			) }
		</>
	);
}
