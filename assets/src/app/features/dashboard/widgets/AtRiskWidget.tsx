import { Button, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { normalizeApiError } from '../../../lib/api';
import type { ApiError } from '../../../lib/api';
import { describeWidgetError } from '../shell/errors';
import { useWidget } from '../shell/useWidget';
import { WidgetCard } from '../shell/WidgetCard';
import { nudgeLearner, parseAtRisk } from './atRisk';
import type { AtRiskLearner, RiskLevel } from './atRisk';
import type { DashboardWidgetProps } from './registry';
import './AtRiskWidget.css';

/** Learners shown before "Show all". */
const FIRST = 6;

function levelLabel( level: RiskLevel ): string {
	return 'high' === level
		? __( 'High risk', 'ohmylms' )
		: __( 'Watch', 'ohmylms' );
}

function nudgeError( error: ApiError ): string {
	if (
		'ohmylms_at_risk_nudge_too_soon' === error.code ||
		'ohmylms_at_risk_nudge_daily_cap' === error.code ||
		'ohmylms_at_risk_nudge_disabled' === error.code ||
		'ohmylms_at_risk_no_address' === error.code ||
		'ohmylms_at_risk_not_flagged' === error.code ||
		'ohmylms_at_risk_out_of_scope' === error.code
	) {
		return error.message;
	}
	return describeWidgetError( error );
}

function nudgedLabelOf( error: ApiError ): string | null {
	const details = error.details;
	if (
		details &&
		'object' === typeof details &&
		'string' ===
			typeof ( details as { nudged_label?: unknown } ).nudged_label
	) {
		return ( details as { nudged_label: string } ).nudged_label;
	}
	return null;
}

/**
 * "At-risk learners": who the at-risk rules flag in the scope (`GET /dashboard/widgets/at-risk`), why, and a
 * nudge button per learner that sends the fixed encouragement email. A learner the user nudged in the last
 * 72 hours shows "Nudged <date>" instead of the button.
 *
 * @param props         Component props.
 * @param props.context Scope and period.
 */
export default function AtRiskWidget( { context }: DashboardWidgetProps ) {
	const request = useWidget( 'at-risk', context, parseAtRisk );
	const [ nudged, setNudged ] = useState< Record< number, string > >( {} );
	const [ busy, setBusy ] = useState< number | null >( null );
	const [ remaining, setRemaining ] = useState< number | null >( null );
	const [ failure, setFailure ] = useState< string | null >( null );
	const [ sent, setSent ] = useState< string | null >( null );
	const [ expanded, setExpanded ] = useState( false );
	const scopeId = context.scope.id;
	const period = context.period;

	useEffect( () => {
		setNudged( {} );
		setRemaining( null );
		setFailure( null );
		setSent( null );
		setExpanded( false );
	}, [ scopeId, period ] );

	const data = request.data;
	const left = remaining ?? data?.nudge.remaining ?? 0;
	const learners = data?.learners ?? [];
	const shown = expanded ? learners : learners.slice( 0, FIRST );
	const allOff =
		!! data &&
		! data.rules.inactive &&
		! data.rules.mastery &&
		! data.rules.overdue;

	const nudge = async ( learner: AtRiskLearner ) => {
		setFailure( null );
		setSent( null );
		setBusy( learner.id );
		try {
			const result = await nudgeLearner( scopeId, period, learner.id );
			setNudged( ( current ) => ( {
				...current,
				[ learner.id ]: result.nudgedLabel,
			} ) );
			setRemaining( result.remaining );
			setSent(
				sprintf(
					/* translators: %s: learner name. */
					__( 'Nudge sent to %s.', 'ohmylms' ),
					learner.name
				)
			);
		} catch ( caught ) {
			const error = normalizeApiError( caught );
			const label = nudgedLabelOf( error );
			if ( label ) {
				setNudged( ( current ) => ( {
					...current,
					[ learner.id ]: label,
				} ) );
			}
			if ( 'ohmylms_at_risk_nudge_daily_cap' === error.code ) {
				setRemaining( 0 );
			}
			setFailure( nudgeError( error ) );
		} finally {
			setBusy( null );
		}
	};

	const meta = data
		? [
				sprintf(
					/* translators: %d: number of learners at high risk. */
					_n( '%d high', '%d high', data.counts.high, 'ohmylms' ),
					data.counts.high
				),
				sprintf(
					/* translators: %d: number of learners to watch. */
					_n( '%d watch', '%d watch', data.counts.watch, 'ohmylms' ),
					data.counts.watch
				),
				__( 'why they’re flagged', 'ohmylms' ),
			].join( ' · ' )
		: undefined;

	let empty: string | false = false;
	if ( data && ! request.loading && 0 === learners.length ) {
		empty = allOff
			? __( 'Every at-risk rule is switched off in Settings.', 'ohmylms' )
			: __( 'No learners are at risk here right now.', 'ohmylms' );
	}

	return (
		<WidgetCard
			id="at-risk"
			title={ __( 'At-risk learners', 'ohmylms' ) }
			meta={ meta }
			loading={ request.loading }
			error={ request.error }
			onRetry={ request.reload }
			empty={ empty }
		>
			{ failure && (
				<Notice status="error" isDismissible={ false }>
					{ failure }
				</Notice>
			) }
			{ sent && (
				<p role="status" className="ohmylms-at-risk__sent">
					{ sent }
				</p>
			) }
			{ shown.length > 0 && (
				<ul className="ohmylms-at-risk">
					{ shown.map( ( learner ) => {
						const label = nudged[ learner.id ];
						const done = undefined !== label || ! learner.canNudge;
						const when = label ?? learner.nudgedLabel;
						return (
							<li
								key={ learner.id }
								className={ `ohmylms-at-risk__item is-${ learner.level }` }
							>
								<span
									className="ohmylms-at-risk__avatar"
									aria-hidden="true"
								>
									{ learner.initials }
								</span>
								<div className="ohmylms-at-risk__text">
									<span className="ohmylms-at-risk__name">
										{ learner.report ? (
											<Link to={ learner.report }>
												{ learner.name }
											</Link>
										) : (
											<strong>{ learner.name }</strong>
										) }{ ' ' }
										<span
											className={ `ohmylms-at-risk__badge is-${ learner.level }` }
										>
											{ levelLabel( learner.level ) }
										</span>
									</span>
									<span className="ohmylms-at-risk__reasons">
										{ learner.summary }
									</span>
								</div>
								<div className="ohmylms-at-risk__action">
									{ done ? (
										<span className="ohmylms-at-risk__nudged">
											{ sprintf(
												/* translators: %s: date. */
												__( 'Nudged %s', 'ohmylms' ),
												when
											) }
										</span>
									) : (
										<Button
											variant="secondary"
											size="compact"
											onClick={ () => nudge( learner ) }
											disabled={
												null !== busy || left <= 0
											}
											isBusy={ busy === learner.id }
											accessibleWhenDisabled
											aria-label={ sprintf(
												/* translators: %s: learner name. */
												__(
													'Send a nudge email to %s',
													'ohmylms'
												),
												learner.name
											) }
										>
											{ __( 'Nudge', 'ohmylms' ) }
										</Button>
									) }
								</div>
							</li>
						);
					} ) }
				</ul>
			) }
			{ data && learners.length > 0 && left <= 0 && (
				<p className="ohmylms-at-risk__note">
					{ __(
						'You reached the daily nudge limit. Try again tomorrow.',
						'ohmylms'
					) }
				</p>
			) }
			{ learners.length > FIRST && (
				<p>
					<Button
						variant="link"
						onClick={ () => setExpanded( ( value ) => ! value ) }
						aria-expanded={ expanded }
					>
						{ expanded
							? __( 'Show fewer', 'ohmylms' )
							: sprintf(
									/* translators: %d: number of flagged learners. */
									__( 'Show all %d flagged', 'ohmylms' ),
									learners.length
								) }
					</Button>
				</p>
			) }
			{ data && data.total > learners.length && expanded && (
				<p className="ohmylms-at-risk__note">
					{ sprintf(
						/* translators: 1: learners shown, 2: learners flagged. */
						__(
							'Showing the %1$d most urgent of %2$d.',
							'ohmylms'
						),
						learners.length,
						data.total
					) }
				</p>
			) }
		</WidgetCard>
	);
}
