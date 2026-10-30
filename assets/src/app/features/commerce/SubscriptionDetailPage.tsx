import { Table } from '../../../ui/Table';
import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { normalizeApiError } from '../../lib/api';
import {
	formatDate,
	formatMoney,
	orderPath,
	parseOrderId,
	paymentLabel,
	statusLabel,
} from './model';
import { runSubscriptionAction } from './subscriptionApi';
import { useSubscription } from './subscriptionHooks';
import {
	actionConsequence,
	actionLabel,
	amountLabel,
	availableActions,
	subscriptionStatusLabel,
} from './subscriptionModel';
import { useOperation } from './useOperation';
import type { SubscriptionAction, SubscriptionDetail } from './types';

function Row( { label, value }: { label: string; value: string } ) {
	return value ? (
		<>
			<dt>{ label }</dt>
			<dd>{ value }</dd>
		</>
	) : null;
}

function doneMessage( action: SubscriptionAction ): string {
	switch ( action ) {
		case 'pause':
			return __( 'The subscription is paused.', 'ohmylms' );
		case 'resume':
			return __( 'The subscription is active again.', 'ohmylms' );
		default:
			return __( 'The subscription is cancelled.', 'ohmylms' );
	}
}

function BackLink() {
	return (
		<p>
			<Link to="/subscriptions">
				{ __( 'Back to subscriptions', 'ohmylms' ) }
			</Link>
		</p>
	);
}

function Subscription( { id }: { id: number } ) {
	const request = useSubscription( id );
	const operation = useOperation();
	const [ confirm, setConfirm ] = useState< SubscriptionAction | null >(
		null
	);
	const [ done, setDone ] = useState< SubscriptionAction | null >( null );
	const subscription: SubscriptionDetail | null = request.data;

	if ( request.error && ! subscription ) {
		return (
			<>
				<Notice status="error" isDismissible={ false }>
					{ request.error.message }{ ' ' }
					{ ! [ 401, 403, 404 ].includes( request.error.status ) && (
						<Button variant="link" onClick={ request.reload }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					) }
				</Notice>
				<BackLink />
			</>
		);
	}
	if ( ! subscription ) {
		return (
			<p role="status">{ __( 'Loading subscription…', 'ohmylms' ) }</p>
		);
	}

	const actions = availableActions( subscription );
	const close = () => {
		if ( ! operation.busy ) {
			setConfirm( null );
			operation.clearError();
		}
	};
	const apply = async ( action: SubscriptionAction ) => {
		const finished = await operation.run( async ( signal ) => {
			try {
				request.replace(
					await runSubscriptionAction( id, action, signal )
				);
			} catch ( failure ) {
				// The status changed elsewhere: show the current state behind the dialog.
				if ( 409 === normalizeApiError( failure ).status ) {
					request.reload();
				}
				throw failure;
			}
		} );
		if ( finished ) {
			setDone( action );
			setConfirm( null );
		}
	};

	return (
		<>
			<BackLink />
			<h2>
				{ sprintf(
					/* translators: %d: subscription ID. */
					__( 'Subscription #%d', 'ohmylms' ),
					subscription.id
				) }
			</h2>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error.message }
				</Notice>
			) }
			{ done && (
				<Notice status="success" onRemove={ () => setDone( null ) }>
					{ doneMessage( done ) }
				</Notice>
			) }

			<section aria-labelledby="subscription-summary-heading">
				<h3 id="subscription-summary-heading">
					{ __( 'Summary', 'ohmylms' ) }
				</h3>
				<dl>
					<Row
						label={ __( 'Status', 'ohmylms' ) }
						value={ subscriptionStatusLabel( subscription.status ) }
					/>
					<Row
						label={ __( 'Plan', 'ohmylms' ) }
						value={
							subscription.plan_name ||
							__( 'Deleted plan', 'ohmylms' )
						}
					/>
					<Row
						label={ __( 'Customer', 'ohmylms' ) }
						value={ subscription.customer_name }
					/>
					<Row
						label={ __( 'Email', 'ohmylms' ) }
						value={ subscription.customer_email }
					/>
					<Row
						label={ __( 'Recurring amount', 'ohmylms' ) }
						value={ amountLabel( subscription ) }
					/>
					<Row
						label={ __( 'Payment method', 'ohmylms' ) }
						value={
							subscription.payment_method_title ||
							paymentLabel( subscription.payment_method )
						}
					/>
					<Row
						label={ __( 'Started', 'ohmylms' ) }
						value={ formatDate( subscription.start_date ) }
					/>
					<Row
						label={ __( 'Next payment', 'ohmylms' ) }
						value={
							formatDate( subscription.next_payment_date ) ||
							__( 'None scheduled', 'ohmylms' )
						}
					/>
					<Row
						label={ __( 'Ends', 'ohmylms' ) }
						value={ formatDate( subscription.end_date ) }
					/>
					<Row
						label={ __( 'Last payment', 'ohmylms' ) }
						value={ formatDate( subscription.last_payment_date ) }
					/>
					<Row
						label={ __( 'Cancelled', 'ohmylms' ) }
						value={ formatDate( subscription.cancelled_date ) }
					/>
				</dl>
				{ subscription.original_order_id > 0 && (
					<p>
						<Link
							to={ orderPath( subscription.original_order_id ) }
						>
							{ sprintf(
								/* translators: %d: order ID. */
								__( 'Open the original order #%d', 'ohmylms' ),
								subscription.original_order_id
							) }
						</Link>
					</p>
				) }
			</section>

			<section aria-labelledby="subscription-actions-heading">
				<h3 id="subscription-actions-heading">
					{ __( 'Change status', 'ohmylms' ) }
				</h3>
				{ actions.length ? (
					<p>
						{ actions.map( ( action ) => (
							<Button
								key={ action }
								variant={
									'cancel' === action
										? 'secondary'
										: 'primary'
								}
								isDestructive={ 'cancel' === action }
								disabled={ operation.busy }
								onClick={ () => {
									setDone( null );
									setConfirm( action );
								} }
							>
								{ actionLabel( action ) }
							</Button>
						) ) }
					</p>
				) : (
					<p>
						{ __(
							'This subscription cannot change status any more.',
							'ohmylms'
						) }
					</p>
				) }
			</section>

			<section aria-labelledby="subscription-orders-heading">
				<h3 id="subscription-orders-heading">
					{ __( 'Related orders', 'ohmylms' ) }
				</h3>
				{ subscription.related_orders.length ? (
					<Table caption={ __( 'Related orders', 'ohmylms' ) }>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Order', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Relationship', 'ohmylms' ) }
								</th>
								<th scope="col">{ __( 'Date', 'ohmylms' ) }</th>
								<th scope="col">
									{ __( 'Status', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Total', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ subscription.related_orders.map( ( order ) => (
								<tr key={ order.id }>
									<th scope="row">
										<Link to={ orderPath( order.id ) }>
											{ sprintf(
												/* translators: %d: order ID. */
												__( 'Order #%d', 'ohmylms' ),
												order.id
											) }
										</Link>
									</th>
									<td>
										{ 'parent' === order.relationship
											? __( 'Original order', 'ohmylms' )
											: __( 'Renewal', 'ohmylms' ) }
									</td>
									<td>
										{ formatDate( order.date_created ) }
									</td>
									<td>{ statusLabel( order.status ) }</td>
									<td>
										{ formatMoney(
											order.total,
											order.currency
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</Table>
				) : (
					<p>{ __( 'No related orders.', 'ohmylms' ) }</p>
				) }
			</section>

			<section aria-labelledby="subscription-notes-heading">
				<h3 id="subscription-notes-heading">
					{ __( 'Notes', 'ohmylms' ) }
				</h3>
				{ subscription.notes.length ? (
					<ul aria-label={ __( 'Subscription notes', 'ohmylms' ) }>
						{ subscription.notes.map( ( note ) => (
							<li key={ note.id }>
								{ note.content }
								{ ' · ' }
								{ note.added_by }
								{ note.date_created
									? ` · ${ formatDate( note.date_created ) }`
									: '' }
							</li>
						) ) }
					</ul>
				) : (
					<p>{ __( 'No notes yet.', 'ohmylms' ) }</p>
				) }
			</section>

			{ confirm && (
				<Modal
					title={ actionLabel( confirm ) }
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>{ actionConsequence( confirm ) }</p>
					<Button
						variant="primary"
						isDestructive={ 'cancel' === confirm }
						isBusy={ operation.busy }
						disabled={ operation.busy }
						onClick={ () => void apply( confirm ) }
					>
						{ actionLabel( confirm ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ close }
					>
						{ __( 'Keep it as it is', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}

/** The `/subscriptions/:id` route. */
export default function SubscriptionDetailPage() {
	const params = useParams();
	const id = parseOrderId( params.id );
	if ( null === id ) {
		return (
			<>
				<Notice status="error" isDismissible={ false }>
					{ __( 'That is not a valid subscription.', 'ohmylms' ) }
				</Notice>
				<BackLink />
			</>
		);
	}
	return <Subscription key={ id } id={ id } />;
}
