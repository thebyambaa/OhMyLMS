import { Table } from '../../../ui/Table';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
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
			<form
				role="search"
				aria-label={ __( 'Search coupons', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.setFilters( { search: text } );
				} }
			>
				<TextControl
					label={ __( 'Search coupons', 'ohmylms' ) }
					help={ __( 'Code, label or description.', 'ohmylms' ) }
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
					{ value: 'any', label: __( 'All', 'ohmylms' ) },
					{ value: 'publish', label: __( 'Active', 'ohmylms' ) },
					{ value: 'draft', label: __( 'Disabled', 'ohmylms' ) },
				] }
				onChange={ ( value ) =>
					list.setFilters( {
						status:
							'publish' === value || 'draft' === value
								? value
								: 'any',
					} )
				}
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
				<p role="status">{ __( 'Loading coupons…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading &&
				! list.error &&
				( items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: total coupon count. */
								_n(
									'%d coupon found.',
									'%d coupons found.',
									total,
									'ohmylms'
								),
								total
							) }
						</p>
						<Table caption={ __( 'Coupons', 'ohmylms' ) } striped>
							<thead>
								<tr>
									{ [
										__( 'Code', 'ohmylms' ),
										__( 'Discount', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Usage', 'ohmylms' ),
										__( 'Expires', 'ohmylms' ),
										__( 'Actions', 'ohmylms' ),
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
										<th scope="row">{ item.code }</th>
										<td>{ discountLabel( item ) }</td>
										<td>
											{ 'publish' === item.status
												? __( 'Active', 'ohmylms' )
												: __( 'Disabled', 'ohmylms' ) }
										</td>
										<td>
											{ null === item.usage_limit
												? String( item.usage_count )
												: sprintf(
														/* translators: 1: times used, 2: usage limit. */
														__(
															'%1$d of %2$d',
															'ohmylms'
														),
														item.usage_count,
														item.usage_limit
													) }
										</td>
										<td>
											{ formatDate( item.date_expires ) ||
												__( 'Never', 'ohmylms' ) }
										</td>
										<td>
											<Button
												variant="link"
												onClick={ () =>
													open( { coupon: item } )
												}
											>
												{ sprintf(
													/* translators: %s: coupon code. */
													__( 'Edit %s', 'ohmylms' ),
													item.code
												) }
											</Button>{ ' ' }
											<Button
												variant="link"
												isDestructive
												onClick={ () => {
													operation.clearError();
													setSaved( null );
													setRemoving( item );
												} }
											>
												{ sprintf(
													/* translators: %s: coupon code. */
													__(
														'Delete %s',
														'ohmylms'
													),
													item.code
												) }
											</Button>
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
					<p>{ __( 'No coupons found.', 'ohmylms' ) }</p>
				) ) }
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
