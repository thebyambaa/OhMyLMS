import { Button, Notice } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { ListTable } from '../../../ui/ListTable';
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
 * Content Hub overview (Courses › Overview): every content area the user may open with its counts, and
 * the most recently changed courses, lessons, quizzes, exams, questions and assignments, as WordPress
 * list tables.
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
					<h2>{ __( 'Content areas', 'ohmylms' ) }</h2>
					<ListTable
						caption={ __( 'Content areas', 'ohmylms' ) }
						footer={ false }
						rows={ rows }
						rowKey={ ( row ) => row.id }
						columns={ [
							{
								id: 'area',
								label: __( 'Area', 'ohmylms' ),
								primary: true,
								render: ( row ) => (
									<strong>
										<Link
											className="row-title"
											to={ row.path }
										>
											{ areaLabel( row.id ) }
										</Link>
									</strong>
								),
							},
							{
								id: 'items',
								label: __( 'Items', 'ohmylms' ),
								className: 'num',
								render: ( row ) => row.summary.total,
							},
							{
								id: 'details',
								label: __( 'Details', 'ohmylms' ),
								render: ( row ) =>
									countPhrases( row.summary ).join( ', ' ) ||
									'—',
							},
							{
								id: 'reports',
								label: __( 'Reports', 'ohmylms' ),
								render: ( row ) =>
									'quizzes' === row.id ? (
										<Link to="/reports/quizzes">
											{ __( 'Quiz reports', 'ohmylms' ) }
										</Link>
									) : (
										'—'
									),
							},
						] }
					/>
					<h2>{ __( 'Recently changed', 'ohmylms' ) }</h2>
					<ListTable
						caption={ __( 'Recently changed content', 'ohmylms' ) }
						footer={ false }
						rows={ recent }
						rowKey={ ( item ) => `${ item.area }-${ item.id }` }
						emptyMessage={ __(
							'Nothing has been changed yet.',
							'ohmylms'
						) }
						pagination={ {
							page: 1,
							totalPages: 1,
							totalItems: recent.length,
							itemsLabel: sprintf(
								/* translators: %d: number of items. */
								_n(
									'%d item shown.',
									'%d items shown.',
									recent.length,
									'ohmylms'
								),
								recent.length
							),
							onChange: () => undefined,
						} }
						columns={ [
							{
								id: 'title',
								label: __( 'Title', 'ohmylms' ),
								primary: true,
								render: ( item ) => (
									<strong>
										<Link
											className="row-title"
											to={ recentPath( item ) }
										>
											{ item.title ||
												__( '(no title)', 'ohmylms' ) }
										</Link>
									</strong>
								),
							},
							{
								id: 'area',
								label: __( 'Area', 'ohmylms' ),
								render: ( item ) => areaLabel( item.area ),
							},
							{
								id: 'status',
								label: __( 'Status', 'ohmylms' ),
								render: ( item ) => statusLabel( item.status ),
							},
							{
								id: 'date',
								label: __( 'Updated', 'ohmylms' ),
								render: ( item ) =>
									formatTime( item.updated_at ),
							},
						] }
					/>
				</>
			) }
		</>
	);
}
