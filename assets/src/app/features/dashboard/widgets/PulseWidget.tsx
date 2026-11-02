import { __, _n, sprintf } from '@wordpress/i18n';
import {
	describeChange,
	formatChange,
	formatMoney,
	formatNumber,
	percentChange,
} from '../model';
import { useWidget } from '../shell/useWidget';
import { WidgetCard } from '../shell/WidgetCard';
import { parsePulse } from './pulse';
import type { Pulse, PulseMetric, PulseMetricId } from './pulse';
import type { DashboardWidgetProps } from './registry';
import { Sparkline } from './Sparkline';

const LABELS: Record< PulseMetricId, () => { label: string; hint: string } > = {
	revenue: () => ( {
		label: __( 'Net revenue', 'ohmylms' ),
		hint: __( 'Earnings minus refunds', 'ohmylms' ),
	} ),
	active_learners: () => ( {
		label: __( 'Weekly active learners', 'ohmylms' ),
		hint: __(
			'Active in the last 7 days; the line shows each day',
			'ohmylms'
		),
	} ),
	completions: () => ( {
		label: __( 'Course completions', 'ohmylms' ),
		hint: __( 'Courses finished in the period', 'ohmylms' ),
	} ),
	renewals_at_risk: () => ( {
		label: __( 'Renewals at risk', 'ohmylms' ),
		hint: __( 'Failed renewal payments', 'ohmylms' ),
	} ),
};

/** Metrics where a rise is bad news. */
const LOWER_IS_BETTER: readonly PulseMetricId[] = [ 'renewals_at_risk' ];

function display( metric: PulseMetric, data: Pulse, value: number ) {
	return 'money' === metric.format && data.currency
		? formatMoney( value, data.currency )
		: formatNumber( value );
}

/**
 * "Pulse": each metric of `GET /dashboard/widgets/pulse` with its change from the previous period and a
 * daily sparkline. Revenue and renewals only reach site administrators in the all-schools scope.
 *
 * @param props         Component props.
 * @param props.context Scope and period.
 */
export default function PulseWidget( { context }: DashboardWidgetProps ) {
	const request = useWidget( 'pulse', context, parsePulse );
	const data = request.data;
	return (
		<WidgetCard
			id="pulse"
			title={ __( 'Pulse', 'ohmylms' ) }
			meta={ sprintf(
				/* translators: %d: number of days. */
				__( 'last %d days vs the period before', 'ohmylms' ),
				context.period
			) }
			loading={ request.loading }
			error={ request.error }
			onRetry={ request.reload }
		>
			{ data && (
				<ul className="ohmylms-pulse">
					{ data.metrics.map( ( metric ) => {
						const { label, hint } = LABELS[ metric.id ]();
						const change = percentChange(
							metric.value,
							metric.previous
						);
						const better = LOWER_IS_BETTER.includes( metric.id )
							? -1
							: 1;
						let tone: 'up' | 'down' | 'neutral' = 'neutral';
						if ( change && change * better > 0 ) {
							tone = 'up';
						} else if ( change && change * better < 0 ) {
							tone = 'down';
						}
						const low = Math.min( ...metric.series );
						const high = Math.max( ...metric.series );
						return (
							<li
								key={ metric.id }
								className="ohmylms-pulse__metric"
							>
								<span className="ohmylms-pulse__label">
									{ label }
								</span>
								<span className="ohmylms-pulse__value">
									{ display( metric, data, metric.value ) }
								</span>
								<span
									className={ `ohmylms-pulse__change is-${ tone }` }
								>
									<span aria-hidden="true">
										{ formatChange( change ) }
									</span>
									<span className="screen-reader-text">
										{ describeChange( change ) }
									</span>
								</span>
								<Sparkline
									values={ metric.series }
									tone={ tone }
								/>
								<span className="screen-reader-text">
									{ sprintf(
										/* translators: 1: number of days, 2: lowest daily value, 3: highest daily value. */
										__(
											'Daily values over %1$d days range from %2$s to %3$s.',
											'ohmylms'
										),
										metric.series.length,
										display( metric, data, low ),
										display( metric, data, high )
									) }
								</span>
								<span className="ohmylms-pulse__hint">
									{ hint }
									{ undefined !== metric.onHold &&
										` · ${ sprintf(
											/* translators: %s: number of subscriptions. */
											_n(
												'%s subscription on hold',
												'%s subscriptions on hold',
												metric.onHold,
												'ohmylms'
											),
											formatNumber( metric.onHold )
										) }` }
								</span>
							</li>
						);
					} ) }
				</ul>
			) }
		</WidgetCard>
	);
}
