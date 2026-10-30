import { Table } from '../../../ui/Table';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
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
import type { OrderOrderBy, OrderStatus, SortOrder } from './types';

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

function sortLabel( value: string ) {
	switch ( value ) {
		case 'date:asc':
			return __( 'Oldest first', 'ohmylms' );
		case 'total:desc':
			return __( 'Highest total', 'ohmylms' );
		case 'total:asc':
			return __( 'Lowest total', 'ohmylms' );
		default:
			return __( 'Newest first', 'ohmylms' );
	}
}

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
			<form
				role="search"
				aria-label={ __( 'Search orders', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.setFilters( { search: text } );
				} }
			>
				<TextControl
					label={ __( 'Search orders', 'ohmylms' ) }
					help={ __(
						'Order number, student name or email.',
						'ohmylms'
					) }
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
				label={ __( 'Status', 'ohmylms' ) }
				value={ list.query.status }
				options={ [
					{ value: 'any', label: __( 'All statuses', 'ohmylms' ) },
					...ORDER_STATUSES.map( ( status ) => ( {
						value: status,
						label: counts.data
							? sprintf(
									/* translators: 1: order status, 2: number of orders. */
									__( '%1$s (%2$d)', 'ohmylms' ),
									statusLabel( status ),
									counts.data[ status ]
								)
							: statusLabel( status ),
					} ) ),
				] }
				onChange={ ( value ) =>
					list.setFilters( {
						status: ORDER_STATUSES.includes( value as OrderStatus )
							? ( value as OrderStatus )
							: 'any',
					} )
				}
			/>
			<SelectControl
				label={ __( 'Payment method', 'ohmylms' ) }
				value={ list.query.paymentMethod }
				options={ [
					{ value: '', label: __( 'All methods', 'ohmylms' ) },
					...PAYMENT_METHODS.map( ( method ) => ( {
						value: method,
						label: paymentLabel( method ),
					} ) ),
				] }
				onChange={ ( paymentMethod ) =>
					list.setFilters( { paymentMethod } )
				}
			/>
			<TextControl
				label={ __( 'From date', 'ohmylms' ) }
				type="date"
				value={ after }
				onChange={ ( value ) => changeDate( 'after', value ) }
			/>
			<TextControl
				label={ __( 'To date', 'ohmylms' ) }
				type="date"
				value={ before }
				onChange={ ( value ) => changeDate( 'before', value ) }
			/>
			{ rangeError && (
				<Notice status="error" isDismissible={ false }>
					{ rangeError }
				</Notice>
			) }
			<SelectControl
				label={ __( 'Sort by', 'ohmylms' ) }
				value={ `${ list.query.orderby }:${ list.query.order }` }
				options={ SORTS.map( ( { value } ) => ( {
					value,
					label: sortLabel( value ),
				} ) ) }
				onChange={ ( value ) => {
					const choice = SORTS.find(
						( option ) => option.value === value
					);
					if ( choice ) {
						list.setFilters( {
							orderby: choice.orderby,
							order: choice.order,
						} );
					}
				} }
			/>
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
			{ ! list.loading &&
				! list.error &&
				( items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: total order count. */
								_n(
									'%d order found.',
									'%d orders found.',
									total,
									'ohmylms'
								),
								total
							) }
						</p>
						<Table caption={ __( 'Orders', 'ohmylms' ) } striped>
							<thead>
								<tr>
									{ [
										__( 'Order', 'ohmylms' ),
										__( 'Date', 'ohmylms' ),
										__( 'Student', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Payment', 'ohmylms' ),
										__( 'Total', 'ohmylms' ),
									].map( ( title ) => (
										<th key={ title } scope="col">
											{ title }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ items.map( ( item ) => (
									<tr key={ item.id }>
										<th scope="row">
											<Link to={ orderPath( item.id ) }>
												{ sprintf(
													/* translators: %s: order number. */
													__(
														'Order #%s',
														'ohmylms'
													),
													item.number
												) }
											</Link>
										</th>
										<td>
											{ formatDate( item.date_created ) }
										</td>
										<td>{ item.student_name }</td>
										<td>{ statusLabel( item.status ) }</td>
										<td>
											{ item.payment_method_title ||
												paymentLabel(
													item.payment_method
												) }
										</td>
										<td>
											{ formatMoney(
												item.total,
												item.currency
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
					<p>{ __( 'No orders found.', 'ohmylms' ) }</p>
				) ) }
		</>
	);
}
