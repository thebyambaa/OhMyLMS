import { __, sprintf } from '@wordpress/i18n';
import { useWidget } from '../shell/useWidget';
import { WidgetCard } from '../shell/WidgetCard';
import { parseActivity, timeAgo } from './ActivityModel';
import type { DashboardWidgetProps } from './registry';
import './ActivityWidget.css';

/**
 * "Happening now": the scope's latest learner events (enrolments, completions, quizzes, submissions and,
 * for all schools, paid orders) from `GET /dashboard/widgets/activity`, newest first. Learners appear by
 * display name only.
 *
 * @param props         Component props.
 * @param props.context Scope and period.
 */
export default function ActivityWidget( { context }: DashboardWidgetProps ) {
	const request = useWidget( 'activity', context, parseActivity );
	const data = request.data;
	return (
		<WidgetCard
			id="activity"
			title={ __( 'Happening now', 'ohmylms' ) }
			meta={
				data
					? sprintf(
							/* translators: %d: number of events. */
							__( 'latest %d events', 'ohmylms' ),
							data.limit
						)
					: undefined
			}
			loading={ request.loading }
			error={ request.error }
			onRetry={ request.reload }
			empty={
				data && 0 === data.events.length
					? __( 'No learner activity in this period yet.', 'ohmylms' )
					: false
			}
		>
			{ data && data.events.length > 0 && (
				<ul className="ohmylms-dash-activity">
					{ data.events.map( ( event ) => (
						<li
							key={ event.id }
							className={ `ohmylms-dash-activity__event is-${ event.type }` }
						>
							<span className="ohmylms-dash-activity__text">
								{ event.text }
							</span>
							<time
								className="ohmylms-dash-activity__time"
								dateTime={ event.at }
							>
								{ timeAgo( event.at ) }
							</time>
						</li>
					) ) }
				</ul>
			) }
		</WidgetCard>
	);
}
