import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { useWidget } from '../shell/useWidget';
import { WidgetCard } from '../shell/WidgetCard';
import { parseInsights } from './InsightsModel';
import type { InsightSeverity } from './InsightsModel';
import type { DashboardWidgetProps } from './registry';
import './InsightsWidget.css';

function severityLabel( severity: InsightSeverity ): string {
	switch ( severity ) {
		case 'error':
			return __( 'Needs action', 'ohmylms' );
		case 'warning':
			return __( 'Worth a look', 'ohmylms' );
		case 'success':
			return __( 'Good news', 'ohmylms' );
		default:
			return __( 'For information', 'ohmylms' );
	}
}

/**
 * "Insights": three to five sentences from `GET /dashboard/widgets/insights`, written by fixed rules over
 * the dashboard's own figures (no AI). Each is marked by severity (colour and a hidden label) and may link
 * to the screen that helps.
 *
 * @param props         Component props.
 * @param props.context Scope and period.
 */
export default function InsightsWidget( { context }: DashboardWidgetProps ) {
	const request = useWidget( 'insights', context, parseInsights );
	const data = request.data;
	return (
		<WidgetCard
			id="insights"
			title={ __( 'Insights', 'ohmylms' ) }
			meta={ __( 'written from your data by fixed rules', 'ohmylms' ) }
			loading={ request.loading }
			error={ request.error }
			onRetry={ request.reload }
			empty={
				data && 0 === data.insights.length
					? __(
							'Nothing stands out in this period. Insights appear when a figure crosses one of the documented thresholds.',
							'ohmylms'
						)
					: false
			}
		>
			{ data && data.insights.length > 0 && (
				<ul className="ohmylms-insights">
					{ data.insights.map( ( insight ) => (
						<li
							key={ insight.key }
							className={ `ohmylms-insights__item is-${ insight.severity }` }
						>
							<span className="screen-reader-text">
								{ `${ severityLabel( insight.severity ) }: ` }
							</span>
							{ insight.text }
							{ insight.action && (
								<>
									{ ' ' }
									<Link
										to={ insight.action.route }
										className="ohmylms-insights__action"
									>
										{ insight.action.label }
										<span aria-hidden="true"> →</span>
									</Link>
								</>
							) }
						</li>
					) ) }
				</ul>
			) }
		</WidgetCard>
	);
}
