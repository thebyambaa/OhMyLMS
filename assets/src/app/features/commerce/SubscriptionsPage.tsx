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
import { formatDate } from './model';
import { useSubscriptions } from './subscriptionHooks';
import {
	SUBSCRIPTION_STATUSES,
	amountLabel,
	subscriptionPath,
	subscriptionStatusLabel,
} from './subscriptionModel';
import type { SortOrder } from './types';

/** Searchable, filterable, paged list of membership subscriptions. */
export default function SubscriptionsPage() {
	const list = useSubscriptions();
	const [ text, setText ] = useState( '' );
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;

	return (
		<>
			<h2>{ __( 'Subscriptions', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Subscriptions start when a learner buys a recurring membership plan. Open one to see its orders or to pause, resume or cancel it.',
					'ohmylms'
				) }
			</p>
			<form
				role="search"
				aria-label={ __( 'Search subscriptions', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.setFilters( { search: text } );
				} }
			>
				<TextControl
					label={ __( 'Search subscriptions', 'ohmylms' ) }
					help={ __(
						'Subscription or order number, customer name or email, or plan name.',
						'ohmylms'
					) }
					value={ text }
					maxLength={ 100 }
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
					...SUBSCRIPTION_STATUSES.map( ( status ) => ( {
						value: status,
						label: subscriptionStatusLabel( status ),
					} ) ),
				] }
				onChange={ ( value ) =>
					list.setFilters( {
						status:
							SUBSCRIPTION_STATUSES.find(
								( status ) => status === value
							) ?? 'any',
					} )
				}
			/>
			<SelectControl
				label={ __( 'Sort by', 'ohmylms' ) }
				value={ list.query.order }
				options={ [
					{ value: 'desc', label: __( 'Newest first', 'ohmylms' ) },
					{ value: 'asc', label: __( 'Oldest first', 'ohmylms' ) },
				] }
				onChange={ ( value ) =>
					list.setFilters( {
						orderby: 'date',
						order: ( 'asc' === value
							? 'asc'
							: 'desc' ) as SortOrder,
					} )
				}
			/>
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error.message }{ ' ' }
					{ 401 !== list.error.status &&
						403 !== list.error.status && (
							<Button variant="link" onClick={ list.reload }>
								{ __( 'Try again', 'ohmylms' ) }
							</Button>
						) }
				</Notice>
			) }
			{ list.loading && (
				<p role="status">
					{ __( 'Loading subscriptions…', 'ohmylms' ) }
				</p>
			) }
			{ ! list.loading &&
				! list.error &&
				( items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: total subscription count. */
								_n(
									'%d subscription found.',
									'%d subscriptions found.',
									total,
									'ohmylms'
								),
								total
							) }
						</p>
						<Table
							caption={ __( 'Subscriptions', 'ohmylms' ) }
							striped
						>
							<thead>
								<tr>
									{ [
										__( 'Subscription', 'ohmylms' ),
										__( 'Customer', 'ohmylms' ),
										__( 'Plan', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Next payment', 'ohmylms' ),
										__( 'Recurring amount', 'ohmylms' ),
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
											<Link
												to={ subscriptionPath(
													item.id
												) }
											>
												{ sprintf(
													/* translators: %d: subscription ID. */
													__(
														'Subscription #%d',
														'ohmylms'
													),
													item.id
												) }
											</Link>
										</th>
										<td>{ item.customer_name }</td>
										<td>{ item.plan_name }</td>
										<td>
											{ subscriptionStatusLabel(
												item.status
											) }
										</td>
										<td>
											{ formatDate(
												item.next_payment_date
											) || __( 'None', 'ohmylms' ) }
										</td>
										<td>{ amountLabel( item ) }</td>
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
					<p>{ __( 'No subscriptions found.', 'ohmylms' ) }</p>
				) ) }
		</>
	);
}
