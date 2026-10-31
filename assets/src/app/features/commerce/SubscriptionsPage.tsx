import { ListTable, ListTableSelect } from '../../../ui/ListTable';
import { Button, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { statusViews } from '../../components/listViews';
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
	const [ text, setText ] = useState( list.query.search );
	useEffect( () => setText( list.query.search ), [ list.query.search ] );
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;
	const statusOf = ( value: string ) =>
		SUBSCRIPTION_STATUSES.find( ( status ) => status === value ) ?? 'any';

	return (
		<>
			<p>
				{ __(
					'Subscriptions start when a learner buys a recurring membership plan. Open one to see its orders or to pause, resume or cancel it.',
					'ohmylms'
				) }
			</p>
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
			{ ! list.loading && ! list.error && (
				<ListTable
					caption={ __( 'Subscriptions', 'ohmylms' ) }
					heading={ __( 'Subscriptions list', 'ohmylms' ) }
					formLabel={ __( 'Search subscriptions', 'ohmylms' ) }
					rows={ items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __( 'No subscriptions found.', 'ohmylms' ) }
					views={ statusViews(
						[
							{ value: 'any', label: __( 'All', 'ohmylms' ) },
							...SUBSCRIPTION_STATUSES.map( ( status ) => ( {
								value: status,
								label: subscriptionStatusLabel( status ),
							} ) ),
						],
						list.query.status,
						total,
						( value ) =>
							list.setFilters( { status: statusOf( value ) } ),
						( value ) =>
							list.filtersHref( { status: statusOf( value ) } )
					) }
					search={ {
						label: __( 'Search subscriptions', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						placeholder: __(
							'Subscription, order, customer or plan',
							'ohmylms'
						),
						value: text,
						maxLength: 100,
						onChange: setText,
					} }
					onFilter={ () => list.setFilters( { search: text } ) }
					filterLabel={ null }
					filters={
						<ListTableSelect
							label={ __( 'Sort by', 'ohmylms' ) }
							value={ list.query.order }
							options={ [
								{
									value: 'desc',
									label: __( 'Newest first', 'ohmylms' ),
								},
								{
									value: 'asc',
									label: __( 'Oldest first', 'ohmylms' ),
								},
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
					}
					pagination={ {
						page: list.query.page,
						totalPages: list.data?.totalPages ?? 1,
						totalItems: total,
						itemsLabel: sprintf(
							/* translators: %d: total subscription count. */
							_n(
								'%d subscription found.',
								'%d subscriptions found.',
								total,
								'ohmylms'
							),
							total
						),
						onChange: list.setPage,
						href: list.pageHref,
					} }
					columns={ [
						{
							id: 'subscription',
							label: __( 'Subscription', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong>
									<Link
										className="row-title"
										to={ subscriptionPath( item.id ) }
									>
										{ sprintf(
											/* translators: %d: subscription ID. */
											__( 'Subscription #%d', 'ohmylms' ),
											item.id
										) }
									</Link>
								</strong>
							),
						},
						{
							id: 'customer',
							label: __( 'Customer', 'ohmylms' ),
							render: ( item ) => item.customer_name,
						},
						{
							id: 'plan',
							label: __( 'Plan', 'ohmylms' ),
							render: ( item ) => item.plan_name,
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) =>
								subscriptionStatusLabel( item.status ),
						},
						{
							id: 'next_payment',
							label: __( 'Next payment', 'ohmylms' ),
							render: ( item ) =>
								formatDate( item.next_payment_date ) ||
								__( 'None', 'ohmylms' ),
						},
						{
							id: 'amount',
							label: __( 'Recurring amount', 'ohmylms' ),
							render: ( item ) => amountLabel( item ),
						},
					] }
					rowActions={ ( item ) => [
						{
							key: 'view',
							label: __( 'View', 'ohmylms' ),
							render: (
								<Link to={ subscriptionPath( item.id ) }>
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
