import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ListTable, ListTableSelect } from '../../../ui/ListTable';
import { useTransactions } from './hooks';
import { formatDate, formatMoney } from './model';
import { RequestStatus } from './RequestStatus';
import type {
	Currency,
	PaymentMethodRow,
	RangeSelection,
	TransactionOrderBy,
} from './types';

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

	return (
		<section aria-label={ __( 'Transactions', 'ohmylms' ) }>
			<h3>{ __( 'Transactions', 'ohmylms' ) }</h3>
			<RequestStatus
				loading={ list.loading }
				error={ list.error }
				label={ __( 'Loading transactions…', 'ohmylms' ) }
				onRetry={ list.reload }
			/>
			{ ! list.loading && ! list.error && (
				<ListTable
					caption={ __( 'Transactions', 'ohmylms' ) }
					formLabel={ __( 'Search transactions', 'ohmylms' ) }
					rows={ items }
					rowKey={ ( order ) => order.order_id }
					emptyMessage={ __(
						'No orders in this period.',
						'ohmylms'
					) }
					search={ {
						label: __( 'Order number', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: text,
						onChange: setText,
					} }
					onFilter={ () => list.setSearch( text ) }
					filterLabel={ null }
					filters={
						<ListTableSelect
							label={ __( 'Payment method', 'ohmylms' ) }
							value={ list.query.payment_method }
							options={ [
								{
									value: '',
									label: __(
										'All payment methods',
										'ohmylms'
									),
								},
								...methods.map( ( method ) => ( {
									value: method.id,
									label: method.title,
								} ) ),
							] }
							onChange={ list.setPaymentMethod }
						/>
					}
					sort={ {
						orderby: list.query.orderby,
						order: list.query.order,
						onChange: ( orderby, order ) =>
							list.setSort(
								orderby as TransactionOrderBy,
								order
							),
					} }
					pagination={ {
						page: list.query.page,
						totalPages: list.data?.totalPages ?? 1,
						totalItems: total,
						itemsLabel: sprintf(
							/* translators: %d: number of orders. */
							_n(
								'%d order found.',
								'%d orders found.',
								total,
								'ohmylms'
							),
							total
						),
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'id',
							label: __( 'Order', 'ohmylms' ),
							primary: true,
							sortable: 'desc',
							render: ( order ) => (
								<strong>#{ order.order_id }</strong>
							),
						},
						{
							id: 'date',
							label: __( 'Date', 'ohmylms' ),
							sortable: 'desc',
							render: ( order ) => formatDate( order.date ),
						},
						{
							id: 'courses',
							label: __( 'Courses', 'ohmylms' ),
							render: ( order ) =>
								order.items
									.map( ( item ) => item.title )
									.join( ', ' ),
						},
						{
							id: 'payment',
							label: __( 'Payment', 'ohmylms' ),
							render: ( order ) => order.payment_title,
						},
						{
							id: 'total',
							label: __( 'Total', 'ohmylms' ),
							sortable: 'desc',
							render: ( order ) =>
								formatMoney( order.total, currency ),
						},
						{
							id: 'refunded',
							label: __( 'Refunded', 'ohmylms' ),
							sortable: 'desc',
							render: ( order ) => (
								<>
									{ formatMoney( order.refunded, currency ) }
									{ 'refunded' === order.status &&
										` (${ __( 'refunded', 'ohmylms' ) })` }
								</>
							),
						},
						{
							id: 'net',
							label: __( 'Net', 'ohmylms' ),
							sortable: 'desc',
							render: ( order ) =>
								formatMoney( order.net, currency ),
						},
					] }
				/>
			) }
		</section>
	);
}
