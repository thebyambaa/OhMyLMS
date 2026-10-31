import { WidefatTable } from '../../components/WidefatTable';
import { Button, Modal, Notice, SelectControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { changeOrderStatus, trashOrder } from './api';
import { useOrder } from './hooks';
import {
	allowedStatuses,
	formatDate,
	formatMoney,
	orderPath,
	parseOrderId,
	paymentLabel,
	revokesAccess,
	statusLabel,
} from './model';
import { OrderNotes } from './OrderNotes';
import { useOperation } from './useOperation';
import type { OrderDetail, OrderStatus } from './types';

function Row( { label, value }: { label: string; value: string } ) {
	return value ? (
		<>
			<dt>{ label }</dt>
			<dd>{ value }</dd>
		</>
	) : null;
}

function Order( { id }: { id: number } ) {
	const request = useOrder( id );
	const operation = useOperation();
	const navigate = useNavigate();
	const [ target, setTarget ] = useState< OrderStatus | '' >( '' );
	const [ confirm, setConfirm ] = useState< 'status' | 'trash' | null >(
		null
	);
	const order: OrderDetail | null = request.data;

	if ( request.error && ! order ) {
		return (
			<>
				<Notice status="error" isDismissible={ false }>
					{ request.error.message }{ ' ' }
					{ 404 !== request.error.status && (
						<Button variant="link" onClick={ request.reload }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					) }
				</Notice>
				<p>
					<Link to="/orders">
						{ __( 'Back to orders', 'ohmylms' ) }
					</Link>
				</p>
			</>
		);
	}
	if ( ! order ) {
		return <p role="status">{ __( 'Loading order…', 'ohmylms' ) }</p>;
	}

	const choices = allowedStatuses( order );
	const money = ( amount: string ) => formatMoney( amount, order.currency );
	const close = () => {
		if ( ! operation.busy ) {
			setConfirm( null );
			operation.clearError();
		}
	};
	const applyStatus = async () => {
		if ( ! target ) {
			return;
		}
		const done = await operation.run( async ( signal ) => {
			request.replace( await changeOrderStatus( id, target, signal ) );
		} );
		if ( done ) {
			setTarget( '' );
			setConfirm( null );
		}
	};
	const moveToTrash = async () => {
		const done = await operation.run( async ( signal ) => {
			await trashOrder( id, signal );
		} );
		if ( done ) {
			navigate( '/orders' );
		}
	};

	return (
		<>
			<p>
				<Link to="/orders">{ __( 'Back to orders', 'ohmylms' ) }</Link>
			</p>
			<h2>
				{ sprintf(
					/* translators: %s: order number. */
					__( 'Order #%s', 'ohmylms' ),
					order.number
				) }
			</h2>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error.message }
				</Notice>
			) }

			<section aria-labelledby="order-summary-heading">
				<h3 id="order-summary-heading">
					{ __( 'Summary', 'ohmylms' ) }
				</h3>
				<dl>
					<Row
						label={ __( 'Status', 'ohmylms' ) }
						value={ statusLabel( order.status ) }
					/>
					<Row
						label={ __( 'Placed', 'ohmylms' ) }
						value={ formatDate( order.date_created ) }
					/>
					<Row
						label={ __( 'Paid', 'ohmylms' ) }
						value={ formatDate( order.date_paid ) }
					/>
					<Row
						label={ __( 'Completed', 'ohmylms' ) }
						value={ formatDate( order.date_completed ) }
					/>
					<Row
						label={ __( 'Payment method', 'ohmylms' ) }
						value={
							order.payment_method_title ||
							paymentLabel( order.payment_method )
						}
					/>
					<Row
						label={ __( 'Transaction', 'ohmylms' ) }
						value={ order.transaction_id }
					/>
				</dl>
			</section>

			<section aria-labelledby="order-status-heading">
				<h3 id="order-status-heading">
					{ __( 'Change status', 'ohmylms' ) }
				</h3>
				{ choices.length ? (
					<>
						<SelectControl
							label={ __( 'New status', 'ohmylms' ) }
							value={ target }
							options={ [
								{
									value: '',
									label: __( 'Choose a status', 'ohmylms' ),
								},
								...choices.map( ( status ) => ( {
									value: status,
									label: statusLabel( status ),
								} ) ),
							] }
							disabled={ operation.busy }
							onChange={ ( value ) =>
								setTarget(
									choices.find(
										( status ) => status === value
									) ?? ''
								)
							}
						/>
						<Button
							variant="primary"
							disabled={ ! target || operation.busy }
							onClick={ () => setConfirm( 'status' ) }
						>
							{ __( 'Update status', 'ohmylms' ) }
						</Button>
					</>
				) : (
					<p>
						{ __(
							'This order cannot change status any more.',
							'ohmylms'
						) }
					</p>
				) }
			</section>

			<section aria-labelledby="order-items-heading">
				<h3 id="order-items-heading">{ __( 'Items', 'ohmylms' ) }</h3>
				<WidefatTable caption={ __( 'Order items', 'ohmylms' ) }>
					<thead>
						<tr>
							<th scope="col">{ __( 'Course', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Quantity', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Price', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Total', 'ohmylms' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ order.line_items.map( ( line ) => (
							<tr key={ line.id }>
								<th scope="row">{ line.name }</th>
								<td>{ line.quantity }</td>
								<td>{ money( line.subtotal ) }</td>
								<td>{ money( line.total ) }</td>
							</tr>
						) ) }
					</tbody>
				</WidefatTable>
				<dl>
					<Row
						label={ __( 'Subtotal', 'ohmylms' ) }
						value={ money( order.subtotal ) }
					/>
					{ order.coupon_lines.map( ( line ) => (
						<Row
							key={ line.code }
							label={ sprintf(
								/* translators: %s: coupon code. */
								__( 'Coupon %s', 'ohmylms' ),
								line.code
							) }
							value={ `-${ money( line.discount ) }` }
						/>
					) ) }
					<Row
						label={ __( 'Tax', 'ohmylms' ) }
						value={ money( order.tax_amount ) }
					/>
					<Row
						label={ __( 'Total', 'ohmylms' ) }
						value={ money( order.total ) }
					/>
					<Row
						label={ __( 'Refunded', 'ohmylms' ) }
						value={ money( order.total_refunded ) }
					/>
					<Row
						label={ __( 'Still refundable', 'ohmylms' ) }
						value={ money( order.remaining_refundable ) }
					/>
				</dl>
			</section>

			<section aria-labelledby="order-billing-heading">
				<h3 id="order-billing-heading">
					{ __( 'Billing', 'ohmylms' ) }
				</h3>
				<dl>
					<Row
						label={ __( 'Name', 'ohmylms' ) }
						value={ order.student_name }
					/>
					<Row
						label={ __( 'Email', 'ohmylms' ) }
						value={ order.billing.email }
					/>
					<Row
						label={ __( 'Phone', 'ohmylms' ) }
						value={ order.billing.phone }
					/>
					<Row
						label={ __( 'Address', 'ohmylms' ) }
						value={ [
							order.billing.address,
							order.billing.city,
							order.billing.state,
							order.billing.postcode,
							order.billing.country,
						]
							.filter( Boolean )
							.join( ', ' ) }
					/>
					<Row
						label={ __( 'VAT number', 'ohmylms' ) }
						value={ order.billing.vat_number }
					/>
				</dl>
				{ order.student_id > 0 && (
					<p>
						<Link to={ `/students?student=${ order.student_id }` }>
							{ __( 'Open the student', 'ohmylms' ) }
						</Link>
					</p>
				) }
			</section>

			<section aria-labelledby="order-refunds-heading">
				<h3 id="order-refunds-heading">
					{ __( 'Refunds', 'ohmylms' ) }
				</h3>
				{ order.refunds.length ? (
					<ul aria-label={ __( 'Recorded refunds', 'ohmylms' ) }>
						{ order.refunds.map( ( refund ) => (
							<li key={ refund.id }>
								{ money( refund.amount ) }
								{ refund.reason ? `: ${ refund.reason }` : '' }
								{ ' · ' }
								{ formatDate( refund.date_created ) }
							</li>
						) ) }
					</ul>
				) : (
					<p>{ __( 'No refunds recorded.', 'ohmylms' ) }</p>
				) }
				<p>
					{ __(
						'Refunds are not issued from this screen. Refund payments in the payment provider.',
						'ohmylms'
					) }
				</p>
			</section>

			{ !! order.related_orders.length && (
				<section aria-labelledby="order-related-heading">
					<h3 id="order-related-heading">
						{ __( 'Related orders', 'ohmylms' ) }
					</h3>
					<WidefatTable caption={ __( 'Related orders', 'ohmylms' ) }>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Record', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Relationship', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Status', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Total', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ order.related_orders.map( ( related ) => (
								<tr key={ `${ related.type }-${ related.id }` }>
									<th scope="row">
										{ 'order' === related.type ? (
											<Link
												to={ orderPath( related.id ) }
											>
												{ sprintf(
													/* translators: %d: order ID. */
													__(
														'Order #%d',
														'ohmylms'
													),
													related.id
												) }
											</Link>
										) : (
											sprintf(
												/* translators: %d: subscription ID. */
												__(
													'Subscription #%d',
													'ohmylms'
												),
												related.id
											)
										) }
									</th>
									<td>{ related.relationship }</td>
									<td>{ statusLabel( related.status ) }</td>
									<td>{ money( related.total ) }</td>
								</tr>
							) ) }
						</tbody>
					</WidefatTable>
				</section>
			) }

			<OrderNotes orderId={ id } />

			<section aria-labelledby="order-danger-heading">
				<h3 id="order-danger-heading">
					{ __( 'Delete order', 'ohmylms' ) }
				</h3>
				<p>
					{ __(
						'Moving an order to the trash removes the enrolments it created.',
						'ohmylms'
					) }
				</p>
				<Button
					variant="secondary"
					isDestructive
					disabled={ operation.busy }
					onClick={ () => setConfirm( 'trash' ) }
				>
					{ __( 'Move to trash', 'ohmylms' ) }
				</Button>
			</section>

			{ confirm && (
				<Modal
					title={
						'trash' === confirm
							? __( 'Move order to trash', 'ohmylms' )
							: __( 'Change order status', 'ohmylms' )
					}
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>
						{ 'trash' === confirm
							? __(
									'The order moves to the trash and the student loses access granted by it.',
									'ohmylms'
								)
							: sprintf(
									/* translators: 1: current status, 2: new status. */
									__(
										'Change this order from %1$s to %2$s?',
										'ohmylms'
									),
									statusLabel( order.status ),
									target ? statusLabel( target ) : ''
								) }
					</p>
					{ 'status' === confirm &&
						target &&
						revokesAccess( target ) && (
							<p>
								{ __(
									'Cancelling removes the access the student received from this order.',
									'ohmylms'
								) }
							</p>
						) }
					<Button
						variant="primary"
						isDestructive={
							'trash' === confirm ||
							( !! target && revokesAccess( target ) )
						}
						isBusy={ operation.busy }
						disabled={ operation.busy }
						onClick={ () =>
							void ( 'trash' === confirm
								? moveToTrash()
								: applyStatus() )
						}
					>
						{ 'trash' === confirm
							? __( 'Move to trash', 'ohmylms' )
							: __( 'Change status', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ close }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}

/** The `/orders/:id` route. */
export default function OrderDetailPage() {
	const params = useParams();
	const id = parseOrderId( params.id );
	if ( null === id ) {
		return (
			<>
				<Notice status="error" isDismissible={ false }>
					{ __( 'That is not a valid order.', 'ohmylms' ) }
				</Notice>
				<p>
					<Link to="/orders">
						{ __( 'Back to orders', 'ohmylms' ) }
					</Link>
				</p>
			</>
		);
	}
	return <Order key={ id } id={ id } />;
}
