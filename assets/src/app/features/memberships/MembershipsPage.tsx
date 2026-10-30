import { ListTable } from '../../../ui/ListTable';
import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { statusViews } from '../../components/listViews';
import { normalizeApiError } from '../../lib/api';
import { createMembership, deleteMembership, duplicateMembership } from './api';
import { billingLabel, formatPrice } from './model';
import { useMemberships } from './useMemberships';
import type { Membership, Option } from './types';

const MAX_NAME = 200;

function statusFilters(): Option[] {
	return [
		{ value: 'all', label: __( 'All', 'ohmylms' ) },
		{ value: 'publish', label: __( 'Published', 'ohmylms' ) },
		{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
	];
}

type Dialog = { kind: 'create' } | { kind: 'delete'; item: Membership } | null;

/**
 * Membership plan list (`#/memberships`): paged, searchable, with create, duplicate and confirmed
 * delete. The editor lives at `#/memberships/<id>`.
 */
export default function MembershipsPage() {
	const list = useMemberships();
	const navigate = useNavigate();
	const [ query, setQuery ] = useState( '' );
	const [ dialog, setDialog ] = useState< Dialog >( null );
	const [ name, setName ] = useState( '' );
	const [ nameError, setNameError ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );

	async function run( work: ( signal: AbortSignal ) => Promise< void > ) {
		if ( operation.current ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( null );
		setMessage( null );
		try {
			await work( controller.signal );
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				setError( normalizeApiError( failure ).message );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				setBusy( false );
				operation.current = null;
			}
		}
	}
	function create() {
		const value = name.trim();
		if ( ! value || Array.from( value ).length > MAX_NAME ) {
			setNameError(
				sprintf(
					/* translators: %d: maximum name length. */
					__( 'Enter a name of 1 to %d characters.', 'ohmylms' ),
					MAX_NAME
				)
			);
			return;
		}
		setNameError( null );
		void run( async ( signal ) => {
			const created = await createMembership( value, signal );
			if ( signal.aborted ) {
				return;
			}
			setDialog( null );
			navigate( `/memberships/${ created.id }` );
		} );
	}
	function duplicate( item: Membership ) {
		void run( async ( signal ) => {
			await duplicateMembership( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setMessage(
				sprintf(
					/* translators: %s: membership plan name. */
					__( 'Duplicated %s as a draft.', 'ohmylms' ),
					item.name
				)
			);
			list.reload();
		} );
	}
	function remove( item: Membership ) {
		void run( async ( signal ) => {
			await deleteMembership( item.id, signal );
			if ( signal.aborted ) {
				return;
			}
			setDialog( null );
			setMessage( __( 'Plan deleted.', 'ohmylms' ) );
			list.afterDelete();
		} );
	}
	function close() {
		if ( ! busy ) {
			setDialog( null );
			setError( null );
			setNameError( null );
		}
	}

	return (
		<>
			<h2>{ __( 'Memberships', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Sell access to a set of courses with a membership plan.',
					'ohmylms'
				) }
			</p>
			<Button
				variant="primary"
				disabled={ busy }
				onClick={ () => {
					setName( '' );
					setError( null );
					setDialog( { kind: 'create' } );
				} }
			>
				{ __( 'Add plan', 'ohmylms' ) }
			</Button>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ error && ! dialog && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }{ ' ' }
					<Button variant="link" onClick={ list.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.loading && (
				<p role="status">
					{ __( 'Loading membership plans…', 'ohmylms' ) }
				</p>
			) }
			{ ! list.loading && ! list.error && (
				<ListTable< Membership >
					caption={ __( 'Membership plans', 'ohmylms' ) }
					formLabel={ __( 'Search membership plans', 'ohmylms' ) }
					busy={ busy }
					rows={ list.data.items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __(
						'No membership plans found.',
						'ohmylms'
					) }
					views={ statusViews(
						statusFilters(),
						list.status,
						list.data.total,
						list.setStatus
					) }
					search={ {
						label: __( 'Search membership plans', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: query,
						onChange: setQuery,
						onSubmit: () => list.search( query ),
					} }
					pagination={ {
						page: list.page,
						totalPages: list.data.totalPages,
						totalItems: list.data.total,
						itemsLabel: sprintf(
							/* translators: %d: total plan count. */
							_n(
								'%d plan found.',
								'%d plans found.',
								list.data.total,
								'ohmylms'
							),
							list.data.total
						),
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong>
									<Link
										className="row-title"
										to={ `/memberships/${ item.id }` }
									>
										{ item.name }
									</Link>
								</strong>
							),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) =>
								'publish' === item.status
									? __( 'Published', 'ohmylms' )
									: __( 'Draft', 'ohmylms' ),
						},
						{
							id: 'price',
							label: __( 'Price', 'ohmylms' ),
							render: ( item ) => (
								<>
									{ formatPrice( item.price, item.currency ) }
									{ item.price !== item.regular_price && (
										<>
											{ ' ' }
											<s>
												{ formatPrice(
													item.regular_price,
													item.currency
												) }
											</s>
										</>
									) }
								</>
							),
						},
						{
							id: 'billing',
							label: __( 'Billing', 'ohmylms' ),
							render: ( item ) => billingLabel( item ),
						},
						{
							id: 'courses',
							label: __( 'Courses', 'ohmylms' ),
							render: ( item ) =>
								item.all_learning_content
									? sprintf(
											/* translators: %d: number of courses. */
											__( 'All (%d)', 'ohmylms' ),
											item.course_count
										)
									: item.course_count,
						},
						{
							id: 'members',
							label: __( 'Members', 'ohmylms' ),
							className: 'num',
							render: ( item ) => item.member_count,
						},
					] }
					rowActions={ ( item ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							render: (
								<Link
									to={ `/memberships/${ item.id }` }
									aria-label={ sprintf(
										/* translators: %s: plan name. */
										__( 'Edit %s', 'ohmylms' ),
										item.name
									) }
								>
									{ __( 'Edit', 'ohmylms' ) }
								</Link>
							),
						},
						{
							key: 'duplicate',
							label: __( 'Duplicate', 'ohmylms' ),
							disabled: busy,
							onClick: () => duplicate( item ),
							ariaLabel: sprintf(
								/* translators: %s: plan name. */
								__( 'Duplicate %s', 'ohmylms' ),
								item.name
							),
						},
						{
							key: 'delete',
							label: __( 'Delete', 'ohmylms' ),
							destructive: true,
							disabled: busy,
							onClick: () => {
								setError( null );
								setDialog( { kind: 'delete', item } );
							},
							ariaLabel: sprintf(
								/* translators: %s: plan name. */
								__( 'Delete %s', 'ohmylms' ),
								item.name
							),
						},
					] }
				/>
			) }
			{ dialog?.kind === 'create' && (
				<Modal
					title={ __( 'Add membership plan', 'ohmylms' ) }
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					<form
						onSubmit={ ( event ) => {
							event.preventDefault();
							create();
						} }
						noValidate
					>
						{ error && (
							<Notice status="error" isDismissible={ false }>
								{ error }
							</Notice>
						) }
						<TextControl
							label={ __( 'Plan name', 'ohmylms' ) }
							value={ name }
							maxLength={ MAX_NAME }
							required
							disabled={ busy }
							aria-invalid={ nameError ? true : undefined }
							aria-describedby={
								nameError ? 'oml-mem-create-error' : undefined
							}
							onChange={ setName }
							help={ __(
								'The plan starts as a draft. Set price and courses in the editor.',
								'ohmylms'
							) }
						/>
						{ nameError && (
							<p id="oml-mem-create-error" role="alert">
								{ nameError }
							</p>
						) }
						<Button
							type="submit"
							variant="primary"
							disabled={ busy }
							isBusy={ busy }
						>
							{ __( 'Create plan', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							disabled={ busy }
							onClick={ close }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
					</form>
				</Modal>
			) }
			{ dialog?.kind === 'delete' && (
				<Modal
					title={ __( 'Delete membership plan', 'ohmylms' ) }
					onRequestClose={ close }
					shouldCloseOnClickOutside={ false }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: %s: plan name. */
							__(
								'Permanently delete %s? A plan with active members cannot be deleted; set it to draft instead.',
								'ohmylms'
							),
							dialog.item.name
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						disabled={ busy }
						isBusy={ busy }
						onClick={ () => remove( dialog.item ) }
					>
						{ __( 'Delete plan', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ close }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
