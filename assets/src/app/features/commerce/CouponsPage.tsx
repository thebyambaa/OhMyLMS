import { ListTable } from '../../../ui/ListTable';
import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { statusViews } from '../../components/listViews';
import { createCoupon, deleteCoupon, updateCoupon } from './api';
import { CouponEditor } from './CouponEditor';
import { useCoupons } from './hooks';
import { discountLabel, formatDate } from './model';
import { useOperation } from './useOperation';
import type { Coupon, CouponPayload } from './types';

interface EditorState {
	coupon: Coupon | null;
}

/** Coupon list with create, edit and confirmed deletion. */
export default function CouponsPage() {
	const list = useCoupons();
	const operation = useOperation();
	const [ text, setText ] = useState( '' );
	const [ editor, setEditor ] = useState< EditorState | null >( null );
	const [ removing, setRemoving ] = useState< Coupon | null >( null );
	const [ saved, setSaved ] = useState< string | null >( null );
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;

	const save = async ( payload: CouponPayload ) => {
		const target = editor?.coupon ?? null;
		const done = await operation.run( async ( signal ) => {
			if ( target ) {
				await updateCoupon( target.id, payload, signal );
			} else {
				await createCoupon( payload, signal );
			}
		} );
		if ( done ) {
			setEditor( null );
			setSaved(
				target
					? __( 'Coupon saved.', 'ohmylms' )
					: __( 'Coupon created.', 'ohmylms' )
			);
			list.reload();
		}
	};
	const remove = async () => {
		if ( ! removing ) {
			return;
		}
		const target = removing;
		const done = await operation.run( async ( signal ) => {
			await deleteCoupon( target.id, signal );
		} );
		if ( done ) {
			setRemoving( null );
			setSaved( __( 'Coupon deleted.', 'ohmylms' ) );
			list.reload();
		}
	};
	const open = ( next: EditorState ) => {
		operation.clearError();
		setSaved( null );
		setEditor( next );
	};

	return (
		<>
			<h2>{ __( 'Coupons', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Discount codes customers enter at checkout. Past orders keep the code they used.',
					'ohmylms'
				) }
			</p>
			<p>
				<Button
					variant="primary"
					onClick={ () => open( { coupon: null } ) }
				>
					{ __( 'Add coupon', 'ohmylms' ) }
				</Button>
			</p>
			{ saved && (
				<Notice status="success" onRemove={ () => setSaved( null ) }>
					{ saved }
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
				<p role="status">{ __( 'Loading coupons…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && ! list.error && (
				<ListTable
					caption={ __( 'Coupons', 'ohmylms' ) }
					formLabel={ __( 'Search coupons', 'ohmylms' ) }
					rows={ items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __( 'No coupons found.', 'ohmylms' ) }
					views={ statusViews(
						[
							{ value: 'any', label: __( 'All', 'ohmylms' ) },
							{
								value: 'publish',
								label: __( 'Active', 'ohmylms' ),
							},
							{
								value: 'draft',
								label: __( 'Disabled', 'ohmylms' ),
							},
						],
						list.query.status,
						total,
						( value ) =>
							list.setFilters( {
								status:
									'publish' === value || 'draft' === value
										? value
										: 'any',
							} )
					) }
					search={ {
						label: __( 'Search coupons', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						placeholder: __(
							'Code, label or description',
							'ohmylms'
						),
						value: text,
						onChange: setText,
						onSubmit: () => list.setFilters( { search: text } ),
					} }
					pagination={ {
						page: list.query.page,
						totalPages: list.data?.totalPages ?? 1,
						totalItems: total,
						itemsLabel: sprintf(
							/* translators: %d: total coupon count. */
							_n(
								'%d coupon found.',
								'%d coupons found.',
								total,
								'ohmylms'
							),
							total
						),
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'code',
							label: __( 'Code', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong className="row-title">
									{ item.code }
								</strong>
							),
						},
						{
							id: 'discount',
							label: __( 'Discount', 'ohmylms' ),
							render: ( item ) => discountLabel( item ),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) =>
								'publish' === item.status
									? __( 'Active', 'ohmylms' )
									: __( 'Disabled', 'ohmylms' ),
						},
						{
							id: 'usage',
							label: __( 'Usage', 'ohmylms' ),
							render: ( item ) =>
								null === item.usage_limit
									? String( item.usage_count )
									: sprintf(
											/* translators: 1: times used, 2: usage limit. */
											__( '%1$d of %2$d', 'ohmylms' ),
											item.usage_count,
											item.usage_limit
										),
						},
						{
							id: 'expires',
							label: __( 'Expires', 'ohmylms' ),
							render: ( item ) =>
								formatDate( item.date_expires ) ||
								__( 'Never', 'ohmylms' ),
						},
					] }
					rowActions={ ( item ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							ariaLabel: sprintf(
								/* translators: %s: coupon code. */
								__( 'Edit %s', 'ohmylms' ),
								item.code
							),
							onClick: () => open( { coupon: item } ),
						},
						{
							key: 'delete',
							label: __( 'Delete', 'ohmylms' ),
							ariaLabel: sprintf(
								/* translators: %s: coupon code. */
								__( 'Delete %s', 'ohmylms' ),
								item.code
							),
							destructive: true,
							onClick: () => {
								operation.clearError();
								setSaved( null );
								setRemoving( item );
							},
						},
					] }
				/>
			) }
			{ editor && (
				<CouponEditor
					coupon={ editor.coupon }
					busy={ operation.busy }
					error={ operation.error }
					onClose={ () => setEditor( null ) }
					onSave={ ( payload ) => void save( payload ) }
				/>
			) }
			{ removing && (
				<Modal
					title={ __( 'Delete coupon', 'ohmylms' ) }
					onRequestClose={ () => {
						if ( ! operation.busy ) {
							setRemoving( null );
						}
					} }
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: %s: coupon code. */
							__(
								'Delete the coupon %s permanently? Customers can no longer use it. Past orders are not changed.',
								'ohmylms'
							),
							removing.code
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						isBusy={ operation.busy }
						disabled={ operation.busy }
						onClick={ () => void remove() }
					>
						{ __( 'Delete coupon', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setRemoving( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
