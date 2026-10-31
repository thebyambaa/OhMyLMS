import { Button, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { normalizeApiError } from '../../../lib/api';
import { snoozeItem, wakeItem } from '../shell/api';
import { describeWidgetError } from '../shell/errors';
import { useWidget } from '../shell/useWidget';
import { WidgetCard } from '../shell/WidgetCard';
import { parseNeedsAttention } from './needsAttention';
import type { AttentionItem, AttentionTone } from './needsAttention';
import type { DashboardWidgetProps } from './registry';

function toneLabel( tone: AttentionTone ): string {
	if ( 'error' === tone ) {
		return __( 'Urgent', 'ohmylms' );
	}
	return 'warning' === tone
		? __( 'Soon', 'ohmylms' )
		: __( 'When you can', 'ohmylms' );
}

/**
 * "Needs attention": the ranked queue from `GET /dashboard/widgets/needs-attention`. Each item links to
 * the screen that resolves it (when the user may open it) and can be snoozed for seven days, with undo.
 *
 * @param props         Component props.
 * @param props.context Scope and period.
 */
export default function NeedsAttentionWidget( {
	context,
}: DashboardWidgetProps ) {
	const request = useWidget(
		'needs-attention',
		context,
		parseNeedsAttention
	);
	const [ hidden, setHidden ] = useState< readonly string[] >( [] );
	const [ snoozed, setSnoozed ] = useState< AttentionItem | null >( null );
	const [ failure, setFailure ] = useState< string | null >( null );
	const scopeId = context.scope.id;

	useEffect( () => {
		setHidden( [] );
		setSnoozed( null );
		setFailure( null );
	}, [ scopeId ] );

	const data = request.data;
	const items = ( data?.items ?? [] ).filter(
		( item ) => ! hidden.includes( item.key )
	);

	const snooze = async ( item: AttentionItem ) => {
		setFailure( null );
		setHidden( ( keys ) => [ ...keys, item.key ] );
		try {
			await snoozeItem( scopeId, item.key );
			setSnoozed( item );
		} catch ( error ) {
			setHidden( ( keys ) => keys.filter( ( key ) => key !== item.key ) );
			setFailure( describeWidgetError( normalizeApiError( error ) ) );
		}
	};

	const undo = async () => {
		if ( ! snoozed ) {
			return;
		}
		const item = snoozed;
		setFailure( null );
		try {
			await wakeItem( scopeId, item.key );
			setSnoozed( null );
			setHidden( ( keys ) => keys.filter( ( key ) => key !== item.key ) );
			request.reload();
		} catch ( error ) {
			setFailure( describeWidgetError( normalizeApiError( error ) ) );
		}
	};

	const snoozedCount =
		( data?.snoozed ?? 0 ) +
		hidden.filter( ( key ) =>
			( data?.items ?? [] ).some( ( item ) => item.key === key )
		).length;
	const meta = data
		? [
				sprintf(
					/* translators: %d: number of open items. */
					_n( '%d open', '%d open', items.length, 'ohmylms' ),
					items.length
				),
				__( 'sorted by urgency', 'ohmylms' ),
				...( snoozedCount
					? [
							sprintf(
								/* translators: %d: number of snoozed items. */
								_n(
									'%d snoozed',
									'%d snoozed',
									snoozedCount,
									'ohmylms'
								),
								snoozedCount
							),
						]
					: [] ),
			].join( ' · ' )
		: undefined;

	return (
		<WidgetCard
			id="needs-attention"
			title={ __( 'Needs attention', 'ohmylms' ) }
			meta={ meta }
			loading={ request.loading }
			error={ request.error }
			onRetry={ request.reload }
		>
			{ data && ! request.loading && 0 === items.length && (
				<p>
					{ __(
						'Nothing needs your attention right now. Everything else is on track.',
						'ohmylms'
					) }
				</p>
			) }
			{ failure && (
				<Notice status="error" isDismissible={ false }>
					{ failure }
				</Notice>
			) }
			{ snoozed && (
				<div role="status" className="ohmylms-attention__snoozed">
					{ sprintf(
						/* translators: %s: item title. */
						__( 'Snoozed for 7 days: %s.', 'ohmylms' ),
						snoozed.title
					) }{ ' ' }
					<Button variant="link" onClick={ undo }>
						{ __( 'Undo', 'ohmylms' ) }
					</Button>
				</div>
			) }
			{ items.length > 0 && (
				<ul className="ohmylms-attention">
					{ items.map( ( item, index ) => (
						<li
							key={ item.key }
							className={ `ohmylms-attention__item is-${ item.tone }` }
						>
							<span
								className="ohmylms-attention__marker"
								aria-hidden="true"
							/>
							<div className="ohmylms-attention__text">
								<strong>{ item.title }</strong>
								<span className="screen-reader-text">
									{ ` (${ toneLabel( item.tone ) })` }
								</span>
								<span className="ohmylms-attention__detail">
									{ item.detail }
								</span>
							</div>
							<div className="ohmylms-attention__actions">
								{ item.action && (
									<Link
										to={ item.action.route }
										className={
											index < 2
												? 'button button-primary'
												: 'button'
										}
									>
										{ item.action.label }
									</Link>
								) }
								<Button
									variant="link"
									onClick={ () => snooze( item ) }
									aria-label={ sprintf(
										/* translators: %s: item title. */
										__(
											'Snooze for 7 days: %s',
											'ohmylms'
										),
										item.title
									) }
								>
									{ __( 'Snooze', 'ohmylms' ) }
								</Button>
							</div>
						</li>
					) ) }
				</ul>
			) }
		</WidgetCard>
	);
}
