import { Button, SelectControl, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Table } from '../../../ui/Table';
import { Pagination } from '../../components/Pagination';
import { useTransactions } from './hooks';
import { formatDate, formatMoney } from './model';
import { RequestStatus } from './RequestStatus';
import { SortHeader } from './SortHeader';
import type { Currency, PaymentMethodRow, RangeSelection } from './types';

/**
 * Paged, sortable orders of the range with an order-number search and a payment method filter.
 *
 * @param props          Component props.
 * @param props.range    Selected range.
 * @param props.currency Site currency.
 * @param props.methods  Payment methods seen in the range (filter choices).
 */
export function TransactionsTable( {
	range,
	currency,
	methods,
}: {
	range: RangeSelection;
	currency: Currency;
	methods: readonly PaymentMethodRow[];
} ) {
	const list = useTransactions( range );
	const [ text, setText ] = useState( '' );
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;
	const sort = { orderby: list.query.orderby, order: list.query.order };

	return (
		<section aria-label={ __( 'Transactions', 'ohmylms' ) }>
			<h3>{ __( 'Transactions', 'ohmylms' ) }</h3>
			<form
				role="search"
				aria-label={ __( 'Search transactions', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.setSearch( text );
				} }
			>
				<TextControl
					label={ __( 'Order number', 'ohmylms' ) }
					value={ text }
					onChange={ setText }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			<SelectControl
				label={ __( 'Payment method', 'ohmylms' ) }
				value={ list.query.payment_method }
				options={ [
					{
						value: '',
						label: __( 'All payment methods', 'ohmylms' ),
					},
					...methods.map( ( method ) => ( {
						value: method.id,
						label: method.title,
					} ) ),
				] }
				onChange={ list.setPaymentMethod }
			/>
			<RequestStatus
				loading={ list.loading }
				error={ list.error }
				label={ __( 'Loading transactions…', 'ohmylms' ) }
				onRetry={ list.reload }
			/>
			{ ! list.loading &&
				! list.error &&
				( items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: number of orders. */
								_n(
									'%d order found.',
									'%d orders found.',
									total,
									'ohmylms'
								),
								total
							) }
						</p>
						<Table
							caption={ __( 'Transactions', 'ohmylms' ) }
							striped
							compact
						>
							<thead>
								<tr>
									<SortHeader
										label={ __( 'Order', 'ohmylms' ) }
										field="id"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<SortHeader
										label={ __( 'Date', 'ohmylms' ) }
										field="date"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<th scope="col">
										{ __( 'Courses', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Payment', 'ohmylms' ) }
									</th>
									<SortHeader
										label={ __( 'Total', 'ohmylms' ) }
										field="total"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<SortHeader
										label={ __( 'Refunded', 'ohmylms' ) }
										field="refunded"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<SortHeader
										label={ __( 'Net', 'ohmylms' ) }
										field="net"
										{ ...sort }
										onSort={ list.setSort }
									/>
								</tr>
							</thead>
							<tbody>
								{ items.map( ( order ) => (
									<tr key={ order.order_id }>
										<th scope="row">#{ order.order_id }</th>
										<td>{ formatDate( order.date ) }</td>
										<td>
											{ order.items
												.map( ( item ) => item.title )
												.join( ', ' ) }
										</td>
										<td>{ order.payment_title }</td>
										<td>
											{ formatMoney(
												order.total,
												currency
											) }
										</td>
										<td>
											{ formatMoney(
												order.refunded,
												currency
											) }
											{ 'refunded' === order.status && (
												<>
													{ ' ' }
													(
													{ __(
														'refunded',
														'ohmylms'
													) }
													)
												</>
											) }
										</td>
										<td>
											{ formatMoney(
												order.net,
												currency
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ list.query.page }
							pages={ list.data?.totalPages ?? 1 }
							busy={ list.loading }
							onChange={ list.setPage }
						/>
					</>
				) : (
					<p>{ __( 'No orders in this period.', 'ohmylms' ) }</p>
				) ) }
		</section>
	);
}
