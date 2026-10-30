import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { CourseList } from './CourseList';
import { EarningsChart } from './EarningsChart';
import { useDashboard, useRange } from './hooks';
import {
	buildCards,
	describeRange,
	formatNumber,
	rangeToSearch,
} from './model';
import { RangeFilter } from './RangeFilter';
import { RequestStatus } from './RequestStatus';
import { SummaryCards } from './SummaryCards';

/** The `/dashboard` route: summary cards, earnings chart and course lists for a date range. */
export default function DashboardPage() {
	const { range, setRange } = useRange();
	const request = useDashboard( range );
	const data = request.data;
	const search = new URLSearchParams( rangeToSearch( range ) ).toString();

	return (
		<>
			<h2>{ __( 'Dashboard', 'ohmylms' ) }</h2>
			<RangeFilter value={ range } onChange={ setRange } />
			<RequestStatus
				loading={ request.loading }
				error={ request.error }
				label={ __( 'Loading dashboard…', 'ohmylms' ) }
				onRetry={ request.reload }
			/>
			{ data && (
				<div aria-busy={ request.loading }>
					<p>
						{ sprintf(
							/* translators: %s: date range. */
							__(
								'Showing %s, compared with the period before.',
								'ohmylms'
							),
							describeRange( data.range )
						) }
					</p>
					<SummaryCards
						cards={ buildCards( data.summary, data.currency ) }
					/>
					<p>
						{ sprintf(
							/* translators: 1: total courses, 2: published courses, 3: students. */
							__(
								'%1$s courses (%2$s published) and %3$s students in total.',
								'ohmylms'
							),
							formatNumber( data.totals.courses ),
							formatNumber( data.totals.published_courses ),
							formatNumber( data.totals.students )
						) }
					</p>
					<EarningsChart
						title={ __( 'Earnings', 'ohmylms' ) }
						series={ data.series }
						currency={ data.currency }
						granularity={ data.range.granularity }
					/>
					<p>
						<Link
							to={ `/earnings${ search ? `?${ search }` : '' }` }
						>
							{ __( 'View the earnings report', 'ohmylms' ) }
						</Link>
					</p>
					<CourseList
						caption={ __( 'Recent courses', 'ohmylms' ) }
						courses={ data.recent_courses }
						currency={ data.currency }
						empty={ __( 'No courses yet.', 'ohmylms' ) }
					/>
					<CourseList
						caption={ __( 'Popular courses', 'ohmylms' ) }
						courses={ data.popular_courses }
						currency={ data.currency }
						empty={ __(
							'No students enrolled in this period.',
							'ohmylms'
						) }
					/>
				</div>
			) }
		</>
	);
}
