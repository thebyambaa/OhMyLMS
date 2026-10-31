import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { useWidget } from '../shell/useWidget';
import { WidgetCard } from '../shell/WidgetCard';
import { parseSiteHealth } from './SiteHealthModel';
import type { CheckStatus, SiteCheck } from './SiteHealthModel';
import type { DashboardWidgetProps } from './registry';
import './SiteHealthWidget.css';

function statusLabel( status: CheckStatus ): string {
	if ( 'ok' === status ) {
		return __( 'Done', 'ohmylms' );
	}
	return 'error' === status
		? __( 'Problem', 'ohmylms' )
		: __( 'To do', 'ohmylms' );
}

const MARKS: Record< CheckStatus, string > = {
	ok: '✓',
	warning: '!',
	error: '✕',
};

function Fix( { action }: { action: NonNullable< SiteCheck[ 'action' ] > } ) {
	if ( action.route ) {
		return <Link to={ action.route }>{ action.label }</Link>;
	}
	return <a href={ action.href ?? undefined }>{ action.label }</a>;
}

/**
 * "Site health": set-up checks from `GET /dashboard/widgets/site-health` (payment gateway, e-mail sender,
 * certificate template, cron, privacy policy page), each with a link to fix it. Site administrators in
 * All schools only.
 *
 * @param props         Component props.
 * @param props.context Scope and period.
 */
export default function SiteHealthWidget( { context }: DashboardWidgetProps ) {
	const request = useWidget( 'site-health', context, parseSiteHealth );
	const data = request.data;
	const done = data
		? sprintf(
				/* translators: 1: checks passed, 2: checks in total. */
				__( '%1$d of %2$d done', 'ohmylms' ),
				data.passed,
				data.total
			)
		: undefined;
	return (
		<WidgetCard
			id="site-health"
			title={ __( 'Site health', 'ohmylms' ) }
			meta={ done }
			loading={ request.loading }
			error={ request.error }
			onRetry={ request.reload }
		>
			{ data && (
				<>
					<ul className="ohmylms-site-health">
						{ data.checks.map( ( check ) => (
							<li
								key={ check.id }
								className={ `ohmylms-site-health__check is-${ check.status }` }
							>
								<span
									className="ohmylms-site-health__mark"
									aria-hidden="true"
								>
									{ MARKS[ check.status ] }
								</span>
								<span className="ohmylms-site-health__text">
									<strong>{ check.label }</strong>
									<span className="screen-reader-text">
										{ ` (${ statusLabel( check.status ) })` }
									</span>
									<span className="ohmylms-site-health__detail">
										{ check.detail }
									</span>
									{ 'ok' !== check.status && check.action && (
										<Fix action={ check.action } />
									) }
								</span>
							</li>
						) ) }
					</ul>
					<progress
						className="ohmylms-site-health__progress"
						value={ data.passed }
						max={ Math.max( 1, data.total ) }
						aria-label={ done }
					/>
				</>
			) }
		</WidgetCard>
	);
}
