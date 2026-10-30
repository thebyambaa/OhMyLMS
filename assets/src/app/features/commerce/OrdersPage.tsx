import {
	ListTable,
	ListTableInput,
	ListTableSelect,
} from '../../../ui/ListTable';
import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { useOrders, useStatusCounts } from './hooks';
import {
	ORDER_STATUSES,
	PAYMENT_METHODS,
	formatDate,
	formatMoney,
	orderPath,
	paymentLabel,
	statusLabel,
	validateDateRange,
} from './model';
import type { OrderOrderBy, SortOrder } from './types';

const SORTS: ReadonlyArray< {
	value: string;
	orderby: OrderOrderBy;
	order: SortOrder;
} > = [
	{ value: 'date:desc', orderby: 'date', order: 'desc' },
	{ value: 'date:asc', orderby: 'date', order: 'asc' },
	{ value: 'total:desc', orderby: 'total', order: 'desc' },
	{ value: 'total:asc', orderby: 'total', order: 'asc' },
];

/** Searchable, filterable, paged list of orders. */
export default function OrdersPage() {
	const list = useOrders();
	const counts = useStatusCounts( 'counts' );
	const [ text, setText ] = useState( '' );
	const [ after, setAfter ] = useState( '' );
	const [ before, setBefore ] = useState( '' );
	const rangeError = validateDateRange( after, before );
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;

	const changeDate = ( key: 'after' | 'before', value: string ) => {
		const next = { after, before, [ key ]: value };
		if ( 'after' === key ) {
			setAfter( value );
		} else {
			setBefore( value );
		}
		if ( ! validateDateRange( next.after, next.before ) ) {
			list.setFilters( { after: next.after, before: next.before } );
		}
	};

	return (
		<>
			<h2>{ __( 'Orders', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Orders are created at checkout. Open an order to see its items, change its status or add notes.',
					'ohmylms'
				) }
			</p>
			{ rangeError && (
				<Notice status="error" isDismissible={ false }>
					{ rangeError }
				</Notice>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error.message }{ ' ' }
					<Button variant="link" onClick={ list.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading orders…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && ! list.error && (
				<ListTable
					caption={ __( 'Orders', 'ohmylms' ) }
					formLabel={ __( 'Search orders', 'ohmylms' ) }
					rows={ items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __( 'No orders found.', 'ohmylms' ) }
					views={ [
						{
							key: 'any',
							label: __( 'All', 'ohmylms' ),
							current: 'any' === list.query.status,
							count:
								'any' === list.query.status ? total : undefined,
							onSelect: () =>
								list.setFilters( { status: 'any' } ),
						},
						...ORDER_STATUSES.map( ( status ) => ( {
							key: status,
							label: statusLabel( status ),
							current: status === list.query.status,
							count: counts.data?.[ status ],
							onSelect: () => list.setFilters( { status } ),
						} ) ),
					] }
					search={ {
						label: __( 'Search orders', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						placeholder: __(
							'Order number, student name or email',
							'ohmylms'
						),
						value: text,
						onChange: setText,
					} }
					onFilter={ () => list.setFilters( { search: text } ) }
					filterLabel={ null }
					filters={
						<>
							<ListTableSelect
								label={ __( 'Payment method', 'ohmylms' ) }
								value={ list.query.paymentMethod }
								options={ [
									{
										value: '',
										label: __( 'All methods', 'ohmylms' ),
									},
									...PAYMENT_METHODS.map( ( method ) => ( {
										value: method,
										label: paymentLabel( method ),
									} ) ),
								] }
								onChange={ ( paymentMethod ) =>
									list.setFilters( { paymentMethod } )
								}
							/>
							<ListTableInput
								label={ __( 'From date', 'ohmylms' ) }
								type="date"
								value={ after }
								onChange={ ( value ) =>
									changeDate( 'after', value )
								}
							/>
							<ListTableInput
								label={ __( 'To date', 'ohmylms' ) }
								type="date"
								value={ before }
								onChange={ ( value ) =>
									changeDate( 'before', value )
								}
							/>
						</>
					}
					sort={ {
						orderby: list.query.orderby,
						order: list.query.order,
						onChange: ( orderby, order ) => {
							const choice = SORTS.find(
								( option ) =>
									option.orderby === orderby &&
									option.order === order
							);
							if ( choice ) {
								list.setFilters( {
									orderby: choice.orderby,
									order: choice.order,
								} );
							}
						},
					} }
					pagination={ {
						page: list.query.page,
						totalPages: list.data?.totalPages ?? 1,
						totalItems: total,
						itemsLabel: sprintf(
							/* translators: %d: total order count. */
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
							id: 'order',
							label: __( 'Order', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong>
									<Link
										className="row-title"
										to={ orderPath( item.id ) }
									>
										{ sprintf(
											/* translators: %s: order number. */
											__( 'Order #%s', 'ohmylms' ),
											item.number
										) }
									</Link>
								</strong>
							),
						},
						{
							id: 'date',
							label: __( 'Date', 'ohmylms' ),
							sortable: 'desc',
							render: ( item ) => formatDate( item.date_created ),
						},
						{
							id: 'student',
							label: __( 'Student', 'ohmylms' ),
							render: ( item ) => item.student_name,
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) => statusLabel( item.status ),
						},
						{
							id: 'payment',
							label: __( 'Payment', 'ohmylms' ),
							render: ( item ) =>
								item.payment_method_title ||
								paymentLabel( item.payment_method ),
						},
						{
							id: 'total',
							label: __( 'Total', 'ohmylms' ),
							sortable: 'desc',
							render: ( item ) =>
								formatMoney( item.total, item.currency ),
						},
					] }
					rowActions={ ( item ) => [
						{
							key: 'view',
							label: __( 'View', 'ohmylms' ),
							render: (
								<Link to={ orderPath( item.id ) }>
									{ __( 'View', 'ohmylms' ) }
								</Link>
							),
						},
					] }
				/>
			) }
		</>
	);
}
