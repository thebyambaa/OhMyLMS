import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { CourseList } from '../CourseList';
import { EarningsChart } from '../EarningsChart';
import { useDashboard } from '../hooks';
import {
	buildCards,
	describeRange,
	formatNumber,
	rangeToSearch,
} from '../model';
import { WidgetCard } from '../shell/WidgetCard';
import { SummaryCards } from '../SummaryCards';
import type { PeriodDays } from '../shell/model';
import type { RangePreset, RangeSelection } from '../types';
import type { DashboardWidgetProps } from './registry';

const PRESETS: Record< PeriodDays, RangePreset > = {
	7: 'last_7_days',
	30: 'last_30_days',
	90: 'last_90_days',
};

/**
 * The site administrators' overview (`GET /dashboard`, docs/api/analytics.md): earnings cards with the
 * change from the previous period, the earnings chart and the recent and popular courses. Only in the
 * all-schools scope, for the dashboard's period.
 *
 * @param props         Component props.
 * @param props.context Scope and period.
 */
export default function OverviewWidget( { context }: DashboardWidgetProps ) {
	const range: RangeSelection = {
		preset: PRESETS[ context.period ],
		start: '',
		end: '',
	};
	const request = useDashboard( range );
	const data = request.data;
	const search = new URLSearchParams( rangeToSearch( range ) ).toString();

	return (
		<WidgetCard
			id="overview"
			title={ __( 'Earnings and courses', 'ohmylms' ) }
			meta={ data ? describeRange( data.range ) : undefined }
			loading={ request.loading }
			error={ request.error }
			onRetry={ request.reload }
		>
			{ data && (
				<>
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
						<Link to={ `/earnings?${ search }` }>
							{ __( 'View the earnings report', 'ohmylms' ) }
						</Link>
					</p>
					<div className="ohmylms-dashboard-columns">
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
				</>
			) }
		</WidgetCard>
	);
}
